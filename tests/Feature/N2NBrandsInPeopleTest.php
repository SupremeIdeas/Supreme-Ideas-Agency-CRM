<?php

namespace Tests\Feature;

use App\Livewire\BrandManage;
use App\Livewire\BrandProfile;
use App\Livewire\N2N\People;
use App\Models\BrandPartner;
use App\Models\BrandPartnerHandle;
use App\Models\BrandPartnerFollower;
use App\Models\N2N\BrandProfileVisit;
use App\Models\N2N\Follow;
use App\Models\User;
use App\Services\N2N\Brands\BrandVisitService;
use App\Services\N2N\Messaging\Privacy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Brands inside Naara Chat People: directory tab, connect + nudge, chat switch, profile visitors. MASTER-ONLY. */
class N2NBrandsInPeopleTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): array
    {
        $owner = User::factory()->create(['is_active' => true, 'email_verified_at' => now(), 'naara_id' => 'NR-OWNER1']);
        $brand = BrandPartner::create(['owner_user_id' => $owner->id, 'brand_name' => 'Kora Beats', 'category' => 'Music', 'short_description' => 'Afro house label',
            'listing_status' => BrandPartner::STATUS_ACTIVE, 'is_active' => true, 'priority_score' => 1]);
        BrandPartnerHandle::create(['brand_partner_id' => $brand->id, 'platform' => 'instagram', 'handle_label' => '@korabeats', 'handle_url' => 'https://instagram.com/korabeats', 'is_active' => true, 'sort_order' => 1]);

        return [$owner, $brand];
    }

    private function user(): User
    {
        return User::factory()->create(['is_active' => true, 'email_verified_at' => now()]);
    }

    public function test_the_brands_tab_lists_brands_and_connecting_nudges_to_follow_their_pages(): void
    {
        [$owner, $brand] = $this->owner();
        $me = $this->user();
        Livewire::actingAs($me)->test(People::class)->call('setTab', 'brands')
            ->assertSee('Kora Beats')->assertSee('after you connect')
            ->call('toggleBrand', $brand->id)->assertSee('Connected');
        $this->assertTrue(BrandPartnerFollower::where('user_id', $me->id)->where('brand_partner_id', $brand->id)->exists());
        $this->assertDatabaseHas('notifications', ['notifiable_id' => $me->id]);
        Livewire::actingAs($me)->test(People::class)->call('setTab', 'brands')->call('setBrandTab', 'rewards')->assertSee('Follow 1 page');
        Livewire::actingAs($me)->test(People::class)->call('setTab', 'brands')->call('setBrandTab', 'following')->assertSee('Kora Beats');
    }

    public function test_a_brand_with_chat_for_connections_only_blocks_strangers_until_they_follow_each_other(): void
    {
        [$owner, $brand] = $this->owner();
        $brand->forceFill(['chat_mode' => 'connections'])->save();
        $stranger = $this->user();
        $this->assertFalse(Privacy::brandAllows($brand->fresh(), $stranger));
        $this->assertSame('drop', Privacy::route($stranger, $owner));

        BrandPartnerFollower::create(['brand_partner_id' => $brand->id, 'user_id' => $stranger->id]);
        $this->assertFalse(Privacy::brandAllows($brand->fresh(), $stranger), 'following alone is not a connection');
        Follow::create(['follower_id' => $owner->id, 'followed_id' => $stranger->id, 'created_at' => now()]);
        $this->assertTrue(Privacy::brandAllows($brand->fresh(), $stranger));
        $this->assertSame('deliver', Privacy::route($stranger, $owner));

        $brand->forceFill(['chat_mode' => 'open'])->save();
        $this->assertTrue(Privacy::brandAllows($brand->fresh(), $this->user()));
    }

    public function test_the_owner_can_switch_chat_mode_and_sees_visitors(): void
    {
        [$owner, $brand] = $this->owner();
        $component = Livewire::actingAs($owner)->test(BrandManage::class)->set('chatMode', 'connections')->call('saveChatMode');
        $this->assertSame('connections', $brand->fresh()->chat_mode);
        $component->assertSee('Profile visitors');
    }

    public function test_visits_count_once_per_person_per_day_and_skip_the_owner_and_bots(): void
    {
        [$owner, $brand] = $this->owner();
        $svc = app(BrandVisitService::class);
        $a = $this->user();
        $b = $this->user();
        $b->forceFill(['n2n_private_visits' => true])->save();
        $req = fn (string $ua) => \Illuminate\Http\Request::create('/x', 'GET', [], [], [], ['HTTP_USER_AGENT' => $ua, 'REMOTE_ADDR' => '10.0.0.5']);
        $ua = 'Mozilla/5.0 (Linux; Android 14) Chrome/120 Mobile Safari/537.36';

        $this->assertTrue($svc->record($brand, $a, $req($ua)));
        $svc->record($brand, $a, $req($ua));
        $svc->record($brand, $b, $req($ua));
        $svc->record($brand, null, $req($ua));
        $this->assertFalse($svc->record($brand, $owner, $req($ua)), 'the owner is not counted');
        $this->assertFalse($svc->record($brand, null, $req('Googlebot/2.1')), 'crawlers are not counted');

        $s = $svc->stats($brand);
        $this->assertSame(3, $s['last24h']);
        $this->assertSame(3, $s['today']);
        $this->assertSame(2, $s['members24h']);
        $this->assertSame(1, $s['guests24h']);
        $this->assertSame(4, $s['views24h']);
        $names = array_column($svc->recentMembers($brand), 'name');
        $this->assertContains($a->name, $names);
        $this->assertContains('A private visitor', $names);
        $this->assertNotContains($b->name, $names);

        BrandProfileVisit::query()->update(['visited_on' => now()->subDays(120)->toDateString(), 'last_at' => now()->subDays(120)]);
        $this->assertSame(3, $svc->prune());
    }

    public function test_opening_a_brand_profile_records_a_visit(): void
    {
        [$owner, $brand] = $this->owner();
        $me = $this->user();
        $this->withHeaders(['User-Agent' => 'Mozilla/5.0 Chrome/120'])->actingAs($me);
        Livewire::actingAs($me)->withHeaders(['User-Agent' => 'Mozilla/5.0 Chrome/120'])->test(BrandProfile::class, ['brandPartner' => $brand])->assertSee('Message');
        $this->assertSame(1, BrandProfileVisit::where('brand_partner_id', $brand->id)->count());
    }

    public function test_following_a_brand_page_tells_the_brand_owner(): void
    {
        [$owner, $brand] = $this->owner();
        $me = $this->user();
        $handle = BrandPartnerHandle::where('brand_partner_id', $brand->id)->first();
        app(\App\Services\N2N\Brands\BrandFollowNudges::class)->afterHandleFollowed($me, $handle);
        $this->assertDatabaseHas('notifications', ['notifiable_id' => $owner->id]);
    }

    public function test_quick_connect_strip_and_connect_and_message_in_one_tap(): void
    {
        [$owner, $brand] = $this->owner();
        $me = $this->user();
        Livewire::actingAs($me)->test(People::class)->set('tab', 'brands')->assertSee('Quick connect')->assertSee('Connect and message')
            ->call('connectAndMessage', $brand->id)->assertRedirect(route('n2n.chat', ['to' => 'NR-OWNER1']));
        $this->assertTrue(BrandPartnerFollower::where('user_id', $me->id)->where('brand_partner_id', $brand->id)->exists());
        // once connected the strip no longer offers that brand
        Livewire::actingAs($me)->test(People::class)->set('tab', 'brands')->assertDontSee('Quick connect');
    }

    public function test_connect_and_message_still_connects_when_the_brand_keeps_chat_for_connections(): void
    {
        [$owner, $brand] = $this->owner();
        $brand->forceFill(['chat_mode' => 'connections'])->save();
        $me = $this->user();
        Livewire::actingAs($me)->test(People::class)->set('tab', 'brands')->call('connectAndMessage', $brand->id)->assertNoRedirect();
        $this->assertTrue(BrandPartnerFollower::where('user_id', $me->id)->where('brand_partner_id', $brand->id)->exists());
    }

    public function test_the_tab_can_be_opened_from_a_link(): void
    {
        Livewire::actingAs($this->user())->withQueryParams(['tab' => 'brands'])->test(People::class)->assertSet('tab', 'brands')->assertSee('data-brands-panel', false);
    }
}
