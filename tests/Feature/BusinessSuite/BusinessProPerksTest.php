<?php

namespace Tests\Feature\BusinessSuite;

use App\Livewire\Account\Appearance;
use App\Models\AppearancePreset;
use App\Models\BrandPartner;
use App\Models\BrandSubscription;
use App\Models\BrandSubscriptionPlan;
use App\Models\Setting;
use App\Models\User;
use App\Services\Brands\BrandSubscriptionService;
use App\Services\Credits\CreditService;
use App\Services\Wallet\WalletService;
use App\Support\Appearance\AppearanceException;
use App\Support\Appearance\AppearanceResolver;
use App\Support\Appearance\UpdateUserAppearance;
use App\Support\Business\BusinessSettings;
use App\Support\Business\BusinessSuiteSettings;
use App\Support\Business\PremiumSkins;
use Illuminate\Support\Facades\Artisan;
use Livewire\Livewire;
use Tests\Feature\Appearance\AppearanceTestCase;

/** What a Naara Pro plan grants outside the Suite itself: premium dashboard skins and a monthly NaaraCredit refill (MASTER-ONLY). */
class BusinessProPerksTest extends AppearanceTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        BusinessSuiteSettings::set(true);
        $this->builtSkins(['calm']);
        AppearancePreset::where('kind', 'skin')->where('key', 'calm')->update(['access' => 'pro', 'enabled' => true]);
        AppearanceResolver::forgetPlatform();
        PremiumSkins::flush();
    }

    private function plan(array $caps, string $name = 'Pro'): BrandSubscriptionPlan
    {
        return BrandSubscriptionPlan::create([
            'name' => $name, 'price_usd_per_month' => 29, 'handles_included' => 1, 'guaranteed_followers_per_handle_per_month' => 10,
            'video_previews_allowed' => 0, 'is_active' => true, 'sort_order' => 1, 'capabilities' => $caps,
        ]);
    }

    private function onPlan(BrandSubscriptionPlan $plan): User
    {
        $u = $this->member();
        app(WalletService::class)->credit($u, 500, 'USD');
        app(BrandSubscriptionService::class)->subscribe($u, $plan);

        return $u;
    }

    private function reserve(bool $on): void
    {
        Setting::setValue(BusinessSettings::ACCESS.'premium_skins', $on, 'business');
        PremiumSkins::flush();
    }

    public function test_premium_skins_stay_open_to_everyone_until_the_admin_reserves_them(): void
    {
        $free = $this->member();
        (app(UpdateUserAppearance::class))($free, ['skin' => 'calm']);
        $this->assertSame('calm', AppearanceResolver::for($free)['skin']);
    }

    public function test_a_reserved_skin_needs_a_plan_that_includes_premium_skins(): void
    {
        $this->reserve(true);
        $free = $this->member();
        $withPlanNoSkins = $this->onPlan($this->plan(['features' => ['workspace']], 'NoSkins'));
        $withSkins = $this->onPlan($this->plan(['features' => [], 'premium_skins' => true], 'Skins'));

        foreach ([$free, $withPlanNoSkins] as $u) {
            try {
                app(UpdateUserAppearance::class)($u, ['skin' => 'calm']);
                $this->fail('A premium skin should be refused without a plan that includes it.');
            } catch (AppearanceException $e) {
                $this->assertStringContainsString('Naara Pro', $e->getMessage());
            }
        }

        app(UpdateUserAppearance::class)($withSkins, ['skin' => 'calm']);
        $this->assertSame('calm', AppearanceResolver::for($withSkins)['skin']);
    }

    public function test_an_admin_is_never_locked_out_and_a_free_skin_is_never_reserved(): void
    {
        $this->reserve(true);
        $admin = $this->admin();
        app(UpdateUserAppearance::class)($admin, ['skin' => 'calm']);
        $this->assertSame('calm', AppearanceResolver::for($admin)['skin']);

        $this->assertNotContains('surface', PremiumSkins::reserved());
        $this->assertContains('calm', PremiumSkins::reserved());
    }

    public function test_a_lapsed_plan_falls_back_to_the_default_skin_and_renewing_brings_it_back(): void
    {
        $u = $this->onPlan($this->plan(['features' => [], 'premium_skins' => true]));
        $this->reserve(true);
        app(UpdateUserAppearance::class)($u, ['skin' => 'calm']);
        $this->assertSame('calm', AppearanceResolver::for($u)['skin']);

        $sub = BrandPartner::where('owner_user_id', $u->id)->firstOrFail()->subscription;
        $sub->forceFill(['status' => BrandSubscription::CANCELLED])->save();
        PremiumSkins::flush();
        AppearanceResolver::forget($u->id);
        $this->assertSame('surface', AppearanceResolver::for($u)['skin']);          // the platform default, nothing deleted
        $this->assertSame('calm', \App\Models\UserAppearance::where('user_id', $u->id)->value('skin_key'));

        $sub->forceFill(['status' => BrandSubscription::ACTIVE])->save();
        PremiumSkins::flush();
        AppearanceResolver::forget($u->id);
        $this->assertSame('calm', AppearanceResolver::for($u)['skin']);
    }

    public function test_the_picker_marks_locked_skins_and_refuses_them(): void
    {
        $this->reserve(true);
        $free = $this->member();

        Livewire::actingAs($free)->test(Appearance::class)->assertSee('Naara Pro')->call('setSkin', 'calm')->assertDispatched('nx-toast');
        $this->assertSame('surface', AppearanceResolver::for($free)['skin']);
    }

    public function test_the_monthly_refill_is_paid_once_per_month_only_while_the_plan_is_active(): void
    {
        $paid = $this->onPlan($this->plan(['features' => [], 'monthly_credits' => 25], 'Refill'));
        $none = $this->onPlan($this->plan(['features' => [], 'monthly_credits' => 0], 'NoRefill'));
        $credits = app(CreditService::class);

        Artisan::call('business:refill-credits');
        Artisan::call('business:refill-credits');   // a second run the same month pays nothing more
        $this->assertSame(25.0, $credits->balance($paid->fresh()));
        $this->assertSame(0.0, $credits->balance($none->fresh()));

        $this->travel(1)->month();
        Artisan::call('business:refill-credits');
        $this->assertSame(50.0, $credits->balance($paid->fresh()));

        // A lapsed plan is not refilled; neither does the switch-off pay anything.
        BrandPartner::where('owner_user_id', $paid->id)->firstOrFail()->subscription->forceFill(['status' => BrandSubscription::CANCELLED])->save();
        $this->travel(1)->month();
        Artisan::call('business:refill-credits');
        $this->assertSame(50.0, $credits->balance($paid->fresh()));
    }

    public function test_the_refill_can_be_switched_off_by_the_admin(): void
    {
        $paid = $this->onPlan($this->plan(['features' => [], 'monthly_credits' => 25], 'Refill'));
        Setting::setValue(BusinessSettings::ACCESS.'credit_refill', false, 'business');

        Artisan::call('business:refill-credits');
        $this->assertSame(0.0, app(CreditService::class)->balance($paid->fresh()));
    }
}
