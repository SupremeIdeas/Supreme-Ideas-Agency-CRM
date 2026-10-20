<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** "X sent you NaaraCredit": carries ONLY the sender's referral link, never the amount's claim token (N2N, MASTER-ONLY). */
class N2NCreditGiftInvite extends Notification
{
    use Queueable;

    public function __construct(public string $senderName, public string $amount, public string $link, public int $hours) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject("{$this->senderName} sent you {$this->amount} NaaraCredit")
            ->line("{$this->senderName} sent you {$this->amount} NaaraCredit to chat and call on Naara.")
            ->line("Join with this link and verify this email within {$this->hours} hours to receive it.")
            ->action('Join Naara', $this->link)
            ->line('If you were not expecting this, you can ignore it. The credit returns to the sender.');
    }
}
