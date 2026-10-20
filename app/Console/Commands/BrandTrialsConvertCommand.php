<?php

namespace App\Console\Commands;

use App\Services\Brands\BrandTrialService;
use Illuminate\Console\Command;

/** Brand-Growth Phase 4 (MASTER-ONLY): convert due Naara Pro trials, retry short wallets, send the heads-up reminders. */
class BrandTrialsConvertCommand extends Command
{
    protected $signature = 'brand-trials:convert';

    protected $description = 'Convert due Naara Pro free trials, retry unfunded ones, and send ending reminders.';

    public function handle(BrandTrialService $trials): int
    {
        $r = $trials->sweep(now());
        $this->info("Converted {$r['converted']}, unfunded {$r['unfunded']}, closed {$r['closed']}, reminded {$r['reminded']}.");

        return self::SUCCESS;
    }
}
