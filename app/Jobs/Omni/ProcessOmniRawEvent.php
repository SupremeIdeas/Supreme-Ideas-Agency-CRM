<?php

namespace App\Jobs\Omni;

use App\Models\Omni\OmniRawEvent;
use App\Services\Omni\Pipeline;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/** Turn one stored webhook body into inbox rows (MASTER-ONLY). Safe to run twice; releases itself when a status beat its message. */
class ProcessOmniRawEvent implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 6;

    public function __construct(public int $rawEventId)
    {
    }

    public function backoff(): array
    {
        return [5, 20, 60, 180, 600];
    }

    public function handle(Pipeline $pipeline): void
    {
        $raw = OmniRawEvent::query()->find($this->rawEventId);
        if (! $raw || $raw->status === 'done') {
            return;
        }
        if (! $pipeline->process($raw)) {
            if ($this->attempts() >= $this->tries) {
                $raw->forceFill(['status' => 'failed', 'error' => 'a status never found its message', 'processed_at' => now()])->save();

                return;
            }
            $this->release($this->backoff()[min($this->attempts() - 1, 4)]);
        }
    }
}
