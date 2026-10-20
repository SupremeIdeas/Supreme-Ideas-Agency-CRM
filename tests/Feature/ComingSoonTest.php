<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Support\Business\BusinessSuiteSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** One behaviour for every feature switch: a friendly "Coming soon" sheet on a page load, a plain 404 for everything that is not a page load or not master. */
class ComingSoonTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_switched_off_master_feature_shows_the_coming_soon_sheet_on_a_page_load(): void
    {
        $this->get('/business')->assertOk()->assertSee('Business Suite is coming soon')->assertSee('Got it')->assertHeader('X-Robots-Tag', 'noindex');
    }

    public function test_it_is_skin_aware_and_has_no_hard_coded_colours(): void
    {
        $html = file_get_contents(resource_path('views/coming-soon.blade.php'));
        $this->assertStringContainsString('<x-nx.page', $html);
        $this->assertStringContainsString('<x-nx.alpine-sheet', $html);
        $this->assertDoesNotMatchRegularExpression('/#[0-9a-fA-F]{3,8}\b|\b(bg|text|border)-(slate|gray|teal|emerald|red)-\d/', $html);
    }

    public function test_n2n_off_shows_the_sheet_too(): void
    {
        $u = User::factory()->create(['email_verified_at' => now(), 'is_active' => true]);
        $this->actingAs($u)->get('/n2n/chat')->assertOk()->assertSee('Naara chat is coming soon');
    }

    public function test_non_page_requests_stay_a_plain_404(): void
    {
        $u = User::factory()->create(['email_verified_at' => now(), 'is_active' => true]);
        $this->postJson('/webhooks/twilio/call-flow/1/1')->assertNotFound();
        $this->actingAs($u)->getJson('/business/workspace')->assertNotFound();
        $this->actingAs($u)->post('/workspace/switch')->assertNotFound();
        $this->actingAs($u)->get('/n2n/media/1', ['Accept' => 'image/webp,*/*'])->assertNotFound();
    }

    public function test_a_non_master_install_never_hints_at_a_master_only_feature(): void
    {
        config(['updater.product_identifier' => 'naarasim-whitelabel']);
        $this->get('/business')->assertNotFound();
    }

    public function test_the_page_returns_when_the_feature_is_switched_on(): void
    {
        BusinessSuiteSettings::set(true);
        $this->get('/business')->assertOk()->assertDontSee('is coming soon');
    }

    public function test_the_gift_store_shows_the_sheet_when_the_admin_switches_it_off(): void
    {
        Setting::setValue('features.naara_gift.enabled', '0');
        $u = User::factory()->create(['email_verified_at' => now(), 'is_active' => true]);
        $this->actingAs($u)->get('/gift-cards')->assertOk()->assertSee('Naara Gift is coming soon');
    }
}
