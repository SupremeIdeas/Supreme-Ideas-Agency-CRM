<?php

namespace App\Services\N2N\Credit;

use App\Models\CreditLedger;
use App\Models\N2N\UsageHold;
use App\Models\N2N\UsageLedger;
use App\Models\User;
use App\Services\Credits\CreditService;
use App\Services\Credits\InsufficientCreditsException;
use App\Services\N2N\Realtime\Realtime;
use Illuminate\Support\Facades\DB;

/**
 * Primitive P2 (N2N, MASTER-ONLY): the server-authoritative meter every N2N feature charges through. charge() is instant and idempotent;
 * hold()/settle()/release() are for actions that finish later (calls). Spend order: loyalty (its non-withdrawable part only) ->
 * received-gift credit -> purchased credit. Callers pass amounts they computed server-side; nothing here trusts a client figure.
 */
class UsageMeter
{
    public function __construct(private readonly UsageLedgerService $ledger, private readonly CreditService $loyalty) {}

    /** What this member can spend on N2N right now, in hundredths (loyalty non-withdrawable part + both usage buckets). */
    public function available(User $user): int
    {
        return $this->loyaltySpendable($user) + $this->ledger->usable($user->id);
    }

    public function loyaltySpendable(User $user): int
    {
        if (! \App\Support\CreditSettings::enabled()) {
            return 0;
        }

        return max(0, Money::cents($this->loyalty->balance($user)) - Money::cents($this->loyalty->withdrawableBalance($user)));
    }

    /**
     * Charge now. @return list<array{0:string,1:int}> the parts taken: [[bucket, cents], ...]
     *
     * @throws InsufficientUsageCredit
     */
    public function charge(User $user, int $cents, string $reference, string $purpose = 'message'): array
    {
        return $this->take($user, $cents, $reference, $purpose, 'consume');
    }

    /** Earmark credit for an action that settles later. Idempotent by reference. */
    public function hold(User $user, int $cents, string $reference, string $purpose = 'call'): UsageHold
    {
        $existing = UsageHold::query()->where('reference', $reference)->first();
        if ($existing !== null) {
            return $existing;
        }
        $parts = $this->take($user, $cents, 'hold:'.$reference, $purpose, 'hold');

        return UsageHold::query()->firstOrCreate(['reference' => $reference], [
            'user_id' => $user->id, 'amount' => Money::credits($cents), 'parts' => array_map(fn ($p) => ['bucket' => $p[0], 'cents' => $p[1]], $parts), 'status' => 'held',
        ]);
    }

    /** Keep `$actualCents` of a hold and return the unused part to where it came from. Idempotent; the actual is clamped to the hold. */
    public function settle(string $reference, int $actualCents): ?UsageHold
    {
        return DB::transaction(function () use ($reference, $actualCents) {
            $hold = UsageHold::query()->where('reference', $reference)->lockForUpdate()->first();
            if ($hold === null || $hold->status !== 'held') {
                return $hold;   // unknown, or already settled/released: a second call is a no-op
            }
            $held = Money::cents($hold->getRawOriginal('amount'));
            $keep = max(0, min($held, $actualCents));
            $giveBack = $held - $keep;
            $user = User::query()->findOrFail($hold->user_id);
            foreach (array_reverse((array) $hold->parts) as $part) {   // return the last-consumed bucket first
                if ($giveBack <= 0) {
                    break;
                }
                $back = min($giveBack, (int) $part['cents']);
                $this->giveBack($user, $part['bucket'], $back, $reference);
                $giveBack -= $back;
            }
            $hold->forceFill(['status' => $keep === 0 ? 'released' : 'settled', 'settled_amount' => Money::credits($keep), 'settled_at' => now()])->save();

            return $hold;
        });
    }

    public function release(string $reference): ?UsageHold
    {
        return $this->settle($reference, 0);
    }

    /** Put back exactly what a charge took (a send failed after the charge). Idempotent by reference; a no-op for an unknown or already-refunded charge. */
    public function refundCharge(User $user, string $reference): void
    {
        if (UsageLedger::query()->where('user_id', $user->id)->where('reference', 'refund:'.$reference)->exists() || CreditLedger::query()->where('user_id', $user->id)->where('reference', 'n2n-undo:'.$reference)->exists()) {
            return;
        }
        foreach ($this->partsFor($user, $reference) ?? [] as [$bucket, $cents]) {
            if ($bucket === 'loyalty') {
                $this->loyalty->earn($user, $cents / 100, 'n2n_refund', 'n2n-undo:'.$reference, 'N2N send failed, charge returned');
            } else {
                $this->ledger->apply([[$user->id, $bucket, $cents, 'refund', 'refund:'.$reference, null, null]]);
            }
        }
    }

    /** @return list<array{0:string,1:int}> */
    private function take(User $user, int $cents, string $reference, string $purpose, string $type): array
    {
        if ($cents <= 0) {
            throw new \InvalidArgumentException('Amount must be positive.');
        }
        // Replay: this reference has already been charged, so return what it took without charging again.
        if (($prior = $this->partsFor($user, $reference)) !== null) {
            return $prior;
        }
        $attempt = CreditLedger::query()->where('user_id', $user->id)->where('reference', 'like', 'n2n-refund:'.$reference.':%')->count();
        $loyRef = 'n2n:'.$reference.':'.$attempt;

        $loy = min($cents, $this->loyaltySpendable($user));
        $usageNeed = $cents - $loy;
        $b = $this->ledger->balances($user->id);
        if ($usageNeed > $b['nonshareable'] + $b['shareable']) {
            throw new InsufficientUsageCredit($cents, $loy + $b['nonshareable'] + $b['shareable']);
        }
        $parts = [];
        if ($loy > 0) {
            try {
                $this->loyalty->spend($user, $loy / 100, 'n2n', $loyRef, $purpose);
            } catch (InsufficientCreditsException) {
                throw new InsufficientUsageCredit($cents, $this->available($user));
            }
            $parts[] = ['loyalty', $loy];
        }
        if ($usageNeed > 0) {
            $fromNon = min($usageNeed, $b['nonshareable']);
            $fromSha = $usageNeed - $fromNon;
            $legs = [];
            if ($fromNon > 0) {
                $legs[] = [$user->id, 'nonshareable', -$fromNon, $type, $reference, null, ['purpose' => $purpose]];
                $parts[] = ['nonshareable', $fromNon];
            }
            if ($fromSha > 0) {
                $legs[] = [$user->id, 'shareable', -$fromSha, $type, $reference, null, ['purpose' => $purpose]];
                $parts[] = ['shareable', $fromSha];
            }
            try {
                $this->ledger->apply($legs);
            } catch (InsufficientUsageCredit $e) {
                if ($loy > 0) {   // a concurrent spend emptied the bucket after we looked: give the loyalty part back
                    $this->loyalty->earn($user, $loy / 100, 'n2n_refund', 'n2n-refund:'.$reference.':'.$attempt, 'N2N charge could not complete');
                }
                throw $e;
            }
        }
        $this->notifyLow($user);

        return $parts;
    }

    /** @return list<array{0:string,1:int}>|null what an earlier call with this reference took, or null if it never ran */
    private function partsFor(User $user, string $reference): ?array
    {
        $rows = UsageLedger::query()->where('user_id', $user->id)->where('reference', $reference)->whereIn('type', ['consume', 'hold'])->get();
        $parts = [];
        $attempt = CreditLedger::query()->where('user_id', $user->id)->where('reference', 'like', 'n2n-refund:'.$reference.':%')->count();
        $loyRow = CreditLedger::query()->where('user_id', $user->id)->where('reference', 'n2n:'.$reference.':'.$attempt)->first();
        if ($loyRow !== null) {
            $parts[] = ['loyalty', Money::cents($loyRow->amount)];
        }
        foreach ($rows as $r) {
            $parts[] = [$r->bucket, -Money::cents($r->getRawOriginal('amount'))];
        }

        return $parts === [] ? null : $parts;
    }

    private function giveBack(User $user, string $bucket, int $cents, string $reference): void
    {
        if ($cents <= 0) {
            return;
        }
        if ($bucket === 'loyalty') {
            $this->loyalty->earn($user, $cents / 100, 'n2n_refund', 'n2n-release:'.$reference, 'Unused N2N hold returned');

            return;
        }
        $this->ledger->apply([[$user->id, $bucket, $cents, 'hold_release', 'release:'.$reference, null, null]]);
    }

    private function notifyLow(User $user): void
    {
        $threshold = Money::cents(max(0, (float) \App\Models\Setting::getValue('n2n.low_balance_credits', 5)));
        $left = $this->available($user);
        if ($left < $threshold) {
            Realtime::publish($user->id, 'credit.low', ['left' => Money::credits($left)]);   // B2 plays the credit_alert tone on this event
        }
    }
}
