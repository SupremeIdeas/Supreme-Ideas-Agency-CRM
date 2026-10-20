<?php

namespace Tests\Feature;

use App\Models\MasterMarketingDockPosition;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Widget Dock (master only): positions save per member, are validated, reset, and the dock script + config reach the signed-in layout. */
class MasterMarketingWidgetDockTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_member_can_save_and_reset_their_positions(): void
    {
        $u = User::factory()->create(['is_active' => true]);

        $this->actingAs($u)->postJson(route('widget-dock.store'), [
            'positions' => ['brain' => ['side' => 'left', 'y' => 0.42], 'journey' => ['side' => 'right', 'y' => 1]],
            'stamp' => 1700000000000,
        ])->assertOk();

        $row = MasterMarketingDockPosition::where('user_id', $u->id)->first();
        $this->assertSame('left', $row->positions['brain']['side']);
        $this->assertSame(1700000000000, $row->stamp);

        $this->actingAs($u)->deleteJson(route('widget-dock.reset'))->assertOk();
        $this->assertNull(MasterMarketingDockPosition::where('user_id', $u->id)->first());
    }

    public function test_the_middle_of_the_screen_is_not_a_valid_side_and_junk_is_rejected(): void
    {
        $u = User::factory()->create(['is_active' => true]);

        $this->actingAs($u)->postJson(route('widget-dock.store'), ['positions' => ['brain' => ['side' => 'center', 'y' => 0.5]], 'stamp' => 1])->assertStatus(422);
        $this->actingAs($u)->postJson(route('widget-dock.store'), ['positions' => ['brain' => ['side' => 'left', 'y' => 4]], 'stamp' => 1])->assertStatus(422);
        $this->actingAs($u)->postJson(route('widget-dock.store'), ['positions' => 'x', 'stamp' => 1])->assertStatus(422);
    }

    public function test_one_member_cannot_touch_anothers_positions(): void
    {
        $a = User::factory()->create(['is_active' => true]);
        $b = User::factory()->create(['is_active' => true]);
        MasterMarketingDockPosition::create(['user_id' => $a->id, 'positions' => ['brain' => ['side' => 'left', 'y' => 0.1]], 'stamp' => 5]);

        $this->actingAs($b)->deleteJson(route('widget-dock.reset'))->assertOk();
        $this->assertNotNull(MasterMarketingDockPosition::where('user_id', $a->id)->first());
    }

    public function test_guests_cannot_save(): void
    {
        $this->postJson(route('widget-dock.store'), ['positions' => [], 'stamp' => 1])->assertStatus(401);
    }

    public function test_the_dock_component_emits_the_saved_positions_for_the_member(): void
    {
        $u = User::factory()->create(['is_active' => true]);
        MasterMarketingDockPosition::create(['user_id' => $u->id, 'positions' => ['brain' => ['side' => 'left', 'y' => 0.3]], 'stamp' => 9]);

        $html = $this->actingAs($u)->blade('<x-master-marketing.widget-dock />')->__toString();

        $this->assertStringContainsString('data-dock-config', $html);
        $this->assertStringContainsString('"brain":{"side":"left","y":0.3}', $html);
        $this->assertStringContainsString(route('widget-dock.store', [], false), $html);
    }

    public function test_every_floating_widget_registers_with_the_dock(): void
    {
        foreach ([
            'resources/views/brain/floating.blade.php' => 'data-dock="brain"',
            'resources/views/n2n/floating-chat.blade.php' => 'data-dock="n2n"',
            'resources/views/livewire/journey-launcher.blade.php' => 'data-dock="journey"',
            'resources/views/livewire/wizard.blade.php' => 'data-dock="wizard"',
            'resources/views/components/layouts/customer.blade.php' => 'data-dock="whatsapp"',
            'resources/views/components/master-marketing/wl-resume-widget.blade.php' => 'data-dock="wl-resume"',
        ] as $file => $needle) {
            $this->assertStringContainsString($needle, file_get_contents(base_path($file)), $file);
        }
    }
}
