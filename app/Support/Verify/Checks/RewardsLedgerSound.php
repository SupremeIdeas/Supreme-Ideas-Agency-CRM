<?php

namespace App\Support\Verify\Checks;

use App\Support\Verify\Check;
use App\Support\Verify\CheckResult;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * `naara:verify rewards`: the referral earnings ledger adds up and a reward is paid once. Pure reads: each person's running balance equals the sum of
 * their ledger movements, every referral marked rewarded has exactly one accrual behind it, and no earning reference is used twice.
 */
class RewardsLedgerSound implements Check
{
    public function area(): string
    {
        return 'rewards';
    }

    public function name(): string
    {
        return 'Rewards ledger adds up and pays once';
    }

    public function run(): CheckResult
    {
        $problems = [];
        try {
            $drift = DB::table('referral_earnings as e')
                ->whereRaw('e.id = (SELECT MAX(x.id) FROM referral_earnings x WHERE x.user_id = e.user_id)')
                ->whereRaw('ABS(e.balance_after - (SELECT COALESCE(SUM(y.amount), 0) FROM referral_earnings y WHERE y.user_id = e.user_id)) > 0.0001')->count();
            if ($drift > 0) {
                $problems[] = "$drift person(s) whose earnings balance differs from the sum of their ledger rows";
            }

            // Portable on purpose (no SQL string concatenation: `||` means OR on MySQL): check the rewarded referrals chunk by chunk.
            $unpaid = 0;
            DB::table('referrals')->where('rewarded', true)->where('type', 'customer')->orderBy('id')->select('id', 'referrer_id')->chunk(500, function ($rows) use (&$unpaid) {
                $refs = $rows->map(fn ($r) => 'referral_reward:'.$r->id)->all();
                $have = DB::table('referral_earnings')->whereIn('reference', $refs)->pluck('user_id', 'reference');
                foreach ($rows as $r) {
                    if (($have['referral_reward:'.$r->id] ?? null) !== $r->referrer_id) {
                        $unpaid++;
                    }
                }
            });
            if ($unpaid > 0) {
                $problems[] = "$unpaid referral(s) marked rewarded with no matching earning";
            }

            $dupes = DB::table('referral_earnings')->select('user_id', 'reference')->whereNotNull('reference')->groupBy('user_id', 'reference')->havingRaw('COUNT(*) > 1')->get()->count();
            if ($dupes > 0) {
                $problems[] = "$dupes earning reference(s) used more than once (a reward paid twice)";
            }
        } catch (Throwable $e) {
            return CheckResult::fail('The rewards ledger could not be read: '.$e->getMessage());
        }

        return $problems === []
            ? CheckResult::pass('Every earnings balance matches its ledger and no reward was paid twice.')
            : CheckResult::fail(count($problems).' rewards problem(s): '.implode('; ', $problems), ['problems' => $problems]);
    }
}
