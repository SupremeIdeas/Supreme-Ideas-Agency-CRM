<?php

namespace App\Jobs\Business;

use App\Models\BusinessBrainRun;
use App\Services\Business\Brain\BusinessBrain;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/** One inbox-assistant action, off the request cycle (MASTER-ONLY). Single attempt: the gateway already retries transient errors and a repeat could double the cost. */
class BusinessBrainJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 120;

    public function __construct(public int $runId) {}

    public function handle(BusinessBrain $brain): void
    {
        $run = BusinessBrainRun::query()->where('status', 'pending')->find($this->runId);
        if ($run !== null) {
            $brain->perform($run);
        }
    }

    public function failed(\Throwable $e): void
    {
        BusinessBrainRun::query()->where('id', $this->runId)->where('status', 'pending')->update(['status' => 'error', 'output' => 'The assistant is unavailable right now. Nothing was used from your allowance.']);
    }
}
