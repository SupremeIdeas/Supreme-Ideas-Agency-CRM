<?php

namespace App\Console\Commands;

use App\Models\HandbookEntitlement;
use App\Models\User;
use App\Services\Handbooks\HandbookUnlockService;
use Illuminate\Console\Command;

/** handbooks:sync (MASTER-ONLY): daily hygiene. Locks Pro-slot unlocks whose Pro term lapsed and restores those whose Pro is back, so a lapse is reflected even if the person never opens the app. */
class HandbooksSyncCommand extends Command
{
    protected $signature = 'handbooks:sync';

    protected $description = 'Lock or restore Naara Pro handbook unlocks to match each member\'s current Pro status';

    public function handle(HandbookUnlockService $svc): int
    {
        $n = 0;
        HandbookEntitlement::query()->where('source', 'pro_slot')->distinct()->orderBy('user_id')->pluck('user_id')->chunk(200)->each(function ($ids) use ($svc, &$n) {
            User::query()->whereIn('id', $ids)->get()->each(function (User $u) use ($svc, &$n) {
                try {
                    $svc->syncProLocks($u);
                    $n++;
                } catch (\Throwable $e) {
                    report($e);
                }
            });
        });
        $this->info("Synced Pro handbook locks for {$n} member(s).");

        return self::SUCCESS;
    }
}
