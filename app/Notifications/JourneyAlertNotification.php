<?php

namespace App\Notifications;

use App\Notifications\Concerns\BrandedMail;
use App\Notifications\Concerns\InApp;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * A protective Journey alert (data running low, eSIM expiring, number renewal the wallet cannot cover) delivered off-app. Only ever built by
 * JourneyEngine\Delivery, which has already checked the user's per-channel consent, the quiet hours and the daily cap. It carries the rendered card text and its "why",
 * never message content (the signals never read any).
 */
class JourneyAlertNotification extends Notification implements ShouldQueue
{
    use BrandedMail;
    use InApp;
    use Queueable;

    /** @param array{title: string, body: string, cta: string, why: string, url: string, priority: string} $card
     *  @param list<string> $channels subset of ['push', 'email'] the user has consented to */
    public function __construct(public array $card, public array $channels) {}

    public function via(object $notifiable): array
    {
        return array_values(array_filter([in_array('push', $this->channels, true) ? 'webpush' : null, in_array('email', $this->channels, true) ? 'mail' : null]));
    }

    public function inApp(object $notifiable): array
    {
        return [
            'category' => 'system', 'icon' => 'bell',
            'title' => $this->card['title'], 'body' => $this->card['body'],
            'action_url' => $this->card['url'], 'action_label' => $this->card['cta'],
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return $this->brandedMail('journey-alert', $this->card['title'].' — '.config('app.name'), $this->card['title'],
            'Hi '.($notifiable->name ?? 'there').',', [$this->card['body']], [$this->card['cta'], $this->card['url']],
            $this->card['why'].' '.__('journey_engine.delivery.manage', ['url' => url('/account/alerts')]));
    }
}
