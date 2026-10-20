<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Content/billing/hot-menu blueprint Phase F1 — sent to the merchant when an
 * admin's approval could not complete because their wallet lacked the funds.
 * Tells them exactly how much more they need, so they aren't left guessing —
 * an admin will re-trigger the charge once the wallet is topped up.
 */
class WhiteLabelApprovalFailedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $brandName,
        public float $shortfallUsd,
    ) {}

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your white-label approval needs a wallet top-up — '.config('app.name'))
            ->greeting('Your "'.$this->brandName.'" request was approved, but the charge could not complete.')
            ->line('Your wallet is short by $'.number_format($this->shortfallUsd, 2).' USD.')
            ->line('Top up your wallet and we\'ll retry the charge — no need to resubmit your request.')
            ->action('Top up wallet', route('wallet'));
    }
}
