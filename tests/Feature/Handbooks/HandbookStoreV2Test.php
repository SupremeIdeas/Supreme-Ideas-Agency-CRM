<?php

namespace Tests\Feature\Handbooks;

use App\Livewire\Admin\Handbooks\HandbookManager;
use App\Livewire\Admin\Handbooks\HandbookSettingsPage;
use App\Livewire\Handbooks\Store;
use App\Models\EsimOrder;
use App\Models\EsimPlan;
use App\Models\Handbook;
use App\Models\HandbookEntitlement;
use App\Models\HandbookEsimPick;
use App\Models\User;
use App\Services\Handbooks\HandbookAccess;
use App\Services\Handbooks\HandbookEsimUnlockService;
use App\Services\Handbooks\HandbookPurchaseService;
use App\Services\Handbooks\HandbookException;
use App\Support\Handbooks\HandbookSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Handbook Store v2 (MASTER-ONLY): landing page, directory, admin-controlled unlock rules and per-book switches. */
class HandbookStoreV2Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RoleSeeder::class);
    }

    private function book(string $cc, array $over = []): Handbook
    {
        return Handbook::create($over + ['slug' => strtolower($cc).'-'.uniqid(), 'title' => $cc.' Handbook', 'country_code' => $cc, 'series_slot' => 'S1', 'list_price_usd' => 9.99, 'status' => Handbook::PUBLISHED, 'description' => 'd', 'epub_path' => 'x.epub', 'last_verified_on' => now()->toDateString(), 'published_at' => now()]);
    }

    private function order(array $plan, float $paid = 5): EsimOrder
    {
        $p = EsimPlan::query()->forceCreate($plan + ['provider' => 'esimgo', 'provider_plan_id' => 'p'.uniqid(), 'name' => 'Plan', 'type' => 'data', 'data_mb' => 1024, 'validity_days' => 7, 'cost_price_usd' => 1, 'is_active' => true]);
        $u = User::factory()->create(['email_verified_at' => now(), 'is_active' => true]);

        return EsimOrder::create(['user_id' => $u->id, 'plan_id' => $p->id, 'provider' => 'esimgo', 'status' => 'processing', 'price_charged' => $paid]);
    }

    private function unlock(EsimOrder $o): array
    {
        return app(HandbookEsimUnlockService::class)->onOrder($o->load('plan'));
    }

    public function test_the_landing_page_shows_popular_books_and_a_see_more_button_to_the_directory(): void
    {
        $this->book('GH', ['title' => 'Moving to Ghana', 'is_featured' => true]);
        $this->book('KE');
        $r = $this->get('/handbooks')->assertOk()->assertSee('Moving to Ghana')->assertSee('data-popular-shelf', false);
        $r->assertSee(route('handbooks.directory'), false);
        $r->assertSee('data-cta="see-more"', false);
    }

    public function test_an_empty_store_still_renders_the_landing_page(): void
    {
        $this->get('/handbooks')->assertOk()->assertSee('Know before you go');
        $this->get('/handbooks/all')->assertOk();
    }

    public function test_a_closed_store_shows_coming_soon_and_hides_book_pages(): void
    {
        $b = $this->book('GH');
        HandbookSettings::put('store_enabled', false);
        $this->get('/handbooks')->assertOk()->assertSee('not open right now');
        $this->get('/handbooks/all')->assertOk()->assertSee('not open right now');
        $this->get('/handbooks/'.$b->slug)->assertRedirect(route('handbooks.store'));
    }

    public function test_the_directory_filters_sorts_and_searches(): void
    {
        $this->book('GH', ['title' => 'Alpha Ghana', 'list_price_usd' => 4]);
        $this->book('KE', ['title' => 'Zulu Kenya', 'list_price_usd' => 14]);
        Livewire::test(Store::class)->assertSee('Alpha Ghana')->assertSee('Zulu Kenya')
            ->set('country', 'KE')->assertDontSee('Alpha Ghana')->assertSee('Zulu Kenya')
            ->call('clear')->set('price', '5')->assertSee('Alpha Ghana')->assertDontSee('Zulu Kenya')
            ->call('clear')->set('q', 'zulu')->assertSee('Zulu Kenya')->assertDontSee('Alpha Ghana');
    }

    public function test_the_directory_pages_after_24(): void
    {
        foreach (range(1, 26) as $i) {
            $this->book('GH', ['slug' => 'b'.$i, 'title' => sprintf('Book %02d', $i)]);
        }
        Livewire::test(Store::class)->set('sort', 'title')->assertSee('Book 01')->assertDontSee('Book 25')->call('gotoPage', 2)->assertSee('Book 25');
    }

    public function test_esim_unlock_off_unlocks_nothing(): void
    {
        $this->book('GH');
        HandbookSettings::put('esim_unlock_enabled', false);
        $o = $this->order(['coverage_type' => 'local', 'countries' => ['GH']]);
        $this->assertSame('none', $this->unlock($o)['mode']);
        $this->assertSame(0, HandbookEntitlement::query()->count());
    }

    public function test_coverage_types_and_minimum_price_are_respected(): void
    {
        $this->book('GH');
        HandbookSettings::put('esim_unlock_coverage', ['regional']);
        $this->assertSame('none', $this->unlock($this->order(['coverage_type' => 'local', 'countries' => ['GH']]))['mode']);

        HandbookSettings::put('esim_unlock_coverage', ['local']);
        HandbookSettings::put('esim_min_price_usd', 10);
        $this->assertSame('none', $this->unlock($this->order(['coverage_type' => 'local', 'countries' => ['GH']], 5))['mode']);
        $this->assertSame('unlocked', $this->unlock($this->order(['coverage_type' => 'local', 'countries' => ['GH']], 12))['mode']);
    }

    public function test_a_book_with_its_esim_switch_off_is_never_unlocked_and_extra_countries_work(): void
    {
        $off = $this->book('GH', ['unlock_via_esim' => false]);
        $this->assertSame('none', $this->unlock($this->order(['coverage_type' => 'local', 'countries' => ['GH']]))['mode']);

        $extra = $this->book('KE', ['extra_unlock_countries' => ['GH']]);
        $o = $this->order(['coverage_type' => 'local', 'countries' => ['GH']]);
        $r = $this->unlock($o);
        $this->assertSame('unlocked', $r['mode']);
        $this->assertSame($extra->id, $r['handbook']->id);
        $this->assertNotSame($off->id, $r['handbook']->id);
    }

    public function test_regional_picks_follow_the_admin_number_and_close_after_the_last(): void
    {
        $gh = $this->book('GH');
        $ke = $this->book('KE');
        $this->book('NG');
        HandbookSettings::put('esim_regional_picks', 2);
        $o = $this->order(['coverage_type' => 'regional', 'countries' => ['GH', 'KE']]);
        $this->unlock($o);
        $pick = HandbookEsimPick::query()->firstOrFail();
        $this->assertSame(2, $pick->picks_allowed);
        $u = User::query()->find($o->user_id);
        $svc = app(HandbookEsimUnlockService::class);

        $svc->choose($u, $pick->id, $gh->id);
        $this->assertNull($pick->fresh()->picked_at);
        $this->expectException(HandbookException::class);
        try {
            $svc->choose($u, $pick->id, $gh->id);   // the same book twice is refused
        } catch (HandbookException) {
            $svc->choose($u, $pick->id, $ke->id);
            $this->assertNotNull($pick->fresh()->picked_at);
            $svc->choose($u, $pick->id, $ke->id);   // nothing left to pick
        }
    }

    public function test_purchase_switches_hide_offers_and_block_buying(): void
    {
        $b = $this->book('GH');
        $u = User::factory()->create(['email_verified_at' => now(), 'is_active' => true]);
        $this->assertNotNull(app(HandbookAccess::class)->offers($u, $b)['buy']);

        $b->forceFill(['allow_purchase' => false])->save();
        $this->assertNull(app(HandbookAccess::class)->offers($u, $b->fresh())['buy']);
        $this->expectException(HandbookException::class);
        app(HandbookPurchaseService::class)->quote($u, $b->fresh());
    }

    public function test_the_admin_manager_toggles_switches_and_the_settings_page_saves(): void
    {
        $b = $this->book('GH');
        $a = User::factory()->create(['email_verified_at' => now(), 'is_active' => true]);
        $a->assignRole('super_admin');

        Livewire::actingAs($a)->test(HandbookManager::class)->call('toggle', $b->id, 'is_featured')->call('toggle', $b->id, 'unlock_via_pro');
        $this->assertTrue($b->fresh()->is_featured);
        $this->assertFalse($b->fresh()->unlock_via_pro);

        Livewire::actingAs($a)->test(HandbookSettingsPage::class)->set('proSlots', 5)->set('esimRegionalPicks', 3)->set('esimCoverage', ['local'])->set('heroHeadline', 'Go prepared.')->call('save')->assertHasNoErrors();
        $this->assertSame(5, HandbookSettings::get('pro_slots'));
        $this->assertSame(3, HandbookSettings::get('esim_regional_picks'));
        $this->assertSame(['local'], HandbookSettings::get('esim_unlock_coverage'));
        $this->get('/handbooks')->assertSee('Go prepared.');
    }

    public function test_a_non_admin_cannot_open_the_settings_page(): void
    {
        $this->actingAs(User::factory()->create(['email_verified_at' => now(), 'is_active' => true]))->get(route('admin.handbooks.settings'))->assertNotFound();
    }

    public function test_the_reserved_slug_all_cannot_be_used(): void
    {
        $a = User::factory()->create(['email_verified_at' => now(), 'is_active' => true]);
        $a->assignRole('super_admin');
        Livewire::actingAs($a)->test(\App\Livewire\Admin\Handbooks\HandbookEditor::class)->set('title', 'All')->set('slug', 'all')->set('price', '5')->call('save')->assertHasErrors('slug');
    }

    public function test_the_esim_hero_carries_a_handbook_slide_only_when_on_and_there_are_books(): void
    {
        $render = fn () => view('partials.esim-hero')->render();
        $this->assertStringNotContainsString('data-esim-hero-handbook-cta', $render());
        $this->book('GH');
        \Illuminate\Support\Facades\Cache::forget('handbooks.esim_banner');
        $html = $render();
        $this->assertStringContainsString('data-esim-hero-handbook-cta', $html);
        $this->assertStringContainsString(route('handbooks.store'), $html);

        HandbookSettings::put('esim_banner_enabled', false);
        $this->assertStringNotContainsString('data-esim-hero-handbook-cta', $render());
    }
}
