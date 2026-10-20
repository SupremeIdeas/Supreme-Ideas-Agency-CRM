<?php

namespace App\Services\N2N\Messaging;

use App\Jobs\SendWebPushJob;
use App\Models\N2N\Attachment;
use App\Models\N2N\Block;
use App\Models\N2N\Conversation;
use App\Models\N2N\Message;
use App\Models\N2N\MessageReceipt;
use App\Models\N2N\Participant;
use App\Models\N2N\Presence;
use App\Models\N2N\Report;
use App\Models\Setting;
use App\Models\User;
use App\Services\N2N\Credit\InsufficientUsageCredit;
use App\Services\N2N\Credit\Money;
use App\Services\N2N\Credit\N2NPricing;
use App\Services\N2N\Credit\UsageMeter;
use App\Services\N2N\Identity\IdentityResolver;
use App\Services\N2N\Media\MediaPolicy;
use App\Services\N2N\Media\MediaService;
use App\Services\N2N\Media\NaaraPro;
use App\Services\N2N\Realtime\Realtime;
use App\Support\Auditor;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;

/**
 * The N2N send pipeline and conversation actions (MASTER-ONLY). Order, with idempotency by (sender, client_id):
 *   authorise (participant / blocks / privacy) -> resolve -> link filter -> limits -> charge -> insert -> commit -> publish -> push.
 * A repeat of the same client_id returns the first message and charges nothing. A send that fails after the charge returns the charge.
 */
class MessageService
{
    public function __construct(private readonly UsageMeter $meter, private readonly IdentityResolver $resolver) {}

    /**
     * @param  array{conversation_id?: int, to?: string}  $target
     * @return array{message: Message, conversation: Conversation, replay: bool, dropped: bool}
     *
     * @throws MessagingException|InsufficientUsageCredit
     */
    public function send(User $sender, array $target, string $clientId, string $body, ?string $ip = null, array $attachmentIds = [], ?array $quote = null, ?int $replyTo = null, ?array $poll = null, ?array $live = null, ?array $event = null, bool $silent = false, ?int $disappearAfter = null): array
    {
        if (! preg_match('/^[A-Za-z0-9_\-]{8,64}$/', $clientId)) {
            throw new MessagingException('invalid', 'That message could not be sent.');
        }
        if (($existing = Message::query()->where('sender_id', $sender->id)->where('client_id', $clientId)->first()) !== null) {
            return ['message' => $existing, 'conversation' => $existing->conversation, 'replay' => true, 'dropped' => $existing->suppressed];
        }
        Moderation::assertCanSend($sender);
        if ($poll !== null) {
            $poll = Polls::clean($poll);   // the question is the message body, so previews, search and export all work unchanged
            $body = $poll['question'];
        }
        if ($event !== null) {
            $event = Planner::clean($event);   // title and description are link-filtered inside; the body is a fixed label plus the title
            $body = 'Event: '.$event['title'];
            app(Planner::class)->assertRoom($sender);
        }
        if ($live !== null) {
            $live = LiveLocations::clean($live);   // the body is a fixed label: coordinates never enter message text
            $body = 'Live location';
        }
        $body = trim($body);
        $max = max(1, (int) Setting::getValue('n2n.msg_max_chars', 4000));
        $attachments = $this->claimable($sender, $attachmentIds);
        if (($body === '' && $attachments->isEmpty()) || mb_strlen($body) > $max) {
            throw new MessagingException('invalid', $body === '' ? 'Write a message first.' : "Messages can be up to {$max} characters.");
        }
        if (($why = LinkFilter::problem($body)) !== null) {
            throw new MessagingException('link_blocked', $why);
        }
        $perMin = max(1, (int) Setting::getValue('n2n.msg_per_min', 30));
        if (RateLimiter::tooManyAttempts('n2n-send:'.$sender->id, $perMin)) {
            throw new MessagingException('rate_limited', 'You are sending too fast. Please wait a moment.');
        }

        [$conversation, $recipient, $route] = $this->locate($sender, $target, $ip);
        $suppressed = $route === 'drop';
        $replyId = $this->replyTarget($sender, $conversation, $replyTo);

        // A first message to someone who is not a connection is a REQUEST; until accepted the sender is capped.
        if ($conversation->status === Conversation::REQUEST && (int) $conversation->requested_by === $sender->id) {
            $cap = max(1, (int) Setting::getValue('n2n.request_cap', 3));
            if (Message::query()->where('conversation_id', $conversation->id)->where('sender_id', $sender->id)->count() >= $cap) {
                throw new MessagingException('request_cap', 'They have not accepted your message request yet. You can send more once they do.');
            }
        }

        RateLimiter::hit('n2n-send:'.$sender->id, 60);
        // Text costs the message price; media is metered from the SERVER-measured size and never costs less than a text.
        $cost = $attachments->isEmpty() ? N2NPricing::messageCostCents() : max(N2NPricing::messageCostCents(), (int) $attachments->sum(fn (Attachment $a) => N2NPricing::mediaCostCents((int) $a->bytes)));
        $ref = 'msg:'.$clientId;
        $this->meter->charge($sender, $cost, $ref, 'message');   // may throw InsufficientUsageCredit: nothing is inserted

        try {
            $message = DB::transaction(function () use ($conversation, $sender, $clientId, $body, $cost, $ref, $suppressed, $recipient, $attachments, $quote, $replyId, $poll, $live, $event, $disappearAfter) {
                $m = Message::query()->create([
                    'conversation_id' => $conversation->id, 'sender_id' => $sender->id, 'client_id' => $clientId, 'type' => $event !== null ? 'event' : ($live !== null ? 'live' : ($poll !== null ? 'poll' : ($attachments->isEmpty() ? 'text' : (string) $attachments->first()->kind))), 'body' => $body,
                    'cost_credits' => Money::credits($cost), 'ledger_ref' => $ref, 'suppressed' => $suppressed, 'created_at' => now(), 'quote_json' => $quote, 'reply_to_id' => $replyId, 'expires_at' => Disappearing::expiryForMessage($conversation, $disappearAfter),
                ]);
                if ($poll !== null) {
                    app(Polls::class)->attach($m, $poll);
                }
                if ($live !== null) {
                    app(LiveLocations::class)->attach($m, $live);
                }
                if ($event !== null) {
                    app(Planner::class)->attach($m, $event);
                }
                if ($attachments->isNotEmpty()) {
                    Attachment::query()->whereIn('id', $attachments->pluck('id'))->update(['message_id' => $m->id, 'status' => Attachment::ATTACHED, 'expires_at' => null]);
                }
                if (! $suppressed) {
                    MessageReceipt::query()->create(['message_id' => $m->id, 'user_id' => $recipient->id]);
                }
                $conversation->forceFill(['last_message_id' => $m->id, 'last_message_at' => $m->created_at])->save();
                Participant::query()->where('conversation_id', $conversation->id)->where('user_id', $sender->id)->update(['last_read_message_id' => $m->id]);

                return $m;
            });
        } catch (QueryException $e) {
            // A concurrent duplicate of the same client_id won the unique index: return it and keep ONE charge.
            $dupe = Message::query()->where('sender_id', $sender->id)->where('client_id', $clientId)->first();
            if ($dupe !== null) {
                return ['message' => $dupe, 'conversation' => $dupe->conversation, 'replay' => true, 'dropped' => $dupe->suppressed];
            }
            $this->meter->refundCharge($sender, $ref);
            throw $e;
        } catch (\Throwable $e) {
            $this->meter->refundCharge($sender, $ref);   // never charge without delivering
            throw $e;
        }

        if (! $suppressed) {
            $this->announce($message, $conversation, $sender, $recipient, $silent);
        }
        Realtime::publish($sender->id, 'message.sent', ['conversation_id' => $conversation->id, 'message_id' => $message->id]);   // the sender's other devices

        return ['message' => $message, 'conversation' => $conversation->fresh(), 'replay' => false, 'dropped' => $suppressed];
    }

    /** The sender's own staged, unexpired uploads. Sending media needs Naara Pro (enforced HERE, on the server; the UI only hides the button). */
    private function claimable(User $sender, array $ids): \Illuminate\Support\Collection
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        if ($ids === []) {
            return collect();
        }
        if (! NaaraPro::active($sender)) {
            throw new MessagingException('pro_required', 'Unlock photos, videos and voice notes with Naara Pro.');
        }
        if (count($ids) > MediaPolicy::maxPerMessage()) {
            throw new MessagingException('invalid', 'You can attach up to '.MediaPolicy::maxPerMessage().' files to one message.');
        }
        $rows = Attachment::query()->whereIn('id', $ids)->where('uploader_id', $sender->id)->where('status', Attachment::STAGED)
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))->where('mime', '!=', 'pending')->get();
        if ($rows->count() !== count($ids)) {
            throw new MessagingException('invalid', 'Your upload expired or was not found. Please attach it again.');
        }

        return $rows;
    }

    /** @return array{0: Conversation, 1: User, 2: string} */
    private function locate(User $sender, array $target, ?string $ip): array
    {
        if (isset($target['conversation_id'])) {
            $conversation = Conversation::query()->find((int) $target['conversation_id']);
            if ($conversation === null || ! $conversation->isParticipant($sender->id)) {
                throw new MessagingException('not_participant', 'You cannot message in this conversation.');   // the same answer for "missing" and "not yours"
            }
            $otherId = Participant::query()->where('conversation_id', $conversation->id)->where('user_id', '!=', $sender->id)->value('user_id');
            $recipient = User::query()->find($otherId);
            if ($recipient === null || ! $recipient->is_active) {
                throw new MessagingException('not_found', 'This person is no longer reachable.');
            }
            // A reply from the person who received a request accepts it.
            if ($conversation->status === Conversation::REQUEST && (int) $conversation->requested_by !== $sender->id) {
                $conversation->forceFill(['status' => Conversation::ACTIVE])->save();
            }

            return [$conversation, $recipient, Privacy::isBlocked($sender->id, $recipient->id) ? 'drop' : 'deliver'];
        }

        $hit = $this->resolver->resolve((string) ($target['to'] ?? ''), 'search', $sender, $ip);
        $recipient = $hit->found ? User::query()->find($hit->userId) : null;
        if ($recipient === null) {
            throw new MessagingException('not_found', 'We could not find that person on Naara. You can invite them instead.');
        }
        if ($recipient->id === $sender->id) {
            throw new MessagingException('invalid', 'You cannot message yourself.');
        }
        $route = Privacy::route($sender, $recipient);
        $key = Conversation::pairKey($sender->id, $recipient->id);
        $conversation = Conversation::query()->where('pair_key', $key)->first();
        if ($conversation === null) {
            $perDay = max(1, (int) Setting::getValue('n2n.new_conv_per_day', 20));
            $made = Conversation::query()->where('requested_by', $sender->id)->where('created_at', '>=', now()->subDay())->count();
            if ($made >= $perDay) {
                throw new MessagingException('rate_limited', 'You have started too many new conversations today.');
            }
            $conversation = DB::transaction(function () use ($sender, $recipient, $key, $route) {
                $c = Conversation::query()->create([
                    'type' => 'direct', 'pair_key' => $key, 'requested_by' => $sender->id,
                    'status' => $route === 'request' ? Conversation::REQUEST : Conversation::ACTIVE,
                ]);
                foreach ([$sender->id, $recipient->id] as $uid) {
                    Participant::query()->create(['conversation_id' => $c->id, 'user_id' => $uid]);
                }

                return $c;
            });
        } elseif ($conversation->status === Conversation::REQUEST && (int) $conversation->requested_by !== $sender->id) {
            $conversation->forceFill(['status' => Conversation::ACTIVE])->save();
        }

        return [$conversation, $recipient, $route === 'request' ? 'deliver' : $route];
    }

    /** The message being replied to: it must be in THIS conversation and visible to the sender. Null when this is not a reply. */
    private function replyTarget(User $sender, Conversation $conversation, ?int $replyTo): ?int
    {
        if ($replyTo === null || $replyTo <= 0) {
            return null;
        }
        $m = Message::query()->where('id', $replyTo)->where('conversation_id', $conversation->id)->whereNull('deleted_for_all_at')
            ->where(fn ($w) => $w->where('suppressed', false)->orWhere('sender_id', $sender->id))->first();
        if ($m === null) {
            throw new MessagingException('invalid', 'The message you are replying to is no longer there.');
        }

        return $m->id;
    }

    private function announce(Message $message, Conversation $conversation, User $sender, User $recipient, bool $silent = false): void
    {
        // The recipient's own preferences (Batch 12): a muted chat is silent (no tone, no push; the unread badge still counts), and a new
        // message brings an archived, un-muted chat back to the inbox. A declined request stays archived.
        $part = Participant::query()->where('conversation_id', $conversation->id)->where('user_id', $recipient->id)->first();
        $muted = ConversationPrefs::isMuted($part) || $silent;   // "Send silently": delivered and counted as unread, but no tone and no push
        if ($part !== null && $part->archived_at !== null && ! ConversationPrefs::isMuted($part) && $conversation->status === Conversation::ACTIVE) {
            $part->forceFill(['archived_at' => null])->save();
        }
        Realtime::publish($recipient->id, 'message.new', [
            'conversation_id' => $conversation->id, 'message_id' => $message->id, 'from' => $sender->naara_id, 'request' => $conversation->status === Conversation::REQUEST,
            'tone' => $muted ? null : 'message.naara', 'muted' => $muted,
        ]);
        // Offline recipients get a web push: generic by default (no message text), content only if the admin allows previews.
        $seen = Presence::query()->where('user_id', $recipient->id)->value('last_seen_at');
        if (! $muted && ($seen === null || now()->diffInSeconds($seen) > 90)) {
            $preview = (bool) Setting::getValue('n2n.push_preview', false);
            SendWebPushJob::dispatch($recipient->id, [
                'title' => $preview ? ($sender->name ?: 'New message') : 'New message on Naara',
                'body' => $preview ? mb_substr($message->body, 0, 80) : 'Open Naara to read it.',
                'url' => url('/n2n/chat?c='.$conversation->id),
            ]);
        }
    }

    /** Delete a message (and its media objects) for everyone: sender only, inside the admin window. Idempotent. */
    public function deleteForEveryone(User $user, int $messageId): bool
    {
        $m = Message::query()->find($messageId);
        if ($m === null || (int) $m->sender_id !== $user->id || ! $m->conversation?->isParticipant($user->id)) {
            throw new MessagingException('not_participant', 'You cannot delete this message.');
        }
        if ($m->deleted_for_all_at !== null) {
            return true;
        }
        if ($m->created_at->lt(now()->subHours(MediaPolicy::deleteWindowHours()))) {
            throw new MessagingException('invalid', 'This message is too old to delete for everyone.');
        }
        $m->forceFill(['deleted_for_all_at' => now()])->save();
        foreach (Attachment::query()->where('message_id', $m->id)->where('status', Attachment::ATTACHED)->get() as $a) {
            app(MediaService::class)->deleteObjects($a);
        }
        $otherId = (int) Participant::query()->where('conversation_id', $m->conversation_id)->where('user_id', '!=', $user->id)->value('user_id');
        foreach ([$user->id, $otherId] as $uid) {
            Realtime::publish($uid, 'message.deleted', ['conversation_id' => $m->conversation_id, 'message_id' => $m->id]);
        }
        Auditor::log('n2n.message_deleted_for_all', Message::class, $m->id, []);

        return true;
    }

    // ---- conversation actions -------------------------------------------------------------------------

    public function accept(User $user, int $conversationId): Conversation
    {
        $c = $this->mine($user, $conversationId);
        if ($c->status === Conversation::REQUEST && (int) $c->requested_by !== $user->id) {
            $c->forceFill(['status' => Conversation::ACTIVE])->save();
            Realtime::publish((int) $c->requested_by, 'conversation.accepted', ['conversation_id' => $c->id]);
        }

        return $c;
    }

    /** Decline a request: the conversation disappears for the recipient and the sender is not told (they cannot keep paying into it). */
    public function decline(User $user, int $conversationId): void
    {
        $c = $this->mine($user, $conversationId);
        if ($c->status === Conversation::REQUEST && (int) $c->requested_by !== $user->id) {
            Participant::query()->where('conversation_id', $c->id)->where('user_id', $user->id)->update(['archived_at' => now()]);
        }
    }

    public function block(User $user, int $conversationId): void
    {
        $c = $this->mine($user, $conversationId);
        $other = (int) Participant::query()->where('conversation_id', $c->id)->where('user_id', '!=', $user->id)->value('user_id');
        $this->blockUser($user, $other);
        Participant::query()->where('conversation_id', $c->id)->where('user_id', $user->id)->update(['archived_at' => now()]);
    }

    public function blockUser(User $user, int $otherId): void
    {
        if ($otherId === 0 || $otherId === $user->id) {
            return;
        }
        Block::query()->firstOrCreate(['blocker_id' => $user->id, 'blocked_id' => $otherId], ['created_at' => now()]);
        Auditor::log('n2n.blocked', User::class, $user->id, ['blocked' => $otherId]);
    }

    public function unblock(User $user, int $otherId): void
    {
        Block::query()->where('blocker_id', $user->id)->where('blocked_id', $otherId)->delete();
    }

    /** Report the other person. Captures a short excerpt of THEIR recent messages so staff can review it later. */
    public function report(User $user, int $conversationId, string $reason, ?string $note = null, bool $alsoBlock = true): Report
    {
        $c = $this->mine($user, $conversationId);
        $other = (int) Participant::query()->where('conversation_id', $c->id)->where('user_id', '!=', $user->id)->value('user_id');
        $reason = in_array($reason, ['spam', 'abuse', 'scam', 'other'], true) ? $reason : 'other';
        $excerpt = Message::query()->where('conversation_id', $c->id)->where('sender_id', $other)->where('suppressed', false)->orderByDesc('id')->limit(5)->get(['id', 'body', 'created_at'])
            ->map(fn ($m) => ['id' => $m->id, 'body' => mb_substr($m->body, 0, 500), 'at' => $m->created_at?->toIso8601String()])->reverse()->values()->all();
        $report = Report::query()->create([
            'reporter_id' => $user->id, 'reported_id' => $other, 'conversation_id' => $c->id, 'reason' => $reason,
            'note' => $note !== null ? mb_substr($note, 0, 1000) : null, 'excerpt' => $excerpt, 'status' => 'open',
        ]);
        Auditor::log('n2n.reported', User::class, $other, ['report' => $report->id, 'reason' => $reason, 'by' => $user->id]);
        if ($alsoBlock) {
            $this->block($user, $c->id);
        }

        return $report;
    }

    /** Mark everything up to the latest message as read; emits read receipts only if the reader allows it. */
    public function markRead(User $user, int $conversationId): int
    {
        $c = $this->mine($user, $conversationId);
        $latest = (int) Message::query()->where('conversation_id', $c->id)->where('suppressed', false)->max('id');
        $part = Participant::query()->where('conversation_id', $c->id)->where('user_id', $user->id)->first();
        $part->forceFill(['last_read_message_id' => max((int) $part->last_read_message_id, $latest), 'marked_unread_at' => null])->save();
        $unread = MessageReceipt::query()->where('user_id', $user->id)->whereNull('read_at')
            ->whereIn('message_id', Message::query()->where('conversation_id', $c->id)->pluck('id'))->pluck('message_id');
        MessageReceipt::query()->whereIn('message_id', $unread)->where('user_id', $user->id)->update(['delivered_at' => now(), 'read_at' => now()]);
        if ($unread->isNotEmpty() && $user->n2n_read_receipts) {
            $senderId = (int) Participant::query()->where('conversation_id', $c->id)->where('user_id', '!=', $user->id)->value('user_id');
            Realtime::publish($senderId, 'message.read', ['conversation_id' => $c->id, 'up_to' => $latest]);
        }

        return $unread->count();
    }

    /** The conversation, only if this user is in it. The same refusal for "not yours" and "does not exist". */
    public function mine(User $user, int $conversationId): Conversation
    {
        $c = Conversation::query()->find($conversationId);
        if ($c === null || ! $c->isParticipant($user->id)) {
            throw new MessagingException('not_participant', 'You cannot open this conversation.');
        }

        return $c;
    }
}
