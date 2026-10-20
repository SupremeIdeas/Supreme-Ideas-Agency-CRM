<?php

namespace App\Notifications;

use App\Models\SupportConversation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Tells a staff member (or admin/sub-admin) a support ticket has been handed
 * to them — the "pass it to someone with more attention" half of the
 * single-owner rule: Nia handles the crowd, one human owns a given thread at
 * a time, and this is how ownership visibly changes hands.
 */
class TicketTransferredNotification extends Notification implements ShouldQueue
{
    use \App\Notifications\Concerns\InApp;
    use Queueable;

    public function __construct(private readonly SupportConversation $conversation, private readonly string $fromName) {}

    public function via(object $notifiable): array
    {
        return ['database', 'webpush'];
    }

    public function inApp(object $notifiable): array
    {
        return [
            'category' => 'support',
            'icon' => 'arrow-right-left',
            'title' => 'A ticket was handed to you',
            'body' => "{$this->fromName} passed you conversation #{$this->conversation->id}.",
            'action_url' => route('admin.tickets'),
            'action_label' => 'Open ticket',
        ];
    }
}
