<?php

namespace App\Jobs\Omni;

use App\Models\Omni\OmniFlow;
use App\Models\Omni\OmniFlowRun;
use App\Services\Omni\Flows\FlowEngine;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/** Resumes a flow after its delay step (MASTER-ONLY). A run that is no longer "running" is left alone, so a double delivery cannot repeat steps. */
class ContinueOmniFlow implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(public int $runId, public int $fromStep)
    {
    }

    public function handle(FlowEngine $engine): void
    {
        $run = OmniFlowRun::query()->find($this->runId);
        if (! $run || $run->status !== 'running') {
            return;
        }
        $flow = OmniFlow::query()->forWorkspace((int) $run->workspace_id)->find($run->flow_id);
        $flow ? $engine->execute($flow, $run, $this->fromStep) : $run->forceFill(['status' => 'stopped'])->save();
    }
}
