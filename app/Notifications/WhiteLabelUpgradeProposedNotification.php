<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Content/billing/hot-menu blueprint Phase F2 — fanned out to every admin/
 * super_admin when a merchant proposes a Basic-to-Extended installment
 * timeframe. The merchant's proposed number of days is never binding on its
 * own — an admin must confirm the actual enforced deadline.
 */
class WhiteLabelUpgradeProposedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $brandName,
        public int $proposedDays,
        public float $balanceDue,
    ) {}

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Upgrade timeframe proposed — '.config('app.name'))
            ->greeting('"'.$this->brandName.'" proposed an upgrade timeframe.')
            ->line('Remaining balance to Extended: $'.number_format($this->balanceDue, 2))
            ->line('Proposed timeframe: '.$this->proposedDays.' day(s).')
            ->action('Set the enforced deadline', route('admin.white-label'))
            ->line('The merchant\'s proposal is not binding — set the actual deadline that will be enforced.');
    }
}
