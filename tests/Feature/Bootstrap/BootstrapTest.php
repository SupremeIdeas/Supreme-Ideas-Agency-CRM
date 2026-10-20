<?php

namespace Tests\Feature\Bootstrap;

use App\Models\Bootstrap\BootstrapEntry;
use App\Models\Coupon;
use App\Models\JourneyGoal;
use App\Models\Setting;
use App\Services\Bootstrap\Bootstrapper;
use App\Services\Bootstrap\Steps\JourneyGoalsStep;
use App\Support\Bootstrap\CredentialRegistry;
use App\Support\Bootstrap\Preflight;
use Database\Seeders\MarketingCouponsSeeder;
use Database\Seeders\PricingSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Bootstrap engine (MASTER-ONLY tooling): idempotent, never overwrites an edit, never invents a credential, and every default stays money-safe. */
class BootstrapTest extends TestCase
{
    use RefreshDatabase;

    private function boot(): array
    {
        $this->seed(PricingSettingsSeeder::class);
        $this->seed(MarketingCouponsSeeder::class);

        return (new Bootstrapper)->run('production');
    }

    public function test_a_dry_run_changes_nothing_and_a_real_run_then_verifies_clean(): void
    {
        $this->seed(PricingSettingsSeeder::class);
        $this->seed(MarketingCouponsSeeder::class);

        $dry = (new Bootstrapper)->run('production', null, true);
        $this->assertNotEmpty($dry['settings']['plan']);
        $this->assertSame(0, Setting::where('key', 'credits.per_usd')->count());
        $this->assertSame(0, JourneyGoal::count());

        $this->boot();
        $this->assertSame(21, JourneyGoal::count());
        foreach ((new Bootstrapper)->verify('production') as $step => $fails) {
            $this->assertSame([], $fails, "{$step}: ".implode('; ', $fails));
        }
    }

    public function test_it_is_idempotent_and_never_overwrites_an_admin_edit(): void
    {
        $this->boot();
        $before = [Setting::count(), Coupon::count(), JourneyGoal::count(), BootstrapEntry::count()];

        Setting::setValue('credits.per_usd', 250);
        $again = (new Bootstrapper)->run('production');

        $this->assertSame($before, [Setting::count(), Coupon::count(), JourneyGoal::count(), BootstrapEntry::count()]);
        $this->assertSame(0, $again['settings']['result']['created']);
        $this->assertEquals(250, Setting::getValue('credits.per_usd'));
        $this->assertNotNull(BootstrapEntry::where('item', 'credits.per_usd')->first()->admin_modified_at, 'the manifest remembers the admin changed it');
    }

    public function test_it_creates_no_credential_and_drafts_stay_off(): void
    {
        $this->boot();

        $this->assertFalse(BootstrapEntry::where('kind', 'credential')->exists());
        $this->assertSame(0, Setting::where('key', 'providers.keys')->count());
        foreach (CredentialRegistry::slots() as $slot) {
            $this->assertSame('empty', $slot['status'], $slot['id'].' must start empty');
        }
        $this->assertSame(0, Coupon::whereIn('code', ['FIRSTESIM', 'BLACKFRIDAY', 'NEWYEAR', 'EASTER', 'INDEPENDENCE'])->where('is_active', true)->count());
        $this->assertSame(0, JourneyGoal::where('is_active', true)->count());
        $this->assertFalse((bool) Setting::getValue('support.autopilot.enabled'), 'AI auto-actions ship off');
    }

    public function test_the_journey_ladder_stays_within_its_reward_budget_and_uses_real_metrics(): void
    {
        $step = new JourneyGoalsStep;
        $this->assertLessThanOrEqual(JourneyGoalsStep::DEFAULT_BUDGET, $step->lifetimeLiability());

        $this->boot();
        $this->assertSame([], $step->verify('production'));

        Setting::setValue(JourneyGoalsStep::BUDGET_KEY, 100);
        $this->assertNotEmpty($step->verify('production'), 'a ladder above the budget fails loudly');
    }

    public function test_every_webhook_route_is_shown_in_a_credential_slot_and_urls_use_app_url(): void
    {
        $this->assertSame([], CredentialRegistry::uncoveredWebhookRoutes());
        config(['app.url' => 'https://naara.example']);
        $urls = array_column(CredentialRegistry::webhooksFor('paystack'), 'url');
        $this->assertSame(['https://naara.example/webhooks/payments/paystack'], $urls);
    }

    public function test_the_notification_catalog_is_complete(): void
    {
        $fails = (new Bootstrapper)->verify('production', 'notification-catalog')['notification-catalog'];
        $this->assertSame([], $fails);
    }

    public function test_preflight_reports_waiting_items_without_failing_a_fresh_install(): void
    {
        $checks = Preflight::checks();
        $byId = array_column($checks, 'status', 'id');
        $this->assertSame('pending', $byId['mail']);
        $this->assertSame('pending', $byId['cron']);
        $this->assertTrue(Preflight::ready($checks));
    }

    public function test_setup_health_is_super_admin_only_and_runs_the_bootstrap(): void
    {
        $this->seed(\Database\Seeders\RoleSeeder::class);
        $this->seed(PricingSettingsSeeder::class);
        $this->seed(MarketingCouponsSeeder::class);

        $user = \App\Models\User::factory()->create(['is_active' => true]);
        $this->actingAs($user)->get('/'.config('app.admin_path', 'adminmaster').'/setup-health')->assertStatus(404);

        $admin = \App\Models\User::factory()->create(['is_active' => true]);
        $admin->assignRole('super_admin');
        \Livewire\Livewire::actingAs($admin)->test(\App\Livewire\Admin\Bootstrap\SetupHealth::class)
            ->assertSee('Setup health')->assertSee('Waiting on you')->assertSee('Scheduled jobs')->assertSee('Naara Line renewals')
            ->call('previewBootstrap')->assertSet('preview.0.kind', 'setting')
            ->call('runBootstrap')->assertSee('Nothing you edited was touched');
        $this->assertSame(21, JourneyGoal::count());
    }

    public function test_provider_test_is_a_no_spend_read_and_never_leaks_a_credential(): void
    {
        config(['services.paystack.secret_key' => 'sk_test_SECRETVALUE']);
        \Illuminate\Support\Facades\Http::fake(['api.paystack.co/balance' => \Illuminate\Support\Facades\Http::response(['status' => true, 'data' => [['balance' => 123456]]], 200)]);

        $r = \App\Support\Bootstrap\ProviderTester::test('paystack');

        $this->assertSame('ok', $r['status']);
        $this->assertStringNotContainsString('SECRETVALUE', json_encode($r));
        $this->assertStringNotContainsString('123456', json_encode($r), 'a balance figure never reaches the screen');
        \Illuminate\Support\Facades\Http::assertSent(fn ($req) => $req->method() === 'GET' && $req->hasHeader('Authorization', 'Bearer sk_test_SECRETVALUE'));
        \Illuminate\Support\Facades\Http::assertSentCount(1);
    }

    public function test_provider_test_reports_a_rejected_key_a_missing_key_and_a_provider_with_no_free_call(): void
    {
        config(['services.stripe.secret_key' => 'sk_live_wrong', 'services.paystack.secret_key' => null]);
        \Illuminate\Support\Facades\Http::fake(['api.stripe.com/*' => \Illuminate\Support\Facades\Http::response(['error' => 'x'], 401)]);

        $this->assertSame('fail', \App\Support\Bootstrap\ProviderTester::test('stripe')['status']);
        $this->assertSame('empty', \App\Support\Bootstrap\ProviderTester::test('paystack')['status']);

        config(['services.reloadly.client_id' => 'abc', 'services.reloadly.client_secret' => 'def']);
        $this->assertContains(\App\Support\Bootstrap\ProviderTester::test('reloadly')['status'], ['manual', 'empty']);
        \Illuminate\Support\Facades\Http::assertSentCount(1);
    }

    public function test_the_test_button_is_super_admin_only_audited_and_throttled(): void
    {
        $this->seed(\Database\Seeders\RoleSeeder::class);
        config(['services.paystack.secret_key' => 'sk_test_x']);
        \Illuminate\Support\Facades\Http::fake(['*' => \Illuminate\Support\Facades\Http::response([], 200)]);

        $staff = \App\Models\User::factory()->create(['is_active' => true]);
        \Livewire\Livewire::actingAs($staff)->test(\App\Livewire\Admin\Bootstrap\SetupHealth::class)->assertForbidden();

        $admin = \App\Models\User::factory()->create(['is_active' => true]);
        $admin->assignRole('super_admin');
        $c = \Livewire\Livewire::actingAs($admin)->test(\App\Livewire\Admin\Bootstrap\SetupHealth::class);
        $c->call('testProvider', 'paystack')->assertSet('tests.paystack.status', 'ok');
        $c->call('testProvider', 'paystack')->call('testProvider', 'paystack')->call('testProvider', 'paystack')
            ->assertSet('tests.paystack.status', 'fail');
        \Illuminate\Support\Facades\Http::assertSentCount(3);
        $this->assertDatabaseHas('audit_logs', ['action' => 'bootstrap.provider_test']);
    }

    public function test_announcement_drafts_are_created_once_as_drafts_and_never_sent(): void
    {
        $this->seed(PricingSettingsSeeder::class);
        (new Bootstrapper)->run('production', 'announcements');

        $this->assertSame(3, \App\Models\Announcement::count());
        $this->assertSame(0, \App\Models\Announcement::where('status', '!=', 'draft')->count());
        $this->assertSame(0, \App\Models\Announcement::where('recipients', '>', 0)->count());
        $this->assertNull(\App\Models\Announcement::whereNotNull('sent_at')->first());
        // The copy states no price, percentage or promise that could go stale or contradict the catalogue.
        foreach (\App\Models\Announcement::all() as $a) {
            $this->assertDoesNotMatchRegularExpression('/[$%]\s?\d|\d\s?%|\d+\s?(usd|ngn)/i', $a->title.' '.$a->body.' '.implode(' ', (array) $a->bullets));
        }

        $first = \App\Models\Announcement::first();
        $first->update(['title' => 'My own title']);
        \App\Models\Announcement::where('id', '!=', $first->id)->first()->delete();
        (new Bootstrapper)->run('production', 'announcements');

        $this->assertSame(2, \App\Models\Announcement::count(), 'a deleted draft is not recreated');
        $this->assertSame('My own title', $first->fresh()->title, 'an edited draft is never overwritten');
    }

    public function test_legal_pages_start_as_unreviewed_drafts_and_an_edit_clears_a_review(): void
    {
        $this->seed(\Database\Seeders\RoleSeeder::class);
        $this->assertCount(5, \App\Support\LegalReview::unreviewed());
        $this->assertSame('pending', array_column(Preflight::checks(), 'status', 'id')['legal_review']);

        $staff = \App\Models\User::factory()->create(['is_active' => true]);
        $staff->assignRole('admin');
        \Livewire\Livewire::actingAs($staff)->test(\App\Livewire\Admin\LegalEditor::class)->call('markReviewed')->assertForbidden();

        $admin = \App\Models\User::factory()->create(['is_active' => true, 'name' => 'Frank']);
        $admin->assignRole('super_admin');
        $c = \Livewire\Livewire::actingAs($admin)->test(\App\Livewire\Admin\LegalEditor::class)
            ->assertSee('Draft: not yet reviewed by counsel')->call('markReviewed')->assertSee('Reviewed by counsel on');
        $this->assertTrue(\App\Support\LegalReview::reviewed('privacy'));
        $this->assertCount(4, \App\Support\LegalReview::unreviewed());

        $c->set('body', 'Edited wording.')->call('save');
        $this->assertFalse(\App\Support\LegalReview::reviewed('privacy'), 'the reviewed words are no longer the live words');
        $this->assertDatabaseHas('audit_logs', ['action' => 'legal.reviewed']);
    }
}
