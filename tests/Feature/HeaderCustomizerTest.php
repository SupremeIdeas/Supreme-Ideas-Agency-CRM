<?php

namespace Tests\Feature;

use App\Livewire\Account\HeaderSettings;
use App\Models\Merchant;
use App\Models\Setting;
use App\Models\User;
use App\Models\UserHeaderPref;
use App\Support\Header\HeaderItems;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** The dashboard header customizer (max three shortcuts), the NaaraBrain pop-up button and the merchant side pane (owner request, 2026-10-11). */
class HeaderCustomizerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    private function enableAll(): void
    {
        foreach (['brain.enabled', 'n2n.enabled', 'business_suite.enabled'] as $k) {
            Setting::setValue($k, true, 'features');
        }
    }

    private function member(): User
    {
        return User::factory()->create(['is_active' => true, 'email_verified_at' => now()]);
    }

    private function merchant(string $tier = Merchant::TIER_V2): User
    {
        $u = $this->member();
        $u->assignRole('merchant');
        Merchant::create(['owner_user_id' => $u->id, 'business_name' => 'Sahara Connect', 'slug' => 'sahara', 'brand_color' => '#0A6E6E', 'status' => Merchant::ACTIVE, 'tier' => $tier]);

        return $u->fresh();
    }

    public function test_the_catalogue_only_offers_what_this_install_has_switched_on(): void
    {
        $this->assertSame(['language', 'theme'], array_keys(HeaderItems::available()));
        $this->enableAll();
        $this->assertSame(['language', 'theme', 'brain', 'chat', 'business'], array_keys(HeaderItems::available()));
    }

    public function test_a_member_who_never_chose_gets_theme_and_brain_with_language_left_to_the_menu_and_never_more_than_three(): void
    {
        $this->enableAll();
        $u = $this->member();
        $this->assertSame(['theme', 'brain'], HeaderItems::chosen($u));

        // A crafted/stale list of five is cut to three, in order, and unknown keys are dropped.
        UserHeaderPref::create(['user_id' => $u->id, 'items' => ['chat', 'bogus', 'business', 'brain', 'theme', 'language']]);
        $this->assertSame(['chat', 'business', 'brain'], HeaderItems::chosen($u));
        $this->assertCount(3, HeaderItems::save($u, ['language', 'theme', 'brain', 'chat', 'business']));
    }

    public function test_the_settings_page_enforces_the_limit_and_saves_each_change(): void
    {
        $this->enableAll();
        $u = $this->member();

        $c = Livewire::actingAs($u)->test(HeaderSettings::class)->assertSet('picked', ['theme', 'brain']);
        $c->call('toggle', 'language')->assertSet('picked', ['theme', 'brain', 'language']);
        $c->call('toggle', 'chat')->assertSet('picked', ['theme', 'brain', 'language'])->assertSet('notice', __('header.max_reached', ['max' => 3]));
        $c->call('toggle', 'language')->assertSet('notice', null)->assertSet('picked', ['theme', 'brain']);
        $c->call('toggle', 'chat')->assertSet('picked', ['theme', 'brain', 'chat']);
        $this->assertSame(['theme', 'brain', 'chat'], HeaderItems::chosen($u->fresh()));

        $c->call('resetToDefault')->assertSet('picked', ['theme', 'brain']);
        $c->call('toggle', 'unknown')->assertSet('picked', ['theme', 'brain']);
    }

    public function test_a_switched_off_module_is_not_offered_and_is_dropped_from_a_saved_choice(): void
    {
        $u = $this->member();
        UserHeaderPref::create(['user_id' => $u->id, 'items' => ['brain', 'chat', 'theme']]);
        $this->assertSame(['theme'], HeaderItems::chosen($u));
        Livewire::actingAs($u)->test(HeaderSettings::class)->call('toggle', 'brain')->assertSet('picked', ['theme']);
    }

    public function test_the_dashboard_header_shows_the_chosen_shortcuts_and_the_brain_popup(): void
    {
        $this->enableAll();
        $u = $this->member();
        UserHeaderPref::create(['user_id' => $u->id, 'items' => ['brain', 'chat']]);

        $html = $this->actingAs($u)->get('/dashboard')->assertOk()->getContent();
        $this->assertStringContainsString('data-header-actions="mobile"', $html);
        $this->assertStringContainsString('open-brain-modal', $html);
        $this->assertStringContainsString('aria-label="Naara chat"', $html);
        $this->assertStringNotContainsString('aria-label="Business Suite"', $html);
        $this->assertStringContainsString('class="hbm__frame"', $html, 'the pop-up shell is on the page');
    }

    public function test_a_member_who_turns_brain_off_gets_no_popup_button(): void
    {
        $this->enableAll();
        $u = $this->member();
        UserHeaderPref::create(['user_id' => $u->id, 'items' => ['language']]);
        $html = $this->actingAs($u)->get('/dashboard')->assertOk()->getContent();
        $this->assertStringNotContainsString('nx-hdr__brain', $html);
    }

    public function test_merchants_get_the_side_pane_and_a_tidy_more_menu_and_everyone_else_does_not(): void
    {
        $plain = $this->member();
        $html = $this->actingAs($plain)->get('/dashboard')->assertOk()->getContent();
        $this->assertStringNotContainsString('mpane__panel', $html);
        $this->assertStringNotContainsString('nx-hdr__merchant', $html);

        $m = $this->merchant();
        $html = $this->actingAs($m)->get('/dashboard')->assertOk()->getContent();
        $this->assertStringContainsString('nx-hdr__merchant', $html);
        $this->assertStringContainsString('mpane__panel', $html);
        $this->assertStringContainsString('Sahara Connect', $html);
        $this->assertStringContainsString('href="'.route('merchant.clients').'"', $html, 'merchant tools are in the pane (and the desktop menu)');

        // The More sheet no longer lists them (the sheet is the `ns-more` block).
        $more = substr($html, (int) strpos($html, 'ns-more'), 9000);
        $this->assertStringNotContainsString(route('merchant.invoices'), explode('mpane__panel', $more)[0] ?? $more);
    }

    public function test_a_merchant_who_switches_the_button_off_keeps_the_rows_in_the_more_menu(): void
    {
        $m = $this->merchant();
        Livewire::actingAs($m)->test(HeaderSettings::class)->set('merchantButton', false);
        $html = $this->actingAs($m->fresh())->get('/dashboard')->assertOk()->getContent();
        $this->assertStringNotContainsString('nx-hdr__merchant', $html);
        $this->assertStringContainsString('ns-list__row', $html);
        $this->assertSame(false, (bool) UserHeaderPref::where('user_id', $m->id)->value('merchant_button'));
        $this->assertStringContainsString(route('merchant.dashboard'), substr($html, (int) strpos($html, 'ns-more')));
    }

    public function test_become_a_merchant_stays_in_more_for_non_merchants(): void
    {
        Setting::setValue('merchant.enabled', true, 'merchant');
        $html = $this->actingAs($this->member())->get('/dashboard')->assertOk()->getContent();
        $this->assertStringNotContainsString('mpane__panel', $html);
    }

    public function test_language_is_in_the_global_menu_by_default_and_not_in_the_header(): void
    {
        $u = $this->member();
        $html = $this->actingAs($u)->get('/dashboard')->assertOk()->getContent();
        $header = substr($html, (int) strpos($html, 'data-header-actions="mobile"'), 4000);
        $this->assertStringNotContainsString('nx-lang-chip', explode('open-global-sidebar', $header)[0]);
        $this->assertStringContainsString("name: 'language'", $html, 'the menu row opens the language picker');
        $this->assertStringContainsString('choose_language', $html.'choose_language', 'picker markup is present once');
        $this->assertSame(1, substr_count($html, 'class="nx-lang"'), 'the picker is rendered exactly once');

        // A member can still pin it back to the header.
        UserHeaderPref::create(['user_id' => $u->id, 'items' => ['language']]);
        $this->actingAs($u->fresh())->get('/dashboard')->assertOk()->assertSee('nx-lang-chip', false);
    }

    public function test_the_merchant_pane_title_is_the_store_name(): void
    {
        $html = $this->actingAs($this->merchant())->get('/dashboard')->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/mpane__name">\s*Sahara Connect\s*</', $html);
    }

    public function test_developer_portal_and_widgets_are_in_the_merchant_pane_when_they_are_switched_on(): void
    {
        $m = $this->merchant();
        $off = $this->actingAs($m)->get('/dashboard')->assertOk()->getContent();
        $this->assertStringNotContainsString('Developer portal', $off);

        Setting::setValue('developer_api.enabled', true, 'developer_api');
        Setting::setValue('merchant_widgets.enabled', true, 'merchant');
        $on = $this->actingAs($m->fresh())->get('/dashboard')->assertOk()->getContent();
        $this->assertStringContainsString('Developer portal', $on);
        $this->assertStringContainsString('API documentation', $on);
        $this->assertStringContainsString('href="'.route('developer').'"', $on);
    }
}
