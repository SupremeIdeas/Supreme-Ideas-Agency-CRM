<?php

namespace Tests\Feature\BusinessSuite;

use App\Models\BrandSubscriptionPlan;
use App\Models\Setting;
use App\Models\User;
use App\Support\Business\BusinessLandingContent;
use App\Support\Business\BusinessSettings;
use App\Support\Business\BusinessSuiteSettings;
use App\Support\SiteContent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The public NaaraBusiness Suite page (MASTER-ONLY): editable copy, live plans, the reused bento/pills/FAQ sections, and the dashboard counterpart. */
class BusinessLandingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        BusinessSuiteSettings::set(true);
        Setting::setValue(BusinessSettings::MODULE.'inbox', true, 'business');
        Setting::setValue(BusinessSettings::MODULE.'recording', false, 'business');
    }

    public function test_the_page_carries_every_section_the_blueprint_promises(): void
    {
        $html = $this->get('/business')->assertOk()->getContent();

        foreach (['Get found. Stay reachable.', 'The problem', 'The solution', 'Everything, A to Z', 'Zero lock-in', 'How it works', 'Naara Pro perks', 'Questions, answered properly'] as $text) {
            $this->assertStringContainsString($text, $html, "missing: $text");
        }
        $this->assertStringContainsString('data-bento-diagnosis', $html);   // the homepage problem/solution block, reused
        $this->assertStringContainsString('data-pill-physics', $html);       // the Matter.js pills, reused
        $this->assertSame(8, substr_count($html, 'data-cell="'));            // four problems, four solutions
        $this->assertSame(26, preg_match_all('/class="sr-only">[A-Z]: /', $html));   // A to Z
        $this->assertStringContainsString('Is call recording legal?', $html);
    }

    public function test_plans_are_live_and_only_list_tools_whose_module_is_on(): void
    {
        BrandSubscriptionPlan::create(['name' => 'Studio', 'price_usd_per_month' => 59, 'handles_included' => 2, 'guaranteed_followers_per_handle_per_month' => 80, 'video_previews_allowed' => 1, 'is_active' => true, 'sort_order' => 1,
            'capabilities' => ['features' => ['workspace', 'inbox', 'recording'], 'premium_skins' => true, 'monthly_credits' => 40, 'limits' => ['seats' => 50]]]);

        $html = $this->get('/business')->assertOk()->getContent();
        $this->assertStringContainsString('Studio', $html);
        $this->assertStringContainsString('$59', $html);
        $this->assertStringContainsString('Shared inbox', $html);
        $this->assertStringNotContainsString('Call recording\n', $html);   // module is off: never advertised on the plan
        $this->assertStringContainsString('Premium dashboard skins', $html);
        $this->assertStringContainsString('Monthly NaaraCredits: 40 every month', $html);   // the one Naara Pro catalogue's wording, the same on every page
        $this->assertStringContainsString('Seats: up to 50', $html);
    }

    public function test_the_words_are_editable_in_the_site_editor_and_the_page_is_master_only_content(): void
    {
        $this->assertContains('business-suite', SiteContent::editablePages());
        $this->assertArrayHasKey('business-suite', SiteContent::defaults());

        SiteContent::saveOverrides('business-suite', ['hero' => ['headline' => 'A brand new headline']]);
        $this->get('/business')->assertOk()->assertSee('A brand new headline');
    }

    public function test_every_cell_and_letter_has_copy_and_a_modal(): void
    {
        $d = BusinessLandingContent::defaults();
        foreach (array_keys(BusinessLandingContent::CELLS) as $id) {
            foreach (['label', 'title', 'text'] as $part) {
                $this->assertNotSame('', $d['problem']["{$id}_{$part}"] ?? '', "{$id}_{$part}");
            }
            $this->assertNotSame('', $d['problem']["m_{$id}_headline"]);
            $this->assertNotSame('', $d['problem']["m_{$id}_bullets"]);
        }
        $this->assertCount(26, array_filter(array_keys($d['atoz']), fn ($k) => preg_match('/^[a-z]_title$/', $k)));
        $this->assertSame(6, count(array_filter(array_keys($d['faq']), fn ($k) => preg_match('/^g\d_label$/', $k))));
    }

    public function test_it_is_still_coming_soon_when_the_suite_is_off(): void
    {
        BusinessSuiteSettings::set(false);
        $this->get('/business')->assertOk()->assertSee('NaaraBusiness Suite is coming soon');
    }

    public function test_the_dashboard_page_is_rebranded_and_shows_what_each_plan_includes(): void
    {
        BrandSubscriptionPlan::create(['name' => 'Studio', 'price_usd_per_month' => 59, 'handles_included' => 2, 'guaranteed_followers_per_handle_per_month' => 80, 'video_previews_allowed' => 0, 'is_active' => true, 'sort_order' => 1,
            'capabilities' => ['features' => ['workspace', 'inbox'], 'monthly_credits' => 40]]);
        $user = User::factory()->create(['is_active' => true, 'email_verified_at' => now()]);

        $this->actingAs($user)->get('/brand/get-listed')->assertOk()
            ->assertSee('NaaraBusiness Suite')->assertSee('Shared inbox')->assertSee('Monthly NaaraCredits: 40 every month')->assertSee('See everything NaaraBusiness Suite');

        BusinessSuiteSettings::set(false);
        $this->actingAs($user)->get('/brand/get-listed')->assertOk()->assertDontSee('See everything NaaraBusiness Suite')->assertSee('Grow your');
    }
}
