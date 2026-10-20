<?php

namespace Tests\Feature;

use App\Models\BrandPartner;
use App\Models\BrandPartnerFollower;
use App\Models\ConnectionIntent;
use App\Models\User;
use App\Services\Brands\ConnectionIntentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Brand-Growth Phase 6: the dashboard connect popup is shown at most 3 times, a day apart, and never for a brand already followed or no longer listed. */
class ConnectPromptCadenceTest extends TestCase
{
    use RefreshDatabase;

    private function intent(User $fan, array $brandOver = []): ConnectionIntent
    {
        $owner = User::factory()->create();
        $b = BrandPartner::create($brandOver + ['owner_user_id' => $owner->id, 'brand_name' => 'Ada Bakes', 'short_description' => 'Bread.', 'category' => 'Food', 'listing_status' => BrandPartner::STATUS_ACTIVE, 'background_color' => '#0A6E6E', 'is_active' => true]);

        return ConnectionIntent::create(['user_id' => $fan->id, 'brand_partner_id' => $b->id, 'source' => 'card', 'status' => ConnectionIntent::PENDING, 'prompted_at' => now()]);
    }

    public function test_it_shows_three_times_a_day_apart_then_stops(): void
    {
        $fan = User::factory()->create();
        $i = $this->intent($fan);
        $svc = app(ConnectionIntentService::class);

        $this->assertSame($i->id, $svc->nextPopup($fan, false)->id);
        $this->assertSame(0, $i->fresh()->popup_count, "peeking never counts");
        $this->assertSame($i->id, $svc->nextPopup($fan)->id);
        $this->assertNull($svc->nextPopup($fan), 'a reload inside the gap never repeats it');
        $this->travel(25)->hours();
        $this->assertNotNull($svc->nextPopup($fan));
        $this->travel(25)->hours();
        $this->assertNotNull($svc->nextPopup($fan));
        $this->assertSame(3, $i->fresh()->popup_count);
        $this->travel(25)->hours();
        $this->assertNull($svc->nextPopup($fan), 'three is the ceiling');
        $svc->notNow($fan, $i->id);
        $this->assertSame(ConnectionIntent::DISMISSED, $i->fresh()->status);
    }

    public function test_not_now_early_keeps_it_for_tomorrow(): void
    {
        $fan = User::factory()->create();
        $i = $this->intent($fan);
        $svc = app(ConnectionIntentService::class);
        $svc->nextPopup($fan);
        $svc->notNow($fan, $i->id);
        $this->assertSame(ConnectionIntent::PENDING, $i->fresh()->status);
    }

    public function test_already_following_or_unlisted_brands_are_settled_not_asked(): void
    {
        $fan = User::factory()->create();
        $followed = $this->intent($fan);
        BrandPartnerFollower::create(['user_id' => $fan->id, 'brand_partner_id' => $followed->brand_partner_id]);
        $this->assertNull(app(ConnectionIntentService::class)->nextPopup($fan));
        $this->assertSame(ConnectionIntent::CONNECTED, $followed->fresh()->status);

        $other = User::factory()->create();
        $paused = $this->intent($other, ['listing_status' => BrandPartner::STATUS_PAUSED]);
        $this->assertNull(app(ConnectionIntentService::class)->nextPopup($other));
        $this->assertSame(ConnectionIntent::DISMISSED, $paused->fresh()->status);
    }

    public function test_the_dashboard_popup_renders_and_not_now_closes_it(): void
    {
        $fan = User::factory()->create();
        $this->intent($fan);
        $c = Livewire::actingAs($fan)->test(\App\Livewire\ConnectPromptPopup::class)->assertSee('Connect with Ada Bakes')->assertSee('Continue')->assertSee('Not now');
        $c->call('notNow')->assertDontSee('Connect with Ada Bakes');
        Livewire::actingAs(User::factory()->create())->test(\App\Livewire\ConnectPromptPopup::class)->assertDontSee('Connect with');
    }
}
