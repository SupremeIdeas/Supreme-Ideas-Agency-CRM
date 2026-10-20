<?php

namespace Tests\Feature;

use App\Support\SiteContent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * BUILD-12 — the "Who Naara Is For" homepage audience-tabs section. Registered
 * as a real, admin-orderable homepage section (not a hardcoded insert),
 * defaulted between products and features, with the six approved panels.
 */
class HomepageAudiencesTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_homepage_no_longer_renders_the_audience_panels_by_default(): void
    {
        // Messaging audit (owner request, 2026-09-21): this section duplicated
        // the hero's own persona-tabs (_who-for, added the same day) with an
        // overlapping-but-different persona list on the same page — confusing
        // for a first-time visitor, so it's skipped in the default homepage
        // render now. The section itself is untouched (still registered,
        // still visible=true, still the platform's one PORTABLE_SECTIONS
        // entry) — an admin can add it back here or to any other page from
        // the Site Editor's portable-section picker (see ReusableSectionsTest).
        $this->get('/')->assertOk()->assertDontSee('Travel Smarter. Stay Connected Everywhere.', false);
    }

    public function test_audiences_is_a_registered_orderable_section_after_products(): void
    {
        $sections = SiteContent::page('home', includeHidden: true);

        $this->assertArrayHasKey('audiences', $sections);
        // Orderable like any section: carries visible + order.
        $this->assertArrayHasKey('order', $sections['audiences']);
        $this->assertTrue($sections['audiences']['visible']);
        // Default position: after products.
        $this->assertGreaterThan($sections['products']['order'], $sections['audiences']['order']);
    }

    public function test_the_panel_images_are_admin_swappable_and_default_to_shipped_webp(): void
    {
        $defaults = SiteContent::defaults()['home']['audiences'];

        $this->assertSame('/images/audiences/naara-leisure-traveler.webp', $defaults['travelers_image']);
        $this->assertArrayHasKey('families_image', $defaults);
    }
}
