<?php

namespace Tests\Feature;

use App\Livewire\Admin\PreloaderStudio;
use App\Models\User;
use App\Support\PreloaderSettings;
use App\Support\Verify\Checks\PreloaderConfigSound;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Livewire\Livewire;
use Tests\TestCase;

/** Blueprint 6.2: a section's preloader can be forced dark or light independently of the site theme. */
class PreloaderThemeOverrideTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(RoleSeeder::class);
        $u = User::factory()->create(['is_active' => true]);
        $u->assignRole('super_admin');

        return $u;
    }

    public function test_the_theme_defaults_to_auto_and_unknown_values_are_defended(): void
    {
        $this->assertSame('auto', PreloaderSettings::safeDefault()['theme']);
        $this->assertSame('auto', PreloaderSettings::theme(['theme' => 'neon']));
        $this->assertSame('dark', PreloaderSettings::theme(['theme' => 'dark']));
    }

    public function test_a_section_forced_light_paints_a_light_canvas_even_in_a_dark_site(): void
    {
        PreloaderSettings::saveAssignment('default', array_merge(PreloaderSettings::safeDefault(), ['preset' => 'simple-pulse', 'enabled' => true, 'inherit' => false, 'theme' => 'light']));
        $html = Blade::render('<x-brand-preloader page-type="default" />');
        $this->assertStringContainsString('data-pl-theme="light"', $html);
        $this->assertStringContainsString('background:#ffffff', str_replace(' ', '', $html));
    }

    public function test_auto_keeps_the_existing_site_following_background(): void
    {
        PreloaderSettings::saveAssignment('default', array_merge(PreloaderSettings::safeDefault(), ['preset' => 'simple-pulse', 'enabled' => true, 'inherit' => false, 'theme' => 'auto']));
        $html = Blade::render('<x-brand-preloader page-type="default" />');
        $this->assertStringContainsString('data-pl-theme="auto"', $html);
        $this->assertStringNotContainsString('background:#ffffff', str_replace(' ', '', $html));
    }

    public function test_the_studio_saves_a_theme_and_applies_it_to_every_configured_section(): void
    {
        PreloaderSettings::saveAssignment('default', array_merge(PreloaderSettings::safeDefault(), ['preset' => 'simple-pulse', 'enabled' => true, 'inherit' => false]));
        PreloaderSettings::saveAssignment('numbers', array_merge(PreloaderSettings::safeDefault(), ['preset' => 'simple-pulse', 'enabled' => true, 'inherit' => false]));
        $c = Livewire::actingAs($this->admin())->test(PreloaderStudio::class);
        $c->set('theme', 'dark')->call('save')->assertHasNoErrors();
        $this->assertSame('dark', PreloaderSettings::forPageType('default')['theme']);
        $c->call('applyThemeToAll', 'light');
        $this->assertSame('light', PreloaderSettings::forPageType('numbers')['theme']);
        $c->set('theme', 'sparkly')->call('save')->assertHasErrors('theme');
    }

    public function test_the_verify_check_passes_on_a_clean_install(): void
    {
        $this->assertSame('pass', (new PreloaderConfigSound)->run()->status);
    }
}
