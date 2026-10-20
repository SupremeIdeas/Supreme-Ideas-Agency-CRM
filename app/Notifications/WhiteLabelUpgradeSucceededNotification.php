<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Content/billing/hot-menu blueprint Phase F2 — sent to the merchant when the
 * scheduled installment charge succeeds on or before the deadline and the
 * instance is upgraded to Extended.
 */
class WhiteLabelUpgradeSucceededNotification extends Notification implements ShouldQueue
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
            ->subject('Upgraded to Extended — '.config('app.name'))
            ->greeting('"'.$this->brandName.'" is now on Extended.')
            ->line('Your installment balance was charged successfully and every Extended feature is unlocked on your next check-in.');
    }
}
