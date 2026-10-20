<?php

namespace Tests\Feature;

use App\Models\PayoutCorridor;
use App\Models\PayoutProbeRun;
use App\Models\Setting;
use App\Models\User;
use App\Services\Payouts\Health\GatewayStatus;
use App\Services\Payouts\Health\GatewayStatusResolver;
use App\Services\Payouts\Health\PayoutProbe;
use App\Services\Payouts\PayoutService;
use App\Services\Payouts\Rail\RadarAlerts;
use App\Support\Verify\Checks\PayoutsGatewaysReady;
use App\Support\Verify\Checks\PayoutsProbesFresh;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

/** Engineering v3 Phase 2: payout gateways report WHY they are not usable, new rails appear with no extra wiring, and every rail can be probed. */
class PayoutGatewayStatusTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        Cache::flush();
        config(['payouts.env' => 'sandbox']);
        app(PayoutService::class); // registers the updater-delivered rails
    }

    private function resolver(): GatewayStatusResolver
    {
        return app(GatewayStatusResolver::class);
    }

    public function test_the_provider_list_comes_from_the_registry_and_includes_updater_rails(): void
    {
        $all = $this->resolver()->providers();
        foreach (['paystack', 'flutterwave', 'stripe', 'paypal', 'cryptomus', 'grey', 'nowpayments'] as $p) {
            $this->assertContains($p, $all, "{$p} must be discovered without any hand-kept list");
        }
        $this->assertContains('payoneer', $all, 'a planned rail that is not installed still shows (as not installed)');
    }

    public function test_each_cause_has_its_own_reason_and_fix(): void
    {
        // No key.
        config(['services.paystack.secret_key' => '']);
        $s = $this->resolver()->statusOf('paystack');
        $this->assertSame([GatewayStatus::R_NO_KEY, false], [$s->reason, $s->usable()]);
        $this->assertStringContainsString('Provider Keys', (string) $s->fix);

        // A live key outside live mode: present, but the wrong environment.
        config(['services.paystack.secret_key' => 'sk_live_abc']);
        $s = $this->resolver()->statusOf('paystack');
        $this->assertSame(GatewayStatus::R_ENV_MISMATCH, $s->reason);
        $this->assertStringContainsString('LIVE key', $s->adminMessage);

        // A good sandbox key.
        config(['services.paystack.secret_key' => 'sk_test_abc']);
        $this->assertTrue($this->resolver()->statusOf('paystack')->usable());

        // The failure breaker.
        Cache::put(RadarAlerts::UNHEALTHY_KEY.'paystack', true, 600);
        $s = $this->resolver()->statusOf('paystack');
        $this->assertSame([GatewayStatus::R_BREAKER, 'temporarily_unavailable'], [$s->reason, $s->userReason()]);
    }

    public function test_updater_rails_report_installed_but_unkeyed_and_wrong_environment_and_not_installed(): void
    {
        config(['services.grey.api_secret' => '']);
        $this->assertSame(GatewayStatus::R_NO_KEY, $this->resolver()->statusOf('grey')->reason);

        // Production host while payouts are in sandbox mode.
        config(['services.grey.api_secret' => 'gbsk_x', 'services.grey.base_url' => 'https://businessapi.grey.co']);
        $s = $this->resolver()->statusOf('grey');
        $this->assertSame(GatewayStatus::R_ENV_MISMATCH, $s->reason);

        config(['services.grey.base_url' => 'https://businessapi-sandbox.grey.co']);
        $this->assertTrue($this->resolver()->statusOf('grey')->usable());

        // Not installed: calm for members, explained for admins.
        $p = $this->resolver()->statusOf('payoneer');
        $this->assertSame([GatewayStatus::R_NOT_INSTALLED, 'not_open_yet'], [$p->reason, $p->userReason()]);

        // Switched off by an admin.
        Setting::setValue(\App\Support\PayoutSettings::DISABLED_EXTENSIONS, 'grey');
        \App\Services\Payouts\Extensions\PayoutRailExtensions::flush();
        $this->assertContains($this->resolver()->statusOf('grey')->reason, [GatewayStatus::R_DISABLED, GatewayStatus::R_NOT_INSTALLED]);
    }

    public function test_members_only_ever_see_calm_reasons(): void
    {
        config(['services.paystack.secret_key' => '']);
        $this->assertSame('not_open_yet', $this->resolver()->statusOf('paystack')->userReason());
        $this->assertNotSame('payout_guide.reason_not_open_yet', __('payout_guide.reason_not_open_yet'));
        foreach (['fr', 'es', 'de', 'pt', 'sw', 'ar', 'zh', 'hi'] as $loc) {
            app()->setLocale($loc);
            $this->assertNotSame('payout_guide.reason_not_open_yet', __('payout_guide.reason_not_open_yet'), $loc);
        }
    }

    public function test_the_automation_checklist_explains_why_no_rail_is_ready(): void
    {
        config(['services.paystack.secret_key' => 'sk_live_abc', 'services.flutterwave.secret_key' => '', 'services.stripe.secret_key' => '', 'services.paypal.client_id' => '', 'services.cryptomus.merchant_id' => '']);
        $report = app(\App\Services\Payouts\Hardening\AutomationStatus::class)->report();
        $row = collect($report['checks'])->firstWhere('label', 'At least one payout provider is ready');
        $this->assertNotNull($row);
        $this->assertStringContainsString('LIVE key', (string) $row['fix'], 'the real cause is named, not just "add a key"');
    }

    public function test_a_gateway_added_by_the_updater_is_evaluated_by_automation_status(): void
    {
        config(['services.grey.api_secret' => 'gbsk_x', 'services.grey.base_url' => 'https://businessapi-sandbox.grey.co']);
        $report = app(\App\Services\Payouts\Hardening\AutomationStatus::class)->report();
        $this->assertTrue(collect($report['checks'])->contains(fn ($c) => $c['label'] === 'Grey pays out automatically'), 'Grey is evaluated with no code change');
    }

    // ------------------------------------------------------------------ probes

    private function fake(array $stubs): void
    {
        Http::swap(new Factory);
        Http::fake($stubs);
    }

    public function test_the_auth_probe_reads_with_the_credentials_and_stores_evidence_without_secrets(): void
    {
        config(['services.paystack.secret_key' => 'sk_test_SECRETKEY123']);
        $this->fake(['*/balance' => Http::response(['status' => true, 'data' => [['currency' => 'NGN', 'balance' => 999999999]]])]);
        $r = app(PayoutProbe::class)->run('paystack', 'auth');

        $this->assertSame('ok', $r['status']);
        $row = PayoutProbeRun::first();
        $blob = json_encode($row->toArray());
        $this->assertStringNotContainsString('SECRETKEY123', $blob);
        $this->assertStringNotContainsString('999999999', $blob, 'no balance is ever stored');

        $this->fake(['*/balance' => Http::response(['message' => 'bad key'], 401)]);
        $this->assertSame('fail', app(PayoutProbe::class)->run('paystack', 'auth')['status']);
    }

    public function test_a_gateway_that_cannot_work_is_skipped_with_the_real_reason_and_nothing_is_called(): void
    {
        config(['services.paystack.secret_key' => '']);
        $this->fake(['*' => Http::response([], 200)]);
        $r = app(PayoutProbe::class)->run('paystack', 'auth');
        $this->assertSame('skipped', $r['status']);
        $this->assertStringContainsString('no credentials', $r['summary']);
        Http::assertNothingSent();
    }

    public function test_the_capability_probe_flags_an_enabled_corridor_the_provider_does_not_confirm(): void
    {
        config(['services.grey.api_secret' => 'gbsk_x', 'services.grey.base_url' => 'https://businessapi-sandbox.grey.co']);
        PayoutCorridor::create(['country' => 'CI', 'currency' => 'XOF', 'provider' => 'grey', 'method' => 'mobile_money', 'enabled' => true, 'priority' => 100]);
        PayoutCorridor::create(['country' => 'NG', 'currency' => 'NGN', 'provider' => 'grey', 'method' => 'bank', 'enabled' => true, 'priority' => 100]);
        $methods = ['status' => 'success', 'data' => ['payout_methods' => [
            ['name' => 'mobile_money', 'currencies' => ['XOF'], 'countries' => ['CI'], 'required_fields' => ['account_number']],
            ['name' => 'bank_account', 'currencies' => ['USD'], 'countries' => ['US'], 'required_fields' => ['account_number']],
        ]]];
        $this->fake(['*/v1/payout-methods' => Http::response($methods)]);
        Cache::flush();

        $r = app(PayoutProbe::class)->run('grey', 'capability');
        $this->assertSame('fail', $r['status']);
        $this->assertCount(1, $r['mismatches']);
        $this->assertStringContainsString('NG NGN', $r['mismatches'][0]);
    }

    public function test_the_capability_probe_is_honest_when_there_is_nothing_to_compare(): void
    {
        config(['services.paystack.secret_key' => 'sk_test_x']);
        PayoutCorridor::query()->where('provider', 'paystack')->delete();
        $this->assertSame('skipped', app(PayoutProbe::class)->run('paystack', 'capability')['status']);
    }

    // ------------------------------------------------------------------ verify checks and the admin panel

    public function test_verify_fails_when_a_corridor_is_enabled_on_a_gateway_that_cannot_pay(): void
    {
        config(['services.paystack.secret_key' => '']);
        PayoutCorridor::query()->delete();
        PayoutCorridor::create(['country' => 'NG', 'currency' => 'NGN', 'provider' => 'paystack', 'method' => 'bank', 'enabled' => true, 'priority' => 1]);
        $r = app(PayoutsGatewaysReady::class)->run();
        $this->assertSame('fail', $r->status);

        config(['services.paystack.secret_key' => 'sk_test_x']);
        $this->assertSame('pass', app(PayoutsGatewaysReady::class)->run()->status);
    }

    public function test_verify_warns_about_stale_probes_and_fails_a_failing_one(): void
    {
        config(['services.paystack.secret_key' => 'sk_test_x']);
        PayoutCorridor::query()->delete();
        PayoutCorridor::create(['country' => 'NG', 'currency' => 'NGN', 'provider' => 'paystack', 'method' => 'bank', 'enabled' => true, 'priority' => 1]);
        $this->assertSame('warn', app(PayoutsProbesFresh::class)->run()->status);

        PayoutProbeRun::create(['provider' => 'paystack', 'tier' => 'auth', 'status' => 'ok', 'summary' => 'ok', 'created_at' => now()]);
        $this->assertSame('pass', app(PayoutsProbesFresh::class)->run()->status);

        PayoutProbeRun::create(['provider' => 'paystack', 'tier' => 'auth', 'status' => 'fail', 'summary' => 'bad', 'created_at' => now()]);
        $this->assertSame('fail', app(PayoutsProbesFresh::class)->run()->status);
    }

    public function test_the_health_page_lists_gateways_and_runs_a_probe(): void
    {
        $admin = User::factory()->create()->assignRole('admin');
        config(['services.paystack.secret_key' => 'sk_test_x']);
        $this->fake(['*/balance' => Http::response(['status' => true, 'data' => []])]);

        $path = '/'.config('admin.path', 'adminmaster');
        $this->actingAs($admin)->get($path.'/payouts/health')->assertOk()->assertSee('Payout gateways')->assertSee('Grey')->assertSee('NOWPayments');

        Livewire::actingAs($admin)->test(\App\Livewire\Admin\PayoutHealth::class)
            ->call('probe', 'paystack', 'auth')->assertSet('error', null)->assertSee('Credentials test');
        $this->assertSame(1, PayoutProbeRun::where('provider', 'paystack')->count());

        // A member with no payout role cannot probe.
        Livewire::actingAs(User::factory()->create())->test(\App\Livewire\Admin\PayoutHealth::class)->assertForbidden();
    }

    public function test_the_naara_verify_payouts_area_is_green_for_the_registry_check(): void
    {
        $r = (new \App\Support\Verify\Checks\PayoutsNoLiteralProviderLists)->run();
        $this->assertSame('pass', $r->status, json_encode($r->evidence ?? []));
    }

    public function test_simulate_names_the_chosen_gateway_or_the_exact_blocker(): void
    {
        $admin = User::factory()->create()->assignRole('admin');
        PayoutCorridor::query()->delete();
        PayoutCorridor::create(['country' => 'GH', 'currency' => 'GHS', 'provider' => 'paystack', 'method' => 'bank', 'enabled' => true, 'priority' => 1]);
        PayoutCorridor::create(['country' => 'GH', 'currency' => 'GHS', 'provider' => 'flutterwave', 'method' => 'bank', 'enabled' => true, 'priority' => 2]);

        // Paystack has no key; Flutterwave is ready: Flutterwave is chosen and Paystack's reason is shown.
        config(['services.paystack.secret_key' => '', 'services.flutterwave.secret_key' => 'FLWSECK_TEST-abc']);
        $c = Livewire::actingAs($admin)->test(\App\Livewire\Admin\PayoutHealth::class)->set('simCountry', 'GH')->set('simCurrency', 'GHS')->set('simAmount', '50')->call('simulate');
        $lines = implode(' | ', $c->get('simLines'));
        $this->assertStringContainsString('CHOSEN: Flutterwave', $lines);
        $this->assertStringContainsString('Paystack (bank): not used: Paystack has no credentials saved', $lines);

        // Nothing ready: the verdict says so.
        config(['services.flutterwave.secret_key' => '']);
        $c = Livewire::actingAs($admin)->test(\App\Livewire\Admin\PayoutHealth::class)->set('simCountry', 'GH')->set('simCurrency', 'GHS')->call('simulate');
        $this->assertStringContainsString('RESULT: nobody can be paid', implode(' | ', $c->get('simLines')));

        // No corridor at all.
        $c = Livewire::actingAs($admin)->test(\App\Livewire\Admin\PayoutHealth::class)->set('simCountry', 'JP')->set('simCurrency', 'JPY')->call('simulate');
        $this->assertStringContainsString('No corridor exists', implode(' ', $c->get('simLines')));
    }
}
