<?php

namespace Tests\Feature;

use App\Models\BrandPartner;
use App\Models\BrandSubscription;
use App\Models\BrandSubscriptionPlan;
use App\Models\Setting;
use App\Models\User;
use App\Services\Brands\BrandTrialService;
use App\Services\Wallet\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/** Brand-Growth Phase 4: free Naara Pro trial lifecycle, driven by a fake clock. */
class BrandTrialLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::setValue('brand.trial.enabled', true);
        Carbon::setTestNow('2026-11-01 10:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function plan(float $price = 19): BrandSubscriptionPlan
    {
        return BrandSubscriptionPlan::create(['name' => 'Starter', 'price_usd_per_month' => $price, 'handles_included' => 1, 'guaranteed_followers_per_handle_per_month' => 0, 'video_previews_allowed' => 0, 'is_active' => true, 'sort_order' => 1]);
    }

    private function owner(float $funds = 0, string $email = 'owner@example.test'): User
    {
        $u = User::factory()->create(['email' => $email, 'email_verified_at' => now()]);
        if ($funds > 0) {
            app(WalletService::class)->credit($u, $funds, 'USD');
        }

        return $u;
    }

    private function balance(User $u): float
    {
        return (float) $u->fresh()->wallet->usd_balance;
    }

    private function svc(): BrandTrialService
    {
        return app(BrandTrialService::class);
    }

    public function test_start_needs_the_notice_a_verified_email_and_charges_nothing(): void
    {
        $plan = $this->plan();
        $o = $this->owner(50);
        try {
            $this->svc()->start($o, $plan, false);
            $this->fail('notice not enforced');
        } catch (\DomainException) {
        }
        $unverified = User::factory()->create(['email_verified_at' => null]);
        $this->assertSame('email_unverified', $this->svc()->blocker($unverified));

        $sub = $this->svc()->start($o, $plan, true);
        $this->assertSame(BrandSubscription::TRIALING, $sub->status);
        $this->assertSame(50.0, $this->balance($o));
        $this->assertTrue($sub->brandPartner->isNaaraPro());
        $this->assertTrue($sub->trial_ends_at->equalTo(now()->addDays(3)));
    }

    public function test_off_by_default_switch(): void
    {
        Setting::setValue('brand.trial.enabled', false);
        $this->assertSame('off', $this->svc()->blocker($this->owner()));
    }

    public function test_one_trial_per_person_even_with_a_new_account_or_gmail_dots(): void
    {
        $plan = $this->plan();
        $a = $this->owner(0, 'jane.doe+x@gmail.com');
        $this->svc()->start($a, $plan, true);
        $this->svc()->cancel($a->fresh()->brand ?? BrandPartner::where('owner_user_id', $a->id)->first()->subscription);   // cancelled trial still counts as used
        $b = $this->owner(0, 'janedoe@gmail.com');
        $this->assertSame('trial_used', $this->svc()->blocker($b));
        $this->assertSame('trial_used', $this->svc()->blocker($a->fresh()));
    }

    public function test_funded_trial_converts_once_and_the_monthly_sweep_does_not_double_charge(): void
    {
        $plan = $this->plan(19);
        $o = $this->owner(50);
        $sub = $this->svc()->start($o, $plan, true);

        Carbon::setTestNow(now()->addDays(3)->addMinute());
        $this->assertSame(1, $this->svc()->sweep(now())['converted']);
        $this->assertSame(31.0, $this->balance($o));
        $sub->refresh();
        $this->assertSame(BrandSubscription::ACTIVE, $sub->status);
        $this->assertSame('converted', $sub->end_reason);

        $this->svc()->sweep(now());                       // again: nothing
        $this->svc()->convert($sub, now());              // direct re-call: nothing
        $this->svc()->convertNow($sub);                  // double click: nothing
        Artisan::call('brand-subscriptions:bill');       // the monthly sweep: not due for a month
        $this->assertSame(31.0, $this->balance($o));
    }

    public function test_unfunded_trial_pauses_retries_then_closes_and_never_charges(): void
    {
        $plan = $this->plan(19);
        $o = $this->owner(5);
        $sub = $this->svc()->start($o, $plan, true);
        $brand = $sub->brandPartner;

        Carbon::setTestNow(now()->addDays(3)->addMinute());
        $this->svc()->sweep(now());
        $sub->refresh();
        $this->assertSame(BrandSubscription::PAST_DUE, $sub->status);
        $this->assertNull($sub->next_billing_at);
        $this->assertSame(BrandPartner::STATUS_PAUSED, $brand->fresh()->listing_status);
        $this->assertFalse($brand->fresh()->isNaaraPro());
        $this->assertSame(5.0, $this->balance($o));

        Artisan::call('brand-subscriptions:bill');
        $this->assertSame(5.0, $this->balance($o));       // the monthly sweep ignores it

        for ($i = 1; $i <= 3; $i++) {
            Carbon::setTestNow(now()->addDays(2)->addMinute());
            $this->svc()->sweep(now());
        }
        $this->assertSame(BrandSubscription::CANCELLED, $sub->fresh()->status);
        $this->assertSame('unfunded', $sub->fresh()->end_reason);
        $this->assertSame(BrandPartner::STATUS_DISABLED, $brand->fresh()->listing_status);
        $this->assertSame(5.0, $this->balance($o));
    }

    public function test_funding_during_retries_converts_on_the_next_retry_or_on_reactivate(): void
    {
        $plan = $this->plan(19);
        $o = $this->owner(0);
        $sub = $this->svc()->start($o, $plan, true);
        Carbon::setTestNow(now()->addDays(3)->addMinute());
        $this->svc()->sweep(now());
        $this->assertSame(BrandSubscription::PAST_DUE, $sub->fresh()->status);

        app(WalletService::class)->credit($o, 30, 'USD');
        Cache::flush();
        $this->assertTrue($this->svc()->reactivate($sub->fresh()));
        $this->assertSame(11.0, $this->balance($o));
        $this->assertSame(BrandSubscription::ACTIVE, $sub->fresh()->status);
        $this->assertFalse($this->svc()->convert($sub->fresh(), now()));
        $this->assertSame(11.0, $this->balance($o));
    }

    public function test_reactivate_is_throttled(): void
    {
        $plan = $this->plan(19);
        $o = $this->owner(0);
        $sub = $this->svc()->start($o, $plan, true);
        Carbon::setTestNow(now()->addDays(3)->addMinute());
        $this->svc()->sweep(now());
        Cache::flush();
        $this->assertFalse($this->svc()->reactivate($sub->fresh()));
        $this->expectException(\DomainException::class);
        $this->svc()->reactivate($sub->fresh());
    }

    public function test_cancel_during_trial_never_charges_and_admin_grant_stops_conversion(): void
    {
        $plan = $this->plan(19);
        $o = $this->owner(50);
        $sub = $this->svc()->start($o, $plan, true);
        $this->svc()->cancel($sub);
        Carbon::setTestNow(now()->addDays(4));
        $this->svc()->sweep(now());
        $this->assertSame(50.0, $this->balance($o));
        $this->assertSame(BrandSubscription::CANCELLED, $sub->fresh()->status);

        $o2 = $this->owner(50, 'two@example.test');
        $sub2 = $this->svc()->start($o2, $plan, true);
        $admin = User::factory()->create();
        app(\App\Services\Brands\BrandSubscriptionService::class)->grantPro($o2, $plan, $admin);
        Carbon::setTestNow(now()->addDays(4));
        $this->svc()->sweep(now());
        $this->assertSame(50.0, $this->balance($o2));
        $this->assertSame('admin_grant', $sub2->fresh()->end_reason);
        $this->assertTrue(BrandPartner::where('owner_user_id', $o2->id)->first()->isNaaraPro());
    }

    public function test_reminders_go_out_once_each_at_48_24_and_6_hours(): void
    {
        $plan = $this->plan(19);
        $o = $this->owner(50);
        $sub = $this->svc()->start($o, $plan, true);
        Carbon::setTestNow($sub->trial_ends_at->copy()->subHours(47));
        $this->assertSame(1, $this->svc()->sweep(now())['reminded']);
        $this->assertSame(0, $this->svc()->sweep(now())['reminded']);
        Carbon::setTestNow($sub->trial_ends_at->copy()->subHours(5));
        $this->assertSame(1, $this->svc()->sweep(now())['reminded']);   // jumps to the 6h stage (reminders_sent -> 3)
        $this->assertSame(0, $this->svc()->sweep(now())['reminded']);
    }

    public function test_get_listed_offers_the_trial_and_starting_needs_the_tick(): void
    {
        $plan = $this->plan(19);
        $o = $this->owner(0);
        \Livewire\Livewire::actingAs($o)->test(\App\Livewire\GetListed::class)
            ->assertSee('Try free for 3 days')
            ->call('askTrial', $plan->id)
            ->call('startTrial')
            ->assertSet('error', 'Please confirm you have read how the trial ends.')
            ->set('trialAccept', true)
            ->call('startTrial')
            ->assertRedirect(route('brand.manage'));
        $this->assertSame(1, BrandSubscription::where('status', 'trialing')->count());
    }

    public function test_brand_manage_shows_the_trial_and_unfunded_banners(): void
    {
        $plan = $this->plan(19);
        $o = $this->owner(0);
        $sub = $this->svc()->start($o, $plan, true);
        \Livewire\Livewire::actingAs($o)->test(\App\Livewire\BrandManage::class)->assertSee('Free trial of Starter');
        Carbon::setTestNow(now()->addDays(3)->addMinute());
        $this->svc()->sweep(now());
        \Livewire\Livewire::actingAs($o)->test(\App\Livewire\BrandManage::class)->assertSee('could not cover Starter')->assertSee('Reactivate');
    }

    public function test_admin_page_is_admin_only_and_saves_settings(): void
    {
        $this->seed(\Database\Seeders\RoleSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        \Livewire\Livewire::actingAs($admin)->test(\App\Livewire\Admin\BrandTrials::class)
            ->set('enabled', false)->set('days', '5')->call('save')->assertHasNoErrors();
        $this->assertSame(5, BrandTrialService::days());
        $user = User::factory()->create();
        $user->assignRole('user');
        \Livewire\Livewire::actingAs($user)->test(\App\Livewire\Admin\BrandTrials::class)->assertStatus(404);
    }

    public function test_verify_check_passes_on_a_healthy_trial_and_flags_an_overdue_one(): void
    {
        $o = $this->owner(50);
        $sub = $this->svc()->start($o, $this->plan(19), true);
        $this->assertSame('pass', (new \App\Support\Verify\Checks\BrandTrialsSound)->run()->status);
        Carbon::setTestNow(now()->addDays(4));
        $this->assertNotSame('pass', (new \App\Support\Verify\Checks\BrandTrialsSound)->run()->status);
    }
}
