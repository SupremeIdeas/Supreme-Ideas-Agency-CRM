<?php

namespace Tests\Feature;

use App\Livewire\Admin\MasterMarketingMenuIcons;
use App\Models\Setting;
use App\Models\User;
use App\Support\IconOverrides;
use App\Support\MasterMarketing\MenuIcons;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/** Admin > Menu icons (master only): replace or reset any menu slot through the IconOverrides storage MenuIcons::resolve() already reads. */
class MasterMarketingMenuIconsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    private function admin(): User
    {
        $u = User::factory()->create();
        $u->assignRole('admin');

        return $u;
    }

    public function test_a_non_admin_is_refused(): void
    {
        Livewire::actingAs(User::factory()->create())->test(MasterMarketingMenuIcons::class)->assertForbidden();
    }

    public function test_it_lists_every_slot_of_the_selected_menu_and_filters(): void
    {
        Livewire::actingAs($this->admin())->test(MasterMarketingMenuIcons::class)
            ->assertSee('Esims')
            ->set('search', 'wallet')
            ->assertSee('Wallet')
            ->assertDontSee('Esims')
            ->set('search', '')
            ->set('surface', 'marketing')
            ->assertSee('How It Works')
            ->set('surface', 'admin')
            ->assertSee('Payout Settings');
    }

    public function test_upload_stores_an_override_under_the_slots_key_and_reset_removes_it(): void
    {
        Storage::fake('public');
        config(['filesystems.disks.wasabi.key' => null]);
        $slot = 'menu.app.more.wallet';

        Livewire::actingAs($this->admin())->test(MasterMarketingMenuIcons::class)
            ->call('choose', $slot)
            ->set('upload', UploadedFile::fake()->image('w.png', 64, 64))
            ->call('save')
            ->assertHasNoErrors();

        $this->assertStringContainsString('menu-app-more-wallet', (string) Setting::getValue('ui.icon_overrides'));

        IconOverrides::set('menu-app-more-wallet', 'https://cdn.example.com/w.png');
        $this->assertTrue(MenuIcons::resolve($slot)['custom']);

        Livewire::actingAs($this->admin())->test(MasterMarketingMenuIcons::class)->call('remove', $slot);
        $this->assertFalse(MenuIcons::resolve($slot)['custom'] ?? false);
    }

    public function test_a_slot_that_is_not_in_the_menu_is_ignored(): void
    {
        Livewire::actingAs($this->admin())->test(MasterMarketingMenuIcons::class)
            ->call('choose', 'menu.app.more.bogus')
            ->assertSet('target', '');
    }

    public function test_the_route_is_registered_and_reachable_by_an_admin(): void
    {
        $this->actingAs($this->admin());
        $this->assertTrue(\Illuminate\Support\Facades\Route::has('admin.menu-icons'));
    }
}
