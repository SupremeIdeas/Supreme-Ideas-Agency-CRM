<?php

namespace App\Support\Bootstrap;

use App\Support\ProviderHealth;
use App\Support\ProviderStatus;
use Illuminate\Support\Facades\Http;

/**
 * No-spend "Test connection" for a credential slot (Bootstrap blueprint 4B.1, the per-provider piece that was left). Every probe is a
 * READ the provider documents as free: an account/balance lookup or an OAuth token. Nothing here buys, sends, charges or dials, and
 * a provider with no safe free call says so ("manual") instead of being tested by spending money.
 *
 * Result: ['status' => ok|fail|manual|empty, 'message' => string]. The message never contains a credential, a response body or a
 * balance figure, so it is safe to show and to log.
 */
final class ProviderTester
{
    /** Direct, documented, read-only lookups authenticated with one bearer secret. */
    private const DIRECT = [
        // provider => [method, config key holding the base URL, default base URL, path, config key holding the secret]
        'paystack' => ['GET', 'services.paystack.base_url', 'https://api.paystack.co', '/balance', 'services.paystack.secret_key'],
        'stripe' => ['GET', 'services.stripe.base_url', 'https://api.stripe.com/v1', '/balance', 'services.stripe.secret_key'],
        'flutterwave' => ['GET', 'services.flutterwave.base_url', 'https://api.flutterwave.com/v3', '/balances', 'services.flutterwave.secret_key'],
        'telnyx' => ['GET', 'services.telnyx.base_url', 'https://api.telnyx.com/v2', '/balance', 'services.telnyx.api_key'],
    ];

    public static function canTest(string $provider): bool
    {
        return isset(self::DIRECT[$provider]) || in_array($provider, ['twilio', 'paypal', 'africastalking', 'grey', 'nowpayments'], true)
            || ProviderHealth::supports($provider);
    }

    /** @return array{status: string, message: string} */
    public static function test(string $provider): array
    {
        if (! ProviderStatus::isActive($provider) && ! self::hasKeysFor($provider)) {
            return ['status' => 'empty', 'message' => 'No credentials saved yet.'];
        }
        if (! self::canTest($provider)) {
            return ['status' => 'manual', 'message' => 'No free test call exists for this provider. The first real order is the test: do one small one in sandbox mode if the provider has it.'];
        }

        try {
            return match (true) {
                $provider === 'twilio' => self::twilio(),
                $provider === 'paypal' => self::paypal(),
                $provider === 'africastalking' => self::africasTalking(),
                $provider === 'grey' => self::grey(),
                $provider === 'nowpayments' => self::nowpayments(),
                isset(self::DIRECT[$provider]) => self::direct($provider),
                default => self::viaHealth($provider),
            };
        } catch (\Throwable) {
            return ['status' => 'fail', 'message' => 'Could not reach the provider (network error or timeout). Check the credentials and try again.'];
        }
    }

    private static function hasKeysFor(string $provider): bool
    {
        $slot = CredentialRegistry::slots()[$provider] ?? null;

        return $slot !== null && $slot['status'] === 'saved';
    }

    private static function verdict(int $status, string $what): array
    {
        return match (true) {
            $status >= 200 && $status < 300 => ['status' => 'ok', 'message' => "{$what}: the provider accepted the credentials."],
            in_array($status, [401, 403], true) => ['status' => 'fail', 'message' => "{$what}: the provider rejected the credentials (HTTP {$status}). Re-copy the key and make sure it is the live (or sandbox) one you meant."],
            default => ['status' => 'fail', 'message' => "{$what}: the provider answered HTTP {$status}. Try again; if it persists check the provider's status page."],
        };
    }

    private static function direct(string $provider): array
    {
        [$method, $baseKey, $baseDefault, $path, $secretKey] = self::DIRECT[$provider];
        $url = rtrim((string) config($baseKey, $baseDefault), '/').$path;
        $res = Http::withToken((string) config($secretKey))->acceptJson()->connectTimeout(3)->timeout(15)->send($method, $url);

        return self::verdict($res->status(), 'Account lookup');
    }

    /** Grey: read-only wallet balances (documented, free). Also refuses a host that disagrees with the payout mode. */
    private static function grey(): array
    {
        $base = rtrim((string) config('services.grey.base_url', 'https://businessapi-sandbox.grey.co'), '/');
        $live = \App\Services\Payouts\Hardening\PayoutEnvGuard::mode() === 'live';
        if ($live === str_contains($base, 'sandbox')) {
            return ['status' => 'fail', 'message' => $live
                ? 'Payouts are in LIVE mode but the Grey base URL is the sandbox host. Use https://businessapi.grey.co.'
                : 'Payouts are in SANDBOX mode but the Grey base URL is the production host. Use https://businessapi-sandbox.grey.co.'];
        }
        $res = Http::withToken((string) config('services.grey.api_secret'))->acceptJson()->connectTimeout(3)->timeout(15)->get($base.'/v1/balances');

        return self::verdict($res->status(), 'Wallet balances lookup');
    }

    /** NOWPayments: payout balance with the API key, plus the dashboard login that mints the payout JWT (both read-only). */
    private static function nowpayments(): array
    {
        $base = rtrim((string) config('services.nowpayments.base_url', 'https://api.nowpayments.io'), '/');
        $key = Http::withHeaders(['x-api-key' => (string) config('services.nowpayments.api_key')])->acceptJson()->connectTimeout(3)->timeout(15)->get($base.'/v1/balance');
        $out = self::verdict($key->status(), 'API key check');
        if ($out['status'] !== 'ok' || ! filled(config('services.nowpayments.payout_email'))) {
            return $out;
        }
        $auth = Http::acceptJson()->asJson()->connectTimeout(3)->timeout(15)->post($base.'/v1/auth', [
            'email' => (string) config('services.nowpayments.payout_email'),
            'password' => (string) config('services.nowpayments.payout_password'),
        ]);
        if (! $auth->successful() || ! filled(data_get($auth->json(), 'token'))) {
            return ['status' => 'fail', 'message' => 'The API key works, but the payout dashboard login was refused (HTTP '.$auth->status().'). Re-check the dashboard email and password (case-sensitive).'];
        }

        return ['status' => 'ok', 'message' => 'API key and payout login both accepted. Remember: payouts also need your server IP and each wallet address whitelisted in the NOWPayments dashboard.'];
    }

    private static function twilio(): array
    {
        $sid = (string) config('services.twilio.account_sid');
        $res = Http::withBasicAuth($sid, (string) config('services.twilio.auth_token'))->acceptJson()->connectTimeout(3)->timeout(15)
            ->get('https://api.twilio.com/2010-04-01/Accounts/'.$sid.'.json');

        return self::verdict($res->status(), 'Account lookup');
    }

    private static function paypal(): array
    {
        $base = rtrim((string) config('services.paypal.base_url', 'https://api-m.paypal.com'), '/');
        $res = Http::withBasicAuth((string) config('services.paypal.client_id'), (string) config('services.paypal.client_secret'))
            ->asForm()->connectTimeout(3)->timeout(15)->post($base.'/v1/oauth2/token', ['grant_type' => 'client_credentials']);

        return self::verdict($res->status(), 'Sign-in token');
    }

    private static function africasTalking(): array
    {
        $res = Http::withHeaders(['apiKey' => (string) config('services.africastalking.api_key'), 'Accept' => 'application/json'])
            ->connectTimeout(3)->timeout(15)
            ->get(rtrim((string) config('services.africastalking.base_url'), '/').'/version1/user', ['username' => (string) config('services.africastalking.username')]);

        return self::verdict($res->status(), 'Account lookup');
    }

    /** eSIM and SMS providers whose service already exposes a read-only balance. */
    private static function viaHealth(string $provider): array
    {
        $row = ProviderHealth::checkOne($provider);

        return match ($row['status'] ?? 'coming_soon') {
            'ok', 'low' => ['status' => 'ok', 'message' => 'Balance lookup: the provider accepted the credentials.'.(($row['status'] ?? '') === 'low' ? ' The balance is below your alert level: top it up.' : '')],
            'configured' => ['status' => 'manual', 'message' => 'Credentials are saved. This provider has no free read call we can use, so the first real order is the test.'],
            'down' => ['status' => 'fail', 'message' => 'Balance lookup failed. Re-copy the key and try again.'],
            default => ['status' => 'empty', 'message' => 'No credentials saved yet.'],
        };
    }
}
