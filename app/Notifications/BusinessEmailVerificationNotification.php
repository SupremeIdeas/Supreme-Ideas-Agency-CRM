<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

/**
 * Business Suite Prompt 13 §3.3 — a trust-signal verification for
 * `WalletGroup.business_email`, never a login credential and never touching
 * `users`. Sent to an arbitrary address via Notification::route('mail', ...)
 * since, unlike a User, a WalletGroup isn't itself Notifiable.
 */
class BusinessEmailVerificationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $walletGroupId)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = URL::temporarySignedRoute(
            'business.email.verify',
            now()->addHours(24),
            ['walletGroup' => $this->walletGroupId],
        );

        return (new MailMessage)
            ->subject('Verify your business email — '.config('app.name'))
            ->view('emails.business-email-verify', ['url' => $url]);
    }
}
