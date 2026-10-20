<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Support\BentoIcons;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Admin-managed 3D bento icons: each product card resolves an icon URL + opacity
 * from settings, falling back to the shipped /icons/*.webp default. Opacity is
 * clamped to 20–100 and the cache busts on any bento.* save.
 */
class BentoIconsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        BentoIcons::flush();
    }

    public function test_it_falls_back_to_the_shipped_default_icon_and_opacity(): void
    {
        $this->assertSame('/icons/esim-data-plans.webp', BentoIcons::icon('esim-data-plans'));
        // Showcase cards default to an 80% watermark; tiles to full strength.
        $this->assertSame(80, BentoIcons::opacity('esim-data-plans'));
        $this->assertSame(100, BentoIcons::opacity('buy-esim'));
        $this->assertSame(0.8, BentoIcons::opacityFraction('esim-data-plans'));
    }

    public function test_tiles_ship_bold_and_scale_is_admin_adjustable_and_clamped(): void
    {
        // Action tiles ship bold (1.6×); showcase cards ship at 1.0×.
        $this->assertSame(1.6, BentoIcons::scale('buy-esim'));
        $this->assertSame(1.0, BentoIcons::scale('esim-data-plans'));

        Setting::setValue('bento.scale.get-number', 2.25, 'bento');
        BentoIcons::flush();
        $this->assertSame(2.25, BentoIcons::scale('get-number'));

        // Clamped to the 0.5–3.0 range.
        Setting::setValue('bento.scale.get-number', 9, 'bento');
        BentoIcons::flush();
        $this->assertSame(3.0, BentoIcons::scale('get-number'));
    }

    public function test_an_admin_upload_and_opacity_override_win(): void
    {
        Setting::setValue('bento.icon.naara-gift', 'https://cdn.example/gift.webp', 'bento');
        Setting::setValue('bento.opacity.naara-gift', 45, 'bento');
        BentoIcons::flush();

        $this->assertSame('https://cdn.example/gift.webp', BentoIcons::icon('naara-gift'));
        $this->assertSame(45, BentoIcons::opacity('naara-gift'));
    }

    public function test_opacity_is_clamped_to_the_supported_range(): void
    {
        Setting::setValue('bento.opacity.virtual-numbers', 5, 'bento');
        BentoIcons::flush();
        $this->assertSame(20, BentoIcons::opacity('virtual-numbers'));

        Setting::setValue('bento.opacity.virtual-numbers', 900, 'bento');
        BentoIcons::flush();
        $this->assertSame(100, BentoIcons::opacity('virtual-numbers'));
    }

    public function test_unknown_keys_are_safe(): void
    {
        $this->assertNull(BentoIcons::icon('not-a-card'));
        $this->assertSame(100, BentoIcons::opacity('not-a-card'));
    }

    public function test_home_showcase_cards_use_each_products_admin_image_opacity_and_size(): void
    {
        // The home product cards are separate, spaced cards; each carries ITS product's admin image behind the text with the opacity
        // and size set in Admin → Bento icons (the skin restyles the card, never the admin's art settings).
        Setting::setValue('bento.icon.esim-data-plans', 'https://cdn.example/plans.webp', 'bento');
        Setting::setValue('bento.opacity.esim-data-plans', 35, 'bento');
        Setting::setValue('bento.scale.esim-data-plans', 1.4, 'bento');
        BentoIcons::flush();

        $html = \Illuminate\Support\Facades\Blade::render('<x-nx.showcase bkey="esim-data-plans" href="/x" icon="globe" title="eSIM Data Plans" text="t" cta="Browse plans" />');

        $this->assertStringContainsString('class="ns-showcase__art"', $html);
        $this->assertStringContainsString('src="https://cdn.example/plans.webp"', $html);
        $this->assertStringContainsString('opacity: 0.35', $html);
        $this->assertStringContainsString('--nx-art-scale: 1.4', $html);
    }

    public function test_the_naara_credits_coin_is_a_light_self_hosted_asset_with_a_size_appropriate_file(): void
    {
        // Owner artwork, committed (never hot-linked) and kept very light: ~4 KB for small inline uses, ~10 KB for larger ones.
        $small = public_path('images/brand/naara-coin-64.webp');
        $large = public_path('images/brand/naara-coin-128.webp');
        $this->assertFileExists($small);
        $this->assertFileExists($large);
        $this->assertLessThan(8 * 1024, filesize($small));
        $this->assertLessThan(16 * 1024, filesize($large));

        $inline = \Illuminate\Support\Facades\Blade::render('<x-naara-coin class="h-4 w-4" />');
        $this->assertStringContainsString('images/brand/naara-coin-64.webp', $inline);
        $this->assertStringContainsString('aria-hidden="true"', $inline);

        $big = \Illuminate\Support\Facades\Blade::render('<x-naara-coin class="h-9 w-9" />');
        $this->assertStringContainsString('images/brand/naara-coin-128.webp', $big);
    }
}
