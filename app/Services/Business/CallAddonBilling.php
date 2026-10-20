<?php

namespace App\Services\Business;

use App\Exceptions\InsufficientBalanceException;
use App\Exceptions\OrphanChargeRefundedException;
use App\Models\CallRecording;
use App\Services\Pricing\PricingEngine;
use App\Services\Wallet\WalletService;
use App\Support\Business\BusinessSettings;
use Closure;

/**
 * The only way recording, transcription and intelligence are paid for (MASTER-ONLY, Business Suite Prompt 15). No money-safety code of its own:
 * the price comes from PricingEngine, the debit, the "never charge without delivering" auto-refund and the idempotency all come from
 * WalletService::charge(). The workspace OWNER's wallet pays (a workspace has no balance of its own).
 *
 * References are one per recording per step, so a repeated job can never debit twice. A failed delivery is refunded by charge() and the step
 * is marked failed; it is never retried blind, because a retry would find the refunded debit under the same reference and deliver for free.
 */
class CallAddonBilling
{
    public const RECORD = 'record';

    public const TRANSCRIBE = 'transcribe';

    public const INTELLIGENCE = 'intelligence';

    public function __construct(private WalletService $wallet, private PricingEngine $pricing)
    {
    }

    /** What a step costs this workspace (USD, retail). Whole minutes, at least one. */
    public function price(string $kind, int $durationSeconds): float
    {
        $minutes = max(1, (int) ceil($durationSeconds / 60));

        return match ($kind) {
            self::RECORD => round($minutes * $this->pricing->calculateCallAddonRetail(BusinessSettings::cost('record_per_min'), 'record'), 4),
            self::TRANSCRIBE => round($minutes * $this->pricing->calculateCallAddonRetail(BusinessSettings::cost('transcribe_per_min'), 'transcribe'), 4),
            self::INTELLIGENCE => round($this->pricing->calculateCallAddonRetail(BusinessSettings::cost('intelligence_per_run'), 'intelligence'), 4),
        };
    }

    /**
     * Charge, deliver, or refund. Returns what $deliver returns.
     *
     * @template T
     *
     * @param  Closure(): T  $deliver
     * @return T
     *
     * @throws InsufficientBalanceException  nothing was charged
     * @throws OrphanChargeRefundedException the step failed and the charge was refunded
     */
    public function charge(CallRecording $recording, string $kind, Closure $deliver): mixed
    {
        $owner = $recording->walletGroup?->owner ?? $recording->user;
        $amount = $this->price($kind, $recording->duration_seconds);
        $reference = 'call-'.$kind.':'.$recording->id;

        $result = $this->wallet->charge($owner, $amount, 'USD', fn () => $deliver(), [
            'reference' => $reference,
            'description' => match ($kind) {
                self::RECORD => 'Call recording',
                self::TRANSCRIBE => 'Call transcript',
                default => 'Call summary',
            },
        ]);

        $billing = (array) $recording->billing;
        $billing[$kind] = ['reference' => $reference, 'amount' => $amount];
        $recording->forceFill(['billing' => $billing])->save();

        return $result;
    }
}
