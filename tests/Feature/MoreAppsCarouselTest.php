<?php

namespace Tests\Feature;

use App\Livewire\Admin\MoreApps as AdminMoreApps;
use App\Models\MoreApp;
use App\Models\User;
use App\Support\MoreApps as MoreAppsCache;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Blade;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * More Apps carousel — master-only cross-promotion in the mobile "More"
 * sheet (App\Support\FeatureEntitlements::isMaster()). A white-label fork is
 * a byte-for-byte copy of this same code, so the boundary must hold even
 * against a direct component call that skips the (already-hidden) nav entry.
 */
class MoreAppsCarouselTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        config()->set('updater.product_identifier', 'naarasim-core');
        \App\Models\MoreApp::query()->delete();   // these tests are about admin-added apps; the built-in rows have their own test (SystemMoreAppsTest)
        MoreAppsCache::flush();
    }

    private function admin(): User
    {
        $u = User::factory()->create();
        $u->assignRole('admin');

        return $u;
    }

    private function app(array $extra = []): MoreApp
    {
        return MoreApp::create(array_merge([
            'name' => 'Naara Business',
            'icon_url' => 'https://cdn.example.com/naara-business.webp',
            'link_url' => 'https://business.naarasim.com',
            'sort_order' => 0,
            'is_active' => true,
        ], $extra));
    }

    public function test_admin_can_add_an_app_and_it_appears_in_the_carousel(): void
    {
        Livewire::actingAs($this->admin())->test(AdminMoreApps::class)
            ->set('name', 'Naara Business')
            ->set('icon', UploadedFile::fake()->image('icon.png', 128, 128))
            ->set('link_url', 'https://business.naarasim.com')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('more_apps', ['name' => 'Naara Business']);
        $this->assertCount(1, MoreAppsCache::active()->where('is_system', false));
    }

    public function test_a_bad_link_url_is_rejected(): void
    {
        Livewire::actingAs($this->admin())->test(AdminMoreApps::class)
            ->set('name', 'Evil')
            ->set('icon', UploadedFile::fake()->image('icon.png'))
            ->set('link_url', 'javascript:alert(1)')
            ->call('save')
            ->assertHasErrors(['link_url']);
    }

    public function test_non_admin_gets_403_on_the_admin_page(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');

        Livewire::actingAs($user)->test(AdminMoreApps::class)->assertForbidden();
    }

    public function test_toggle_and_delete_take_effect_immediately(): void
    {
        $app = $this->app();

        Livewire::actingAs($this->admin())->test(AdminMoreApps::class)
            ->call('toggle', $app->id);
        $this->assertFalse($app->fresh()->is_active);
        $this->assertCount(0, MoreAppsCache::active()->where('is_system', false));

        Livewire::actingAs($this->admin())->test(AdminMoreApps::class)
            ->call('delete', $app->id);
        $this->assertDatabaseMissing('more_apps', ['id' => $app->id]);
    }

    public function test_inactive_apps_are_excluded_from_the_active_list(): void
    {
        $this->app(['name' => 'Live App']);
        $this->app(['name' => 'Paused App', 'is_active' => false]);

        $active = MoreAppsCache::active();
        $this->assertCount(1, $active);
        $this->assertSame('Live App', $active->first()->name);
    }

    public function test_carousel_renders_on_master_and_is_absent_on_a_white_label_fork(): void
    {
        $this->app();

        config()->set('updater.product_identifier', 'naarasim-core');
        MoreAppsCache::flush();
        $html = Blade::render('<x-more-apps-carousel />');
        $this->assertStringContainsString('Naara Business', $html);
        $this->assertStringContainsString('nx-more-apps', $html);

        config()->set('updater.product_identifier', 'naarasim-whitelabel');
        MoreAppsCache::flush();
        $html = Blade::render('<x-more-apps-carousel />');
        $this->assertStringNotContainsString('Naara Business', $html);
        $this->assertStringNotContainsString('nx-more-apps', $html);
    }

    public function test_a_white_label_fork_cannot_reach_the_admin_page(): void
    {
        config()->set('updater.product_identifier', 'naarasim-whitelabel');

        Livewire::actingAs($this->admin())->test(AdminMoreApps::class)
            ->assertStatus(404);
    }

    public function test_carousel_is_absent_when_no_apps_are_configured(): void
    {
        $html = Blade::render('<x-more-apps-carousel />');
        $this->assertStringNotContainsString('nx-more-apps', $html);
    }
}
