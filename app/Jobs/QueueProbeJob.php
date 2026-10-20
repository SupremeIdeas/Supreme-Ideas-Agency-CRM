<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;

/** Proves a queue worker is running: it does nothing except record that it ran (Admin > Business Suite > Setup and health > "Test the queue now"). */
class QueueProbeJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public string $token)
    {
        $this->onQueue('default');
    }

    public function handle(): void
    {
        $state = Cache::get('setup.queue_probe');
        if (is_array($state) && ($state['token'] ?? null) === $this->token) {
            Cache::put('setup.queue_probe', $state + ['done_at' => now()->toIso8601String()], 86400);
        }
    }
}
