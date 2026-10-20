<?php

namespace App\Console\Commands;

use App\Models\BrandLink;
use App\Services\BrandLinks\BrandLinkService;
use Illuminate\Console\Command;

/** Weekly re-check of every approved Business link (Brand-Growth Phase 2, MASTER-ONLY, cron-able). Suspends links that now redirect elsewhere, fail policy or are flagged unsafe. */
class BrandLinksRecheck extends Command
{
    protected $signature = 'brand-links:recheck {--days= : re-check links not checked for this many days} {--limit=200}';

    protected $description = 'Re-validate approved Business links and suspend the ones that went bad (master only)';

    public function handle(BrandLinkService $svc): int
    {
        $days = (int) ($this->option('days') ?: \App\Services\BrandLinks\BrandLinkSettings::int('recheck_days', 7));
        $due = BrandLink::approved()->where(fn ($q) => $q->whereNull('last_checked_at')->orWhere('last_checked_at', '<=', now()->subDays($days)))->orderBy('last_checked_at')->limit(max(1, (int) $this->option('limit')))->get();
        $suspended = 0;
        foreach ($due as $link) {
            try {
                $svc->recheck($link);
                $suspended += $link->fresh()->status === BrandLink::SUSPENDED ? 1 : 0;
            } catch (\Throwable $e) {
                report($e);   // one broken link must not stop the sweep
            }
        }
        $this->info("Checked {$due->count()} link(s); suspended {$suspended}.");

        return self::SUCCESS;
    }
}
