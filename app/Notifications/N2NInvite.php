<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** "X invited you to Naara" (N2N Batch 9, MASTER-ONLY): carries only the inviter's referral link. */
class N2NInvite extends Notification
{
    use Queueable;

    public function __construct(public string $inviterName, public string $link) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject("{$this->inviterName} invited you to Naara")
            ->line("{$this->inviterName} would like to chat with you on Naara.")
            ->action('Join Naara', $this->link)
            ->line('If you were not expecting this, you can ignore it.');
    }
}
