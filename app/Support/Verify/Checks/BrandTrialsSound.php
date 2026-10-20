<?php

namespace App\Support\Verify\Checks;

use App\Models\BrandSubscription;
use App\Support\Verify\Check;
use App\Support\Verify\CheckResult;
use Throwable;

/** MASTER-ONLY. `naara:verify brand`: no trial is overdue for conversion, no trial row is malformed, and the monthly sweep cannot also bill a trial. */
class BrandTrialsSound implements Check
{
    public function area(): string
    {
        return 'brand';
    }

    public function name(): string
    {
        return 'Naara Pro trials: conversions are on time and rows are consistent';
    }

    public function run(): CheckResult
    {
        try {
            $overdue = BrandSubscription::where('status', BrandSubscription::TRIALING)->where('trial_ends_at', '<=', now()->subHour())->count();
            $retryOverdue = BrandSubscription::where('status', BrandSubscription::PAST_DUE)->whereNotNull('trial_started_at')->whereNull('converted_at')->where('next_retry_at', '<=', now()->subHours(2))->count();
            $malformed = BrandSubscription::where('status', BrandSubscription::TRIALING)->where(fn ($q) => $q->whereNull('trial_ends_at')->orWhereNull('convert_to_plan_id')->orWhereNotNull('next_billing_at'))->count();
        } catch (Throwable $e) {
            return CheckResult::fail('Trials could not be checked: '.$e->getMessage());
        }
        if ($malformed > 0) {
            return CheckResult::fail("$malformed trial row(s) are malformed (no end date, no plan to convert to, or a billing date the monthly sweep could act on).", ['malformed' => $malformed]);
        }
        if ($overdue + $retryOverdue > 0) {
            return CheckResult::warn(($overdue + $retryOverdue).' trial(s) are overdue for conversion. Is `brand-trials:convert` scheduled and the scheduler running?', ['overdue' => $overdue, 'retry' => $retryOverdue]);
        }

        return CheckResult::pass('Every trial is converting on time.');
    }
}
