<?php

namespace App\Console\Commands;

use App\Jobs\AlertAdminJob;
use App\Services\Handbooks\HandbookAnomalies;
use Illuminate\Console\Command;

/** handbooks:watch-downloads (MASTER-ONLY): tell the admin when a buyer's downloads look like a shared copy. Never blocks anyone. */
class HandbooksWatchDownloadsCommand extends Command
{
    protected $signature = 'handbooks:watch-downloads';

    protected $description = 'Flag buyers whose handbook downloads look like sharing (a prompt for a person to look; nothing is blocked)';

    public function handle(HandbookAnomalies $watch): int
    {
        $flags = $watch->scan();
        foreach ($flags as $f) {
            $this->line("{$f['email']}: {$f['downloads']} downloads from {$f['networks']} networks in 24h");
            AlertAdminJob::dispatch(code: 'handbook_download_watch', message: "Handbook downloads to look at: {$f['email']} made {$f['downloads']} downloads from {$f['networks']} different networks in the last 24 hours.", context: ['user_id' => $f['user_id']]);
        }
        $this->info($flags->count().' buyer(s) flagged.');

        return self::SUCCESS;
    }
}
