<?php

namespace Tests\Feature;

use App\Models\PayoutAccount;
use App\Models\PayoutRequest;
use App\Models\User;
use App\PayoutRails\Nowpayments\NowpaymentsPayoutGateway;
use App\Services\Payouts\AccountResolutionException;
use App\Services\Payouts\Crypto\CryptoAccountService;
use App\Services\Payouts\Crypto\CryptoWhitelist;
use App\Services\Payouts\LookupResult;
use App\Services\Payouts\PayoutException;
use App\Services\Payouts\PayoutService;
use App\Support\CryptoPayoutNetworks;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The NOWPayments crypto payout rail, whitelist hand-off and wallet-add checks, against responses shaped exactly
 * like the NOWPayments Postman collection ("Mass payouts"). No real NOWPayments call is made.
 */
class NowpaymentsPayoutRailTest extends TestCase
{
    use RefreshDatabase;

    private const TRON = 'TEmGwPeRTPiLFLVfBxXkSP91yc5GMNQhfS';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        Cache::flush();
        config([
            'services.nowpayments.api_key' => 'np_key', 'services.nowpayments.ipn_secret' => 'ipn_secret',
            'services.nowpayments.base_url' => 'https://api-sandbox.nowpayments.io',
            'services.nowpayments.payout_email' => 'ops@example.com', 'services.nowpayments.payout_password' => 'Pa55word',
            'services.nowpayments.payout_2fa_secret' => 'JBSWY3DPEHPK3PXP',
            'payouts.env' => 'sandbox',
        ]);
        app(PayoutService::class); // registers extension gateways
    }

    private function offer(string $country = 'NG', bool $enabled = true): void
    {
        \App\Models\PayoutCorridor::updateOrCreate(
            ['country' => $country, 'currency' => 'USD', 'provider' => 'nowpayments', 'method' => 'crypto'],
            ['enabled' => $enabled, 'priority' => 100],
        );
    }

    private function member(): User
    {
        return User::factory()->create(['country_code' => 'NG']);
    }

    private function fake(array $stubs): void
    {
        Http::swap(new Factory);
        Http::fake($stubs);
    }

    private function gw(): NowpaymentsPayoutGateway
    {
        return app(NowpaymentsPayoutGateway::class);
    }

    private function account(string $wl = PayoutAccount::WHITELISTED, array $over = []): PayoutAccount
    {
        $user = User::factory()->create();

        return PayoutAccount::create(array_merge([
            'user_id' => $user->id, 'type' => 'crypto', 'method' => 'crypto', 'country' => 'NG', 'currency' => 'USD',
            'bank_code' => 'usdttrc20', 'bank_name' => 'USDT on Tron (TRC20)', 'account_number' => self::TRON, 'account_name' => 'Ada O',
            'provider' => 'nowpayments', 'is_verified' => true, 'whitelist_status' => $wl,
        ], $over));
    }

    private function request(PayoutAccount $a, array $over = []): PayoutRequest
    {
        return PayoutRequest::create(array_merge([
            'user_id' => $a->user_id, 'payee_type' => 'referral', 'payout_account_id' => $a->id, 'amount' => 25.5, 'currency' => 'USD',
            'source_bucket' => 'referral', 'status' => 'processing', 'provider' => 'nowpayments', 'reference' => 'PO-np-1', 'provider_reference' => 'NS-NP-0001',
        ], $over));
    }

    private function createStubs(array $extra = []): array
    {
        return $extra + [
            '*/v1/auth' => Http::response(['token' => 'jwt-token']),
            '*/v1/payout/*/verify' => Http::response('OK', 200),
            '*/v1/payout' => Http::response(['id' => '5000000713', 'withdrawals' => [['id' => '5000000000', 'status' => 'CREATING', 'batch_withdrawal_id' => '5000000713']]]),
        ];
    }

    // ---------------------------------------------------------------- rail basics

    public function test_installs_as_an_extension_and_availability_follows_keys_and_environment(): void
    {
        $this->assertTrue(\App\Services\Payouts\Extensions\PayoutRailExtensions::installed('nowpayments'));
        $this->assertSame('nowpayments', app('payout.nowpayments')->name());
        $this->assertTrue($this->gw()->available());

        config(['services.nowpayments.payout_password' => '']);
        $this->assertFalse($this->gw()->available(), 'dashboard login missing');
        config(['services.nowpayments.payout_password' => 'x', 'services.nowpayments.base_url' => 'https://api.nowpayments.io']);
        $this->assertFalse($this->gw()->available(), 'production host in sandbox mode');
    }

    public function test_send_follows_the_documented_flow_jwt_create_then_2fa_verify(): void
    {
        $this->fake($this->createStubs());
        $a = $this->account();
        $res = $this->gw()->sendTransfer($this->request($a), $a);

        $this->assertSame('processing', $res->status);
        $this->assertSame('5000000000', $res->providerRef);

        Http::assertSent(fn (HttpRequest $r) => str_ends_with($r->url(), '/v1/auth') && $r->data() === ['email' => 'ops@example.com', 'password' => 'Pa55word']);
        Http::assertSent(function (HttpRequest $r) {
            if (! str_ends_with($r->url(), '/v1/payout') || $r->method() !== 'POST') {
                return false;
            }
            $w = $r->data()['withdrawals'][0];

            return $r->hasHeader('Authorization', 'Bearer jwt-token') && $r->hasHeader('x-api-key', 'np_key')
                && $w['address'] === self::TRON && $w['currency'] === 'usdttrc20' && (float) $w['amount'] === 25.5
                && $w['unique_external_id'] === 'NS-NP-0001'
                && str_ends_with($w['ipn_callback_url'], '/webhooks/payouts/nowpayments');
        });
        Http::assertSent(fn (HttpRequest $r) => str_contains($r->url(), '/v1/payout/5000000713/verify')
            && preg_match('/^\d{6}$/', (string) $r->data()['verification_code']) === 1);
    }

    public function test_a_non_whitelisted_wallet_is_never_sent(): void
    {
        $this->fake($this->createStubs());
        $a = $this->account(PayoutAccount::WL_PENDING);
        try {
            $this->gw()->sendTransfer($this->request($a), $a);
            $this->fail('must refuse a wallet that is not whitelisted');
        } catch (PayoutException $e) {
            $this->assertStringContainsString('whitelisted', $e->getMessage());
        }
        Http::assertNothingSent();
    }

    public function test_only_usd_stablecoin_payouts_are_allowed(): void
    {
        $this->fake($this->createStubs());
        $a = $this->account();
        $this->expectException(PayoutException::class);
        $this->gw()->sendTransfer($this->request($a, ['currency' => 'NGN']), $a);
    }

    public function test_no_2fa_secret_creates_the_payout_but_leaves_verification_to_a_human(): void
    {
        config(['services.nowpayments.payout_2fa_secret' => '']);
        \Illuminate\Support\Facades\Bus::fake([\App\Jobs\AlertAdminJob::class]);
        $this->fake($this->createStubs());
        $a = $this->account();
        $res = $this->gw()->sendTransfer($this->request($a), $a);
        $this->assertSame('processing', $res->status);
        Http::assertNotSent(fn (HttpRequest $r) => str_contains($r->url(), '/verify'));
        \Illuminate\Support\Facades\Bus::assertDispatched(\App\Jobs\AlertAdminJob::class, fn ($j) => $j->code === 'nowpayments_verify_needed');
    }

    public function test_a_failed_2fa_verification_alerts_but_does_not_change_the_money_outcome(): void
    {
        \Illuminate\Support\Facades\Bus::fake([\App\Jobs\AlertAdminJob::class]);
        $this->fake($this->createStubs(['*/v1/payout/*/verify' => Http::response(['message' => 'bad code'], 400)]));
        $a = $this->account();
        $this->assertSame('processing', $this->gw()->sendTransfer($this->request($a), $a)->status);
        \Illuminate\Support\Facades\Bus::assertDispatched(\App\Jobs\AlertAdminJob::class, fn ($j) => $j->code === 'nowpayments_verify_failed');
    }

    public function test_provider_rejection_is_failed_and_server_errors_are_unknown(): void
    {
        $a = $this->account();
        $this->fake($this->createStubs(['*/v1/payout' => Http::response(['message' => 'Invalid payout_address'], 400)]));
        $res = $this->gw()->sendTransfer($this->request($a), $a);
        $this->assertSame('failed', $res->status);
        $this->assertSame('Invalid payout_address', $res->failureReason);

        $this->fake($this->createStubs(['*/v1/payout' => Http::response('x', 503)]));
        $this->expectException(RequestException::class);
        $this->gw()->sendTransfer($this->request($a, ['reference' => 'PO-2', 'provider_reference' => 'NS-NP-0002']), $a);
    }

    public function test_bad_dashboard_login_is_a_clean_refusal_not_a_blind_send(): void
    {
        \Illuminate\Support\Facades\Bus::fake([\App\Jobs\AlertAdminJob::class]);
        $this->fake(['*/v1/auth' => Http::response(['message' => 'wrong'], 401)]);
        $a = $this->account();
        try {
            $this->gw()->sendTransfer($this->request($a), $a);
            $this->fail('expected refusal');
        } catch (PayoutException) {
            Http::assertNotSent(fn (HttpRequest $r) => str_ends_with($r->url(), '/v1/payout'));
            \Illuminate\Support\Facades\Bus::assertDispatched(\App\Jobs\AlertAdminJob::class, fn ($j) => $j->code === 'nowpayments_auth_failed');
        }
    }

    // ---------------------------------------------------------------- status

    public function test_lookup_maps_statuses_and_only_finished_and_rejected_are_final(): void
    {
        $a = $this->account();
        $req = $this->request($a, ['provider_ref' => '5000000000']);
        foreach (['FINISHED' => 'paid', 'REJECTED' => 'failed', 'SENDING' => 'processing', 'PROCESSING' => 'processing', 'WAITING' => 'processing', 'CREATING' => 'processing', 'FAILED' => 'processing'] as $np => $ours) {
            $this->fake(['*/v1/payout/5000000000' => Http::response(['id' => '5000000713', 'withdrawals' => [['id' => '5000000000', 'status' => $np, 'error' => null]]])]);
            $r = $this->gw()->lookupTransfer($req);
            $this->assertSame(LookupResult::FOUND, $r->state, $np);
            $this->assertSame($ours, $r->status, $np);
        }
    }

    public function test_lookup_without_a_stored_id_scans_by_unique_external_id_and_never_guesses_on_a_partial_scan(): void
    {
        $a = $this->account();
        $req = $this->request($a); // no provider_ref
        $this->fake(['*/v1/payout?*' => Http::response(['payouts' => [['id' => '77', 'status' => 'FINISHED', 'unique_external_id' => 'NS-NP-0001']]])]);
        $r = $this->gw()->lookupTransfer($req);
        $this->assertSame(['found', 'paid', '77'], [$r->state, $r->status, $r->providerRef]);

        $this->fake(['*/v1/payout?*' => Http::response(['payouts' => []])]);
        $this->assertSame(LookupResult::NOT_FOUND, $this->gw()->lookupTransfer($req)->state);

        // Five full pages and still no match: not a complete scan -> unsupported (no refund + resend).
        $page = ['payouts' => array_fill(0, 100, ['id' => '1', 'status' => 'FINISHED', 'unique_external_id' => 'other'])];
        $this->fake(['*/v1/payout?*' => Http::response($page)]);
        $this->assertSame(LookupResult::UNSUPPORTED, $this->gw()->lookupTransfer($req)->state);
    }

    public function test_balances_sum_stablecoins_into_usd_for_the_float_tracker(): void
    {
        $this->fake(['*/v1/balance' => Http::response(['usdttrc20' => ['amount' => 100.5, 'pendingAmount' => 0], 'usdtbsc' => ['amount' => 20, 'pendingAmount' => 0], 'eth' => ['amount' => 0.1, 'pendingAmount' => 0]])]);
        $b = $this->gw()->balances();
        $this->assertSame(120.5, $b['USD']);
        $this->assertSame(0.1, $b['ETH']);
    }

    // ---------------------------------------------------------------- webhook (IPN)

    private function ipn(array $payload, ?string $secret = 'ipn_secret'): Request
    {
        $sorted = $payload;
        ksort($sorted);
        $sig = hash_hmac('sha512', json_encode($sorted, JSON_UNESCAPED_SLASHES), (string) $secret);

        return Request::create('/webhooks/payouts/nowpayments', 'POST', [], [], [], ['HTTP_X_NOWPAYMENTS_SIG' => $sig, 'CONTENT_TYPE' => 'application/json'], json_encode($payload));
    }

    public function test_ipn_signature_uses_hmac_sha512_over_the_key_sorted_body(): void
    {
        $p = ['status' => 'FINISHED', 'id' => '77', 'unique_external_id' => 'NS-NP-0001', 'batch_withdrawal_id' => '5'];
        $this->assertTrue($this->gw()->verifyWebhook($this->ipn($p)));
        $this->assertFalse($this->gw()->verifyWebhook($this->ipn($p, 'wrong')));
        $noSig = Request::create('/x', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json'], json_encode($p));
        $this->assertFalse($this->gw()->verifyWebhook($noSig));
        config(['services.nowpayments.ipn_secret' => '']);
        $this->assertFalse($this->gw()->verifyWebhook($this->ipn($p)));
    }

    public function test_ipn_events_map_and_non_final_statuses_are_ignored(): void
    {
        $e = $this->gw()->parseWebhook($this->ipn(['status' => 'FINISHED', 'id' => '77', 'unique_external_id' => 'NS-NP-0001']));
        $this->assertSame(['nowpayments', 'NS-NP-0001', 'paid', '77'], [$e->provider, $e->reference, $e->status, $e->providerRef]);
        $this->assertSame('failed', $this->gw()->parseWebhook($this->ipn(['status' => 'REJECTED', 'unique_external_id' => 'NS-NP-0001']))->status);
        foreach (['SENDING', 'WAITING', 'FAILED', 'PROCESSING'] as $s) {
            $this->assertNull($this->gw()->parseWebhook($this->ipn(['status' => $s, 'unique_external_id' => 'NS-NP-0001'])), $s);
        }
        $this->assertNull($this->gw()->parseWebhook($this->ipn(['status' => 'FINISHED', 'id' => '1'])), 'no reference of ours');
    }

    public function test_the_webhook_route_settles_once_and_rejects_a_forged_ipn(): void
    {
        $a = $this->account();
        $req = $this->request($a);
        $p = ['status' => 'FINISHED', 'id' => '77', 'unique_external_id' => 'NS-NP-0001'];
        $good = $this->ipn($p);
        $server = ['HTTP_X_NOWPAYMENTS_SIG' => $good->header('x-nowpayments-sig'), 'CONTENT_TYPE' => 'application/json'];

        $this->call('POST', '/webhooks/payouts/nowpayments', [], [], [], $server, json_encode($p))->assertOk();
        $this->assertSame(PayoutRequest::PAID, $req->fresh()->status);
        $this->call('POST', '/webhooks/payouts/nowpayments', [], [], [], $server, json_encode($p))->assertOk(); // duplicate

        $b = $this->request($a, ['reference' => 'PO-9', 'provider_reference' => 'NS-NP-0009']);
        $forged = ['HTTP_X_NOWPAYMENTS_SIG' => str_repeat('a', 128), 'CONTENT_TYPE' => 'application/json'];
        $this->call('POST', '/webhooks/payouts/nowpayments', [], [], [], $forged, json_encode(['status' => 'FINISHED', 'unique_external_id' => 'NS-NP-0009']))->assertStatus(401);
        $this->assertNotSame(PayoutRequest::PAID, $b->fresh()->status);
    }

    // ---------------------------------------------------------------- adding a wallet

    public function test_address_shape_is_checked_per_network(): void
    {
        $this->assertTrue(CryptoPayoutNetworks::looksValid('usdttrc20', self::TRON));
        $this->assertFalse(CryptoPayoutNetworks::looksValid('usdttrc20', '0x1EBAeF7Bee7B3a7B2EEfC72e86593Bf15ED37522'), 'EVM address on Tron');
        $this->assertTrue(CryptoPayoutNetworks::looksValid('usdterc20', '0x1EBAeF7Bee7B3a7B2EEfC72e86593Bf15ED37522'));
        $this->assertFalse(CryptoPayoutNetworks::looksValid('usdterc20', self::TRON), 'Tron address on Ethereum');
        $this->assertFalse(CryptoPayoutNetworks::looksValid('usdttrc20', self::TRON.'x'));
        $this->assertFalse(CryptoPayoutNetworks::looksValid('doge', 'D8x'));
    }

    public function test_add_wallet_saves_only_after_nowpayments_confirms_the_address_and_starts_pending(): void
    {
        $this->fake(['*/v1/payout/validate-address' => Http::response('OK', 200)]);
        $this->offer();
        $user = $this->member();
        $acct = app(CryptoAccountService::class)->add($user, 'usdttrc20', self::TRON, self::TRON);

        $this->assertSame(PayoutAccount::WL_PENDING, $acct->whitelist_status);
        $this->assertSame(['crypto', 'nowpayments', 'usdttrc20', 'USD'], [$acct->type, $acct->provider, $acct->bank_code, $acct->currency]);
        Http::assertSent(fn (HttpRequest $r) => str_ends_with($r->url(), '/v1/payout/validate-address') && $r->data()['address'] === self::TRON && $r->data()['currency'] === 'usdttrc20' && $r->hasHeader('x-api-key', 'np_key'));
    }

    public function test_add_wallet_refuses_mismatch_bad_network_provider_rejection_and_provider_outage(): void
    {
        $svc = app(CryptoAccountService::class);
        $this->offer();
        $user = $this->member();
        $this->fake(['*/v1/payout/validate-address' => Http::response('OK', 200)]);

        foreach ([
            fn () => $svc->add($user, 'usdttrc20', self::TRON, 'TDifferent00000000000000000000000000'),
            fn () => $svc->add($user, 'usdttrc20', '0x1EBAeF7Bee7B3a7B2EEfC72e86593Bf15ED37522', '0x1EBAeF7Bee7B3a7B2EEfC72e86593Bf15ED37522'),
            fn () => $svc->add($user, 'btc-lightning', self::TRON, self::TRON),
        ] as $attempt) {
            try {
                $attempt();
                $this->fail('expected refusal');
            } catch (AccountResolutionException) {
                $this->addToAssertionCount(1);
            }
        }
        Http::assertNothingSent();

        // NOWPayments says the address is invalid.
        $this->fake(['*/v1/payout/validate-address' => Http::response(['code' => 'BAD_CREATE_WITHDRAWAL_REQUEST', 'message' => 'Invalid payout_address'], 400)]);
        try {
            $svc->add($user, 'usdttrc20', self::TRON, self::TRON);
            $this->fail('invalid address must be refused');
        } catch (AccountResolutionException $e) {
            $this->assertStringContainsString("can't receive", $e->getMessage());
        }

        // NOWPayments unreachable: NOT saved ("we never store a destination we could not confirm").
        $this->fake(['*/v1/payout/validate-address' => Http::response('x', 503)]);
        try {
            $svc->add($user, 'usdttrc20', self::TRON, self::TRON);
            $this->fail('outage must not save');
        } catch (AccountResolutionException $e) {
            $this->assertStringContainsString('try again', $e->getMessage());
        }
        $this->assertSame(0, PayoutAccount::where('user_id', $user->id)->count());
    }

    public function test_crypto_is_only_offered_where_a_corridor_is_enabled(): void
    {
        $this->fake(['*/v1/payout/validate-address' => Http::response('OK', 200)]);
        $svc = app(CryptoAccountService::class);
        $user = $this->member();
        $this->assertFalse($svc->offeredTo($user));
        $this->offer('NG', enabled: false);
        $this->assertFalse($svc->offeredTo($user), 'a disabled corridor is not an offer');
        try {
            $svc->add($user, 'usdttrc20', self::TRON, self::TRON);
            $this->fail('must refuse without an enabled corridor');
        } catch (AccountResolutionException $e) {
            $this->assertStringContainsString('your country', $e->getMessage());
        }
        Http::assertNothingSent();
        $this->offer('NG');
        $this->assertTrue($svc->offeredTo($user));
    }

    public function test_the_same_wallet_cannot_be_saved_twice_by_one_user(): void
    {
        $this->fake(['*/v1/payout/validate-address' => Http::response('OK', 200)]);
        $this->offer();
        $user = $this->member();
        app(CryptoAccountService::class)->add($user, 'usdttrc20', self::TRON, self::TRON);
        $this->expectException(AccountResolutionException::class);
        app(CryptoAccountService::class)->add($user, 'usdttrc20', self::TRON, self::TRON);
    }

    // ---------------------------------------------------------------- whitelist hand-off

    public function test_csv_export_has_only_new_addresses_then_marks_them_exported(): void
    {
        $admin = User::factory()->create();
        $a = $this->account(PayoutAccount::WL_PENDING);
        $b = $this->account(PayoutAccount::WL_PENDING, ['bank_code' => 'usdterc20', 'bank_name' => 'USDT on Ethereum (ERC20)', 'account_number' => '0x1EBAeF7Bee7B3a7B2EEfC72e86593Bf15ED37522']);
        $live = $this->account(PayoutAccount::WHITELISTED, ['account_number' => 'TLiveAddressAlreadyOnWhitelist000000']);

        $wl = app(CryptoWhitelist::class);
        $csv = $wl->exportCsv($admin);
        $rows = array_map('str_getcsv', array_filter(explode("\n", trim($csv))));

        $this->assertSame(CryptoWhitelist::CSV_HEADER, $rows[0]);
        $this->assertCount(3, $rows, 'header + the two new addresses only');
        $this->assertContains(self::TRON, array_column($rows, 3));
        $this->assertContains('0x1EBAeF7Bee7B3a7B2EEfC72e86593Bf15ED37522', array_column($rows, 3));
        $this->assertNotContains('TLiveAddressAlreadyOnWhitelist000000', array_column($rows, 3));
        $this->assertSame(PayoutAccount::WL_EXPORTED, $a->fresh()->whitelist_status);

        // A second export of "new" is empty: only the header.
        $this->assertCount(1, array_filter(explode("\n", trim($wl->exportCsv($admin)))));
        // ...but "everything awaiting" re-lists the exported ones.
        $this->assertCount(3, array_filter(explode("\n", trim($wl->exportCsv($admin, includeExported: true)))));

        $this->assertSame(2, $wl->markWhitelisted($admin, [$a->id, $b->id, $live->id]));
        $this->assertSame(PayoutAccount::WHITELISTED, $a->fresh()->whitelist_status);
        $this->assertNotNull($a->fresh()->whitelisted_at);
        $this->assertSame(['pending' => 0, 'exported' => 0, 'whitelisted' => 3], $wl->counts());
    }

    public function test_guardian_waits_for_a_pending_wallet_and_does_not_refuse_it(): void
    {
        $gate = app(\App\Services\Payouts\Guardian\Gates\AccountIntegrityGate::class);
        $pending = $this->account(PayoutAccount::WL_PENDING);
        $ctx = new \App\Services\Payouts\Guardian\GuardianContext($this->request($pending), $pending);
        $r = $gate->check($ctx);
        $this->assertSame(\App\Services\Payouts\Guardian\GateResult::DEFER, $r->outcome);
        $this->assertSame('crypto_awaiting_whitelist', $r->reason);
        $this->assertNotNull($r->nextCheckAt);

        $live = $this->account(PayoutAccount::WHITELISTED);
        $this->assertFalse($gate->check(new \App\Services\Payouts\Guardian\GuardianContext($this->request($live, ['reference' => 'PO-x', 'provider_reference' => 'NS-X']), $live))->failed());
    }

    public function test_the_admin_page_exports_and_marks_and_is_closed_to_ordinary_users(): void
    {
        $admin = User::factory()->create()->assignRole('admin');
        $a = $this->account(PayoutAccount::WL_PENDING);
        $path = '/'.config('admin.path', 'adminmaster');

        $this->actingAs($admin)->get($path.'/payouts/crypto-whitelist')->assertOk()->assertSee(self::TRON);

        \Livewire\Livewire::actingAs($admin)->test(\App\Livewire\Admin\CryptoWhitelist::class)
            ->call('exportNew')->assertFileDownloaded();
        $this->assertSame(PayoutAccount::WL_EXPORTED, $a->fresh()->whitelist_status);

        \Livewire\Livewire::actingAs($admin)->test(\App\Livewire\Admin\CryptoWhitelist::class)
            ->set('selected', [$a->id])->call('markSelected');
        $this->assertSame(PayoutAccount::WHITELISTED, $a->fresh()->whitelist_status);

        $this->actingAs(User::factory()->create())->get($path.'/payouts/crypto-whitelist')->assertNotFound();
    }
}
