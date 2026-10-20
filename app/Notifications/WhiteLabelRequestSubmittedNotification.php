<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Content/billing/hot-menu blueprint Phase F1 — sent to the merchant the
 * moment they submit a white-label license request. Charging now happens
 * only once a super-admin approves it (never at request time), so this sets
 * that expectation up front: get your wallet funded before approval lands,
 * since approval triggers an automatic charge.
 */
class WhiteLabelRequestSubmittedNotification extends Notification implements ShouldQueue
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
            ->subject('Your white-label request is in for review — '.config('app.name'))
            ->greeting('Thanks — your request for "'.$this->brandName.'" is in.')
            ->line('An admin will review it and confirm a price shortly.')
            ->line('Once approved, your wallet is charged automatically to activate your license — make sure it\'s funded ahead of time so approval isn\'t held up.');
    }
}
