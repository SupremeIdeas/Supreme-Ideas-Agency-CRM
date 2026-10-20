<?php

namespace App\Services\N2N\Messaging;

use App\Models\N2N\Attachment;
use App\Models\N2N\Participant;
use App\Models\N2N\ScheduledMessage;
use App\Models\Setting;
use App\Models\User;
use App\Services\N2N\Credit\InsufficientUsageCredit;
use App\Services\N2N\Media\MediaPolicy;
use App\Services\N2N\Media\MediaService;
use App\Services\N2N\Media\NaaraPro;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Log;

/**
 * Scheduled sending (N2N, MASTER-ONLY). Queueing validates the text exactly as a live send would and checks the sender is a member of the conversation,
 * but charges nothing and tells nobody; delivery goes through MessageService at the due time, so every rule (block, privacy, rate limit, credit) is judged
 * then. A failure is shown to the sender only, in generic terms: a block never becomes visible through a failed schedule.
 */
class ScheduledSend
{
    public function __construct(private readonly MessageService $messages) {}

    public static function minLeadSeconds(): int
    {
        return 60;
    }

    public static function maxDays(): int
    {
        return min(90, max(1, (int) Setting::getValue('n2n.schedule_max_days', 30)));
    }

    public static function maxPending(): int
    {
        return max(1, (int) Setting::getValue('n2n.schedule_max_pending', 25));
    }

    /** @throws MessagingException */
    public function create(User $sender, int $conversationId, string $clientId, string $body, CarbonInterface $at, ?int $replyTo = null, array $attachmentIds = []): ScheduledMessage
    {
        if (! preg_match('/^[A-Za-z0-9_\-]{8,64}$/', $clientId)) {
            throw new MessagingException('invalid', 'That message could not be scheduled.');
        }
        if (($dupe = ScheduledMessage::query()->where('sender_id', $sender->id)->where('client_id', $clientId)->first()) !== null) {
            return $dupe;
        }
        if (! Participant::query()->where('conversation_id', $conversationId)->where('user_id', $sender->id)->exists()) {
            throw new MessagingException('not_found', 'That chat could not be found.');
        }
        $body = trim($body);
        $attachmentIds = array_values(array_unique(array_map('intval', $attachmentIds)));
        $max = max(1, (int) Setting::getValue('n2n.msg_max_chars', 4000));
        if (($body === '' && $attachmentIds === []) || mb_strlen($body) > $max) {
            throw new MessagingException('invalid', $body === '' ? 'Write a message first.' : "Messages can be up to {$max} characters.");
        }
        if (($why = LinkFilter::problem($body)) !== null) {
            throw new MessagingException('link_blocked', $why);
        }
        if ($at->lt(now()->addSeconds(self::minLeadSeconds())) || $at->gt(now()->addDays(self::maxDays()))) {
            throw new MessagingException('invalid', 'Pick a time between one minute and '.self::maxDays().' days from now.');
        }
        if (ScheduledMessage::query()->where('sender_id', $sender->id)->where('status', ScheduledMessage::PENDING)->count() >= self::maxPending()) {
            throw new MessagingException('invalid', 'You have '.self::maxPending().' scheduled messages waiting. Cancel one or wait for it to send.');
        }

        if ($attachmentIds !== []) {
            $this->holdAttachments($sender, $attachmentIds, $at);
        }

        return ScheduledMessage::query()->create(['sender_id' => $sender->id, 'conversation_id' => $conversationId, 'client_id' => $clientId, 'body' => $body, 'attachment_ids' => $attachmentIds ?: null, 'reply_to_id' => $replyTo, 'send_at' => $at]);
    }

    public static function maxPendingMedia(): int
    {
        return max(1, (int) Setting::getValue('n2n.schedule_max_pending_media', 5));
    }

    /**
     * Keep the sender's own staged uploads alive until the send time (plus a day of retry room). Pro is checked now so a person is told at once, and again
     * at delivery. The number of queued media messages is capped because each one holds storage that nothing is yet paying for.
     *
     * @param  list<int>  $ids
     */
    private function holdAttachments(User $sender, array $ids, CarbonInterface $at): void
    {
        if (! NaaraPro::active($sender)) {
            throw new MessagingException('pro_required', 'Unlock photos, videos and voice notes with Naara Pro.');
        }
        if (count($ids) > MediaPolicy::maxPerMessage()) {
            throw new MessagingException('invalid', 'You can attach up to '.MediaPolicy::maxPerMessage().' files to one message.');
        }
        $queued = ScheduledMessage::query()->where('sender_id', $sender->id)->where('status', ScheduledMessage::PENDING)->whereNotNull('attachment_ids')->count();
        if ($queued >= self::maxPendingMedia()) {
            throw new MessagingException('invalid', 'You have '.self::maxPendingMedia().' scheduled messages with files waiting. Cancel one or wait for it to send.');
        }
        $rows = Attachment::query()->whereIn('id', $ids)->where('uploader_id', $sender->id)->where('status', Attachment::STAGED)->where('mime', '!=', 'pending')->get();
        if ($rows->count() !== count($ids)) {
            throw new MessagingException('invalid', 'Your upload expired or was not found. Please attach it again.');
        }
        Attachment::query()->whereIn('id', $ids)->update(['expires_at' => $at->copy()->addDay()]);
    }

    /** Remove the held uploads of a schedule that will never send. */
    public function releaseAttachments(ScheduledMessage $row): void
    {
        $ids = (array) $row->attachment_ids;
        if ($ids === []) {
            return;
        }
        $media = app(MediaService::class);
        foreach (Attachment::query()->whereIn('id', $ids)->where('uploader_id', $row->sender_id)->where('status', Attachment::STAGED)->get() as $a) {
            $media->deleteObjects($a);
        }
    }

    public function cancel(User $user, int $id): bool
    {
        $row = ScheduledMessage::query()->whereKey($id)->where('sender_id', $user->id)->where('status', ScheduledMessage::PENDING)->first();
        if ($row === null) {
            return false;
        }
        $won = ScheduledMessage::query()->whereKey($id)->where('status', ScheduledMessage::PENDING)->update(['status' => ScheduledMessage::CANCELLED]) === 1;
        if ($won) {
            $this->releaseAttachments($row);
        }

        return $won;
    }

    /** A pending row can be re-timed or its text corrected until it is claimed for delivery. */
    public function edit(User $user, int $id, string $body, CarbonInterface $at): bool
    {
        $body = trim($body);
        $hasFiles = ScheduledMessage::query()->whereKey($id)->where('sender_id', $user->id)->whereNotNull('attachment_ids')->exists();
        if (($body === '' && ! $hasFiles) || mb_strlen($body) > max(1, (int) Setting::getValue('n2n.msg_max_chars', 4000)) || LinkFilter::problem($body) !== null
            || $at->lt(now()->addSeconds(self::minLeadSeconds())) || $at->gt(now()->addDays(self::maxDays()))) {
            return false;
        }

        return ScheduledMessage::query()->whereKey($id)->where('sender_id', $user->id)->where('status', ScheduledMessage::PENDING)->update(['body' => $body, 'send_at' => $at]) === 1;
    }

    /** Deliver everything that is due. Safe to run from several workers at once: a row is claimed with a compare-and-set. @return int how many were sent */
    public function dispatchDue(int $limit = 200): int
    {
        // A worker that died mid-delivery: put its rows back (the delivery id is stable, so a replay can never send twice).
        ScheduledMessage::query()->where('status', ScheduledMessage::SENDING)->where('claimed_at', '<', now()->subMinutes(5))->update(['status' => ScheduledMessage::PENDING]);
        $sent = 0;
        $due = ScheduledMessage::query()->where('status', ScheduledMessage::PENDING)->where('send_at', '<=', now())->orderBy('send_at')->limit($limit)->pluck('id');
        foreach ($due as $id) {
            $claimed = ScheduledMessage::query()->whereKey($id)->where('status', ScheduledMessage::PENDING)->update(['status' => ScheduledMessage::SENDING, 'claimed_at' => now()]);
            if ($claimed !== 1) {
                continue;
            }
            $row = ScheduledMessage::query()->find($id);
            $sent += $this->deliver($row) ? 1 : 0;
        }

        return $sent;
    }

    private function deliver(ScheduledMessage $row): bool
    {
        $sender = User::query()->find($row->sender_id);
        $fail = function (string $reason, bool $retry = false) use ($row) {
            $row->forceFill(['status' => $retry ? ScheduledMessage::PENDING : ScheduledMessage::FAILED, 'fail_reason' => $retry ? null : $reason, 'attempts' => $row->attempts + 1])
                ->forceFill($retry ? ['send_at' => now()->addMinutes(2)] : [])->save();
            if (! $retry) {
                $this->releaseAttachments($row);   // a schedule that will never send must not leave uploads in storage
            }

            return false;
        };
        if ($sender === null || ! $sender->is_active) {
            return $fail('undelivered');
        }
        try {
            $r = $this->messages->send($sender, ['conversation_id' => (int) $row->conversation_id], 'sch-'.$row->id.'-'.substr(sha1($row->client_id), 0, 12), (string) $row->body, null, array_map('intval', (array) $row->attachment_ids), null, $row->reply_to_id);
        } catch (InsufficientUsageCredit) {
            return $fail('credit');
        } catch (MessagingException $e) {
            return $fail($e->reason === 'rate_limited' && $row->attempts < 3 ? 'retry' : 'undelivered', $e->reason === 'rate_limited' && $row->attempts < 3);
        } catch (\Throwable $e) {
            Log::warning('n2n scheduled send failed', ['id' => $row->id, 'error' => $e->getMessage()]);

            return $fail('error');
        }
        $row->forceFill(['status' => ScheduledMessage::SENT, 'message_id' => $r['message']->id, 'attempts' => $row->attempts + 1])->save();

        return true;
    }
}
