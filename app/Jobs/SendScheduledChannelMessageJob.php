<?php

namespace App\Jobs;

use App\Models\ScheduledChannelMessage;
use App\Services\Inbox\ChannelSender;
use App\Services\Inbox\ChannelSendException;
use App\Services\Inbox\OutgoingMessage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Delivers one scheduled shared-inbox message (MASTER-ONLY). It never retries: the message is claimed (pending -> sending) before it is queued, and a
 * failure is recorded on the row for the team to see, so a message can be sent at most once and a silent resend can never happen.
 */
class SendScheduledChannelMessageJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public int $scheduledId)
    {
    }

    public function handle(ChannelSender $sender): void
    {
        $row = ScheduledChannelMessage::query()->with('thread')->find($this->scheduledId);
        if (! $row || $row->status !== ScheduledChannelMessage::SENDING) {
            return;
        }
        $thread = $row->thread;
        $user = \App\Models\User::find($row->user_id);
        if (! $thread || ! $user) {
            $row->forceFill(['status' => ScheduledChannelMessage::FAILED, 'error' => 'The conversation or the sender no longer exists.'])->save();

            return;
        }

        try {
            $sender->send($thread, OutgoingMessage::fromArray((array) $row->payload), $user);
            $row->forceFill(['status' => ScheduledChannelMessage::SENT, 'sent_at' => now(), 'error' => null])->save();
        } catch (ChannelSendException $e) {
            $row->forceFill(['status' => ScheduledChannelMessage::FAILED, 'error' => mb_substr($e->getMessage(), 0, 190)])->save();
        } catch (\Throwable $e) {
            report($e);
            $row->forceFill(['status' => ScheduledChannelMessage::FAILED, 'error' => 'It could not be sent.'])->save();
        }
    }
}
