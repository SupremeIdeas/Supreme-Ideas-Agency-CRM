<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Content/billing/hot-menu blueprint Phase F2 — an advance-notice reminder,
 * mirroring RenewVirtualNumbersCommand's own notice pattern: sent once per
 * upgrade window as the admin-confirmed deadline approaches.
 */
class WhiteLabelUpgradeDueSoonNotification extends Notification implements ShouldQueue
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
            ->subject('Your upgrade balance is due soon — '.config('app.name'))
            ->greeting('A reminder for "'.$this->brandName.'".')
            ->line('$'.number_format($this->balanceDue, 2).' is due by '.$this->deadline->format('j M Y').' to complete your upgrade to Extended.')
            ->line('We\'ll charge your wallet automatically on that date. If the balance isn\'t there, the upgrade window closes and your instance stays on standard Basic terms — no other action needed if you\'re already funded.')
            ->action('Go to wallet', route('wallet'));
    }
}
