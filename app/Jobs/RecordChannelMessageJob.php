<?php

namespace App\Jobs;

use App\Models\ChannelConnection;
use App\Models\InboundMessage;
use App\Models\MessageThread;
use App\Models\User;
use App\Notifications\InboundChannelMessageNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Files one inbound WhatsApp / Telegram / Instagram message into the workspace inbox (MASTER-ONLY, Business Suite Prompt 16), off the webhook request.
 * It writes the same InboundMessage row the SMS path writes (the model hook then updates the MessageThread), owned by the workspace owner and tagged with
 * the workspace so seats can see it. Idempotent per (provider, provider_ref): a re-delivered webhook never creates a second row.
 */
class RecordChannelMessageJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public int $connectionId,
        public string $counterpart,
        public string $body,
        public string $providerRef,
        public ?int $receivedAt = null,
    ) {
    }

    public function handle(): void
    {
        $c = ChannelConnection::query()->with('walletGroup')->find($this->connectionId);
        if (! $c || ! $c->isActive() || ! $c->walletGroup || $this->counterpart === '' || $this->providerRef === '') {
            return;
        }

        if (InboundMessage::where('provider', $c->channel)->where('provider_ref', $this->providerRef)->exists()) {
            return;
        }

        $message = InboundMessage::create([
            'user_id' => $c->walletGroup->owner_user_id,
            'channel' => $c->channel,
            'wallet_group_id' => $c->wallet_group_id,
            'virtual_number_id' => null,
            'from_number' => mb_substr($this->counterpart, 0, 32),
            'body' => mb_substr($this->body, 0, 4096),
            'provider' => $c->channel,
            'provider_ref' => mb_substr($this->providerRef, 0, 190),
            'received_at' => $this->receivedAt ? \Illuminate\Support\Carbon::createFromTimestamp($this->receivedAt) : now(),
        ]);

        $c->forceFill(['last_event_at' => now(), 'last_error' => null])->save();

        // A person who says STOP (/stop on Telegram) is not messaged again until they write to us themselves; writing again lifts it.
        $stop = in_array(strtoupper(trim($this->body)), ['STOP', 'UNSUBSCRIBE', 'CANCEL', '/STOP'], true);
        MessageThread::query()->where('user_id', $message->user_id)->where('channel', $c->channel)->where('counterpart_number', $message->from_number)
            ->update(['opted_out_at' => $stop ? now() : null]);

        if (class_exists(\App\Services\Business\Integrations\WebhookEvents::class)) {
            \App\Services\Business\Integrations\WebhookEvents::fire('message.received', $c->wallet_group_id, [
                'channel' => $c->channel, 'counterpart' => $message->from_number, 'body' => $message->body, 'received_at' => $message->received_at?->toIso8601String(),
            ]);
        }

        try {
            User::find($c->walletGroup->owner_user_id)?->notify(new InboundChannelMessageNotification($message));
        } catch (\Throwable) {
            // best effort: a notification failure must not fail recording
        }
    }
}
