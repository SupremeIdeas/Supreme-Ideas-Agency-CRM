<?php

namespace App\Jobs\Omni;

use App\Models\Omni\OmniBroadcast;
use App\Services\Omni\Broadcast\BroadcastService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/** Sends one throttled batch of a broadcast, then schedules the next minute's batch until nobody is left (MASTER-ONLY). */
class SendOmniBroadcast implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(public int $broadcastId)
    {
    }

    public function handle(BroadcastService $svc): void
    {
        $b = OmniBroadcast::query()->find($this->broadcastId);
        if ($b && $svc->sendBatch($b)) {
            self::dispatch($b->id)->delay(now()->addMinute());
        }
    }
}
