<?php

namespace App\Services\N2N\Credit;

use App\Models\Setting;
use App\Models\User;
use App\Services\N2N\Identity\IdentityResolver;
use App\Services\N2N\Realtime\Realtime;
use App\Services\Wallet\WalletService;
use App\Support\Auditor;
use App\Support\CreditSettings;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

/**
 * Buy and share N2N usage credit (MASTER-ONLY). One-way: wallet money becomes credit and never converts back, so usage credit is not a
 * cash-out route. Only PURCHASED or subscription-granted credit is shareable; credit received as a gift is spendable but cannot be
 * re-shared (this blocks gift rings). Every mutation is idempotent on a caller-supplied key and runs through UsageLedgerService.
 */
class UsageCredit
{
    public function __construct(
        private readonly UsageLedgerService $ledger,
        private readonly WalletService $wallet,
        private readonly IdentityResolver $resolver,
    ) {}

    public function balances(User $user): array
    {
        $b = $this->ledger->balances($user->id);
        $loyalty = app(UsageMeter::class)->loyaltySpendable($user);

        return $b + ['loyalty' => $loyalty, 'total' => $b['shareable'] + $b['nonshareable'] + $loyalty];
    }

    /** Native-iOS purchase is OFF until the owner decides on in-app purchase rules (docs/APP-STORE-PAYMENTS-COMPLIANCE.md). */
    public function purchaseAllowed(?string $userAgent = null): bool
    {
        return ! \App\Support\AppPlatform::isIosBuild($userAgent) || (bool) Setting::getValue('n2n.credit_purchase_ios', false);
    }

    /**
     * Buy credit with wallet money. The wallet is debited and the credit delivered under the orphan-charge guard: if the credit cannot be
     * written, the debit is refunded automatically. Replaying the same key buys exactly once.
     *
     * @return array{credits: string, usd: float}
     */
    public function purchase(User $user, float $usd, string $key): array
    {
        $usd = round($usd, 2);
        $min = max(0.5, (float) Setting::getValue('n2n.purchase_min_usd', 1));
        $max = max($min, (float) Setting::getValue('n2n.purchase_max_usd', 500));
        if ($usd < $min || $usd > $max) {
            throw new InvalidArgumentException("Choose an amount between \${$min} and \${$max}.");
        }
        $cents = Money::cents(CreditSettings::usdToCredits($usd));
        $walletRef = 'n2n_credit_purchase:'.$user->id.':'.$key;

        $this->wallet->charge($user, $usd, 'USD', function () use ($user, $cents, $key, $usd, $walletRef) {
            $this->ledger->apply([[$user->id, 'shareable', $cents, 'purchase', 'purchase:'.$key, null, ['usd' => $usd, 'wallet_ref' => $walletRef]]]);
        }, ['reference' => $walletRef, 'description' => 'NaaraCredit purchase']);
        Auditor::log('n2n.credit_purchased', User::class, $user->id, ['usd' => $usd, 'credits' => Money::credits($cents)]);

        return ['credits' => Money::credits($cents), 'usd' => $usd];
    }

    /**
     * Share purchased credit with another member found by Naara ID, username, verified phone or email. The lookup uses the SEARCH
     * purpose, so a person who hides from discovery cannot be paid by guessing their phone (no existence oracle); their Naara ID still works.
     *
     * @return array{ok: bool, message: string}
     */
    public function share(User $from, string $identifier, float $credits, string $key, ?string $ip = null): array
    {
        $cents = Money::cents($credits);
        if (($why = $this->shareProblem($from, $cents)) !== null) {
            return ['ok' => false, 'message' => $why];
        }
        $hit = $this->resolver->resolve($identifier, 'search', $from, $ip);
        $to = $hit->found ? User::query()->find($hit->userId) : null;
        if ($to === null) {
            return ['ok' => false, 'message' => "We could not find that person. Check the Naara ID, username or number."];
        }
        if ($to->id === $from->id) {
            return ['ok' => false, 'message' => 'You cannot share credit with yourself.'];
        }
        if (($why = $this->capProblem($from, $to, $cents)) !== null) {
            return ['ok' => false, 'message' => $why];
        }
        try {
            $this->ledger->apply([
                [$from->id, 'shareable', -$cents, 'share_out', 'share:'.$key, $to->id, null],
                [$to->id, 'nonshareable', $cents, 'share_in', 'share:'.$key, $from->id, null],
            ]);
        } catch (InsufficientUsageCredit) {
            return ['ok' => false, 'message' => 'You do not have enough shareable credit. Only credit you bought can be shared.'];
        }
        Realtime::publish($to->id, 'credit.received', ['from' => $from->naara_id, 'amount' => Money::credits($cents)]);
        Auditor::log('n2n.credit_shared', User::class, $from->id, ['to' => $to->id, 'amount' => Money::credits($cents)]);

        return ['ok' => true, 'message' => 'Sent '.Money::credits($cents).' credits to '.($hit->name ?: 'them').'.'];
    }

    private function shareProblem(User $from, int $cents): ?string
    {
        $min = Money::cents(max(0.01, (float) Setting::getValue('n2n.share_min', 1)));
        $max = Money::cents(max(1, (float) Setting::getValue('n2n.share_max', 500)));
        if ($cents < $min || $cents > $max) {
            return 'You can share between '.Money::credits($min).' and '.Money::credits($max).' credits at a time.';
        }
        $age = (int) Setting::getValue('n2n.share_min_account_days', 7);
        if ($age > 0 && $from->created_at !== null && Carbon::parse($from->created_at)->gt(now()->subDays($age))) {
            return "Credit sharing opens {$age} days after you join.";
        }

        return null;
    }

    private function capProblem(User $from, User $to, int $cents): ?string
    {
        $day = now()->subDay();
        $out = \App\Models\N2N\UsageLedger::query()->where('user_id', $from->id)->where('type', 'share_out')->where('created_at', '>=', $day);
        $total = -Money::cents((clone $out)->sum('amount'));
        $toThem = -Money::cents((clone $out)->where('counterparty_user_id', $to->id)->sum('amount'));
        $capDay = Money::cents(max(1, (float) Setting::getValue('n2n.share_cap_per_day', 2000)));
        $capRecipient = Money::cents(max(1, (float) Setting::getValue('n2n.share_cap_per_recipient_day', 1000)));
        if ($total + $cents > $capDay) {
            return 'That is over your daily sharing limit.';
        }
        if ($toThem + $cents > $capRecipient) {
            return 'That is over the daily limit for this person.';
        }

        return null;
    }
}
