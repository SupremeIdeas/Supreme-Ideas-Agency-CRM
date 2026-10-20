<?php

namespace Tests\Feature\BusinessSuite;

use App\Models\User;
use App\Support\Business\BusinessSuiteSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The Business Suite master switch (MASTER-ONLY): ships off, 404s everywhere when off, never touches the old forwarding path. */
class BusinessSuiteSwitchTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_ships_off(): void
    {
        $this->assertFalse(BusinessSuiteSettings::enabled());
    }

    public function test_every_business_page_says_coming_soon_when_off(): void
    {
        $u = User::factory()->create(['email_verified_at' => now(), 'is_active' => true]);
        // A page load gets the friendly "Coming soon" sheet (see ComingSoonTest); anything that is not a page load is a plain 404.
        $this->get('/business')->assertOk()->assertSee('is coming soon');
        $this->actingAs($u)->get('/business/workspace')->assertOk()->assertSee('is coming soon');
        $this->actingAs($u)->get('/business/call-flows')->assertOk()->assertSee('is coming soon');
        $this->actingAs($u)->post('/workspace/switch')->assertNotFound();
        $this->post('/webhooks/twilio/call-flow/1/1')->assertNotFound();
    }

    public function test_the_pages_come_back_when_on(): void
    {
        BusinessSuiteSettings::set(true);
        $this->get('/business')->assertOk();
        BusinessSuiteSettings::set(false);
        $this->get('/business')->assertSee('is coming soon');
    }

    public function test_a_non_master_install_can_never_turn_it_on(): void
    {
        BusinessSuiteSettings::set(true);
        config(['updater.product_identifier' => 'naarasim-whitelabel']);
        $this->assertFalse(BusinessSuiteSettings::enabled());
        $this->get('/business')->assertNotFound();
    }

    public function test_only_an_admin_can_open_the_admin_page(): void
    {
        $member = User::factory()->create();
        $this->actingAs($member)->get('/'.config('app.admin_path', 'adminmaster').'/business-suite')->assertStatus(404);
    }
}
