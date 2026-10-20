<?php

namespace Tests\Feature\MasterMarketing;

use App\Livewire\Admin\SiteEditor;
use App\Models\User;
use App\Support\MasterMarketing\PageSwitch;
use App\Support\MasterOnlyModules;
use App\Support\SiteContent;
use Database\Seeders\RoleSeeder;
use Database\Seeders\WhiteLabelLicensePlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** The master's sales pages: Naara Chat, White Label and the expanded "What Naara Gives You" (MASTER-ONLY). */
class MasterMarketingPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_expanded_products_page_carries_the_new_sections(): void
    {
        $this->get('/products')->assertOk()
            ->assertSee('largest connectivity infrastructure in the world', false)
            ->assertSee('However you earn, Naara pays you for showing up')
            ->assertSee('Meet the businesses that grow with you')
            ->assertSee('Your dashboard, in your mood');
    }

    public function test_the_naara_chat_page_renders_and_is_honest_about_encryption(): void
    {
        $this->get('/naara-chat')->assertOk()
            ->assertSee('Chat and call anyone on Naara.')
            ->assertSee('not end-to-end encrypted');
    }

    public function test_the_naara_brain_page_ships_on_is_honest_and_can_be_switched_off(): void
    {
        $this->assertTrue(PageSwitch::isLive('naara-brain'));
        $this->get('/naara-brain')->assertOk()
            ->assertSee('Ask once,', false)
            ->assertSee('On the way')
            ->assertSee('It is an AI and it can make mistakes', false)
            ->assertDontSee('per month');
        $this->get('/')->assertSee('Ask once, move on');   // the header menu entry's description

        PageSwitch::set('naara-brain', false);

        $this->get('/naara-brain')->assertOk()->assertHeader('X-Robots-Tag', 'noindex')->assertSee('This page is not open right now.');
        $this->get('/')->assertDontSee('Ask once, move on');
    }

    public function test_the_naara_gift_page_shows_real_brands_and_can_be_switched_off(): void
    {
        $this->get('/naara-gift')->assertOk()
            ->assertSee('Gift cards for the brands', false)
            ->assertSee('Pay from your wallet')
            ->assertSee('images/marketing/gift/cards/amazon.webp', false)
            ->assertSee('Gift cards are final once they are delivered', false);

        PageSwitch::set('naara-gift', false);

        $this->get('/naara-gift')->assertOk()->assertHeader('X-Robots-Tag', 'noindex')->assertSee('This page is not open right now.');
    }

    public function test_the_white_label_page_reads_live_prices_from_the_license_catalogue(): void
    {
        $this->seed(WhiteLabelLicensePlanSeeder::class);

        $this->get('/white-label')->assertOk()
            ->assertSee('Launch your own Naara.')
            ->assertSee('$1,500')->assertSee('$5,000')->assertSee('$7,500')
            ->assertSee('Already Extended')
            ->assertSee('$15,000')->assertSee('$30,000');
    }

    public function test_the_white_label_page_still_shows_the_four_licenses_before_the_catalogue_is_seeded(): void
    {
        $this->get('/white-label')->assertOk()->assertSee('Extended V2')->assertSee('Basic');
    }

    public function test_the_white_label_page_is_a_portfolio_with_an_editable_illustration(): void
    {
        $this->get('/white-label')->assertOk()
            ->assertSee('Official Naara engine')                // the illustration's labels are Site Editor text
            ->assertSee('Vault')->assertSee('Ankara')           // eight skins in the portfolio
            ->assertSee('See the four screens');

        SiteContent::saveOverrides('white-label', ['engine' => ['art_core_title' => 'Core of my brand']]);
        $this->get('/white-label')->assertSee('Core of my brand');
    }

    public function test_switching_a_page_off_shows_a_friendly_sheet_and_hides_its_links(): void
    {
        $this->get('/')->assertSee('Launch your own Naara');   // the header menu entry's description

        PageSwitch::set('white-label', false);

        $this->get('/white-label')->assertOk()->assertHeader('X-Robots-Tag', 'noindex')->assertSee('This page is not open right now.');
        $this->get('/')->assertDontSee('Launch your own Naara');
        $this->get('/naara-chat')->assertOk()->assertSee('Chat and call anyone on Naara.');   // the other page is untouched

        PageSwitch::set('white-label', true);
        $this->get('/white-label')->assertSee('Launch your own Naara.');
    }

    public function test_an_admin_can_switch_the_page_from_the_site_editor(): void
    {
        $this->seed(RoleSeeder::class);
        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole('super_admin');

        $c = Livewire::actingAs($admin)->test(SiteEditor::class)->set('page', 'white-label');
        $this->assertTrue($c->get('pageLive'));
        $c->call('togglePageLive');
        $this->assertFalse(PageSwitch::isLive('white-label'));
        $c->call('togglePageLive');
        $this->assertTrue(PageSwitch::isLive('white-label'));

        // Pages that are not switchable ignore the toggle.
        Livewire::actingAs($admin)->test(SiteEditor::class)->set('page', 'about')->call('togglePageLive')->assertSet('pageLive', true);
    }

    public function test_an_admin_text_edit_changes_the_live_page(): void
    {
        SiteContent::saveOverrides('white-label', ['hero' => ['headline' => 'Run your own connectivity brand.']]);

        $this->get('/white-label')->assertSee('Run your own connectivity brand.');
    }

    public function test_a_white_label_fork_never_gets_these_pages(): void
    {
        config()->set('updater.product_identifier', 'naarasim-whitelabel');

        $this->get('/white-label')->assertNotFound();
        $this->get('/naara-chat')->assertNotFound();
        $this->get('/naara-brain')->assertNotFound();
        $this->get('/naara-gift')->assertNotFound();
        $this->get('/')->assertDontSee('Launch your own Naara');
        foreach (['products', 'naara-chat', 'naara-brain', 'naara-gift', 'white-label'] as $page) {
            $this->assertNotContains($page, SiteContent::editablePages());
        }
        $this->assertFalse(PageSwitch::isLive('white-label'));

        config()->set('updater.product_identifier', 'naarasim-core');
    }

    public function test_every_master_marketing_file_is_refused_by_the_packaging_guard(): void
    {
        foreach ([
            'app/Support/MasterMarketing/ChatContent.php',
            'app/Http/Controllers/MasterMarketing/LandingController.php',
            'resources/views/marketing/master-marketing/chat.blade.php',
            'tests/Feature/MasterMarketing/MasterMarketingPagesTest.php',
        ] as $path) {
            $this->assertTrue(MasterOnlyModules::matches($path), $path);
        }
        $this->assertFalse(MasterOnlyModules::matches('app/Support/MasterOnlyModules.php'));
    }

    public function test_the_widgets_illustration_ships_in_the_wordpress_plugin_zip_and_the_merchant_page_component(): void
    {
        $svg = base_path('resources/merchant-widget/wordpress/naara-widgets/assets/infrastructure.svg');
        $this->assertFileExists($svg);
        $this->assertStringContainsString('Naara orchestration', (string) file_get_contents($svg));

        $zipPath = (new \App\Services\MerchantWidgets\MerchantWidgetPluginPackager)->build();
        $zip = new \ZipArchive;
        $zip->open($zipPath);
        $this->assertNotFalse($zip->locateName('naara-widgets/assets/infrastructure.svg'));
        $zip->close();
        @unlink($zipPath);

        $html = \Illuminate\Support\Facades\Blade::render('<x-merchant-widget-illustration />');
        $this->assertStringContainsString('Naara orchestration', $html);
        $this->assertStringNotContainsString('id="ni-bg"', $html);   // ids are made unique per copy
    }
}
