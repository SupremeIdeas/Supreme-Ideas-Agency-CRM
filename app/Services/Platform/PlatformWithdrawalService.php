<?php

namespace App\Services\Platform;

use App\Models\PayoutAccount;
use App\Models\PayoutRequest;
use App\Models\User;
use App\Services\Payouts\PayoutException;
use App\Services\Payouts\PayoutService;
use App\Services\Payouts\PayoutThreshold;
use App\Services\Pricing\CurrencyService;
use App\Support\PayoutSettings;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Prompt 21-EXT §5.3 — the platform-earnings cash-out. Turns the global
 * white-label sale proceeds into a real bank transfer through the SAME
 * payout engine merchants already use (PayoutService/PayoutThreshold), never
 * a parallel mechanism. Unlike MerchantWithdrawalService there is no owning
 * merchant: any `super_admin` may request a cash-out of the ONE global
 * balance to THEIR OWN verified payout account — "admin can withdraw this
 * the way normal users withdraw funds" (owner's own framing).
 *
 * Oversight/Withdrawal blueprint Phase B (2026-09-22) — the withdrawable
 * ceiling is no longer just the White-Label ledger. AdminWithdrawalCalculator
 * composes the core-business margin (net of staff/partner/merchant/referral
 * shares) with the White-Label balance into one live figure; a single
 * request() call may now draw from BOTH pools, WL first (the only one with
 * its own hold()-enforced ledger) then core margin for any remainder.
 */
class PlatformWithdrawalService
{
    public function __construct(
        private PlatformEarningsService $earnings,
        private AdminWithdrawalCalculator $calculator,
        private PayoutService $payouts,
        private CurrencyService $currency,
        private PayoutThreshold $threshold,
    ) {}

    /** Live, pull-based — never cached (Phase B requirement). */
    public function availableUsd(): float
    {
        return $this->calculator->withdrawableBalance();
    }

    /** The formula's components, for the admin screen's transparency panel. */
    public function breakdown(): array
    {
        return $this->calculator->breakdown();
    }


    /**
     * Request a cash-out of `$usd` from admin's TOTAL withdrawable pool
     * (Phase B) to their own verified payout account. Draws from the
     * White-Label ledger first (the only pool with its own hold()-enforced
     * balance), then from core-business margin for any remainder — see
     * AdminWithdrawalCalculator for why core margin has no ledger to hold
     * against and is instead re-derived live on every call.
     *
     * A withdrawal that fits entirely within the WL balance behaves exactly
     * as before (one PayoutRequest, source_bucket=platform_earnings). One
     * that spans both pools creates a second PayoutRequest on the
     * admin_core_margin bucket — two transfers instead of one, but each
     * independently tracked/reversible through the same engine.
     *
     * @throws PayoutException
     */
    public function request(User $admin, PayoutAccount $account, float $usd): PayoutRequest
    {
        if (! $admin->hasRole('super_admin')) {
            throw new PayoutException('Only a super admin may withdraw platform earnings.');
        }
        if (! PayoutSettings::enabled()) {
            throw new PayoutException('Withdrawals are not available right now.');
        }
        if ($account->user_id !== $admin->id || ! $account->is_verified) {
            throw new PayoutException('Choose a verified payout account.');
        }

        $usd = round($usd, 2);
        if ($usd < PayoutSettings::minWithdrawal()) {
            throw new PayoutException('Below the minimum withdrawal of $'.number_format(PayoutSettings::minWithdrawal(), 2).'.');
        }
        if (! $this->threshold->canWithdraw($admin)) {
            throw new PayoutException("You've reached your free payout limit — verify your identity to keep withdrawing.");
        }

        $currency = strtoupper($account->currency);
        $reference = 'pwd:'.Str::uuid();

        // Locked around the read-ceiling-then-spend sequence: the core-margin
        // portion has no per-request ledger of its own to hold() against, so
        // this lock is what stops two concurrent admin withdrawals from both
        // reading the same "available" figure and together over-drawing it.
        return Cache::lock('admin-core-margin-withdrawal', 10)->block(5, function () use ($admin, $usd, $account, $currency, $reference) {
            if ($usd <= 0 || $usd > $this->availableUsd()) {
                throw new PayoutException('Amount exceeds the platform\'s available earnings.');
            }

            $wlPortion = min($usd, round($this->earnings->balance(), 2));
            $corePortion = round($usd - $wlPortion, 2);

            // Price both portions BEFORE the transaction (the FX feed is an HTTP call).
            $quoter = app(\App\Services\Payouts\PayoutQuoter::class);
            $wlQuote = $wlPortion > 0 ? $quoter->quote($wlPortion, $currency, $account) : null;
            $coreQuote = $corePortion > 0 ? $quoter->quote($corePortion, $currency, $account) : null;

            return DB::transaction(function () use ($admin, $account, $currency, $reference, $wlPortion, $corePortion, $wlQuote, $coreQuote) {
                $primary = null;

                if ($wlPortion > 0) {
                    // HOLD the earnings before any money is promised (idempotent).
                    $this->earnings->hold($wlPortion, 'plat-hold:'.$reference, 'Platform earnings withdrawal');

                    $wlRequest = $this->payouts->createRequest(
                        $admin, $wlQuote['local_amount'], $currency, 'platform_earnings', $account, $reference.'-wl', $wlQuote['quote'],
                    );
                    // Record the USD earnings held so a reversal returns the exact amount.
                    $wlRequest->forceFill(['payee_type' => 'admin', 'credit_amount' => $wlPortion])->save();
                    $primary = $wlRequest;
                }

                if ($corePortion > 0) {
                    $coreRequest = $this->payouts->createRequest(
                        $admin, $coreQuote['local_amount'], $currency, AdminWithdrawalCalculator::CORE_MARGIN_BUCKET, $account, $reference.'-core', $coreQuote['quote'],
                    );
                    $coreRequest->forceFill(['payee_type' => 'admin', 'credit_amount' => $corePortion])->save();
                    $primary ??= $coreRequest;
                }

                return $primary;
            });
        });
    }
}
