<?php

namespace App\Services\N2N\Messaging;

use App\Models\N2N\Attachment;
use App\Models\N2N\Block;
use App\Models\N2N\Message;
use App\Models\N2N\MessageReaction;
use App\Models\N2N\MessageReceipt;
use App\Models\N2N\Participant;
use App\Models\Setting;
use App\Models\User;
use App\Services\N2N\Credit\InsufficientUsageCredit;
use App\Services\N2N\Realtime\Realtime;
use App\Support\Auditor;
use Illuminate\Support\Facades\RateLimiter;

/**
 * What a member can do with a message from the long-press menu (N2N, MASTER-ONLY): react, forward, edit, see its delivery info. Every action starts
 * from ConversationPrefs::visibleMessage (member of the chat, not a dropped or deleted message), never widens who can see a message, and goes through
 * the same limits, link filter and credit meter as a live send.
 */
class MessageActions
{
    /** The only reactions that exist. Stored as codes, never free text, so nothing the sender types can ride in a reaction. */
    public const REACTIONS = [
        'like' => "\u{1F44D}", 'love' => "\u{2764}\u{FE0F}", 'laugh' => "\u{1F602}", 'wow' => "\u{1F62E}", 'sad' => "\u{1F622}", 'pray' => "\u{1F64F}",
        'fire' => "\u{1F525}", 'clap' => "\u{1F44F}", 'party' => "\u{1F389}", 'heart_eyes' => "\u{1F60D}", 'cool' => "\u{1F60E}", 'think' => "\u{1F914}",
        'ok' => "\u{1F44C}", 'hundred' => "\u{1F4AF}", 'muscle' => "\u{1F4AA}", 'eyes' => "\u{1F440}", 'check' => "\u{2705}", 'star' => "\u{2B50}",
        'rocket' => "\u{1F680}", 'sparkle' => "\u{2728}", 'smile' => "\u{1F60A}", 'grin' => "\u{1F601}", 'wink' => "\u{1F609}", 'hug' => "\u{1F917}",
        'angry' => "\u{1F621}", 'dislike' => "\u{1F44E}", 'facepalm' => "\u{1F926}", 'shrug' => "\u{1F937}", 'blush' => "\u{1F970}", 'mindblown' => "\u{1F92F}",
        'money' => "\u{1F4B0}", 'globe' => "\u{1F30D}", 'sun' => "\u{2600}\u{FE0F}", 'cry' => "\u{1F62D}", 'sleepy' => "\u{1F634}", 'handshake' => "\u{1F91D}",
    ];

    /** What a member's quick bar holds until they change it. */
    public const DEFAULT_BAR = ['like', 'love', 'laugh', 'wow', 'sad', 'pray'];

    public const BAR_MAX = 24;

    /** @return list<string> the member's quick-reaction codes, in order; always valid catalog codes, never empty */
    public static function quickBar(User $user): array
    {
        $bar = array_values(array_filter((array) $user->n2n_reaction_bar, fn ($c) => is_string($c) && array_key_exists($c, self::REACTIONS)));

        return $bar === [] ? self::DEFAULT_BAR : array_slice(array_values(array_unique($bar)), 0, self::BAR_MAX);
    }

    /** @param list<string> $codes @return list<string> */
    public function saveQuickBar(User $user, array $codes): array
    {
        $clean = array_slice(array_values(array_unique(array_filter($codes, fn ($c) => is_string($c) && array_key_exists($c, self::REACTIONS)))), 0, self::BAR_MAX);
        if ($clean === []) {
            throw new MessagingException('invalid', 'Keep at least one reaction in your bar.');
        }
        $user->forceFill(['n2n_reaction_bar' => $clean === self::DEFAULT_BAR ? null : $clean])->save();

        return $clean;
    }

    public function __construct(private readonly ConversationPrefs $prefs, private readonly MessageService $messages) {}

    public static function forwardMaxTargets(): int
    {
        return min(10, max(1, (int) Setting::getValue('n2n.forward_max_targets', 5)));
    }

    public static function editWindowMinutes(): int
    {
        return min(24 * 60, max(1, (int) Setting::getValue('n2n.edit_window_min', 15)));
    }

    // ---- reactions -------------------------------------------------------------------------------------

    /** Set, change or (same code again, or null) remove the member's one reaction. A blocked pair gets a silent no-op, never an error. @return string|null the member's reaction now */
    public function react(User $user, int $messageId, ?string $code): ?string
    {
        $m = $this->prefs->visibleMessage($user, $messageId);
        if ($code !== null && ! array_key_exists($code, self::REACTIONS)) {
            throw new MessagingException('invalid', 'That reaction is not available.');
        }
        if (RateLimiter::tooManyAttempts('n2n-react:'.$user->id, 40)) {
            throw new MessagingException('rate_limited', 'Slow down a little.');
        }
        RateLimiter::hit('n2n-react:'.$user->id, 60);
        $other = (int) Participant::query()->where('conversation_id', $m->conversation_id)->where('user_id', '!=', $user->id)->value('user_id');
        if ($other > 0 && Block::query()->where(fn ($q) => $q->where('blocker_id', $other)->where('blocked_id', $user->id))->orWhere(fn ($q) => $q->where('blocker_id', $user->id)->where('blocked_id', $other))->exists()) {
            return null;
        }
        $row = MessageReaction::query()->where('message_id', $m->id)->where('user_id', $user->id)->first();
        if ($code === null || ($row !== null && $row->code === $code)) {
            $row?->delete();
            $now = null;
        } else {
            MessageReaction::query()->updateOrCreate(['message_id' => $m->id, 'user_id' => $user->id], ['code' => $code]);
            $now = $code;
        }
        if ($other > 0) {
            Realtime::publish($other, 'message.reaction', ['conversation_id' => $m->conversation_id, 'message_id' => $m->id]);
        }

        return $now;
    }

    /** @param list<int> $messageIds @return array<int, array{counts: array<string, int>, mine: ?string}> */
    public function reactionsFor(User $user, array $messageIds): array
    {
        $out = [];
        foreach (MessageReaction::query()->whereIn('message_id', $messageIds)->get() as $r) {
            $out[$r->message_id]['counts'][$r->code] = ($out[$r->message_id]['counts'][$r->code] ?? 0) + 1;
            $out[$r->message_id]['mine'] = (int) $r->user_id === $user->id ? $r->code : ($out[$r->message_id]['mine'] ?? null);
        }

        return $out;
    }

    // ---- forward ---------------------------------------------------------------------------------------

    /**
     * Send a copy of a text message to up to N of the member's own conversations. Each copy is a normal send (price, limits, link filter, blocks all apply)
     * marked "Forwarded", carrying no name or trace of who first wrote it. Photos, videos and voice notes cannot be forwarded: that would copy stored files.
     *
     * @param  list<int>  $conversationIds
     * @return array{sent: int, failed: int, credit: bool}
     */
    public function forward(User $user, int $messageId, array $conversationIds, string $requestKey): array
    {
        $m = $this->prefs->visibleMessage($user, $messageId);
        if (trim((string) $m->body) === '' || $m->type !== 'text' || Attachment::query()->where('message_id', $m->id)->exists()) {
            throw new MessagingException('invalid', 'Only text messages can be forwarded.');
        }
        $targets = array_values(array_unique(array_map('intval', $conversationIds)));
        if ($targets === [] || count($targets) > self::forwardMaxTargets()) {
            throw new MessagingException('invalid', 'Choose between 1 and '.self::forwardMaxTargets().' chats.');
        }
        $sent = $failed = 0;
        $credit = false;
        foreach ($targets as $cid) {
            try {
                $r = $this->messages->send($user, ['conversation_id' => $cid], 'fwd-'.substr(sha1($requestKey.'|'.$m->id.'|'.$cid), 0, 24), (string) $m->body, null, [], null, null);
                Message::query()->whereKey($r['message']->id)->update(['forwarded' => true]);
                $sent++;
            } catch (InsufficientUsageCredit) {
                $credit = true;
                $failed += count($targets) - $sent - $failed;
                break;
            } catch (MessagingException) {
                $failed++;
            }
        }
        Auditor::log('n2n.message_forwarded', Message::class, $m->id, ['targets' => count($targets), 'sent' => $sent]);

        return ['sent' => $sent, 'failed' => $failed, 'credit' => $credit];
    }

    // ---- edit ------------------------------------------------------------------------------------------

    /** Correct your own text message inside the edit window. The earlier text is kept for moderation and never shown to the other member. */
    public function edit(User $user, int $messageId, string $body): Message
    {
        $m = $this->prefs->visibleMessage($user, $messageId);
        if ((int) $m->sender_id !== $user->id || $m->type !== 'text' || Attachment::query()->where('message_id', $m->id)->exists()) {
            throw new MessagingException('invalid', 'You can only edit your own text messages.');
        }
        if ($m->created_at->lt(now()->subMinutes(self::editWindowMinutes()))) {
            throw new MessagingException('invalid', 'This message is too old to edit ('.self::editWindowMinutes().' minutes).');
        }
        $body = trim($body);
        $max = max(1, (int) Setting::getValue('n2n.msg_max_chars', 4000));
        if ($body === '' || mb_strlen($body) > $max) {
            throw new MessagingException('invalid', $body === '' ? 'A message cannot be empty. Delete it instead.' : "Messages can be up to {$max} characters.");
        }
        if (($why = LinkFilter::problem($body)) !== null) {
            throw new MessagingException('link_blocked', $why);
        }
        if (RateLimiter::tooManyAttempts('n2n-edit:'.$user->id, 20)) {
            throw new MessagingException('rate_limited', 'You are editing too fast.');
        }
        RateLimiter::hit('n2n-edit:'.$user->id, 60);
        if ($body === trim((string) $m->body)) {
            return $m;
        }
        $log = (array) ($m->edit_log ?? []);
        $log[] = ['body' => $m->body, 'at' => now()->toIso8601String()];
        $m->forceFill(['body' => $body, 'edited_at' => now(), 'edit_log' => array_slice($log, -10)])->save();
        $other = (int) Participant::query()->where('conversation_id', $m->conversation_id)->where('user_id', '!=', $user->id)->value('user_id');
        foreach ([$user->id, $other] as $uid) {
            if ($uid > 0) {
                Realtime::publish($uid, 'message.edited', ['conversation_id' => $m->conversation_id, 'message_id' => $m->id]);
            }
        }

        return $m;
    }

    // ---- info ------------------------------------------------------------------------------------------

    /**
     * Delivery details for the member's OWN message. "Read" appears only if the other person allows read receipts, exactly like the ticks.
     *
     * @return array{sent: \Carbon\CarbonInterface, delivered: ?\Carbon\CarbonInterface, read: ?\Carbon\CarbonInterface, edited: ?\Carbon\CarbonInterface, forwarded: bool, cost: string}
     */
    public function info(User $user, int $messageId): array
    {
        $m = $this->prefs->visibleMessage($user, $messageId);
        if ((int) $m->sender_id !== $user->id) {
            throw new MessagingException('invalid', 'Details are available for your own messages.');
        }
        $other = (int) Participant::query()->where('conversation_id', $m->conversation_id)->where('user_id', '!=', $user->id)->value('user_id');
        $rc = MessageReceipt::query()->where('message_id', $m->id)->where('user_id', $other)->first();
        $allows = (bool) User::query()->whereKey($other)->value('n2n_read_receipts');

        return [
            'sent' => $m->created_at, 'delivered' => $rc?->delivered_at ?? ($rc?->read_at), 'read' => $allows ? $rc?->read_at : null,
            'edited' => $m->edited_at, 'forwarded' => (bool) $m->forwarded, 'cost' => (string) $m->cost_credits,
        ];
    }
}
