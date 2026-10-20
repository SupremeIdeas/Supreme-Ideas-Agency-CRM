<?php

namespace Tests\Feature;

use App\Livewire\Admin\SiteEditor;
use App\Models\FaqCategory;
use App\Models\Post;
use App\Models\Setting;
use App\Models\User;
use App\Support\SiteContent;
use Database\Seeders\BlogPostSeeder;
use Database\Seeders\DefaultAdminSeeder;
use Database\Seeders\FaqSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Marketing CMS audit (2026-10-02). The owner's rule: every word on every
 * master marketing page is seeded AND editable in the admin panel. This is the
 * standing guard for it:
 *
 *  1. every seeded SiteContent text field, set to a unique marker, must actually
 *     show up on a rendered page (a field that doesn't is DEAD — the admin can
 *     edit it with no effect), and
 *  2. a fresh `db:seed` ships the DB-backed marketing content (FAQ + blog).
 */
class MarketingCmsEditabilityTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Fields that are legitimately not visible in this test's render, each with
     * its reason. Anything NOT listed here must render.
     */
    private const INTENTIONALLY_NOT_RENDERED = [
        // 'audiences' is the platform's portable section: the owner removed it from the home page
        // (it duplicated the hero's persona tabs) but it stays editable for pages it is copied onto.
        'home.audiences.eyebrow', 'home.audiences.headline', 'home.audiences.subtext',
        // Only shown AFTER the form is submitted — covered by test_the_contact_form_uses_its_editable_copy_including_the_sent_state.
        'contact.form.success', 'contact.form.sent_title',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(DefaultAdminSeeder::class);
        $this->seed(FaqSeeder::class);
        $this->seed(BlogPostSeeder::class);
        config(['naara.support.whatsapp' => '2348012345678']);
    }

    /** @return array{0: array<string,array<string,string>>, 1: array<string,string>} overrides + markers */
    private function markersFor(string $page): array
    {
        $overrides = [];
        $markers = [];
        foreach (SiteContent::defaults()[$page] as $section => $fields) {
            foreach ($fields as $field => $value) {
                // Images/colours/dials are not text; numeric/hex defaults are settings, not copy.
                if (! is_string($value) || $value === '' || in_array($field, ['image', 'image_dark', 'subheadline_color', 'video_source', 'video_file', 'video_url', 'player_color'], true)
                    || str_starts_with($field, 'opt_')
                    || str_ends_with($field, '_image') || preg_match('/^(\d+|#[0-9a-f]+)$/i', $value)) {
                    continue;
                }
                $marker = 'MK'.substr(md5("{$page}.{$section}.{$field}"), 0, 10);
                $overrides[$section][$field] = $marker;
                $markers["{$page}.{$section}.{$field}"] = $marker;
            }
            $overrides[$section]['visible'] = true;
        }
        // The White Label video section only renders once a video is set; a YouTube link is enough to show the player (and its copy).
        if ($page === 'white-label') {
            $overrides['video'] += ['video_source' => 'link', 'video_url' => 'https://cdn.example.com/promo.mp4', 'transcript' => 'A short transcript.', 'duration_label' => '2 min'];
        }

        return [$overrides, $markers];
    }

    private function html(string ...$uris): string
    {
        $out = '';
        foreach ($uris as $uri) {
            $out .= html_entity_decode($this->get($uri)->getContent());
        }

        return $out;
    }

    public function test_every_seeded_text_field_reaches_a_rendered_page(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        // The download page only shows some strings in some app-store states.
        $download = function (array $settings): string {
            Setting::setValue('appexport', ['download_enabled' => true] + $settings);

            return $this->html('/download');
        };

        $missing = [];
        foreach (['home', 'about', 'how-it-works', 'contact', 'faq', 'pricing', 'download', 'navigation', 'products', 'naara-chat', 'naara-brain', 'naara-gift', 'white-label'] as $page) {
            [$overrides, $markers] = $this->markersFor($page);
            SiteContent::saveOverrides($page, $overrides);

            $html = match ($page) {
                'home' => $this->html('/', '/products'),
                'about' => $this->html('/about'),
                'how-it-works' => $this->html('/how-it-works'),
                'contact' => $this->html('/contact').$this->actingAs($user)->html('/contact'),
                'faq' => $this->html('/faq'),
                'products' => $this->html('/products'),
                'naara-chat' => $this->html('/naara-chat'),
                'naara-brain' => $this->html('/naara-brain'),
                'naara-gift' => $this->html('/naara-gift'),
                'white-label' => $this->html('/white-label'),
                'pricing' => $this->html('/pricing'),
                'download' => $download(['android_apk_url' => 'https://x.test/a.apk', 'android_store_live' => true, 'android_store_url' => 'https://x.test/p', 'ios_store_live' => true, 'ios_store_url' => 'https://x.test/i'])
                    .$download([]),
                // The header menu renders on every marketing page; "My Dashboard" only when signed in.
                'navigation' => $this->html('/about'),
            };
            if ($page === 'navigation') {
                $html .= $this->actingAs($user)->html('/about');
            }

            foreach ($markers as $key => $marker) {
                if (! str_contains($html, $marker) && ! in_array($key, self::INTENTIONALLY_NOT_RENDERED, true)) {
                    $missing[] = $key;
                }
            }
            SiteContent::saveOverrides($page, []);
            $this->app['auth']->logout();
        }

        $this->assertSame([], $missing, "Seeded Site Editor fields that never render (dead fields):\n".implode("\n", $missing));
    }

    public function test_an_admin_edit_in_the_site_editor_changes_the_live_homepage(): void
    {
        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole('super_admin');

        $c = Livewire::actingAs($admin)->test(SiteEditor::class)->set('page', 'home');
        $c->set('sections.hero.spot_title_1', 'Edited Spot One')
            ->set('sections.hero.who_1_title', 'Edited Persona')
            ->set('sections.hero.flags_label', 'Edited flags line')
            ->set('sections.wizard.headline', 'Edited wizard headline')
            ->set('sections.diagnosis.f1_title', 'Edited failure title')
            ->set('sections.diagnosis.m_s2_a_headline', 'Edited modal headline')
            ->set('sections.blogs.headline', 'Edited blog band')
            ->call('save');

        $html = html_entity_decode($this->get('/')->getContent());
        foreach (['Edited Spot One', 'Edited Persona', 'Edited flags line', 'Edited wizard headline', 'Edited failure title', 'Edited modal headline', 'Edited blog band'] as $edit) {
            $this->assertStringContainsString($edit, $html);
        }
    }

    public function test_the_contact_form_uses_its_editable_copy_including_the_sent_state(): void
    {
        SiteContent::saveOverrides('contact', ['form' => [
            'sent_title' => 'Edited sent title', 'success' => 'Edited success body', 'subjects' => "Alpha topic\nBeta topic", 'visible' => true,
        ]]);
        $user = User::factory()->create(['is_active' => true]);

        Livewire::actingAs($user)->test(\App\Livewire\ContactForm::class)
            ->assertSee('Alpha topic')->assertSee('Beta topic')->assertDontSee('Billing question')
            // an edited option validates; the old shipped one no longer does
            ->set('subject', 'Billing question')->set('message', 'Hello there, I need help')->call('send')->assertHasErrors('subject')
            ->set('subject', 'Beta topic')->call('send')
            ->assertSee('Edited sent title')->assertSee('Edited success body');
    }

    public function test_a_pasted_script_in_a_modal_body_is_stripped(): void
    {
        SiteContent::saveOverrides('home', ['diagnosis' => ['m_s2_a_body' => '<p>Safe</p><script>alert(1)</script><img src=x onerror=alert(1)>', 'visible' => true]]);

        $modals = SiteContent::diagnosisModals(SiteContent::page('home')['diagnosis']);

        $this->assertStringContainsString('<p>Safe</p>', $modals['s2-a']['body']);
        $this->assertStringNotContainsString('<script', $modals['s2-a']['body']);
        $this->assertStringNotContainsString('onerror', $modals['s2-a']['body']);
    }

    public function test_a_fresh_seed_ships_the_db_backed_marketing_content(): void
    {
        $this->artisan('db:seed', ['--force' => true]);

        $this->assertGreaterThanOrEqual(40, Post::published()->count(), 'the /blog index and the homepage "From the Blog" band are empty without the blog seeder');
        $this->assertGreaterThanOrEqual(12, FaqCategory::count());
    }

    public function test_the_homepage_renders_every_section_a_visitor_expects_in_order(): void
    {
        $html = $this->html('/');

        $order = ['Land Anywhere', 'Made for the way you move', 'What we', 'The problem isn', 'Connected in Three Steps', 'Stop juggling numbers', 'From the Blog'];
        $last = -1;
        foreach ($order as $needle) {
            $pos = strpos($html, $needle);
            $this->assertNotFalse($pos, "homepage is missing: {$needle}");
            $this->assertGreaterThan($last, $pos, "{$needle} is out of order");
            $last = $pos;
        }
    }
}
