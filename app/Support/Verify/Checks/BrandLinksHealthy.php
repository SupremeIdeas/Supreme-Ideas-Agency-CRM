<?php

namespace App\Support\Verify\Checks;

use App\Models\BrandLink;
use App\Services\BrandLinks\BrandLinkSettings;
use App\Services\BrandLinks\PublicSuffix;
use App\Support\Verify\Check;
use App\Support\Verify\CheckResult;
use Throwable;

/** MASTER-ONLY. `naara:verify brand`: the pinned Public Suffix List loads and splits correctly, and no live link is overdue for its weekly re-check. */
class BrandLinksHealthy implements Check
{
    public function area(): string
    {
        return 'brand';
    }

    public function name(): string
    {
        return 'Business links: suffix data loads and re-checks are keeping up';
    }

    public function run(): CheckResult
    {
        try {
            $p = PublicSuffix::parse('shop.adabakes.com.ng');
            if ($p === null || $p->registrable !== 'adabakes.com.ng' || ! PublicSuffix::parse('x.blogspot.com')?->privateSuffix) {
                return CheckResult::fail('The Public Suffix List is missing or unreadable, so Business links cannot be judged. Use Admin > Business links > Refresh from source.');
            }
            $days = BrandLinkSettings::int('recheck_days', 7);
            $overdue = BrandLink::approved()->where(fn ($q) => $q->whereNull('last_checked_at')->orWhere('last_checked_at', '<=', now()->subDays($days * 2)))->count();
            $suspended = BrandLink::where('status', BrandLink::SUSPENDED)->count();
        } catch (Throwable $e) {
            return CheckResult::fail('Business links could not be checked: '.$e->getMessage());
        }

        return $overdue > 0
            ? CheckResult::warn("$overdue live link(s) have not been re-checked in over ".($days * 2).' days. Is `brand-links:recheck` scheduled?', ['overdue' => $overdue])
            : CheckResult::pass('Suffix data loads and every live link is current'.($suspended ? " ($suspended paused for review)." : '.'));
    }
}
