<?php

namespace App\Console\Commands;

use App\Services\Business\RecordingRetention;
use Illuminate\Console\Command;

/** Delete expired call recordings and any recording with no consent record (MASTER-ONLY, Business Suite). Scheduled daily by BusinessSuiteServiceProvider. */
class BusinessPurgeRecordings extends Command
{
    protected $signature = 'business:purge-recordings';

    protected $description = 'Delete expired call recordings (audio, transcripts, summaries) and recordings without a consent record';

    public function handle(RecordingRetention $retention): int
    {
        $total = 0;
        do {
            $n = $retention->sweep(500);
            $total += $n;
        } while ($n === 500);
        $this->info("Purged {$total} recording(s).");

        return self::SUCCESS;
    }
}
