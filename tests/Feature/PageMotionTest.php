<?php

namespace Tests\Feature;

use App\Livewire\Admin\PageMotionSettings;
use App\Models\User;
use App\Support\PageMotion;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Dashboard page transitions (MASTER-ONLY): admin switch, style and speed, emitted only on dashboard pages. */
class PageMotionTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        PageMotion::flush();
        parent::tearDown();
    }

    private function admin(): User
    {
        $u = User::factory()->create();
        $u->assignRole(Role::findOrCreate('admin', 'web'));

        return $u;
    }

    public function test_defaults_are_on_and_fluid_and_only_dashboard_pages_get_the_attributes(): void
    {
        $this->assertTrue(PageMotion::enabled());
        $this->assertStringContainsString('data-nx-pt="fluid"', PageMotion::htmlAttributes(true));
        $this->assertSame('', PageMotion::htmlAttributes(false), 'marketing and admin surfaces never animate');
    }

    public function test_the_admin_switch_turns_it_off_everywhere_and_style_and_speed_are_whitelisted(): void
    {
        $admin = $this->admin();
        Livewire::actingAs($admin)->test(PageMotionSettings::class)->set('enabled', false)->call('save')->assertSet('saved', 'Saved. Page transitions are off.');
        $this->assertSame('', PageMotion::htmlAttributes(true));
        $this->actingAs($admin)->followingRedirects()->get(route('dashboard'))->assertOk()->assertDontSee('data-nx-pt=', false);

        Livewire::actingAs($admin)->test(PageMotionSettings::class)->set('enabled', true)->set('style', 'fade')->set('speed', 'slow')->call('save');
        $this->assertStringContainsString('data-nx-pt="fade" data-nx-pt-speed="slow"', PageMotion::htmlAttributes(true));
        $this->actingAs($admin)->followingRedirects()->get(route('dashboard'))->assertOk()->assertSee('data-nx-pt="fade"', false)->assertSee('data-nx-page', false);

        Livewire::actingAs($admin)->test(PageMotionSettings::class)->set('style', '<script>')->call('save')->assertHasErrors('style');
    }

    public function test_only_staff_can_open_or_save_the_settings(): void
    {
        $member = User::factory()->create();
        $this->actingAs($member)->get(route('admin.page-motion'))->assertNotFound();
        Livewire::actingAs($member)->test(PageMotionSettings::class)->call('save')->assertForbidden();
    }

    public function test_the_admin_area_never_carries_the_motion_attributes(): void
    {
        $this->actingAs($this->admin())->get(route('admin.page-motion'))->assertOk()->assertDontSee('data-nx-pt=', false);
    }
}
