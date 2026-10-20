<?php

namespace Tests\Feature;

use App\Livewire\BrandSetupWizard;
use App\Models\BrandPartner;
use App\Models\BrandPartnerHandle;
use App\Models\BrandSubscription;
use App\Models\BrandSubscriptionPlan;
use App\Models\Setting;
use App\Models\User;
use App\Services\Wallet\WalletService;
use App\Support\Business\NaaraProCatalogue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** The wizard's plan step (choose a plan, free trial on it, change the plan the trial converts to, subscribe) and the one Naara Pro catalogue behind every comparison. */
class BrandWizardPlanStepTest extends TestCase
{
    use RefreshDatabase;

    private BrandSubscriptionPlan $small;

    private BrandSubscriptionPlan $big;

    private function owner(float $funds = 0): User
    {
        Setting::setValue('brand.trial.enabled', true);
        $this->small = BrandSubscriptionPlan::create(['name' => 'Starter', 'price_usd_per_month' => 19, 'handles_included' => 1, 'guaranteed_followers_per_handle_per_month' => 20, 'video_previews_allowed' => 0, 'credit_reward_per_follow' => 2, 'is_active' => true, 'sort_order' => 1]);
        $this->big = BrandSubscriptionPlan::create(['name' => 'Growth', 'price_usd_per_month' => 49, 'handles_included' => 5, 'guaranteed_followers_per_handle_per_month' => 80, 'video_previews_allowed' => 2, 'credit_reward_per_follow' => 2, 'is_active' => true, 'sort_order' => 2,
            'capabilities' => ['premium_skins' => true, 'monthly_credits' => 500]]);
        $u = User::factory()->create(['is_active' => true, 'email_verified_at' => now()]);
        if ($funds > 0) {
            app(WalletService::class)->credit($u, $funds, 'USD');
        }
        BrandPartner::create(['owner_user_id' => $u->id, 'brand_name' => 'Acme', 'listing_status' => BrandPartner::STATUS_PENDING, 'is_active' => true, 'gate_version' => 2]);

        return $u;
    }

    public function test_the_trial_starts_on_the_chosen_plan_only_after_the_notice_is_ticked(): void
    {
        $u = $this->owner();
        $c = Livewire::actingAs($u)->test(BrandSetupWizard::class)->call('goTo', 'plan')->assertSeeHtml('data-brand-plan')
            ->call('pickPlan', $this->big->id)->assertSeeHtml('data-plan-trial-offer')->assertSee('Try Growth free')
            ->call('startTrial')->assertSet('error', 'Please confirm you have read how the trial ends.');
        $this->assertSame(0, BrandSubscription::count());
        $c->set('trialAccept', true)->call('startTrial')->assertSet('error', null)->assertSeeHtml('data-plan-trial-running');
        $sub = BrandSubscription::first();
        $this->assertSame('trialing', $sub->status);
        $this->assertSame($this->big->id, (int) $sub->convert_to_plan_id);
        $this->assertSame('49.00', number_format((float) $sub->locked_price_usd, 2, '.', ''));
        $this->assertSame(0.0, (float) ($u->fresh()->wallet?->usd_balance ?? 0));   // nothing charged now
    }

    public function test_during_the_trial_the_plan_it_converts_to_can_change_and_the_price_follows(): void
    {
        $u = $this->owner();
        Livewire::actingAs($u)->test(BrandSetupWizard::class)->call('goTo', 'plan')->call('pickPlan', $this->big->id)->set('trialAccept', true)->call('startTrial')
            ->call('pickPlan', $this->small->id)->call('changeTrialPlan');
        $sub = BrandSubscription::first();
        $this->assertSame($this->small->id, (int) $sub->convert_to_plan_id);
        $this->assertSame('19.00', number_format((float) $sub->locked_price_usd, 2, '.', ''));
    }

    public function test_a_plan_that_is_too_small_for_what_is_already_listed_is_refused(): void
    {
        $u = $this->owner();
        $b = BrandPartner::where('owner_user_id', $u->id)->first();
        foreach (['instagram', 'tiktok'] as $i => $p) {
            BrandPartnerHandle::create(['brand_partner_id' => $b->id, 'platform' => $p, 'handle_label' => '@a'.$i, 'handle_url' => "https://$p.com/a$i", 'credit_reward' => 2, 'verification' => 'self', 'is_active' => true, 'sort_order' => $i]);
        }
        Livewire::actingAs($u)->test(BrandSetupWizard::class)->call('goTo', 'plan')->call('pickPlan', $this->small->id)->set('trialAccept', true)->call('startTrial');
        $this->assertSame(0, BrandSubscription::count());
    }

    public function test_subscribing_with_a_short_wallet_says_so_and_charges_nothing(): void
    {
        $u = $this->owner(5);
        Livewire::actingAs($u)->test(BrandSetupWizard::class)->call('goTo', 'plan')->call('pickPlan', $this->big->id)->call('subscribeNow')
            ->assertSet('error', 'Your wallet is short for the first month ($49.00). Top up your wallet, then try again.');
        $this->assertSame(0, BrandSubscription::count());
        $this->assertSame(5.0, (float) $u->fresh()->wallet->usd_balance);
    }

    public function test_subscribing_with_funds_debits_the_plan_price(): void
    {
        $u = $this->owner(60);
        Livewire::actingAs($u)->test(BrandSetupWizard::class)->call('goTo', 'plan')->call('pickPlan', $this->big->id)->call('subscribeNow')->assertSet('error', null);
        $this->assertSame(11.0, (float) $u->fresh()->wallet->usd_balance);
        $this->assertSame('active', BrandSubscription::first()->status);
    }

    public function test_one_catalogue_describes_every_plan_the_same_way_everywhere(): void
    {
        $this->owner();
        $plans = BrandSubscriptionPlan::active()->ordered()->get();
        $bullets = NaaraProCatalogue::bullets($this->big);
        $this->assertContains('Social handles: 5 handles', $bullets);
        $this->assertContains('Premium dashboard skins', $bullets);
        $this->assertContains('Monthly NaaraCredits: 500 every month', $bullets);
        $this->assertNotContains('Premium dashboard skins', NaaraProCatalogue::bullets($this->small));
        $this->assertContains('Video previews: 2 videos', NaaraProCatalogue::delta($this->small, $this->big));

        $ent = NaaraProCatalogue::entitlements($this->small, $plans);
        $locked = collect($ent['locked'])->firstWhere(fn ($l) => $l['cap']['key'] === 'skins');
        $this->assertSame('Growth', $locked['plan']);

        // The public pages render that same catalogue.
        $this->actingAs(User::factory()->create(['is_active' => true, 'email_verified_at' => now()]))->get(route('brand.get-listed'))->assertOk()->assertSeeHtml('data-pro-bullets')->assertSeeHtml('data-pro-compare-wrap')->assertSee('Premium dashboard skins');
    }
}
