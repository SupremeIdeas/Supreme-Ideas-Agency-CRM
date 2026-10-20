<?php

namespace App\Support\Verify\Checks;

use App\Support\Verify\Check;
use App\Support\Verify\CheckResult;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * `naara:verify wallet`: the wallet ledger is internally consistent. Pure reads. Every completed transaction moved the balance by exactly its amount
 * (debits down, everything else up); the last transaction's balance_after equals the live balance (nothing changed a balance without a ledger row);
 * no balance is negative; reserved funds never exceed the balance; and no person has two rows with the same idempotency reference.
 */
class WalletLedgerSound implements Check
{
    private const EPS = 0.0001;

    public function area(): string
    {
        return 'wallet';
    }

    public function name(): string
    {
        return 'Wallet ledger adds up';
    }

    public function run(): CheckResult
    {
        $problems = [];
        try {
            $neg = DB::table('user_wallets')->where(fn ($q) => $q->where('usd_balance', '<', 0)->orWhere('ngn_balance', '<', 0))->count();
            if ($neg > 0) {
                $problems[] = "$neg wallet(s) have a negative balance";
            }
            $over = DB::table('user_wallets')->whereColumn('reserved_usd', '>', 'usd_balance')->where('reserved_usd', '>', 0)->count();
            if ($over > 0) {
                $problems[] = "$over wallet(s) have more reserved than they hold";
            }

            // Each completed row moved the balance by exactly its amount.
            $bad = DB::table('wallet_transactions')->where('status', 'completed')
                ->whereRaw('ABS((balance_after - balance_before) - (CASE WHEN type = ? THEN -amount ELSE amount END)) > '.self::EPS, ['debit'])->count();   // the tolerance is inlined: SQLite compares a bound float (sent as text) as larger than any number
            if ($bad > 0) {
                $problems[] = "$bad ledger row(s) whose balance movement does not match their amount";
            }

            // The newest row per person and currency ends on the live balance.
            foreach (['USD' => 'usd_balance', 'NGN' => 'ngn_balance'] as $cur => $col) {
                $mismatch = DB::table('wallet_transactions as t')
                    ->join('user_wallets as w', 'w.user_id', '=', 't.user_id')
                    ->where('t.currency', $cur)->where('t.status', 'completed')
                    ->whereRaw('t.id = (SELECT MAX(x.id) FROM wallet_transactions x WHERE x.user_id = t.user_id AND x.currency = t.currency AND x.status = ?)', ['completed'])
                    ->whereRaw("ABS(t.balance_after - w.$col) > ".self::EPS)->count();
                if ($mismatch > 0) {
                    $problems[] = "$mismatch $cur wallet(s) whose live balance differs from their last ledger row";
                }
            }

            $dupes = DB::table('wallet_transactions')->select('user_id', 'reference')->whereNotNull('reference')->groupBy('user_id', 'reference')->havingRaw('COUNT(*) > 1')->get()->count();
            if ($dupes > 0) {
                $problems[] = "$dupes reference(s) used more than once by the same person (an action ran twice)";
            }
        } catch (Throwable $e) {
            return CheckResult::fail('The wallet ledger could not be read: '.$e->getMessage());
        }

        return $problems === []
            ? CheckResult::pass('Every balance matches its ledger, no balance is negative, and no action ran twice.')
            : CheckResult::fail(count($problems).' wallet problem(s): '.implode('; ', $problems), ['problems' => $problems]);
    }
}
