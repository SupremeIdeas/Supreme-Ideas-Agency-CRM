<?php

namespace Tests\Feature;

use App\Services\Builder\PageBuilderService;
use App\Support\PageSections;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * BUILD-6 §B: the Section Builder publishes correctly, but no public view ever
 * rendered PageSections::live() — so "published" pages never changed. These lock
 * the fix: a published page renders its built sections on the real public URL,
 * and an untouched page still falls back to its existing content.
 */
class PageBuilderPublicRenderTest extends TestCase
{
    use RefreshDatabase;

    public function test_published_sections_render_on_the_real_public_page(): void
    {
        $svc = app(PageBuilderService::class);
        $section = $svc->addSection('home', 'faq');
        $svc->updateConfig($section, [
            'heading' => 'NAARA_BUILT_MARKER_XYZ',
            'items' => [['q' => 'Is this page live?', 'a' => 'Yes — built in the Section Builder.']],
        ]);
        $svc->publish('home');
        PageSections::flush('home');

        // A fresh, uncached public request must now show the built section.
        $this->get('/')
            ->assertOk()
            ->assertSee('NAARA_BUILT_MARKER_XYZ', false)
            ->assertSee('Is this page live?', false);
    }

    public function test_a_page_with_no_published_sections_falls_back_to_existing_content(): void
    {
        $this->assertSame([], PageSections::live('about'));
        // The hardcoded/CMS content still renders — nothing breaks for an untouched page.
        $this->get('/about')->assertOk();
    }

    /**
     * Owner request (2026-09-21): a brand-new page built entirely from the
     * Section Builder (not one of the 4 "starter" pages, which already
     * render built sections through their own existing views) must be
     * reachable on the public site with no new route/view of its own.
     */
    public function test_a_brand_new_page_key_is_publicly_reachable_once_published(): void
    {
        $svc = app(PageBuilderService::class);
        $section = $svc->addSection('a-new-landing-page', 'two_column');
        $svc->updateConfig($section, ['headline' => 'NAARA_NEW_PAGE_MARKER']);
        $svc->publish('a-new-landing-page');
        PageSections::flush('a-new-landing-page');

        $this->get('/page/a-new-landing-page')->assertOk()->assertSee('NAARA_NEW_PAGE_MARKER');
    }

    public function test_an_unpublished_page_key_404s(): void
    {
        $this->get('/page/never-built-this')->assertNotFound();
    }

    public function test_a_starter_page_key_404s_on_the_generic_route(): void
    {
        // home/about/how-it-works/contact already render Section Builder
        // output through their own views — no second URL for the same content.
        $this->get('/page/home')->assertNotFound();
    }
}
