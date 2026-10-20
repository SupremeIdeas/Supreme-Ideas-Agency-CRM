<?php

namespace Tests\Feature\Trustpilot;

use App\Models\Setting;
use App\Support\Trustpilot\TrustpilotSettings as TP;
use App\Support\Verify\Checks\TrustpilotSound;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrustpilotDisplayTest extends TestCase
{
    use RefreshDatabase;

    private function turnOn(array $extra = []): void
    {
        TP::put('enabled', true);
        TP::put('business_unit_id', '4f3e2d1c0b9a8f7e6d5c4b3a');
        TP::put('profile_url', 'https://www.trustpilot.com/review/example.com');
        foreach ($extra as $k => $v) {
            TP::put($k, $v);
        }
    }

    public function test_off_by_default_renders_nothing_and_page_is_404(): void
    {
        $this->get('/reviews')->assertNotFound();
        $this->get('/login')->assertOk()->assertDontSee('trustpilot', false);
        $this->assertSame('pass', (new TrustpilotSound)->run()->status);
    }

    public function test_on_with_placement_renders_profile_link_and_adds_csp_origins(): void
    {
        $this->turnOn(['placements' => ['auth.badge' => true]]);
        $res = $this->get('/login')->assertOk();
        $res->assertSee('See our reviews on Trustpilot', false);
        $csp = (string) $res->headers->get('Content-Security-Policy');
        if ($csp !== '') {
            $this->assertStringContainsString('https://widget.trustpilot.com', $csp);
        }
    }

    public function test_template_id_renders_official_trustbox_with_business_unit(): void
    {
        $this->turnOn(['placements' => ['auth.badge' => true], 'templates' => ['micro_combo' => '5419b6a8b0d04a076446a9ad']]);
        $this->get('/login')->assertSee('data-template-id="5419b6a8b0d04a076446a9ad"', false)->assertSee('data-businessunit-id="4f3e2d1c0b9a8f7e6d5c4b3a"', false);
    }

    public function test_reviews_page_needs_the_page_switch(): void
    {
        $this->turnOn();
        $this->get('/reviews')->assertNotFound();
        TP::put('page_enabled', true);
        $this->get('/reviews')->assertOk()->assertSee('trustpilot.com/review/example.com', false);
    }

    public function test_verify_fails_when_on_without_business_unit(): void
    {
        TP::put('enabled', true);
        $this->assertSame('fail', (new TrustpilotSound)->run()->status);
    }

    public function test_admin_panel_saves_settings(): void
    {
        $this->seed(\Database\Seeders\RoleSeeder::class);
        $admin = \App\Models\User::factory()->create();
        $admin->assignRole('admin');
        \Livewire\Livewire::actingAs($admin)->test(\App\Livewire\Admin\TrustpilotPanel::class)
            ->set('enabled', true)->set('businessUnitId', '4f3e2d1c0b9a8f7e6d5c4b3a')->call('save')->assertHasNoErrors();
        $this->assertTrue(TP::enabled());
        $this->assertSame('4f3e2d1c0b9a8f7e6d5c4b3a', TP::businessUnitId());
    }

    public function test_master_defaults_to_the_naara_profile_and_a_saved_link_wins(): void
    {
        $this->assertSame('https://www.trustpilot.com/review/naara.app', TP::profileUrl());
        TP::put('profile_url', 'https://www.trustpilot.com/review/example.com');
        $this->assertSame('https://www.trustpilot.com/review/example.com', TP::profileUrl());
    }

    public function test_invite_script_loads_only_when_on_with_a_key_and_not_behind_click_to_load(): void
    {
        $this->get('/login')->assertDontSee('invitejs.trustpilot.com', false);          // layer off
        $this->turnOn();
        $res = $this->get('/login')->assertSee('https://invitejs.trustpilot.com/tp.min.js', false)->assertSee("tp('register', \"A5H7RxzB3RWdbggz\")", false);
        $csp = (string) $res->headers->get('Content-Security-Policy');
        if ($csp !== '') {
            $this->assertStringContainsString('https://invitejs.trustpilot.com', $csp);
        }
        TP::put('click_to_load', true);
        $this->get('/login')->assertDontSee('invitejs.trustpilot.com', false);          // never before consent
        TP::put('click_to_load', false);
        TP::put('integration_key', 'bad key!');
        $this->assertSame('', TP::integrationKey(), 'an invalid saved key never reaches the page');
        TP::put('integration_key', 'ZZZZ1111yyyy2222');
        $this->get('/login')->assertSee('ZZZZ1111yyyy2222', false);
    }

    private function adminPanel()
    {
        $this->seed(\Database\Seeders\RoleSeeder::class);
        $admin = \App\Models\User::factory()->create();
        $admin->assignRole('admin');

        return \Livewire\Livewire::actingAs($admin)->test(\App\Livewire\Admin\TrustpilotPanel::class);
    }

    public function test_the_panel_shows_the_preset_key_and_an_admin_key_replaces_it_everywhere(): void
    {
        $this->turnOn();
        $this->adminPanel()->assertSet('integrationKey', 'A5H7RxzB3RWdbggz')
            ->set('integrationKey', 'NewKey12345678')->call('save')->assertHasNoErrors();
        $this->assertSame('NewKey12345678', TP::integrationKey());
        auth()->logout();
        $this->get('/login')->assertSee("tp('register', \"NewKey12345678\")", false)->assertDontSee('A5H7RxzB3RWdbggz', false);
    }

    public function test_a_bad_key_is_rejected_by_the_panel_and_never_saved(): void
    {
        $this->adminPanel()->set('integrationKey', "x'); alert(1);//")->call('save')->assertHasErrors(['integrationKey']);
        $this->adminPanel()->set('integrationKey', 'short')->call('save')->assertHasErrors(['integrationKey']);
        $this->assertSame('A5H7RxzB3RWdbggz', TP::integrationKey(), 'nothing invalid was stored');
    }

    public function test_clearing_the_key_on_master_returns_to_the_preset_and_the_script_follows_the_layer_switch(): void
    {
        $this->turnOn();
        TP::put('integration_key', 'CustomKey99999');
        $this->adminPanel()->set('integrationKey', '')->call('save')->assertHasNoErrors();
        $this->assertSame('A5H7RxzB3RWdbggz', TP::integrationKey());
        TP::put('enabled', false);
        auth()->logout();
        $this->get('/login')->assertDontSee('invitejs.trustpilot.com', false);
    }

    public function test_the_script_is_in_the_head_of_public_and_signed_in_pages(): void
    {
        $this->turnOn();
        $html = $this->get('/')->assertOk()->getContent();
        $this->assertLessThan(strpos($html, '</head>'), strpos($html, 'invitejs.trustpilot.com'), 'inside <head>');
        $this->assertSame(1, substr_count($html, "tp('register'"), 'registered exactly once');
        $this->actingAs(\App\Models\User::factory()->create())->get('/account')->assertSee("tp('register'", false);
    }
}
