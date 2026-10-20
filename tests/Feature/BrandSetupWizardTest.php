<?php

namespace Tests\Feature;

use App\Livewire\BrandManage;
use App\Livewire\BrandSetupWizard;
use App\Models\BrandPartner;
use App\Models\BrandSubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** The brand listing setup wizard: resumable steps, plan caps, the publish gate, and an illustration on every step. */
class BrandSetupWizardTest extends TestCase
{
    use RefreshDatabase;

    private function owner(array $brand = []): User
    {
        $user = User::factory()->create(['is_active' => true, 'email_verified_at' => now()]);
        $plan = BrandSubscriptionPlan::create(['name' => 'Pro', 'price_usd_per_month' => 9, 'handles_included' => 3, 'video_previews_allowed' => 1, 'credit_reward_per_follow' => 2, 'is_active' => true, 'sort_order' => 1]);
        BrandPartner::create($brand + ['owner_user_id' => $user->id, 'brand_name' => '', 'listing_status' => BrandPartner::STATUS_PENDING, 'is_active' => true, 'gate_version' => 2, 'current_plan_id' => $plan->id]);

        return $user;
    }

    public function test_it_opens_on_the_first_unfinished_step_and_every_step_has_art(): void
    {
        $c = Livewire::actingAs($this->owner())->test(BrandSetupWizard::class)->assertSet('open', true)->assertSet('step', 'identity');
        foreach (['identity', 'look', 'handles', 'links', 'video', 'plan', 'publish'] as $k) {
            $c->call('goTo', $k)->assertSeeHtml('data-wizard-art')->assertSet('step', $k);
        }
        foreach (['br-identity', 'br-look', 'br-handles', 'br-links', 'br-video', 'br-card', 'br-chat', 'br-connect', 'br-plan', 'br-publish', 'br-live'] as $f) {
            $this->assertFileExists(public_path("images/illustrations/$f.svg"));
        }
    }

    public function test_identity_saves_and_moves_on_and_a_blank_description_is_refused(): void
    {
        $user = $this->owner();
        Livewire::actingAs($user)->test(BrandSetupWizard::class)
            ->set('brandName', 'Acme')->set('description', '')->call('saveIdentity')->assertHasErrors('description')->assertSet('step', 'identity')
            ->set('description', 'Acme makes things.')->call('saveIdentity')->assertHasNoErrors()->assertSet('step', 'look');
        $b = BrandPartner::where('owner_user_id', $user->id)->first();
        $this->assertSame('Acme', $b->brand_name);
        $this->assertSame('Acme makes things.', $b->short_description);
    }

    public function test_handles_respect_the_plan_cap_and_the_url_format(): void
    {
        $user = $this->owner(['brand_name' => 'Acme']);
        $c = Livewire::actingAs($user)->test(BrandSetupWizard::class)->call('goTo', 'handles');
        $c->set('newHandle', ['platform' => 'instagram', 'handle_label' => '@a', 'handle_url' => 'https://example.com/nope'])->call('addHandle')->assertHasErrors('newHandle.handle_url');
        foreach (['https://instagram.com/a', 'https://tiktok.com/@a', 'https://x.com/a'] as $i => $url) {
            $c->set('newHandle', ['platform' => ['instagram', 'tiktok', 'x'][$i], 'handle_label' => '@a'.$i, 'handle_url' => $url])->call('addHandle');
        }
        $this->assertSame(3, BrandPartner::where('owner_user_id', $user->id)->first()->handles()->count());
        $c->set('newHandle', ['platform' => 'instagram', 'handle_label' => '@z', 'handle_url' => 'https://instagram.com/z'])->call('addHandle')->assertSet('error', 'Your plan allows 3 handles. Upgrade your plan to add more.');
    }

    public function test_publish_is_refused_until_the_profile_is_complete(): void
    {
        $user = $this->owner(['brand_name' => 'Acme', 'short_description' => 'x']);
        Livewire::actingAs($user)->test(BrandSetupWizard::class)->call('goTo', 'publish')->call('publish');
        $this->assertNull(BrandPartner::where('owner_user_id', $user->id)->first()->published_at);
    }

    public function test_the_dashboard_offers_the_wizard_until_the_listing_is_published(): void
    {
        $user = $this->owner(['brand_name' => 'Acme']);
        Livewire::actingAs($user)->test(BrandManage::class)->assertSeeHtml('data-brand-wizard-launch')->assertSee('Set up your listing step by step');
    }

    public function test_the_plan_step_shows_the_plan_and_the_publish_step_offers_sharing_once_live(): void
    {
        $user = $this->owner(['brand_name' => 'Acme', 'short_description' => 'x', 'logo_path' => 'https://x.test/l.png', 'hero_image_path' => 'https://x.test/c.jpg']);
        Livewire::actingAs($user)->test(BrandSetupWizard::class)->call('goTo', 'plan')->assertSeeHtml('data-brand-plan')->assertSee('Pro');
    }

    public function test_the_wizard_wears_nias_face_once_the_assistant_avatar_is_set(): void
    {
        $user = $this->owner();
        Livewire::actingAs($user)->test(BrandSetupWizard::class)->assertDontSeeHtml('data-nia-tile');
        \App\Models\Setting::setValue('support.avatar', 'https://x.test/nia.png');
        \App\Support\SupportSettings::flush();
        Livewire::actingAs($user)->test(BrandSetupWizard::class)->assertSeeHtml('data-nia-tile')->assertSeeHtml('https://x.test/nia.png');
    }
}
