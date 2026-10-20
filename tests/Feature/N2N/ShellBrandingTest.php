<?php

namespace Tests\Feature\N2N;

use App\Models\Setting;
use App\Support\CustomPreloader;
use App\Support\N2N\FloatingChat;
use App\Support\PreloaderSettings;

/** Owner request 2026-10-04: Naara logo animations as seeded preloaders, the chat logo in the shell, and the quick chat button. */
class ShellBrandingTest extends N2NTestCase
{
    private function user2(): \App\Models\User
    {
        return $this->user(['naara_id' => \App\Services\N2N\Identity\NaaraId::generate(), 'username' => 'u'.substr(md5(uniqid()), 0, 8)]);
    }

    public function test_the_logo_animations_are_seeded_through_the_preloader_studio_with_the_requested_daily_caps(): void
    {
        $expect = ['dashboard_home' => [3, 'naara-preloader.webp'], 'marketing' => [2, 'naara-preloader.webp'], 'numbers' => [4, 'naara-numbers-intro.webp'], 'n2n' => [4, 'naara-chat-intro.webp']];
        foreach ($expect as $type => [$cap, $file]) {
            $cfg = PreloaderSettings::forPageType($type);
            $this->assertSame('custom', $cfg['preset'], $type);
            $this->assertTrue($cfg['enabled']);
            $this->assertSame($cap, $cfg['max_per_day'], "{$type} daily cap");
            foreach (['light', 'dark'] as $mode) {
                $this->assertSame('images/brand/animations/'.$file, $cfg['custom'][$mode]['asset']);
                $this->assertFileExists(public_path($cfg['custom'][$mode]['asset']));
                $this->assertStringEndsWith($file, CustomPreloader::url($cfg['custom'][$mode]));
            }
        }
        $this->assertArrayHasKey('n2n', PreloaderSettings::pageTypes());
        $this->assertArrayHasKey('dashboard_home', PreloaderSettings::pageTypes());
    }

    public function test_the_seed_never_overrides_what_an_admin_already_configured(): void
    {
        PreloaderSettings::saveAssignment('marketing', ['preset' => 'spokes', 'enabled' => true, 'max_per_day' => 9]);
        (include database_path('migrations/2026_10_27_100000_seed_naara_logo_animations.php'))->up();
        $this->assertSame('spokes', PreloaderSettings::forPageType('marketing')['preset']);
        $this->assertSame(9, PreloaderSettings::forPageType('marketing')['max_per_day']);
    }

    public function test_a_committed_asset_cannot_escape_its_folder_and_deleting_never_touches_it(): void
    {
        $bad = CustomPreloader::clean(['light' => ['kind' => 'webp', 'asset' => 'images/brand/animations/../../../.env']]);
        $this->assertSame([], $bad);
        $bad2 = CustomPreloader::clean(['light' => ['kind' => 'webp', 'asset' => 'storage/secret.webp']]);
        $this->assertSame([], $bad2);
        $meta = ['kind' => 'webp', 'asset' => 'images/brand/animations/naara-preloader.webp'];
        CustomPreloader::delete($meta);
        $this->assertFileExists(public_path($meta['asset']));
    }

    public function test_the_pages_choose_their_preloader_type_from_the_route(): void
    {
        $u = $this->user2();
        $this->actingAs($u)->get(route('dashboard'))->assertOk()->assertSee('data-page-type="dashboard_home"', false)->assertSee('data-max-per-day="3"', false);
        $this->get(route('numbers.lines'))->assertOk()->assertSee('data-page-type="numbers"', false);
        $this->get(route('n2n.chat'))->assertOk()->assertSee('data-page-type="n2n"', false)->assertSee('naara-chat-intro.webp', false);
        $this->get(route('account'))->assertOk()->assertDontSee('data-page-type="dashboard_home"', false)->assertDontSee('data-page-type="numbers"', false);
    }

    public function test_the_chat_shell_header_and_rail_use_the_naara_chat_logo(): void
    {
        $this->actingAs($this->user2())->get(route('n2n.chat'))->assertOk()
            ->assertSee('naara-chat-mark-192.webp', false)->assertSee('Naara chat');
        foreach (['naara-chat-mark-96.webp', 'naara-chat-mark-192.webp', 'naara-chat-logo.webp', 'naara-chat-intro.webp'] as $f) {
            $this->assertFileExists(public_path('images/brand/animations/'.$f));
        }
    }

    public function test_the_quick_chat_button_shows_on_chosen_pages_only_and_never_on_the_dashboard(): void
    {
        $u = $this->user2();
        $this->actingAs($u);
        $this->get(route('dashboard'))->assertOk()->assertDontSee('nn-fab', false);
        $this->get(route('numbers.contacts'))->assertOk()->assertSee('nn-fab', false)->assertSee('Open Naara chat');
        $this->get(route('account'))->assertOk()->assertDontSee('nn-fab', false);
        $this->get(route('n2n.people'))->assertOk()->assertDontSee('nn-fab__btn', false);
        // Even if the admin lists the dashboard, it stays off there.
        Setting::setValue('n2n.floating_chat_routes', 'dashboard,numbers.contacts');
        $this->get(route('dashboard'))->assertOk()->assertDontSee('nn-fab', false);
        // The switch turns it off everywhere; a guest or N2N-off never sees it.
        Setting::setValue('n2n.floating_chat', false);
        $this->get(route('numbers.contacts'))->assertOk()->assertDontSee('nn-fab', false);
        Setting::setValue('n2n.floating_chat', true);
        Setting::setValue('n2n.enabled', false);
        $this->assertFalse(FloatingChat::enabled());
    }

    public function test_the_admin_can_change_the_quick_button_settings_and_bad_input_is_refused(): void
    {
        \Spatie\Permission\Models\Role::findOrCreate('super_admin', 'web');
        $admin = $this->user();
        $admin->assignRole('super_admin');
        $this->actingAs($admin);
        \Livewire\Livewire::test(\App\Livewire\Admin\N2N\Settings::class)->set('floatingRoutes', 'numbers.messages,wallet')->set('floatingHours', 12)->set('floatingChat', true)->call('save')->assertHasNoErrors();
        $this->assertSame('numbers.messages,wallet', Setting::getValue('n2n.floating_chat_routes'));
        $this->assertSame(12, FloatingChat::dismissHours());
        \Livewire\Livewire::test(\App\Livewire\Admin\N2N\Settings::class)->set('floatingRoutes', '<script>')->call('save')->assertHasErrors('floatingRoutes');
    }
    public function test_every_logo_hold_covers_its_animation_and_the_bump_leaves_admin_edits_alone(): void
    {
        // animation length (ms) measured from the committed WebP frames; the hold must outlast it
        $anim = ['dashboard_home' => 1944, 'marketing' => 1944, 'numbers' => 2476, 'n2n' => 2049];
        foreach ($anim as $type => $ms) {
            $this->assertGreaterThan($ms + 500, PreloaderSettings::forPageType($type)['min_display_ms'], $type);
        }
        PreloaderSettings::saveAssignment('marketing', array_merge(PreloaderSettings::forPageType('marketing'), ['min_display_ms' => 4200]));
        (include database_path('migrations/2026_10_30_100000_extend_logo_preloader_hold.php'))->up();
        $this->assertSame(4200, PreloaderSettings::forPageType('marketing')['min_display_ms']);
    }

    public function test_the_chat_logo_is_a_setting_not_a_literal_and_the_admin_can_replace_or_reset_it(): void
    {
        $this->seed(\Database\Seeders\RoleSeeder::class);
        $this->assertSame(\App\Support\N2N\N2NSettings::DEFAULT_LOGO, Setting::getValue('n2n.logo_url'));   // seeded by the migration
        $u = $this->user2();
        $this->actingAs($u)->get(route('n2n.chat'))->assertOk()->assertSee('naara-chat-mark-192.webp', false);
        Setting::setValue('n2n.logo_url', 'https://cdn.example.test/brand/chat.png');
        $this->get(route('n2n.chat'))->assertOk()->assertSee('https://cdn.example.test/brand/chat.png', false)->assertDontSee('naara-chat-mark-192.webp', false);
        $admin = $this->user();
        $admin->assignRole('admin');
        \Livewire\Livewire::actingAs($admin)->test(\App\Livewire\Admin\N2N\Settings::class)->call('resetLogo')->assertSee('reset to the shipped mark');
        $this->assertStringEndsWith(\App\Support\N2N\N2NSettings::DEFAULT_LOGO, \App\Support\N2N\N2NSettings::logoUrl());
        \Livewire\Livewire::actingAs($admin)->test(\App\Livewire\Admin\N2N\Settings::class)
            ->set('logoUpload', \Illuminate\Http\UploadedFile::fake()->create('notimage.pdf', 10, 'application/pdf'))->call('uploadLogo')->assertHasErrors(['logoUpload']);
    }
}
