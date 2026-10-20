<?php

namespace App\Services\N2N\Credit;

use App\Models\N2N\UsageLedger;
use App\Models\N2N\UsageWallet;
use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * The one writer of the usage-credit ledger (N2N, MASTER-ONLY). Every mutation is: idempotency key + per-user Cache::lock (taken in
 * ascending user-id order, so two transfers can never deadlock) + one DB transaction + row locks, with a ledger row carrying
 * balance_after. Amounts are integer hundredths; a balance can never go negative.
 *
 * A "leg" is [user_id, bucket (shareable|nonshareable), delta_cents, type, reference, counterparty_user_id?, meta?]. A transfer is two
 * legs in ONE transaction under the same reference, so it either happens fully or not at all.
 */
class UsageLedgerService
{
    public const BUCKETS = ['shareable', 'nonshareable'];

    /**
     * @param  list<array{0:int,1:string,2:int,3:string,4:string,5?:?int,6?:?array}>  $legs
     * @param  Closure(): void|null  $inside  runs in the SAME transaction (e.g. create the escrow row)
     * @return array{replay: bool, rows: list<UsageLedger>}
     */
    public function apply(array $legs, ?Closure $inside = null): array
    {
        foreach ($legs as [$uid, $bucket, $delta]) {
            if (! in_array($bucket, self::BUCKETS, true) || $delta === 0) {
                throw new \InvalidArgumentException('Invalid usage-credit leg.');
            }
        }
        $existing = $this->existing($legs);
        if ($existing !== []) {
            return ['replay' => true, 'rows' => $existing];   // idempotent replay: nothing moves twice
        }
        $ids = collect($legs)->pluck(0)->unique()->sort()->values()->all();

        return $this->withLocks($ids, function () use ($legs, $ids, $inside) {
            return DB::transaction(function () use ($legs, $ids, $inside) {
                if (($again = $this->existing($legs)) !== []) {
                    return ['replay' => true, 'rows' => $again];
                }
                $wallets = [];
                foreach ($ids as $id) {
                    UsageWallet::query()->firstOrCreate(['user_id' => $id]);
                    $wallets[$id] = UsageWallet::query()->whereKey($id)->lockForUpdate()->first();
                }
                $rows = [];
                foreach ($legs as $leg) {
                    [$uid, $bucket, $delta, $type, $ref] = $leg;
                    $w = $wallets[$uid];
                    $before = Money::cents($w->getRawOriginal($bucket));
                    $after = $before + $delta;
                    if ($after < 0) {
                        throw new InsufficientUsageCredit(-$delta, $before);
                    }
                    $w->setAttribute($bucket, Money::credits($after));
                    $w->save();
                    $rows[] = UsageLedger::query()->create([
                        'user_id' => $uid, 'bucket' => $bucket, 'type' => $type, 'amount' => Money::credits($delta), 'balance_after' => Money::credits($after),
                        'reference' => $ref, 'counterparty_user_id' => $leg[5] ?? null, 'meta' => $leg[6] ?? null,
                    ]);
                }
                if ($inside !== null) {
                    $inside();
                }

                return ['replay' => false, 'rows' => $rows];
            });
        });
    }

    public function balances(int $userId): array
    {
        $w = UsageWallet::query()->find($userId);

        return ['shareable' => Money::cents($w?->getRawOriginal('shareable') ?? 0), 'nonshareable' => Money::cents($w?->getRawOriginal('nonshareable') ?? 0)];
    }

    /** Spendable on N2N from the usage buckets: shareable + nonshareable. */
    public function usable(int $userId): int
    {
        return array_sum($this->balances($userId));
    }

    /** @return list<UsageLedger> */
    private function existing(array $legs): array
    {
        $rows = [];
        foreach ($legs as $leg) {
            $r = UsageLedger::query()->where('user_id', $leg[0])->where('bucket', $leg[1])->where('reference', $leg[4])->first();
            if ($r !== null) {
                $rows[] = $r;
            }
        }

        return $rows;
    }

    /** Nested per-user locks in ascending id order. */
    private function withLocks(array $ids, Closure $fn): mixed
    {
        if ($ids === []) {
            return $fn();
        }
        $id = array_shift($ids);

        return Cache::lock('n2n-usage:'.$id, 15)->block(8, fn () => $this->withLocks($ids, $fn));
    }
}
