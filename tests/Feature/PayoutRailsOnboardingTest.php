<?php

namespace Tests\Feature;

use App\Models\PayoutAccount;
use App\Models\PayoutCorridor;
use App\Models\User;
use App\PayoutRails\Grey\GreyAccountService;
use App\Services\Payouts\AccountResolutionException;
use App\Services\Payouts\PayoutService;
use App\Support\Bootstrap\ProviderTester;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * How members add accounts on the Grey and NOWPayments rails, how corridors are created from the provider's own
 * list, and the read-only "Test connection" probes. Everything runs on responses shaped like the supplied docs.
 */
class PayoutRailsOnboardingTest extends TestCase
{
    use RefreshDatabase;

    /** Shaped like the LIVE /v1/payout-methods answer: required_fields is a union across countries; some rails publish none. */
    private const METHODS = ['status' => 'success', 'data' => ['payout_methods' => [
        ['name' => 'bank_account', 'currencies' => ['USD', 'EUR', 'GBP', 'XOF'], 'countries' => ['US', 'DE', 'GB', 'CI'], 'required_fields' => ['account_number', 'account_type', 'bank_code', 'bank_country_code', 'branch_code', 'document_number', 'first_name', 'iban', 'last_name', 'phone_number', 'recipient_type', 'routing_number'], 'payment_purposes' => ['Other'], 'average_processing_time' => '24 hours'],
        ['name' => 'interac', 'currencies' => ['CAD'], 'countries' => ['CA'], 'required_fields' => null, 'average_processing_time' => '15 minutes'],
        ['name' => 'pix', 'currencies' => ['BRL'], 'countries' => ['BR'], 'required_fields' => null],
        ['name' => 'wallet_address', 'currencies' => ['USDC', 'USDT'], 'countries' => ['NG', 'US'], 'required_fields' => ['wallet_address']],
        ['name' => 'mobile_money', 'currencies' => ['XOF', 'GHS'], 'countries' => ['CI', 'GH'], 'required_fields' => ['account_number', 'first_name', 'last_name', 'network_type', 'phone_number', 'wallet_id'], 'payment_purposes' => ['Salary']],
    ]]];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        Cache::flush();
        config([
            'services.grey.api_secret' => 'gbsk_test', 'services.grey.base_url' => 'https://businessapi-sandbox.grey.co', 'payouts.env' => 'sandbox',
            'services.nowpayments.api_key' => 'k', 'services.nowpayments.base_url' => 'https://api-sandbox.nowpayments.io',
            'services.nowpayments.payout_email' => 'ops@example.com', 'services.nowpayments.payout_password' => 'pw',
        ]);
        app(PayoutService::class);
    }

    private function fake(array $stubs): void
    {
        Http::swap(new Factory);
        Http::fake($stubs);
    }

    // -------------------------------------------------------------- corridor sync

    public function test_grey_sync_creates_only_disabled_own_currency_corridors_from_greys_list(): void
    {
        $this->fake(['*/v1/payout-methods' => Http::response(self::METHODS)]);
        $this->artisan('payouts:sync-corridors', ['provider' => 'grey'])->assertSuccessful();

        $rows = PayoutCorridor::where('provider', 'grey')->get();
        $key = fn ($c) => "{$c->country}:{$c->currency}:{$c->method}";
        // bank_account in each country's OWN currency; mobile money likewise. interac / pix / wallet_address are not collectable.
        $this->assertEqualsCanonicalizing(['US:USD:bank', 'DE:EUR:bank', 'GB:GBP:bank', 'CI:XOF:bank', 'CI:XOF:mobile_money', 'GH:GHS:mobile_money'], $rows->map($key)->all());
        $this->assertNull(PayoutCorridor::where(['provider' => 'grey', 'country' => 'CA'])->first(), 'interac publishes no field list');
        $this->assertTrue($rows->every(fn ($c) => $c->enabled === false), 'every synced corridor ships disabled');
        $this->assertNull(PayoutCorridor::where(['provider' => 'grey', 'country' => 'DE', 'currency' => 'USD'])->first(), 'no invented cross pairs');

        // Re-running is a no-op and never re-disables something an admin enabled.
        PayoutCorridor::where('country', 'US')->update(['enabled' => true]);
        $this->artisan('payouts:sync-corridors', ['provider' => 'grey'])->assertSuccessful();
        $this->assertSame(6, PayoutCorridor::where('provider', 'grey')->count());
        $this->assertTrue(PayoutCorridor::where('country', 'US')->first()->enabled);
    }

    public function test_grey_sync_creates_nothing_when_grey_cannot_be_read(): void
    {
        $this->fake(['*/v1/payout-methods' => Http::response('x', 500)]);
        $this->artisan('payouts:sync-corridors', ['provider' => 'grey'])->assertFailed();
        $this->assertSame(0, PayoutCorridor::where('provider', 'grey')->count());
    }

    public function test_nowpayments_sync_needs_explicit_countries(): void
    {
        $this->artisan('payouts:sync-corridors', ['provider' => 'nowpayments'])->assertFailed();
        $this->artisan('payouts:sync-corridors', ['provider' => 'nowpayments', '--countries' => 'NG,GH'])->assertSuccessful();
        $this->assertSame(2, PayoutCorridor::where(['provider' => 'nowpayments', 'method' => 'crypto', 'currency' => 'USD', 'enabled' => false])->count());
    }

    // -------------------------------------------------------------- Grey account add

    private function enableGrey(string $country, string $currency, string $method): void
    {
        PayoutCorridor::create(['country' => $country, 'currency' => $currency, 'provider' => 'grey', 'method' => $method, 'enabled' => true, 'priority' => 100]);
    }

    public function test_the_form_requires_only_what_every_account_needs_and_offers_greys_other_fields_as_optional(): void
    {
        $this->fake(['*/v1/payout-methods' => Http::response(self::METHODS)]);
        $this->enableGrey('US', 'USD', 'bank');
        $this->enableGrey('CI', 'XOF', 'mobile_money');
        $svc = app(GreyAccountService::class);

        $us = $svc->formFor('US');
        $this->assertSame('USD', $us['currency']);
        $this->assertSame(['account_number'], $us['fields']);
        $this->assertContains('routing_number', $us['optional']);
        $this->assertContains('document_number', $us['optional']);
        $this->assertNotContains('first_name', $us['optional'], 'names are filled by us');

        $ci = $svc->formFor('CI');
        $this->assertSame(['account_number', 'network_type'], $ci['fields']);
        $this->assertContains('phone_number', $ci['optional']);
        $this->assertNull($svc->formFor('NG'), 'no enabled corridor');
    }

    public function test_a_rail_that_publishes_no_field_list_is_not_offered(): void
    {
        $this->fake(['*/v1/payout-methods' => Http::response(self::METHODS)]);
        $this->enableGrey('CA', 'CAD', 'bank'); // Canada is only on interac, which has no field list
        $this->assertNull(app(GreyAccountService::class)->formFor('CA'));
    }

    public function test_add_saves_a_grey_account_with_the_members_own_name_and_refuses_gaps(): void
    {
        $this->fake(['*/v1/payout-methods' => Http::response(self::METHODS)]);
        $this->enableGrey('US', 'USD', 'bank');
        $user = User::factory()->create(['name' => 'John Doe']);
        $svc = app(GreyAccountService::class);

        try {
            $svc->add($user, 'US', ['routing_number' => '021000021']); // no account number
            $this->fail('missing account number must be refused');
        } catch (AccountResolutionException $e) {
            $this->assertStringContainsString('account number', $e->getMessage());
        }
        try {
            $svc->add($user, 'NG', ['account_number' => '1']);
            $this->fail('country without a corridor must be refused');
        } catch (AccountResolutionException) {
            $this->addToAssertionCount(1);
        }

        $a = $svc->add($user, 'US', ['account_number' => '1234567890', 'routing_number' => '021000021', 'document_number' => '  ', 'evil' => 'ignored']);
        $this->assertSame(['grey', 'bank', 'US', 'USD', 'John Doe'], [$a->provider, $a->type, $a->country, $a->currency, $a->account_name]);
        $this->assertSame('021000021', $a->details['routing_number']);
        $this->assertArrayNotHasKey('evil', $a->details);
        $this->assertArrayNotHasKey('document_number', $a->details, 'blank optional fields are dropped');
    }

    public function test_grey_payouts_stay_on_manual_review(): void
    {
        $this->fake(['*/v1/payout-methods' => Http::response(self::METHODS)]);
        $this->enableGrey('US', 'USD', 'bank');
        $user = User::factory()->create(['name' => 'John Doe']);
        $a = app(GreyAccountService::class)->add($user, 'US', ['account_number' => '1234567890']);
        $req = \App\Models\PayoutRequest::create(['user_id' => $user->id, 'payee_type' => 'referral', 'payout_account_id' => $a->id, 'amount' => 10, 'currency' => 'USD', 'source_bucket' => 'referral', 'status' => 'pending', 'provider' => 'grey', 'reference' => 'PO-g']);
        $r = app(\App\Services\Payouts\Guardian\Gates\AccountIntegrityGate::class)->check(new \App\Services\Payouts\Guardian\GuardianContext($req, $a));
        $this->assertSame('enrollment_unverifiable', $r->reason);
    }

    // -------------------------------------------------------------- the member UI

    public function test_the_withdraw_page_offers_the_new_tabs_only_when_a_corridor_is_enabled(): void
    {
        $this->fake(['*/v1/payout-methods' => Http::response(self::METHODS), '*/v1/payout/validate-address' => Http::response('OK')]);
        $user = User::factory()->create(['country_code' => 'NG']);

        Livewire::actingAs($user)->test(\App\Livewire\Withdraw::class)->assertDontSee('International bank')->assertDontSee('Crypto wallet');

        $this->enableGrey('US', 'USD', 'bank');
        PayoutCorridor::create(['country' => 'NG', 'currency' => 'USD', 'provider' => 'nowpayments', 'method' => 'crypto', 'enabled' => true, 'priority' => 100]);

        $c = Livewire::actingAs($user)->test(\App\Livewire\Withdraw::class)->assertSee('International bank')->assertSee('Crypto wallet');

        $c->set('accountType', 'crypto')->set('cryptoNetwork', 'usdttrc20')
            ->set('cryptoAddress', 'TEmGwPeRTPiLFLVfBxXkSP91yc5GMNQhfS')->set('cryptoAddressConfirm', 'TEmGwPeRTPiLFLVfBxXkSP91yc5GMNQhfS')
            ->call('addCryptoAccount')->assertSet('accountError', null);
        $this->assertSame(PayoutAccount::WL_PENDING, PayoutAccount::where('user_id', $user->id)->value('whitelist_status'));

        Livewire::actingAs($user)->test(\App\Livewire\Withdraw::class)->assertSee('Being activated for payouts');
    }

    // -------------------------------------------------------------- Test connection

    public function test_grey_probe_reads_balances_and_never_leaks_them(): void
    {
        $this->fake(['*/v1/balances' => Http::response(['status' => 'success', 'data' => ['balances' => [['currency' => 'USD', 'available_balance' => 99999.99]]]])]);
        $r = ProviderTester::test('grey');
        $this->assertSame('ok', $r['status']);
        $this->assertStringNotContainsString('99999', $r['message']);
        Http::assertSent(fn (HttpRequest $q) => $q->hasHeader('Authorization', 'Bearer gbsk_test') && str_ends_with($q->url(), '/v1/balances'));

        $this->fake(['*/v1/balances' => Http::response(['message' => 'bad key'], 401)]);
        $this->assertSame('fail', ProviderTester::test('grey')['status']);
    }

    public function test_grey_probe_refuses_a_host_that_disagrees_with_the_payout_mode(): void
    {
        config(['services.grey.base_url' => 'https://businessapi.grey.co']); // production host, sandbox mode
        $this->fake(['*' => Http::response([], 200)]);
        $r = ProviderTester::test('grey');
        $this->assertSame('fail', $r['status']);
        $this->assertStringContainsString('SANDBOX', $r['message']);
        Http::assertNothingSent();
    }

    public function test_nowpayments_probe_checks_the_api_key_then_the_payout_login(): void
    {
        $this->fake(['*/v1/balance' => Http::response(['usdttrc20' => ['amount' => 1, 'pendingAmount' => 0]]), '*/v1/auth' => Http::response(['token' => 'jwt'])]);
        $this->assertSame('ok', ProviderTester::test('nowpayments')['status']);

        $this->fake(['*/v1/balance' => Http::response([]), '*/v1/auth' => Http::response(['message' => 'wrong'], 401)]);
        $r = ProviderTester::test('nowpayments');
        $this->assertSame('fail', $r['status']);
        $this->assertStringContainsString('login was refused', $r['message']);

        $this->fake(['*/v1/balance' => Http::response(['message' => 'Invalid API key'], 403)]);
        $this->assertSame('fail', ProviderTester::test('nowpayments')['status']);
    }
}
