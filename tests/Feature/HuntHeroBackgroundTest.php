<?php

namespace Tests\Feature;

use App\Livewire\Admin\HuntHero;
use App\Livewire\BrandHunt;
use App\Models\Setting;
use App\Models\User;
use App\Support\HeroBackground;
use App\Support\HuntHeroBackground;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The Hunt + Get-listed hero (owner request): the same hero system as the
 * dashboard home, under its own hunt.hero.* namespace, but an unset Hunt
 * image falls back to the dashboard hero image so it "just works".
 */
class HuntHeroBackgroundTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    private function admin(): User
    {
        $u = User::factory()->create(['is_active' => true]);
        $u->assignRole('admin');

        return $u;
    }

    public function test_defaults_and_dashboard_image_fallback(): void
    {
        $this->assertSame('Connect', HuntHeroBackground::titleFirstWord());
        $this->assertSame('With Brands: Build Good Business Relationships', HuntHeroBackground::titleRestWords());

        Setting::setValue(HeroBackground::LIGHT_KEY, 'https://cdn/dash.webp', 'brand');
        HeroBackground::flush();
        HuntHeroBackground::flush();

        $this->assertSame('https://cdn/dash.webp', HuntHeroBackground::light());

        Setting::setValue(HuntHeroBackground::LIGHT_KEY, 'https://cdn/hunt.webp', 'rewards');
        HuntHeroBackground::flush();

        $this->assertSame('https://cdn/hunt.webp', HuntHeroBackground::light());
    }

    public function test_admin_page_is_gated_and_saves_overrides(): void
    {
        Livewire::actingAs(User::factory()->create())->test(HuntHero::class)->assertStatus(403);

        Livewire::actingAs($this->admin())->test(HuntHero::class)
            ->assertOk()
            ->set('title', 'Brand Rush')
            ->set('description', 'Follow, earn, repeat.')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('Brand Rush', HuntHeroBackground::title());
        $this->assertSame('Follow, earn, repeat.', HuntHeroBackground::description());
    }

    public function test_hunt_page_renders_the_hero(): void
    {
        Livewire::actingAs(User::factory()->create())->test(BrandHunt::class)
            ->assertSee('nx-home-hero', false)
            ->assertSee('Connect')->assertSee('Build Good Business Relationships');
    }

    public function test_committed_default_artwork_shows_until_an_admin_uploads(): void
    {
        HuntHeroBackground::flush();

        foreach ([HuntHeroBackground::DEFAULT_LIGHT, HuntHeroBackground::DEFAULT_DARK, HuntHeroBackground::LISTED_DEFAULT_LIGHT, HuntHeroBackground::LISTED_DEFAULT_DARK] as $path) {
            $this->assertFileExists(public_path($path));
        }

        $this->assertStringContainsString('hunt-hero-light.webp', (string) HuntHeroBackground::light());
        $this->assertStringContainsString('hunt-hero-dark.webp', (string) HuntHeroBackground::dark());
        $this->assertStringContainsString('listed-hero-light.webp', (string) HuntHeroBackground::listedLight());
        $this->assertTrue(HuntHeroBackground::showsImage());

        Setting::setValue(HuntHeroBackground::LIGHT_KEY, 'https://cdn/mine.webp', 'rewards');
        HuntHeroBackground::flush();

        $this->assertSame('https://cdn/mine.webp', HuntHeroBackground::light());
        $this->assertSame('https://cdn/mine.webp', HuntHeroBackground::listedLight());
        $this->assertStringContainsString('listed-hero-dark.webp', (string) HuntHeroBackground::listedDark());
    }
}
