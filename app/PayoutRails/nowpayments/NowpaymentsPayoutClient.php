<?php

namespace App\PayoutRails\Nowpayments;

use App\Support\PayoutSettings;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use PragmaRX\Google2FA\Google2FA;

/**
 * NOWPayments mass-payout client (docs: "Mass payouts" in the NOWPayments Postman collection).
 *
 *  - Every call sends `x-api-key`.
 *  - Create / verify also need `Authorization: Bearer <JWT>`; the JWT comes from POST /v1/auth with the dashboard
 *    email + password (case-sensitive) and lives 5 minutes, so it is cached for 4.
 *  - Payouts are only accepted from a whitelisted server IP to whitelisted wallet addresses (dashboard -> Settings
 *    -> Whitelist). 2FA verification is mandatory by default; an unverified payout is auto-rejected after an hour.
 */
class NowpaymentsPayoutClient
{
    public function baseUrl(): string
    {
        return rtrim((string) config('services.nowpayments.base_url', 'https://api.nowpayments.io'), '/');
    }

    public function configured(): bool
    {
        return filled(config('services.nowpayments.api_key'))
            && filled(config('services.nowpayments.payout_email'))
            && filled(config('services.nowpayments.payout_password'));
    }

    public function sandboxHost(): bool
    {
        return str_contains($this->baseUrl(), 'sandbox');
    }

    public function environmentConsistent(): bool
    {
        $live = \App\Services\Payouts\Hardening\PayoutEnvGuard::mode() === 'live';

        return $live ? ! $this->sandboxHost() : $this->sandboxHost();
    }

    public function canAutoVerify(): bool
    {
        return filled(config('services.nowpayments.payout_2fa_secret'));
    }

    private function http(bool $withJwt = false): PendingRequest
    {
        $req = Http::connectTimeout(PayoutSettings::httpConnectTimeout())
            ->timeout(PayoutSettings::httpTimeout())
            ->withHeaders(['x-api-key' => (string) config('services.nowpayments.api_key')])
            ->acceptJson()
            ->asJson();

        return $withJwt ? $req->withToken($this->jwt()) : $req;
    }

    /** @throws \RuntimeException when the dashboard credentials are refused */
    public function jwt(): string
    {
        $cached = Cache::get($this->jwtKey());
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }
        $response = Http::connectTimeout(PayoutSettings::httpConnectTimeout())->timeout(PayoutSettings::httpTimeout())
            ->acceptJson()->asJson()
            ->post($this->baseUrl().'/v1/auth', [
                'email' => (string) config('services.nowpayments.payout_email'),
                'password' => (string) config('services.nowpayments.payout_password'),
            ]);
        $token = (string) data_get($response->json(), 'token', '');
        if (! $response->successful() || $token === '') {
            if ($response->serverError()) {
                $response->throw(); // provider down: not a credentials problem
            }
            throw new \RuntimeException('NOWPayments refused the payout login (HTTP '.$response->status().').');
        }
        Cache::put($this->jwtKey(), $token, 240);

        return $token;
    }

    private function jwtKey(): string
    {
        return 'nowpayments.payout_jwt.'.md5($this->baseUrl().config('services.nowpayments.payout_email'));
    }

    public function status(): Response
    {
        return Http::connectTimeout(PayoutSettings::httpConnectTimeout())->timeout(PayoutSettings::httpTimeout())
            ->acceptJson()->get($this->baseUrl().'/v1/status');
    }

    public function balance(): Response
    {
        return $this->http()->get($this->baseUrl().'/v1/balance');
    }

    public function validateAddress(string $address, string $currency, ?string $extraId = null): Response
    {
        return $this->http()->post($this->baseUrl().'/v1/payout/validate-address', [
            'address' => $address, 'currency' => $currency, 'extra_id' => $extraId,
        ]);
    }

    public function createPayout(array $withdrawal): Response
    {
        return $this->http(true)->post($this->baseUrl().'/v1/payout', [
            'withdrawals' => [$withdrawal],
        ]);
    }

    public function verifyPayout(string $batchId): Response
    {
        $code = (new Google2FA)->getCurrentOtp((string) config('services.nowpayments.payout_2fa_secret'));

        return $this->http(true)->post($this->baseUrl().'/v1/payout/'.rawurlencode($batchId).'/verify', ['verification_code' => $code]);
    }

    public function payout(string $id): Response
    {
        return $this->http()->get($this->baseUrl().'/v1/payout/'.rawurlencode($id));
    }

    /** @param  array<string, scalar>  $query */
    public function listPayouts(array $query): Response
    {
        return $this->http()->get($this->baseUrl().'/v1/payout', $query);
    }

    public function minWithdrawal(string $coin): Response
    {
        return $this->http()->get($this->baseUrl().'/v1/payout-withdrawal/min-amount/'.rawurlencode($coin));
    }
}
