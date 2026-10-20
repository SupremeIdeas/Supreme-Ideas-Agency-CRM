<?php

namespace App\Notifications;

use App\Notifications\Concerns\InApp;
use Illuminate\Notifications\Notification;

/** "Missed call from X" with a Call back action (N2N Batch 10, MASTER-ONLY). In-app only. */
class N2NMissedCall extends Notification
{
    use InApp;

    public function __construct(public string $name, public string $url) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function inApp(object $notifiable): array
    {
        return ['category' => 'system', 'icon' => 'phone', 'title' => 'Missed call', 'body' => 'You missed a call from '.$this->name.'.', 'action_url' => url('/n2n/calls'), 'action_label' => 'Call back'];
    }
}
