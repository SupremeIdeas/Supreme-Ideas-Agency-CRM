<?php

namespace Tests\Feature;

use App\Livewire\Admin\NumbersHero;
use App\Models\Setting;
use App\Models\User;
use App\Support\NumbersHeroContent;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Numbers landing hero (Numbers V6 §0): admin-managed title/description + up
 * to NumbersHeroContent::MAX_IMAGES ordered images, falling back to the
 * shipped seed images.
 */
class NumbersHeroTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        NumbersHeroContent::flush();
    }

    private function admin(): User
    {
        $u = User::factory()->create();
        $u->assignRole('super_admin');

        return $u;
    }

    public function test_it_falls_back_to_seed_images_before_any_admin_edit(): void
    {
        $c = NumbersHeroContent::current();
        $this->assertCount(4, $c['images']);
        $this->assertStringContainsString('/img/numbers/hero-1.webp', $c['images'][0]);
        $this->assertNotEmpty($c['title']);
    }

    public function test_admin_can_edit_title_and_description(): void
    {
        Livewire::actingAs($this->admin())->test(NumbersHero::class)
            ->set('title', 'Numbers, everywhere')
            ->set('description', 'A line for every need.')
            ->call('save')
            ->assertHasNoErrors();

        NumbersHeroContent::flush();
        $this->assertSame('Numbers, everywhere', NumbersHeroContent::title());
        $this->assertSame('A line for every need.', NumbersHeroContent::description());
        $this->assertDatabaseHas('settings', ['key' => NumbersHeroContent::TITLE_KEY]);
    }

    public function test_admin_can_upload_up_to_the_full_ten_image_cap_in_one_go(): void
    {
        Storage::fake('public');
        config(['filesystems.disks.wasabi.key' => null]);

        $uploads = array_map(
            fn ($i) => UploadedFile::fake()->image("slide{$i}.jpg", 1280, 540),
            range(1, 10)
        );

        Livewire::actingAs($this->admin())->test(NumbersHero::class)
            ->set('images', [])
            ->set('newImages', $uploads)
            ->call('save')
            ->assertHasNoErrors();

        NumbersHeroContent::flush();
        $this->assertCount(10, NumbersHeroContent::current()['images']);
    }

    public function test_the_hero_key_guard_is_scoped(): void
    {
        $this->assertTrue(NumbersHeroContent::isHeroKey('numbers.hero.title'));
        $this->assertFalse(NumbersHeroContent::isHeroKey('esim.hero.title'));
    }

    public function test_saving_a_hero_setting_flushes_the_cache(): void
    {
        NumbersHeroContent::current(); // warm cache
        Setting::setValue(NumbersHeroContent::TITLE_KEY, 'Fresh title', 'numbers');
        // The AppServiceProvider Setting-saved observer flushes on numbers.hero.*
        $this->assertSame('Fresh title', NumbersHeroContent::title());
    }
}
