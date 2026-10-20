<?php

namespace App\Notifications;

use App\Models\ChannelConnection;
use App\Models\InboundMessage;
use Illuminate\Notifications\Notification;

/** A new message arrived on a workspace channel (WhatsApp, Telegram, Instagram). Database channel only, no message text in the notification. */
class InboundChannelMessageNotification extends Notification
{
    public function __construct(public InboundMessage $message) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'inbound_channel_message',
            'title' => 'New '.ChannelConnection::label($this->message->channel).' message',
            'message' => 'Your team has a new '.ChannelConnection::label($this->message->channel).' message.',
            'url' => route('numbers.messages'),
            'icon' => 'message-circle',
        ];
    }
}
