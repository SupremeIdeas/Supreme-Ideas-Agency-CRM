<?php

namespace Tests\Feature;

use App\Livewire\Admin\BrandDirectory;
use App\Livewire\BrandProfile;
use App\Models\BrandPartner;
use App\Models\BrandPartnerHandle;
use App\Models\BrandSubscription;
use App\Models\BrandSubscriptionPlan;
use App\Models\User;
use App\Services\Brands\BrandSubscriptionService;
use App\Services\Wallet\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/** Naara Pro verified badge: earned by an active paid brand listing or an admin grant; shown on the brand profile and the member's account. */
class NaaraProBadgeTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        Role::findOrCreate('admin');
        $u = User::factory()->create();
        $u->assignRole('admin');

        return $u;
    }

    private function plan(): BrandSubscriptionPlan
    {
        return BrandSubscriptionPlan::create([
            'name' => 'Starter', 'price_usd_per_month' => 19, 'handles_included' => 2,
            'guaranteed_followers_per_handle_per_month' => 50, 'video_previews_allowed' => 1,
            'is_active' => true, 'sort_order' => 1,
        ]);
    }

    private function completeSetup(BrandPartner $brand): void
    {
        // The soft gate (Brand-Growth 3): cover, profile photo, name, description and two social links. Publishing is a separate, deliberate step.
        $brand->forceFill(['category' => 'Fashion', 'fallback_image' => 'https://example.test/x.jpg', 'logo_path' => 'https://example.test/logo.png', 'short_description' => 'Acme makes things.'])->save();
        foreach (['instagram' => '@acme', 'tiktok' => '@acme_ng'] as $i => $label) {
            BrandPartnerHandle::create([
                'brand_partner_id' => $brand->id, 'platform' => $i, 'handle_label' => $label,
                'handle_url' => 'https://'.$i.'.com/'.ltrim($label, '@'), 'credit_reward' => 2, 'verification' => 'self', 'is_active' => true, 'sort_order' => 1,
            ]);
        }
    }

    public function test_subscribing_to_list_a_brand_earns_the_badge_and_cancelling_removes_it(): void
    {
        $owner = User::factory()->create();
        app(WalletService::class)->credit($owner, 50, 'USD');
        $svc = app(BrandSubscriptionService::class);

        $this->assertFalse($owner->hasNaaraPro());

        $sub = $svc->subscribe($owner, $this->plan());
        $this->assertTrue($sub->brandPartner->fresh()->load('subscription')->isNaaraPro());
        $this->assertTrue($owner->fresh()->hasNaaraPro());

        $svc->cancel($sub->brandPartner->fresh());
        $this->assertFalse($owner->fresh()->hasNaaraPro());
    }

    public function test_a_billing_pause_removes_the_badge(): void
    {
        $owner = User::factory()->create();
        app(WalletService::class)->credit($owner, 50, 'USD');
        $sub = app(BrandSubscriptionService::class)->subscribe($owner, $this->plan());

        $sub->forceFill(['status' => BrandSubscription::PAST_DUE])->save();

        $this->assertFalse($owner->fresh()->hasNaaraPro());
    }

    public function test_admin_grants_pro_without_charging_and_billing_never_touches_it(): void
    {
        $admin = $this->admin();
        $member = User::factory()->create(['email' => 'Brand.Owner@Example.test']);
        $plan = $this->plan();

        Livewire::actingAs($admin)->test(BrandDirectory::class)
            ->set('grantEmail', 'brand.owner@example.test')
            ->set('grantPlanId', $plan->id)
            ->set('grantNote', 'partner deal')
            ->call('grantPro')
            ->assertHasNoErrors();

        $brand = BrandPartner::where('owner_user_id', $member->id)->firstOrFail();
        $this->assertTrue($brand->hasProGrant());
        $this->assertSame($admin->id, $brand->pro_granted_by);
        $this->assertSame('partner deal', $brand->pro_grant_note);
        $this->assertSame($plan->id, $brand->current_plan_id);
        $this->assertSame(BrandPartner::STATUS_PENDING, $brand->listing_status);
        $this->assertSame(0, BrandSubscription::count());
        $this->assertTrue($member->fresh()->hasNaaraPro());
        $this->assertDatabaseMissing('wallet_transactions', ['user_id' => $member->id]);

        $this->artisan('brand-subscriptions:bill')->assertSuccessful();
        $this->assertTrue($member->fresh()->hasNaaraPro());
    }

    public function test_a_granted_brand_goes_live_once_setup_is_complete(): void
    {
        $member = User::factory()->create();
        $brand = app(BrandSubscriptionService::class)->grantPro($member, $this->plan(), $this->admin());

        $this->assertFalse(app(BrandSubscriptionService::class)->activateIfComplete($brand));
        $this->completeSetup($brand);
        // Content complete is not enough any more: nothing goes live until the owner presses Publish.
        $this->assertFalse(app(BrandSubscriptionService::class)->activateIfComplete($brand->fresh()));
        \App\Services\Brands\BrandVisibility::publish($brand->fresh());
        $this->assertSame(BrandPartner::STATUS_ACTIVE, $brand->fresh()->listing_status);
    }

    public function test_granting_is_idempotent_and_keeps_a_paying_brands_own_plan(): void
    {
        $owner = User::factory()->create();
        app(WalletService::class)->credit($owner, 50, 'USD');
        $paid = $this->plan();
        $other = BrandSubscriptionPlan::create(['name' => 'Big', 'price_usd_per_month' => 99, 'handles_included' => 9, 'guaranteed_followers_per_handle_per_month' => 0, 'video_previews_allowed' => 3, 'is_active' => true, 'sort_order' => 2]);
        app(BrandSubscriptionService::class)->subscribe($owner, $paid);
        $admin = $this->admin();

        $svc = app(BrandSubscriptionService::class);
        $first = $svc->grantPro($owner, $other, $admin, 'a');
        $second = $svc->grantPro($owner, $other, $admin, 'b');

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, BrandPartner::where('owner_user_id', $owner->id)->count());
        $this->assertSame($paid->id, $second->fresh()->current_plan_id);
        $this->assertSame('b', $second->fresh()->pro_grant_note);
    }

    public function test_revoking_removes_the_badge_and_switches_an_unpaid_listing_off(): void
    {
        $admin = $this->admin();
        $member = User::factory()->create();
        $brand = app(BrandSubscriptionService::class)->grantPro($member, $this->plan(), $admin);

        Livewire::actingAs($admin)->test(BrandDirectory::class)->call('revokePro', $brand->id);

        $brand->refresh();
        $this->assertFalse($brand->hasProGrant());
        $this->assertFalse($member->fresh()->hasNaaraPro());
        $this->assertSame(BrandPartner::STATUS_DISABLED, $brand->listing_status);
    }

    public function test_revoking_a_paying_brands_grant_keeps_it_listed_and_badged(): void
    {
        $admin = $this->admin();
        $owner = User::factory()->create();
        app(WalletService::class)->credit($owner, 50, 'USD');
        $svc = app(BrandSubscriptionService::class);
        $sub = $svc->subscribe($owner, $this->plan());
        $brand = $svc->grantPro($owner, $this->plan(), $admin);
        $brand->forceFill(['listing_status' => BrandPartner::STATUS_ACTIVE])->save();

        $svc->revokePro($brand->fresh(), $admin);

        $this->assertSame(BrandPartner::STATUS_ACTIVE, $brand->fresh()->listing_status);
        $this->assertTrue($owner->fresh()->hasNaaraPro());
    }

    public function test_grant_validates_member_and_plan_and_is_admin_only(): void
    {
        $plan = $this->plan();

        Livewire::actingAs($this->admin())->test(BrandDirectory::class)
            ->set('grantEmail', 'nobody@example.test')->set('grantPlanId', $plan->id)->call('grantPro')
            ->assertHasErrors('grantEmail');

        $plan->update(['is_active' => false]);
        $member = User::factory()->create();
        Livewire::actingAs($this->admin())->test(BrandDirectory::class)
            ->set('grantEmail', $member->email)->set('grantPlanId', $plan->id)->call('grantPro')
            ->assertHasErrors('grantPlanId');
        $this->assertSame(0, BrandPartner::count());

        Livewire::actingAs(User::factory()->create())->test(BrandDirectory::class)->assertStatus(403);
    }

    public function test_the_badge_shows_on_the_public_brand_profile_only_for_pro_brands(): void
    {
        $viewer = User::factory()->create();
        $pro = BrandPartner::create(['owner_user_id' => User::factory()->create()->id, 'brand_name' => 'Gold Co', 'listing_status' => BrandPartner::STATUS_ACTIVE, 'background_color' => '#0A6E6E', 'is_active' => true, 'pro_granted_at' => now()]);
        $plain = BrandPartner::create(['owner_user_id' => User::factory()->create()->id, 'brand_name' => 'Plain Co', 'listing_status' => BrandPartner::STATUS_ACTIVE, 'background_color' => '#0A6E6E', 'is_active' => true]);

        Livewire::actingAs($viewer)->test(BrandProfile::class, ['brandPartner' => $pro])->assertSee('Naara Pro verified', false)->assertSee('Naara Pro');
        Livewire::actingAs($viewer)->test(BrandProfile::class, ['brandPartner' => $plain])->assertDontSee('Naara Pro');
    }

    public function test_the_members_account_page_shows_their_pro_status(): void
    {
        $member = User::factory()->create();
        app(BrandSubscriptionService::class)->grantPro($member, $this->plan(), $this->admin());

        $this->actingAs($member)->get(route('account'))->assertOk()->assertSee('Naara Pro verified');
        $this->actingAs(User::factory()->create())->get(route('account'))->assertOk()->assertDontSee('Naara Pro verified');
    }
}
