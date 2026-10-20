<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Content/billing/hot-menu blueprint Phase F2 — sent to the merchant once an
 * admin confirms the actual ENFORCED deadline for their proposed Basic-to-
 * Extended installment upgrade.
 */
class WhiteLabelUpgradeDeadlineSetNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $brandName,
        public float $balanceDue,
        public \DateTimeInterface $deadline,
    ) {}

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your upgrade deadline is set — '.config('app.name'))
            ->greeting('Your upgrade to Extended for "'.$this->brandName.'" has a confirmed deadline.')
            ->line('Balance due: $'.number_format($this->balanceDue, 2))
            ->line('Deadline: '.$this->deadline->format('j M Y').'.')
            ->line('We\'ll charge your wallet automatically on that date — make sure it\'s funded ahead of time.')
            ->action('Go to wallet', route('wallet'));
    }
}
