<?php

namespace App\Events\N2N;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/** Pushes one realtime envelope to a user's private channel (N2N, MASTER-ONLY; Reverb driver). Small payload: ids, never message bodies. */
class RealtimeBroadcast implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(public int $userId, public array $envelope) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('n2n.user.'.$this->userId)];
    }

    public function broadcastAs(): string
    {
        return 'n2n.event';
    }

    public function broadcastWith(): array
    {
        return $this->envelope;
    }
}
