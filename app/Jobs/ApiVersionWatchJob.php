<?php

namespace App\Jobs;

use App\Services\ProviderRadar\ApiVersionWatch;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/** The daily API version-watch run, as a queued job (every external call is a job, never inside a request). Read-only: it cannot touch money. */
class ApiVersionWatchJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 300;

    public function handle(ApiVersionWatch $watch): void
    {
        $watch->run();
    }
}
