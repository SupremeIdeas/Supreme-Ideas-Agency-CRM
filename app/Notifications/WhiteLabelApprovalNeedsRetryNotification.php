<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Content/billing/hot-menu blueprint Phase F1 — fanned out to every admin/
 * super_admin when an approval's wallet charge fails for insufficient funds,
 * so nobody is left guessing why activation didn't happen. The instance sits
 * in APPROVED_AWAITING_FUNDS until the same approval action is re-triggered.
 */
class WhiteLabelApprovalNeedsRetryNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $brandName) {}

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('White-label approval could not complete — '.config('app.name'))
            ->greeting('Approving "'.$this->brandName.'" could not complete.')
            ->line('The merchant\'s wallet did not have enough funds at the moment of approval — they\'ve been notified of exactly how much more is needed.')
            ->action('Review in White Label Oversight', route('admin.white-label'))
            ->line('Once they top up, re-approve from the same screen — no need to redo anything.');
    }
}
