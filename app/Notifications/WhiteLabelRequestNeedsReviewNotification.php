<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Content/billing/hot-menu blueprint Phase F1 — fanned out to every admin/
 * super_admin the moment a merchant submits a new white-label license
 * request, so it doesn't sit unnoticed in the oversight queue.
 */
class WhiteLabelRequestNeedsReviewNotification extends Notification implements ShouldQueue
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
            ->subject('New white-label request needs review — '.config('app.name'))
            ->greeting('A new white-label license request needs review.')
            ->line('Brand: '.$this->brandName)
            ->action('Review in White Label Oversight', route('admin.white-label'))
            ->line('Set a price, then approve it once the merchant is ready — approving is what triggers the charge.');
    }
}
