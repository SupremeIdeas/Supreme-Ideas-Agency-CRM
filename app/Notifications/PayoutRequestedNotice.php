<?php

namespace App\Notifications;

use App\Models\PayoutRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

/**
 * Sent to the payee the moment a withdrawal is requested (and when a payout account changes), with a
 * one-click "This wasn't me" link that freezes payouts, cancels what can still be cancelled and tells
 * an admin (Addendum D-3.5). The link is signed and expires in 7 days.
 */
class PayoutRequestedNotice extends Notification implements ShouldQueue
{
    use \App\Notifications\Concerns\BrandedMail;
    use Queueable;

    public function __construct(public ?int $payoutRequestId = null, public string $kind = 'request') {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $r = $this->payoutRequestId ? PayoutRequest::find($this->payoutRequestId) : null;
        $notMe = URL::temporarySignedRoute('payouts.not-me', now()->addDays(7), ['user' => $notifiable->id]);

        if ($this->kind === 'account') {
            $subject = 'Your payout account was changed — '.config('app.name');
            $heading = 'Payout account changed';
            $lines = ['A payout account on your '.config('app.name').' account was just added or changed.'];
        } else {
            $subject = 'Withdrawal requested — '.config('app.name');
            $heading = 'Withdrawal requested';
            $lines = [
                'We received a withdrawal request'.($r ? ' for '.number_format((float) $r->amount, 2).' '.$r->currency : '').' on your account.',
                'You can cancel it from your Withdrawals page until it has been sent.',
            ];
        }
        $lines[] = 'If this was you, there is nothing to do.';

        return $this->brandedMail('payout-requested', $subject, $heading, 'Hi '.($notifiable->name ?? 'there').',', $lines,
            ["This wasn't me", $notMe], 'That button pauses payouts on your account straight away and tells our team.');
    }
}
