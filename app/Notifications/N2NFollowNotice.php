<?php

namespace App\Notifications;

use App\Notifications\Concerns\InApp;
use Illuminate\Notifications\Notification;

/** "X followed you" / "X wants to connect" / "You are now connected" with a Follow back action (N2N Batch 9, MASTER-ONLY). In-app only. */
class N2NFollowNotice extends Notification
{
    use InApp;

    public function __construct(public string $kind, public string $name, public string $profileUrl) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function inApp(object $notifiable): array
    {
        $copy = [
            'followed' => ['title' => 'New follower', 'body' => $this->name.' followed you.', 'label' => 'Follow back'],
            'connect' => ['title' => 'Connection request', 'body' => $this->name.' wants to connect with you.', 'label' => 'Follow back'],
            'connected' => ['title' => 'You are connected', 'body' => 'You and '.$this->name.' are now connected.', 'label' => 'View profile'],
        ][$this->kind] ?? ['title' => 'Naara', 'body' => $this->name, 'label' => 'Open'];

        return ['category' => 'system', 'icon' => $this->kind === 'connected' ? 'users' : 'user-plus', 'title' => $copy['title'], 'body' => $copy['body'], 'action_url' => $this->profileUrl, 'action_label' => $copy['label']];
    }
}
