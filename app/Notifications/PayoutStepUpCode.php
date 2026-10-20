<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** The email code for payout step-up (Addendum D-3.5). Sent immediately — it expires in 10 minutes. */
class PayoutStepUpCode extends Notification
{
    use \App\Notifications\Concerns\BrandedMail;
    public function __construct(private string $code) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return $this->brandedMail('payout-step-up', 'Your security code — '.config('app.name'), 'Your security code',
            'Hi '.($notifiable->name ?? 'there').',',
            ['Use this code to confirm a payout change. It expires in 10 minutes.', ['code' => (string) $this->code]],
            null, 'If you did not ask for this, ignore this email — nothing will change. Consider changing your password.');
    }
}
