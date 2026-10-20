<?php

namespace App\Services\Platform;

use App\Models\MerchantEarning;
use App\Models\OrderLog;
use App\Models\PartnerEarning;
use App\Models\PayoutRequest;
use App\Models\ReferralEarning;
use App\Models\SmsOrder;
use App\Models\StaffEarning;

/**
 * Oversight/Withdrawal blueprint Phase B — composes the admin's TRUE,
 * live/pull-based withdrawable balance from data FIVE existing services
 * already own. No new ledger table: every figure below is a fresh SUM
 * query against current DB state, recomputed on every call.
 *
 * ── Why this isn't just PlatformEarningsService::balance() ──────────────
 * PlatformEarningsService's ledger is DELIBERATELY isolated to White Label
 * license sale proceeds only (its own docblock: "never merged into any
 * general platform-profit reporting... a separate pool ON PURPOSE"). It
 * has never once accrued from an eSIM/Numbers order. So "platform's raw
 * earnings" for the CORE business has no running ledger at all today —
 * only OrderLog.profit / SmsOrder.profit rows (per-order margin, charged
 * minus provider cost). This service sums those directly instead.
 *
 * ── Why ALL-TIME accrual, not the 4 services' live balance() ────────────
 * OrderLog/SmsOrder profit rows never change once written, so the "raw"
 * side of this formula is a static historical total. If the 4 parties'
 * CURRENT balance() (which drops to ~0 the moment they're paid out) were
 * subtracted from that static total, admin's withdrawable figure would
 * silently INCREASE every time staff/a partner/a merchant/a referrer gets
 * paid — handing admin cash that already left the building to someone
 * else's bank account. Using the ALL-TIME sum of each ledger's ACCRUAL
 * rows instead means money is excluded from admin's pool the instant it's
 * earned by someone else, and stays excluded permanently — paid or not.
 * That is the only version that reconciles to a real bank balance.
 *
 * ── Why merchant is subtracted here even though PlatformProfitService
 *    already nets it out ──────────────────────────────────────────────
 * PlatformProfitService (the partner-payout profit figure) computes
 * OrderLog+SmsOrder profit MINUS merchant accrual — i.e. it is already
 * merchant-netted. This service intentionally does NOT reuse that netted
 * figure: it sums OrderLog/SmsOrder profit RAW (before anyone's cut) so
 * all four shares can be subtracted uniformly at the same level, per the
 * blueprint's literal instruction. Reusing the netted figure AND also
 * subtracting merchant separately would double-subtract merchant's cut.
 *
 * ── White Label revenue ──────────────────────────────────────────────
 * Added back in full via PlatformEarningsService::balance() — that ledger
 * already books 100% of every WL sale as admin's own share (verified:
 * WhiteLabelLicenseService's accrue() calls pass the full charged amount,
 * no split), so it is added once, never re-derived from OrderLog/SmsOrder
 * (which WL sales never touch in the first place).
 *
 * ── Admin's own prior core-margin withdrawals ────────────────────────
 * There is no ledger to `hold()` against for the core-margin portion (by
 * design — see above), so instead of inventing a new one, this reuses the
 * SAME payout_requests table every other party's withdrawal already flows
 * through: any non-reversed/failed request with source_bucket
 * `admin_core_margin` has already left (or is committed to leave) the
 * pool, and is excluded going forward.
 */
class AdminWithdrawalCalculator
{
    public const CORE_MARGIN_BUCKET = 'admin_core_margin';

    public function __construct(private PlatformEarningsService $platformEarnings) {}

    /** Every eSIM + Numbers order's margin, ever — before ANY party's cut. */
    private function rawCoreMargin(): float
    {
        return round((float) OrderLog::sum('profit') + (float) SmsOrder::sum('profit'), 4);
    }

    private function outstandingShare(string $model): float
    {
        return round((float) $model::where('type', $model::ACCRUAL)->sum('amount'), 4);
    }

    /** Core-margin cash admin has already withdrawn (or has a request in flight for). */
    public function alreadyWithdrawnFromCoreMargin(): float
    {
        return round((float) PayoutRequest::where('payee_type', 'admin')
            ->where('source_bucket', self::CORE_MARGIN_BUCKET)
            ->whereNotIn('status', [PayoutRequest::FAILED, PayoutRequest::REVERSED])
            ->sum('credit_amount'), 2);
    }

    /** The core-business (non-White-Label) share of admin's withdrawable pool. */
    public function coreMarginPool(): float
    {
        $pool = $this->rawCoreMargin()
            - $this->outstandingShare(StaffEarning::class)
            - $this->outstandingShare(PartnerEarning::class)
            - $this->outstandingShare(MerchantEarning::class)
            - $this->outstandingShare(ReferralEarning::class)
            - $this->alreadyWithdrawnFromCoreMargin();

        return round(max(0.0, $pool), 2);
    }

    /** The single figure admin can actually withdraw right now — live, never cached. */
    public function withdrawableBalance(): float
    {
        return round($this->coreMarginPool() + $this->platformEarnings->balance(), 2);
    }

    /** Every component of the formula, for the admin screen + audit trail. */
    public function breakdown(): array
    {
        return [
            'raw_core_margin' => $this->rawCoreMargin(),
            'staff_outstanding' => $this->outstandingShare(StaffEarning::class),
            'partner_outstanding' => $this->outstandingShare(PartnerEarning::class),
            'merchant_outstanding' => $this->outstandingShare(MerchantEarning::class),
            'referral_outstanding' => $this->outstandingShare(ReferralEarning::class),
            'already_withdrawn_core' => $this->alreadyWithdrawnFromCoreMargin(),
            'core_margin_pool' => $this->coreMarginPool(),
            'white_label_balance' => round($this->platformEarnings->balance(), 2),
            'total_withdrawable' => $this->withdrawableBalance(),
        ];
    }
}
