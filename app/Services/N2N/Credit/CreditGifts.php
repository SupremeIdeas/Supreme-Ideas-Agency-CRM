<?php

namespace App\Services\N2N\Credit;

use App\Jobs\N2N\SendCreditGiftInviteJob;
use App\Models\N2N\CreditGift;
use App\Models\Setting;
use App\Models\User;
use App\Services\N2N\Identity\IdentityResolver;
use App\Services\N2N\Identity\PhoneNormalizer;
use App\Support\Auditor;
use Illuminate\Support\Facades\DB;

/**
 * Credit gifted to someone who is not on Naara yet (MASTER-ONLY). The sender is debited immediately and the credit sits in ESCROW (in no
 * balance) for up to 24h. The recipient must register THROUGH THE SENDER'S REFERRAL LINK and VERIFY the same email or phone the gift was
 * addressed to, so forwarding the invite steals nothing. Claim and expiry are serialised on the gift row, so whichever runs first wins and
 * the other is a no-op: the credit is claimed once or refunded once, never both and never twice.
 */
class CreditGifts
{
    public function __construct(private readonly UsageLedgerService $ledger, private readonly IdentityResolver $resolver) {}

    /** @return array{ok: bool, message: string, gift?: CreditGift} */
    public function create(User $from, string $identifier, float $credits, string $key, ?string $ip = null): array
    {
        $cents = Money::cents($credits);
        $min = Money::cents(max(0.01, (float) Setting::getValue('n2n.share_min', 1)));
        $max = Money::cents(max(1, (float) Setting::getValue('n2n.share_max', 500)));
        if ($cents < $min || $cents > $max) {
            return ['ok' => false, 'message' => 'You can gift between '.Money::credits($min).' and '.Money::credits($max).' credits at a time.'];
        }
        $identifier = trim($identifier);
        [$kind, $norm] = $this->normalise($identifier, $from);
        if ($kind === null) {
            return ['ok' => false, 'message' => 'Enter an email address or a phone number.'];
        }
        // Someone who can be found is on Naara already: send it as a normal share instead (the UI says so; no existence is revealed).
        $hit = $this->resolver->resolve($identifier, 'search', $from, $ip);
        if ($hit->found) {
            return ['ok' => false, 'message' => 'That person is already on Naara. Share credit with them directly.'];
        }
        $perDay = max(1, (int) Setting::getValue('n2n.gift_max_per_day', 10));
        if (CreditGift::query()->where('sender_id', $from->id)->where('created_at', '>=', now()->subDay())->count() >= $perDay) {
            return ['ok' => false, 'message' => 'You have reached the daily gift limit.'];
        }
        if (($again = CreditGift::query()->where('sender_id', $from->id)->where('idempotency_key', $key)->first()) !== null) {
            return ['ok' => true, 'message' => 'Already sent.', 'gift' => $again];   // replay: never debit twice
        }

        $gift = null;
        try {
            $this->ledger->apply(
                [[$from->id, 'shareable', -$cents, 'gift_escrow', 'gift:'.$key, null, ['kind' => $kind]]],
                function () use (&$gift, $from, $kind, $norm, $cents, $key) {
                    $gift = CreditGift::query()->create([
                        'sender_id' => $from->id, 'recipient_kind' => $kind, 'recipient_hash' => $this->hash($kind, $norm), 'recipient_masked' => $this->mask($kind, $norm),
                        'amount' => Money::credits($cents), 'status' => CreditGift::PENDING, 'expires_at' => now()->addHours(max(1, (int) Setting::getValue('n2n.gift_hours', 24))),
                        'idempotency_key' => $key,
                    ]);
                },
            );
        } catch (InsufficientUsageCredit) {
            return ['ok' => false, 'message' => 'You do not have enough shareable credit. Only credit you bought can be gifted.'];
        }
        SendCreditGiftInviteJob::dispatch($gift->id, $kind, $norm);
        Auditor::log('n2n.gift_created', User::class, $from->id, ['gift' => $gift->id, 'amount' => Money::credits($cents)]);

        return ['ok' => true, 'message' => 'Gift sent. They have 24 hours to join with your invite link.', 'gift' => $gift];
    }

    /** Claim whatever pending gifts this person is entitled to. Safe to call any time (after registration, email or phone verification). */
    public function claimForUser(User $user): int
    {
        if ($user->referred_by === null) {
            return 0;   // a gift is only ever claimable through the sender's referral link
        }
        $hashes = [];
        if ($user->email !== null && $user->email_verified_at !== null) {
            $hashes[] = $this->hash('email', strtolower($user->email));
        }
        $phone = \App\Models\N2N\UserPhone::query()->where('user_id', $user->id)->value('e164');
        if ($phone !== null) {
            $hashes[] = $this->hash('phone', $phone);
        }
        if ($hashes === []) {
            return 0;
        }
        $claimed = 0;
        $ids = CreditGift::query()->where('status', CreditGift::PENDING)->where('sender_id', $user->referred_by)->whereIn('recipient_hash', $hashes)
            ->where('expires_at', '>', now())->where('created_at', '<=', $user->created_at)->pluck('id');
        foreach ($ids as $id) {
            $claimed += $this->claimOne((int) $id, $user) ? 1 : 0;
        }

        return $claimed;
    }

    private function claimOne(int $id, User $user): bool
    {
        return (bool) DB::transaction(function () use ($id, $user) {
            $gift = CreditGift::query()->whereKey($id)->lockForUpdate()->first();
            if ($gift === null || $gift->status !== CreditGift::PENDING || $gift->expires_at->lte(now())) {
                return false;   // expiry (or another claim) won the race
            }
            $cents = Money::cents($gift->getRawOriginal('amount'));
            $this->ledger->apply([[$user->id, 'nonshareable', $cents, 'gift_claim', 'gift-claim:'.$gift->id, $gift->sender_id, null]], function () use ($gift, $user) {
                $gift->forceFill(['status' => CreditGift::CLAIMED, 'claimed_by_user_id' => $user->id, 'claimed_at' => now()])->save();
            });
            Auditor::log('n2n.gift_claimed', User::class, $user->id, ['gift' => $gift->id]);
            \App\Services\N2N\Realtime\Realtime::publish($user->id, 'credit.received', ['from' => $gift->sender?->naara_id, 'amount' => Money::credits($cents)]);

            return true;
        });
    }

    /** Refund every expired pending gift to its sender, exactly once each. Cheap when idle; run every minute. */
    public function expireDue(int $limit = 500): int
    {
        $n = 0;
        $ids = CreditGift::query()->where('status', CreditGift::PENDING)->where('expires_at', '<=', now())->orderBy('id')->limit($limit)->pluck('id');
        foreach ($ids as $id) {
            $n += $this->refundOne((int) $id) ? 1 : 0;
        }

        return $n;
    }

    private function refundOne(int $id): bool
    {
        return (bool) DB::transaction(function () use ($id) {
            $gift = CreditGift::query()->whereKey($id)->lockForUpdate()->first();
            if ($gift === null || $gift->status !== CreditGift::PENDING || $gift->expires_at->gt(now())) {
                return false;   // already claimed/refunded, or not due
            }
            $cents = Money::cents($gift->getRawOriginal('amount'));
            $this->ledger->apply([[$gift->sender_id, 'shareable', $cents, 'gift_refund', 'gift-refund:'.$gift->id, null, null]], function () use ($gift) {
                $gift->forceFill(['status' => CreditGift::REFUNDED, 'refunded_at' => now()])->save();
            });
            Auditor::log('n2n.gift_refunded', User::class, $gift->sender_id, ['gift' => $gift->id]);

            return true;
        });
    }

    /** @return array{0: ?string, 1: ?string} [kind, normalised value] */
    private function normalise(string $input, User $from): array
    {
        if (filter_var($input, FILTER_VALIDATE_EMAIL)) {
            return ['email', strtolower($input)];
        }
        $p = PhoneNormalizer::normalise($input, $from->country_code);

        return $p ? ['phone', $p['e164']] : [null, null];
    }

    public function hash(string $kind, string $normalised): string
    {
        return hash_hmac('sha256', $kind.'|'.$normalised, (string) config('app.key'));
    }

    private function mask(string $kind, string $v): string
    {
        if ($kind === 'email') {
            [$u, $d] = explode('@', $v, 2) + [1 => ''];

            return mb_substr($u, 0, 1).str_repeat('•', max(1, min(5, mb_strlen($u) - 1))).'@'.$d;
        }

        return substr($v, 0, 4).str_repeat('•', max(2, strlen($v) - 7)).substr($v, -3);
    }
}
