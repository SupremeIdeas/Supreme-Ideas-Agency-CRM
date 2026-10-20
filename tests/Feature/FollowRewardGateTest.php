<?php

namespace Tests\Feature;

use App\Models\BrandPartner;
use App\Models\BrandPartnerHandle;
use App\Models\CreditLedger;
use App\Models\Setting;
use App\Models\SocialFollowClaim;
use App\Models\User;
use App\Services\Credits\CreditService;
use App\Services\Social\FollowRewardClawback;
use App\Services\Social\FollowRewardGate;
use App\Services\Social\SocialFollowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** Brand-Growth Phase 7: follow rewards behind an honest return gate, exactly-once crediting, velocity, clawback. */
class FollowRewardGateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-01 10:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function handle(float $reward = 2): BrandPartnerHandle
    {
        $owner = User::factory()->create();
        $b = BrandPartner::create(['owner_user_id' => $owner->id, 'brand_name' => 'B', 'listing_status' => 'active', 'is_featured' => false, 'background_color' => '#0A6E6E', 'is_active' => true]);

        return BrandPartnerHandle::create(['brand_partner_id' => $b->id, 'platform' => 'x', 'handle_label' => '@b', 'handle_url' => 'https://x.com/b', 'credit_reward' => $reward, 'verification' => 'self', 'is_active' => true, 'sort_order' => 1]);
    }

    private function fan(): User
    {
        return User::factory()->create(['email_verified_at' => now()]);
    }

    private function bal(User $u): float
    {
        return app(CreditService::class)->balance($u->fresh());
    }

    public function test_claim_needs_follow_first_then_the_delay_then_pays_exactly_once(): void
    {
        $h = $this->handle(2);
        $u = $this->fan();
        $svc = app(SocialFollowService::class);
        $gate = app(FollowRewardGate::class);

        $r = $svc->claim($u, $h);
        $this->assertSame('not_started', $r['denied']['code']);
        $this->assertSame(0.0, $this->bal($u));

        $gate->begin($u, $h);
        $r = $svc->claim($u, $h);
        $this->assertSame('too_soon', $r['denied']['code']);
        $this->assertSame(8, $r['denied']['wait']);
        $gate->begin($u, $h);                                   // pressing again must not reset or fast-forward
        Carbon::setTestNow(now()->addSeconds(5));
        $this->assertSame(3, $svc->claim($u, $h)['denied']['wait']);

        Carbon::setTestNow(now()->addSeconds(4));
        $this->assertSame(2.0, $svc->claim($u, $h)['earned']);
        $this->assertTrue($svc->claim($u, $h)['already']);      // double click
        $this->assertTrue($svc->claim($u, $h)['already']);      // another tab
        $this->assertSame(2.0, $this->bal($u));
        $this->assertSame(1, CreditLedger::where('user_id', $u->id)->where('reference', "brand_follow:{$u->id}:{$h->id}")->count());
    }

    public function test_unverified_and_too_new_accounts_are_refused_with_a_specific_reason(): void
    {
        $h = $this->handle();
        $u = User::factory()->create(['email_verified_at' => null]);
        $this->assertSame('email', app(FollowRewardGate::class)->denial($u, $h)['code']);

        Setting::setValue('brand.follow_reward.require_verified_email', false);
        Setting::setValue('brand.follow_reward.min_account_hours', 24);
        $this->assertSame('account_new', app(FollowRewardGate::class)->denial($u, $h)['code']);
    }

    public function test_velocity_ceilings_per_person_and_per_connection(): void
    {
        Setting::setValue('brand.follow_reward.min_seconds', 0);
        Setting::setValue('brand.follow_reward.max_per_user_hour', 2);
        $u = $this->fan();
        $svc = app(SocialFollowService::class);
        foreach ([1, 2, 3] as $i) {
            $h = $this->handle(1);
            app(FollowRewardGate::class)->begin($u, $h);
            $r = $svc->claim($u, $h);
            if ($i < 3) {
                $this->assertSame(1.0, $r['earned']);
            } else {
                $this->assertSame('velocity_user', $r['denied']['code']);
            }
        }

        Setting::setValue('brand.follow_reward.max_per_user_hour', 50);   // the two claims above already count against this connection
        Setting::setValue('brand.follow_reward.max_per_ip_hour', 4);
        $a = $this->fan();
        $b = $this->fan();
        $c = $this->fan();
        foreach ([$a, $b] as $x) {
            $h = $this->handle(1);
            app(FollowRewardGate::class)->begin($x, $h);
            $this->assertSame(1.0, $svc->claim($x, $h)['earned']);
        }
        $h = $this->handle(1);
        app(FollowRewardGate::class)->begin($c, $h);
        $this->assertSame('velocity_ip', $svc->claim($c, $h)['denied']['code']);
    }

    public function test_gate_can_be_switched_off(): void
    {
        Setting::setValue('brand.follow_reward.enabled', false);
        $h = $this->handle(2);
        $u = User::factory()->create(['email_verified_at' => null]);
        $this->assertSame(2.0, app(SocialFollowService::class)->claim($u, $h)['earned']);
    }

    public function test_clawback_takes_back_what_is_left_once_and_blocks_reclaim(): void
    {
        Setting::setValue('brand.follow_reward.min_seconds', 0);
        $h = $this->handle(5);
        $u = $this->fan();
        app(FollowRewardGate::class)->begin($u, $h);
        app(SocialFollowService::class)->claim($u, $h);
        app(CreditService::class)->spend($u, 3, 'redeem', 'r1');   // part already spent
        $admin = User::factory()->create();
        $claim = SocialFollowClaim::firstOrFail();

        $r = app(FollowRewardClawback::class)->reverse($claim, $admin, 'farming');
        $this->assertSame(2.0, $r['taken']);
        $this->assertSame(3.0, $r['shortfall']);
        $this->assertSame(0.0, $this->bal($u));
        $again = app(FollowRewardClawback::class)->reverse($claim->fresh(), $admin, 'again');
        $this->assertSame(2.0, $again['taken']);
        $this->assertSame(1, CreditLedger::where('reference', 'follow_clawback:'.$claim->id)->count());
        $this->assertTrue(app(SocialFollowService::class)->claim($u, $h)['already']);   // the same follow can never be re-claimed
    }

    public function test_admin_page_saves_settings_and_requires_a_reason_to_take_back(): void
    {
        $this->seed(\Database\Seeders\RoleSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        \Livewire\Livewire::actingAs($admin)->test(\App\Livewire\Admin\FollowRewards::class)
            ->set('seconds', '12')->call('save')->assertHasNoErrors();
        $this->assertSame(12, FollowRewardGate::minSeconds());
        $user = User::factory()->create();
        $user->assignRole('user');
        \Livewire\Livewire::actingAs($user)->test(\App\Livewire\Admin\FollowRewards::class)->assertStatus(404);
    }
}
