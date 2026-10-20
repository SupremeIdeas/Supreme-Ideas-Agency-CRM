<?php

namespace Tests\Feature;

use App\Livewire\Admin\SocialHunt;
use App\Livewire\BrandHunt;
use App\Models\SocialFollowClaim;
use App\Models\SocialFollowHandle;
use App\Models\User;
use App\Services\Credits\CreditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * BUILD-6 §C: the Hunt page grants a one-time follow reward server-side, and the
 * admin can manage handles. The reward is only revealed after the claim.
 */
class BrandHuntTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        \App\Models\Setting::setValue('brand.follow_reward.enabled', false);   // these tests are about credit logic; the gate has its own test (FollowRewardGateTest)
    }

    public function test_following_a_handle_grants_the_reward_once_via_the_component(): void
    {
        $user = User::factory()->create();
        $handle = SocialFollowHandle::create([
            'platform' => 'instagram', 'handle_label' => 'Naara', 'handle_url' => 'https://instagram.com/naara',
            'credit_reward' => 8.0, 'verification' => 'self', 'is_active' => true, 'sort_order' => 1,
        ]);

        Livewire::actingAs($user)->test(BrandHunt::class)
            ->call('followHandle', $handle->id)
            ->assertSee('8'); // reward revealed in the flash only after claiming

        $this->assertSame(8.0, app(CreditService::class)->balance($user->fresh()));

        // A second claim never double-grants.
        Livewire::actingAs($user)->test(BrandHunt::class)->call('followHandle', $handle->id);
        $this->assertSame(8.0, app(CreditService::class)->balance($user->fresh()));
        $this->assertSame(1, SocialFollowClaim::where('user_id', $user->id)->count());
    }

    public function test_the_hunt_page_never_shows_the_reward_amount_before_following(): void
    {
        $user = User::factory()->create();
        SocialFollowHandle::create([
            'platform' => 'x', 'handle_label' => 'Naara X', 'handle_url' => 'https://x.com/naara',
            'credit_reward' => 12.34, 'verification' => 'self', 'is_active' => true, 'sort_order' => 1,
        ]);

        Livewire::actingAs($user)->test(BrandHunt::class)
            ->assertSee('Naara X')
            ->assertDontSee('12.34'); // surprise — hidden until claimed
    }

    public function test_admin_can_add_a_platform_handle(): void
    {
        Role::findOrCreate('admin');
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        Livewire::actingAs($admin)->test(SocialHunt::class)
            ->set('handle.platform', 'youtube')
            ->set('handle.handle_label', 'Naara TV')
            ->set('handle.handle_url', 'https://youtube.com/@naara')
            ->set('handle.credit_reward', 10)
            ->set('handle.verification', 'self')
            ->call('addHandle')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('social_follow_handles', ['handle_label' => 'Naara TV', 'platform' => 'youtube']);
    }

    public function test_the_hunt_page_requires_auth(): void
    {
        $this->get(route('rewards.hunt'))->assertRedirect();
    }

    public function test_a_fully_followed_and_connected_brand_moves_to_the_followed_tab(): void
    {
        $user = User::factory()->create();
        $brand = \App\Models\BrandPartner::create(['brand_name' => 'Zeta Foods', 'background_color' => '#123456', 'sort_order' => 1, 'is_active' => true, 'listing_status' => \App\Models\BrandPartner::STATUS_ACTIVE]);
        $h = \App\Models\BrandPartnerHandle::create(['brand_partner_id' => $brand->id, 'platform' => 'instagram', 'handle_label' => '@zeta', 'handle_url' => 'https://instagram.com/zeta', 'credit_reward' => 3.0, 'verification' => 'self', 'is_active' => true, 'sort_order' => 1]);

        $c = Livewire::actingAs($user)->test(BrandHunt::class)->assertSee('Zeta Foods');
        $c->call('followBrandHandle', $h->id)->assertSee('Zeta Foods');   // claimed but not connected: stays in Discover
        $c->call('toggleConnect', $brand->id)->assertDontSeeHtml('wire:key="brand-'.$brand->id.'"')->assertSee('Followed (1)');
        $c->call('setTab', 'followed')->assertSeeHtml('wire:key="brand-'.$brand->id.'"');
    }

    public function test_a_claimed_follow_button_is_not_clickable(): void
    {
        $user = User::factory()->create();
        $handle = SocialFollowHandle::create(['platform' => 'instagram', 'handle_label' => 'Naara', 'handle_url' => 'https://instagram.com/naara', 'credit_reward' => 2.0, 'verification' => 'self', 'is_active' => true, 'sort_order' => 1]);
        Livewire::actingAs($user)->test(BrandHunt::class)->call('followHandle', $handle->id)->assertSeeHtml('aria-disabled="true"')->assertDontSeeHtml('wire:click="followHandle('.$handle->id.')"');
    }
}
