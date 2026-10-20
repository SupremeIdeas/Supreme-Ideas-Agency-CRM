<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Content/billing/hot-menu blueprint Phase F2 — owner decision: a missed
 * installment deadline reverts the instance to standard Basic terms (no
 * suspension, no auto-extension). Sent when that happens so the merchant
 * isn't left guessing why the upgrade didn't go through.
 */
class WhiteLabelUpgradeMissedDeadlineNotification extends Notification implements ShouldQueue
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
            ->subject('Your upgrade window has closed — '.config('app.name'))
            ->greeting('The upgrade deadline for "'.$this->brandName.'" has passed.')
            ->line('The balance wasn\'t charged in time, so the instance stays on standard Basic terms — nothing else changes, and there\'s no penalty.')
            ->line('You can propose a new upgrade timeframe any time from your dashboard.');
    }
}
