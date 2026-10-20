<?php

namespace App\Console\Commands;

use App\Jobs\ApiVersionWatchJob;
use Illuminate\Console\Command;

/** provider:api-watch (master-only): queue the read-only API version-watch. See App\Services\ProviderRadar\ApiVersionWatch. */
class ProviderApiWatchCommand extends Command
{
    protected $signature = 'provider:api-watch';

    protected $description = 'Check every provider API for a newer version, a Deprecation header or a Sunset date (read-only, queued)';

    public function handle(): int
    {
        ApiVersionWatchJob::dispatch();
        $this->info('API version-watch queued.');

        return self::SUCCESS;
    }
}
