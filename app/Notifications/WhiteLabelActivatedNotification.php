<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Content/billing/hot-menu blueprint Phase F1 — sent to the merchant once an
 * admin's approval succeeds and the license is live. Since activation is now
 * admin-triggered (not a button the merchant clicks themselves), this is
 * their only signal that it happened.
 */
class WhiteLabelActivatedNotification extends Notification implements ShouldQueue
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
            ->subject('Your white-label license is active — '.config('app.name'))
            ->greeting('"'.$this->brandName.'" is approved and active.')
            ->line('Your license key is ready in your dashboard, and your fork can now activate against it.');
    }
}
