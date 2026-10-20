<?php

namespace Tests\Feature\MasterMarketing;

use App\Models\Setting;
use App\Support\IconOverrides;
use App\Support\MasterMarketing\MenuIcons;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The Naara custom menu icon set (MASTER-ONLY): every slot has its own light and dark file, and every menu route resolves to a slot. */
class MenuIconsTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_mapped_slot_has_a_light_and_a_dark_icon_on_disk(): void
    {
        $slots = [];
        foreach (array_unique(MenuIcons::MORE) as $k) {
            $slots[] = ['more', 'app-more-'.$k];
        }
        foreach (MenuIcons::MARKETING as $k) {
            $slots[] = ['marketing', 'marketing-mega-'.$k];
        }
        foreach (array_unique(MenuIcons::ADMIN) as $k) {
            $slots[] = ['admin', 'admin-more-'.$k];
        }
        $this->assertGreaterThan(150, count($slots));
        foreach ($slots as [$dir, $file]) {
            foreach (['svg', 'webp'] as $ext) {
                foreach (['', '-dark'] as $mode) {
                    $this->assertFileExists(public_path(MenuIcons::BASE."/{$dir}/{$ext}/{$file}{$mode}.{$ext}"), "{$file}{$mode}.{$ext}");
                }
            }
        }
    }

    public function test_every_route_the_admin_and_customer_menus_use_has_an_icon_slot(): void
    {
        $admin = file_get_contents(base_path('resources/views/components/layouts/admin.blade.php'));
        preg_match_all("/'route' => '([a-z0-9._-]+)'/", $admin, $m);
        $missing = array_values(array_filter(array_unique($m[1]), fn ($r) => MenuIcons::slotForRoute($r, 'admin') === null));
        $this->assertSame([], $missing, 'admin menu routes without a slot icon');

        $nav = file_get_contents(base_path('app/Support/BottomNav.php'));
        preg_match_all("/\['route' => '([a-z0-9._-]+)', 'label'/", $nav, $m2);
        $missing2 = array_values(array_filter(array_unique($m2[1]), fn ($r) => MenuIcons::slotForRoute($r, 'app') === null));
        $this->assertSame([], $missing2, 'customer menu routes without a slot icon');
    }

    public function test_two_items_that_used_to_share_one_generic_icon_now_differ(): void
    {
        $chat = MenuIcons::resolve(MenuIcons::marketingSlot('chat'));
        $contact = MenuIcons::resolve(MenuIcons::marketingSlot('contact'));
        $this->assertNotSame($chat['light'], $contact['light']);
        $this->assertNotSame(MenuIcons::resolve('menu.app.more.help-support')['light'], MenuIcons::resolve('menu.app.more.messages')['light']);
    }

    public function test_an_admin_override_wins_and_an_unknown_slot_falls_back(): void
    {
        $this->assertNull(MenuIcons::resolve('menu.app.more.does-not-exist'));
        $this->assertNull(MenuIcons::resolve('menu.evil/../../x'));
        $this->assertNull(MenuIcons::resolve(null));

        IconOverrides::set('menu-app-more-security', 'https://cdn.example.test/sec.svg');
        $r = MenuIcons::resolve('menu.app.more.security');
        $this->assertTrue($r['custom']);
        $this->assertSame('https://cdn.example.test/sec.svg', $r['light']);
    }

    public function test_short_labels_keep_the_full_name_for_unknown_labels(): void
    {
        $this->assertSame('Business', MenuIcons::short('NaaraBusiness Suite'));
        $this->assertSame('Calls', MenuIcons::short('Internet calls'));
        $this->assertSame('Wallet', MenuIcons::short('Wallet'));
        $this->assertLessThanOrEqual(14, max(array_map('mb_strlen', MenuIcons::SHORT)));
    }

    public function test_the_marketing_mega_menu_renders_the_set_with_light_and_dark_files(): void
    {
        $html = $this->get('/')->assertOk()->getContent();
        $this->assertStringContainsString('menu-icons/marketing/svg/marketing-mega-pricing.svg', $html);
        $this->assertStringContainsString('menu-icons/marketing/svg/marketing-mega-pricing-dark.svg', $html);
        $this->assertStringContainsString('menu-icons/marketing/svg/marketing-mega-contact.svg', $html);
    }

    public function test_the_dashboard_more_sheet_uses_the_set_and_short_labels(): void
    {
        $u = \App\Models\User::factory()->create(['is_active' => true, 'email_verified_at' => now()]);
        $html = $this->actingAs($u)->get('/dashboard')->assertOk()->getContent();
        $this->assertStringContainsString('menu-icons/more/svg/app-more-wallet.svg', $html);
        $this->assertStringContainsString('ns-lbl--short', $html);
        $this->assertStringContainsString('menu-icons/more/svg/app-more-security-dark.svg', $html);
        unset($u);
        Setting::query()->count();
    }
}
