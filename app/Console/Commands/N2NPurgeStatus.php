<?php

namespace App\Console\Commands;

use App\Services\N2N\Updates\StatusService;
use Illuminate\Console\Command;

/** Delete expired Naara Chat status posts AND their stored files (MASTER-ONLY). Scheduled every five minutes by N2NServiceProvider. */
class N2NPurgeStatus extends Command
{
    protected $signature = 'n2n:purge-status';

    protected $description = 'Delete status posts older than 24 hours, files first';

    public function handle(StatusService $svc): int
    {
        $deleted = $failed = 0;
        do {
            $r = $svc->purgeExpired(200);
            $deleted += $r['deleted'];
            $failed += $r['failed'];
        } while ($r['deleted'] === 200);
        $this->info("Deleted {$deleted} expired status post(s); {$failed} could not be removed yet and will be retried.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
