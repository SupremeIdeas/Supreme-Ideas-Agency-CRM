<?php

namespace App\Services\Payouts\Crypto;

use App\Models\PayoutAccount;
use App\Models\User;
use App\Services\Payouts\AccountResolutionException;
use App\Services\Payouts\Hardening\PayoutFreeze;
use App\Services\Payouts\Hardening\StepUpAuth;
use App\Support\Auditor;
use App\Support\CryptoPayoutNetworks;
use App\Support\PayoutSettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Saves a crypto payout wallet. Three checks, in order, all before anything is stored:
 *   1. the address has the right shape for the chosen network (typo / wrong-chain guard);
 *   2. the user typed it twice and both match;
 *   3. NOWPayments itself confirms it can pay that address on that network (validate-address). If the provider
 *      cannot be reached the account is NOT saved — we never store a destination we could not confirm.
 * The account starts as whitelist `pending`: it cannot be paid until an admin has put it on the provider
 * whitelist and marked it so ({@see CryptoWhitelist}).
 */
class CryptoAccountService
{
    /** Is crypto payout switched on (an ENABLED corridor) for this member's country? */
    public function offeredTo(User $user): bool
    {
        return \App\Models\PayoutCorridor::query()->enabled()
            ->where(['country' => strtoupper((string) $user->country_code), 'currency' => 'USD', 'provider' => 'nowpayments', 'method' => 'crypto'])
            ->exists();
    }

    /** @throws AccountResolutionException */
    public function add(User $user, string $network, string $address, string $addressConfirm, bool $makeDefault = false): PayoutAccount
    {
        $this->guard($user);
        if (! $this->offeredTo($user)) {
            throw new AccountResolutionException('Crypto payouts are not available in your country yet.');
        }

        $net = CryptoPayoutNetworks::get($network);
        if ($net === null) {
            throw new AccountResolutionException('Choose a supported network.');
        }
        $address = trim($address);
        if (! CryptoPayoutNetworks::looksValid($network, $address)) {
            throw new AccountResolutionException("That doesn't look like a valid {$net['chain']} address. Check you copied the whole address and picked the right network.");
        }
        if ($address !== trim($addressConfirm)) {
            throw new AccountResolutionException('The two addresses do not match.');
        }

        $gateway = app()->bound('payout.nowpayments') ? app('payout.nowpayments') : null;
        if ($gateway === null || ! $gateway->available()) {
            throw new AccountResolutionException('Crypto payouts are not available yet.');
        }
        $verdict = $gateway->addressStatus($address, $net['currency']);
        if ($verdict === 'invalid') {
            throw new AccountResolutionException("Our payment partner says that address can't receive {$net['label']}. Check the address and network.");
        }
        if ($verdict !== 'valid') {
            throw new AccountResolutionException("We couldn't confirm that address right now. Please try again in a few minutes.");
        }

        $hash = PayoutAccount::lookupHashFor($address);
        if (PayoutAccount::query()->where('user_id', $user->id)->where('provider', 'nowpayments')->where('lookup_hash', $hash)->exists()) {
            throw new AccountResolutionException('You have already saved that address.');
        }

        return DB::transaction(function () use ($user, $net, $network, $address, $makeDefault) {
            $isFirst = ! PayoutAccount::query()->where('user_id', $user->id)->exists();
            $default = $makeDefault || $isFirst;
            if ($default) {
                PayoutAccount::query()->where('user_id', $user->id)->update(['is_default' => false]);
            }

            $account = PayoutAccount::create([
                'user_id' => $user->id,
                'type' => 'crypto',
                'method' => 'crypto',
                'country' => $user->country_code ?: 'ZZ',
                'currency' => 'USD',
                'bank_code' => strtolower($network),
                'bank_name' => $net['label'],
                'account_number' => $address,
                'account_name' => $user->name,
                'provider' => 'nowpayments',
                'is_verified' => true,
                'is_default' => $default,
                'whitelist_status' => PayoutAccount::WL_PENDING,
            ]);

            Auditor::log('payout.account_added', 'PayoutAccount', $account->id, ['provider' => 'nowpayments', 'type' => 'crypto', 'network' => $network]);
            if (PayoutSettings::notifyOnRequest()) {
                $user->notify(new \App\Notifications\PayoutRequestedNotice(null, 'account'));
            }

            return $account;
        });
    }

    private function guard(User $user): void
    {
        if (PayoutFreeze::isFrozen($user->id)) {
            throw new AccountResolutionException('Payouts on this account are paused for your protection. Please contact support.');
        }
        $limit = PayoutSettings::addAccountPerHour();
        if ($limit > 0 && ! RateLimiter::attempt('payout-add-account:'.$user->id, $limit, fn () => true, 3600)) {
            throw new AccountResolutionException("You've added several accounts in a short time. Please wait a while before adding another.");
        }
        app(StepUpAuth::class)->assertFresh($user);
    }
}
