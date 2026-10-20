<?php

namespace App\PayoutRails\Nowpayments;

use App\Jobs\AlertAdminJob;
use App\Models\PayoutAccount;
use App\Models\PayoutRequest;
use App\Services\Payouts\DeclaresCapabilities;
use App\Services\Payouts\LookupResult;
use App\Services\Payouts\PayoutEvent;
use App\Services\Payouts\PayoutException;
use App\Services\Payouts\PayoutGatewayInterface;
use App\Services\Payouts\PayoutTransferResult;
use App\Services\Payouts\ReportsBalance;
use App\Services\Payouts\SupportsStatusLookup;
use App\Support\CryptoPayoutNetworks;
use Illuminate\Http\Request;

/**
 * NOWPayments crypto payout rail (stablecoin payouts to whitelisted wallets).
 *
 * Flow per the NOWPayments docs: JWT -> POST /v1/payout -> POST /v1/payout/{batch}/verify (2FA) -> IPN / status.
 * Safety rules honoured here:
 *  - Only addresses an admin has marked WHITELISTED are ever sent (the Guardian defers the rest; this class
 *    refuses as a second line). NOWPayments rejects non-whitelisted addresses anyway, but we never rely on that.
 *  - `unique_external_id` = the request's wireReference(); nothing is created twice for one claim.
 *  - NOWPayments statuses: creating/waiting/processing/sending are in flight; `finished` = paid; `rejected` =
 *    failed (final). `failed` is NOT final (their side may resume it), so it is kept as processing and an admin is
 *    alerted — never refunded, never re-sent (their docs: don't recreate until finished/rejected).
 *  - Stablecoin networks only (USD-pegged), so the USD request amount is the coin amount; no price conversion.
 */
class NowpaymentsPayoutGateway implements PayoutGatewayInterface, DeclaresCapabilities, \App\Services\Payouts\ExplainsAvailability, SupportsStatusLookup, ReportsBalance
{
    public function __construct(private readonly NowpaymentsPayoutClient $client)
    {
    }

    public function name(): string
    {
        return 'nowpayments';
    }

    public function available(): bool
    {
        return $this->client->configured() && $this->client->environmentConsistent();
    }

    public function unavailableReason(): ?string
    {
        if (! $this->client->configured()) {
            return 'no_key';
        }

        return $this->client->environmentConsistent() ? null : 'key_present_env_mismatch';
    }

    public function capabilities(): array
    {
        return ['confirms_synchronously' => false, 'webhook' => true, 'lookup' => true, 'cancel' => false];
    }

    /**
     * Ask NOWPayments whether it can pay this address (POST /v1/payout/validate-address). 'unknown' means we could
     * not get an answer — callers must treat that as "not confirmed", never as valid.
     *
     * @return 'valid'|'invalid'|'unknown'
     */
    public function addressStatus(string $address, string $currency, ?string $memo = null): string
    {
        try {
            $response = $this->client->validateAddress($address, $currency, $memo);
        } catch (\Throwable) {
            return 'unknown';
        }
        if ($response->successful()) {
            return 'valid';
        }

        return $response->status() === 400 ? 'invalid' : 'unknown';
    }

    /** No recipient object: the wallet address is the destination. */
    public function createRecipient(PayoutAccount $account): string
    {
        if (CryptoPayoutNetworks::get((string) $account->bank_code) === null) {
            throw new PayoutException('This crypto network is not supported.');
        }

        return (string) $account->account_number;
    }

    public function sendTransfer(PayoutRequest $request, PayoutAccount $account): PayoutTransferResult
    {
        $network = CryptoPayoutNetworks::get((string) $account->bank_code);
        if ($network === null) {
            throw new PayoutException('This crypto network is not supported.');
        }
        if (strtoupper((string) $request->currency) !== 'USD') {
            throw new PayoutException('Crypto payouts are USD-denominated stablecoins only.');
        }
        if ($account->whitelist_status !== PayoutAccount::WHITELISTED) {
            throw new PayoutException('This wallet address has not been whitelisted yet.');
        }

        try {
            $this->client->jwt();
        } catch (\RuntimeException $e) {
            AlertAdminJob::dispatch('nowpayments_auth_failed', $e->getMessage().' Crypto payouts cannot be created until the dashboard login in Provider Keys is fixed.', []);
            throw new PayoutException('Crypto payouts are temporarily unavailable.');
        }

        $withdrawal = array_filter([
            'address' => (string) $account->account_number,
            'currency' => $network['currency'],
            'amount' => (float) number_format((float) $request->amount, 6, '.', ''), // max 6 decimals per the docs
            'extra_id' => $network['memo'] ? (string) data_get($account->details, 'memo') : null,
            'ipn_callback_url' => route('webhooks.payouts', ['provider' => $this->name()]),
            'payout_description' => 'Naara payout',
            'unique_external_id' => $request->wireReference(),
        ], fn ($v) => $v !== null && $v !== '');

        $response = $this->client->createPayout($withdrawal);

        if (! $response->successful()) {
            $status = $response->status();
            if ($status === 401 || $status === 403) {
                AlertAdminJob::dispatch('nowpayments_payout_denied', 'NOWPayments denied a payout (HTTP '.$status.'): check the API key, the whitelisted server IP and that the address is whitelisted.', ['status' => $status]);
            }
            if ($status >= 500 || $status === 409 || $status === 429) {
                $response->throw(); // outcome unknown -> reconciler
            }

            return new PayoutTransferResult(status: 'failed', failureReason: (string) (data_get($response->json(), 'message') ?: 'NOWPayments rejected the payout.'));
        }

        $batchId = (string) data_get($response->json(), 'id', '');
        $payoutId = (string) data_get($response->json(), 'withdrawals.0.id', '') ?: $batchId;
        if ($batchId === '') {
            // Accepted but unreadable: we cannot tell what was created. Unknown, not failed.
            throw new \RuntimeException('NOWPayments accepted the payout but returned no id.');
        }

        $this->verify($batchId, $request);

        return new PayoutTransferResult(status: 'processing', providerRef: $payoutId);
    }

    /** Verify with the 2FA code. Never changes the money outcome; a miss is surfaced to an admin. */
    private function verify(string $batchId, PayoutRequest $request): void
    {
        if (! $this->client->canAutoVerify()) {
            AlertAdminJob::dispatch('nowpayments_verify_needed', "Payout #{$request->id} (batch {$batchId}) needs its 2FA verification in the NOWPayments dashboard within 1 hour, or it is auto-rejected.", ['batch' => $batchId], 'warning');

            return;
        }
        try {
            $ok = $this->client->verifyPayout($batchId)->successful();
        } catch (\Throwable) {
            $ok = false;
        }
        if (! $ok) {
            AlertAdminJob::dispatch('nowpayments_verify_failed', "Payout #{$request->id} (batch {$batchId}) was created but automatic 2FA verification failed. Verify it in the NOWPayments dashboard within 1 hour or it is auto-rejected.", ['batch' => $batchId], 'warning');
        }
    }

    /** NOWPayments payout status -> engine status. Only `finished` and `rejected` are final. */
    private function map(string $status): string
    {
        return match (strtolower($status)) {
            'finished' => 'paid',
            'rejected', 'cancelled', 'canceled' => 'failed',
            default => 'processing', // creating / waiting / processing / sending / failed(retriable)
        };
    }

    public function lookupTransfer(PayoutRequest $request): LookupResult
    {
        if (filled($request->provider_ref)) {
            $response = $this->client->payout((string) $request->provider_ref);
            if ($response->status() === 404) {
                return LookupResult::notFound();
            }
            $response->throw();
            $w = $this->firstWithdrawal($response->json());
            if ($w !== null) {
                return $this->found($w);
            }

            return LookupResult::unsupported();
        }

        // No stored id (the create call's answer was lost): scan recent payouts for our unique_external_id.
        // Only a COMPLETE scan may say "not found" — a partial one must not trigger a refund + resend.
        $from = ($request->created_at ?? now())->copy()->subDay()->toDateString();
        for ($page = 0; $page < 5; $page++) {
            $response = $this->client->listPayouts(['date_from' => $from, 'limit' => 100, 'page' => $page, 'order_by' => 'dateCreated', 'order' => 'desc']);
            $response->throw();
            $rows = (array) data_get($response->json(), 'payouts', []);
            foreach ($rows as $row) {
                if (($row['unique_external_id'] ?? null) === $request->wireReference()) {
                    return $this->found($row);
                }
            }
            if (count($rows) < 100) {
                return LookupResult::notFound();
            }
        }

        return LookupResult::unsupported();
    }

    /** @param  array<string, mixed>  $w */
    private function found(array $w): LookupResult
    {
        $status = $this->map((string) ($w['status'] ?? ''));

        return LookupResult::found($status, (string) ($w['id'] ?? '') ?: null, $status === 'failed' ? (string) ($w['error'] ?? 'rejected') : null);
    }

    /** The status endpoint answers with either the withdrawal itself or a batch wrapper. */
    private function firstWithdrawal(mixed $json): ?array
    {
        if (! is_array($json)) {
            return null;
        }
        if (isset($json['withdrawals'][0]) && is_array($json['withdrawals'][0])) {
            return $json['withdrawals'][0];
        }

        return isset($json['status']) ? $json : null;
    }

    /** @return array<string, float> coin ticker (upper-case) => spendable amount */
    public function balances(): array
    {
        $response = $this->client->balance();
        $response->throw();

        $out = [];
        foreach ((array) $response->json() as $coin => $row) {
            if (is_array($row) && isset($row['amount']) && is_numeric($row['amount'])) {
                $out[strtoupper((string) $coin)] = (float) $row['amount'];
            }
        }
        // Stablecoin balances are USD-pegged: the float tracker keys payout float by request currency (USD).
        $usd = 0.0;
        foreach (CryptoPayoutNetworks::all() as $n) {
            $usd += $out[strtoupper($n['currency'])] ?? 0.0;
        }

        return ['USD' => round($usd, 4)] + $out;
    }

    /** x-nowpayments-sig = HMAC-SHA512(sorted-by-key JSON body, IPN secret). */
    public function verifyWebhook(Request $request): bool
    {
        $secret = trim((string) config('services.nowpayments.ipn_secret'));
        $given = (string) $request->header('x-nowpayments-sig', '');
        if ($secret === '' || $given === '') {
            return false;
        }
        $data = json_decode($request->getContent(), true);
        if (! is_array($data)) {
            return false;
        }
        $this->sortKeys($data);

        return hash_equals(hash_hmac('sha512', json_encode($data, JSON_UNESCAPED_SLASHES), $secret), strtolower($given));
    }

    private function sortKeys(array &$a): void
    {
        ksort($a);
        foreach ($a as &$v) {
            if (is_array($v)) {
                $this->sortKeys($v);
            }
        }
    }

    public function parseWebhook(Request $request): ?PayoutEvent
    {
        $reference = (string) $request->input('unique_external_id', '');
        $status = strtolower((string) $request->input('status', ''));
        // Payment IPNs share the route family; a payout IPN always carries a payout status and our reference.
        if ($reference === '' || ! in_array($status, ['finished', 'rejected'], true)) {
            return null;
        }

        return new PayoutEvent(
            provider: $this->name(),
            reference: $reference,
            status: $status === 'finished' ? 'paid' : 'failed',
            providerRef: (string) $request->input('id', '') ?: null,
        );
    }
}
