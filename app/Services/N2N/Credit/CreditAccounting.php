<?php

namespace App\Services\N2N\Credit;

use App\Models\N2N\CreditGift;
use App\Models\N2N\UsageLedger;
use App\Models\N2N\UsageWallet;
use App\Support\CreditSettings;

/**
 * What the owner and their accountant need to see about usage credit (N2N, MASTER-ONLY): the outstanding liability, money in escrow,
 * and consumption. It REPORTS; how consumption is recognised as revenue is an accounting decision made outside the code.
 */
class CreditAccounting
{
    public function report(): array
    {
        $perUsd = max(1, CreditSettings::perUsd());
        $shareable = Money::cents(UsageWallet::query()->sum('shareable'));
        $non = Money::cents(UsageWallet::query()->sum('nonshareable'));
        $pending = Money::cents(CreditGift::query()->where('status', CreditGift::PENDING)->sum('amount'));
        $since = now()->subDays(30);
        $consumed = -Money::cents(UsageLedger::query()->where('type', 'consume')->where('created_at', '>=', $since)->sum('amount'));
        $purchasedUsd = (float) UsageLedger::query()->where('type', 'purchase')->where('created_at', '>=', $since)->get(['meta'])->sum(fn ($r) => (float) ($r->meta['usd'] ?? 0));

        return [
            'outstanding_credits' => Money::credits($shareable + $non),
            'outstanding_usd' => round(($shareable + $non) / 100 / $perUsd, 2),
            'shareable_credits' => Money::credits($shareable),
            'nonshareable_credits' => Money::credits($non),
            'escrow_credits' => Money::credits($pending),
            'escrow_usd' => round($pending / 100 / $perUsd, 2),
            'consumed_30d_credits' => Money::credits($consumed),
            'consumed_30d_usd' => round($consumed / 100 / $perUsd, 2),
            'purchased_30d_usd' => round($purchasedUsd, 2),
            'note' => 'Outstanding credit is a liability until consumed. Consumption becomes revenue only as your accountant decides.',
        ];
    }

    /** Invariant: per user and bucket, the ledger sums to the wallet column. @return list<string> discrepancies (empty = reconciled) */
    public function reconcile(): array
    {
        $bad = [];
        foreach (UsageWallet::query()->get() as $w) {
            foreach (['shareable', 'nonshareable'] as $b) {
                $sum = Money::cents(UsageLedger::query()->where('user_id', $w->user_id)->where('bucket', $b)->sum('amount'));
                if ($sum !== Money::cents($w->getRawOriginal($b))) {
                    $bad[] = "user {$w->user_id} {$b}: ledger {$sum} vs wallet ".Money::cents($w->getRawOriginal($b));
                }
            }
        }

        return $bad;
    }
}
