<?php

namespace Tests\Feature;

use App\Livewire\Admin\MoreApps as MoreAppsPage;
use App\Models\MoreApp;
use App\Models\User;
use App\Support\Business\SystemMoreApps;
use App\Support\Business\SystemMoreAppsCheck;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SystemMoreAppsTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_three_built_in_apps_are_seeded_idempotently_and_keep_admin_edits(): void
    {
        $this->assertSame(3, MoreApp::where('is_system', true)->count());
        MoreApp::where('system_key', 'brain')->update(['name' => 'Brain Pro', 'sort_order' => 5]);
        $this->assertSame(0, SystemMoreApps::ensure());
        $this->assertSame('Brain Pro', MoreApp::where('system_key', 'brain')->value('name'));
        MoreApp::where('system_key', 'brain')->delete();
        $this->assertSame(1, SystemMoreApps::ensure());   // self-heal
        foreach (SystemMoreApps::APPS as [, , $logo]) {
            $this->assertFileExists(public_path($logo));
        }
    }

    public function test_the_verify_check_passes_and_heals(): void
    {
        MoreApp::where('system_key', 'naara_chat')->delete();
        $r = (new SystemMoreAppsCheck)->run();
        $this->assertSame('pass', $r->status);
        $this->assertSame(3, MoreApp::where('is_system', true)->count());
    }

    public function test_built_in_apps_are_hidden_from_people_who_cannot_open_them(): void
    {
        $u = User::factory()->create();
        $this->assertFalse(SystemMoreApps::visibleTo('naara_chat', null));
        $this->assertFalse(SystemMoreApps::visibleTo('nonsense', $u));
        $custom = MoreApp::create(['name' => 'X', 'icon_url' => '/x.png', 'link_url' => 'https://example.com', 'sort_order' => 1, 'is_active' => true]);
        $this->assertTrue($custom->visibleTo(null));
        $this->assertTrue($custom->isExternalLink());
        $sys = MoreApp::where('system_key', 'brain')->first();
        $this->assertFalse($sys->isExternalLink());
    }

    public function test_admin_cannot_delete_a_built_in_app_but_can_rename_and_pause_it(): void
    {
        Role::findOrCreate('super_admin', 'web');
        $admin = User::factory()->create();
        $admin->assignRole('super_admin');
        $app = MoreApp::where('system_key', 'business_suite')->first();
        $c = Livewire::actingAs($admin)->test(MoreAppsPage::class);
        $c->call('delete', $app->id);
        $this->assertNotNull(MoreApp::find($app->id));
        $c->set("names.{$app->id}", 'Business Hub')->call('rename', $app->id);
        $this->assertSame('Business Hub', $app->fresh()->name);
        $c->call('toggle', $app->id);
        $this->assertFalse($app->fresh()->is_active);
    }
}
