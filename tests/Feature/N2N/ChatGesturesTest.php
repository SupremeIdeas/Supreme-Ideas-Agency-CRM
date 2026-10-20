<?php

namespace Tests\Feature\N2N;

use App\Livewire\Admin\N2N\ChatControls;
use App\Livewire\N2N\GestureSettings;
use App\Models\Setting;
use App\Models\User;
use App\Services\N2N\Messaging\ChatGesturePrefs;
use App\Support\Verify\Checks\N2NChatGesturesSound;
use Livewire\Livewire;
use Tests\Feature\N2N\N2NTestCase;

/** Gestures blueprint: per-member settings (clamped, layered over admin defaults), the settings screen, anonymous counters, verify. */
class ChatGesturesTest extends N2NTestCase
{

    public function test_settings_are_layered_and_always_clamped(): void
    {
        $u = User::factory()->create();
        $this->assertSame(ChatGesturePrefs::DEFAULTS, ChatGesturePrefs::for($u));

        Setting::setValue('n2n.gesture_defaults', ['swipe_start' => 'star', 'long_press_ms' => 600]);
        $p = ChatGesturePrefs::for($u);
        $this->assertSame('star', $p['swipe_start']);
        $this->assertSame(600, $p['long_press_ms']);

        ChatGesturePrefs::save($u, ['swipe_start' => 'forward', 'long_press_ms' => 5000, 'swipe_commit_px' => 1, 'double_tap_code' => 'bogus', 'buttons_only' => true]);
        $p = ChatGesturePrefs::for($u->fresh());
        $this->assertSame('forward', $p['swipe_start']);        // own choice beats the admin default
        $this->assertSame(1000, $p['long_press_ms']);           // clamped
        $this->assertSame(40, $p['swipe_commit_px']);
        $this->assertSame('love', $p['double_tap_code']);       // unknown reaction falls back
        $this->assertTrue($p['buttons_only']);

        ChatGesturePrefs::reset($u);
        $this->assertSame('star', ChatGesturePrefs::for($u->fresh())['swipe_start']);
        $this->assertArrayHasKey('glyph', ChatGesturePrefs::forEngine($u));
    }

    public function test_the_settings_screen_saves_validates_and_resets(): void
    {
        $u = User::factory()->create();
        Livewire::actingAs($u)->test(GestureSettings::class)
            ->set('swipeStart', 'star')->set('doubleTapReact', false)->set('longPressMs', 700)->call('save')->assertHasNoErrors()
            ->set('longPressMs', 5000)->call('save')->assertHasErrors(['longPressMs'])
            ->call('resetToDefaults')->assertSet('swipeStart', 'info')->assertSet('doubleTapReact', true);
        $this->assertNull($u->fresh()->n2n_gesture_prefs);
    }

    public function test_the_settings_page_renders_with_the_engine_config_and_a_practice_bubble(): void
    {
        $u = $this->user();
        $res = $this->actingAs($u)->get(route('n2n.gestures'));
        $this->assertSame(200, $res->status(), 'redirected to '.$res->headers->get('Location'));
        $html = $res->getContent();
        $this->assertStringContainsString('id="ng-config"', $html);
        $this->assertStringContainsString('data-ctx=', $html);
        $this->assertStringContainsString('ng-practice-log', $html);
        $this->assertStringContainsString('"double_tap_code":"love"', $html);
    }

    public function test_counters_accept_only_known_gestures_and_keep_no_identity(): void
    {
        $u = $this->user();
        $this->actingAs($u)->postJson(route('n2n.api.gesture-stat'), ['s' => [
            ['g' => 'double_tap', 'o' => 'committed', 'd' => 'touch', 'n' => 3],
            ['g' => 'double_tap', 'o' => 'committed', 'd' => 'touch', 'n' => 2],
            ['g' => 'evil', 'o' => 'fired'], ['g' => 'swipe_end', 'o' => 'nuke'],
            ['g' => 'swipe_end', 'o' => 'cancelled', 'd' => 'mouse', 'n' => 999],
        ]])->assertOk();
        $this->assertSame(5, (int) \DB::table('n2n_gesture_stats')->where(['gesture' => 'double_tap', 'outcome' => 'committed', 'device' => 'touch'])->value('n'));
        $this->assertSame(50, (int) \DB::table('n2n_gesture_stats')->where(['gesture' => 'swipe_end', 'device' => 'mouse'])->value('n'));   // one report is capped
        $this->assertSame(2, \DB::table('n2n_gesture_stats')->count());
        $this->assertFalse(\Schema::hasColumn('n2n_gesture_stats', 'user_id'));
        auth()->logout();
        $this->postJson(route('n2n.api.gesture-stat'), ['s' => []])->assertStatus(401);
    }

    public function test_admin_defaults_and_stats_page_is_admin_only(): void
    {
        $this->seed(\Database\Seeders\RoleSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        Livewire::actingAs($admin)->test(ChatControls::class)->set('swipeStart', 'forward')->call('save')->assertHasNoErrors();
        $this->assertSame('forward', Setting::getValue('n2n.gesture_defaults')['swipe_start']);
        $this->assertArrayNotHasKey('buttons_only', Setting::getValue('n2n.gesture_defaults'));
        $user = User::factory()->create();
        $user->assignRole('user');
        Livewire::actingAs($user)->test(ChatControls::class)->assertStatus(404);
    }

    public function test_verify_check_passes(): void
    {
        $this->assertSame('pass', (new N2NChatGesturesSound)->run()->status);
    }

    public function test_list_swipe_is_a_clamped_setting_and_its_counters_are_accepted(): void
    {
        $this->assertTrue(ChatGesturePrefs::clean([])['list_swipe'], 'on by default');
        $this->assertFalse(ChatGesturePrefs::clean(['list_swipe' => false])['list_swipe']);
        $this->assertContains('list_pin', ChatGesturePrefs::GESTURES);
        $this->assertContains('list_archive', ChatGesturePrefs::GESTURES);
        $this->assertContains('undone', ChatGesturePrefs::OUTCOMES);
        $u = User::factory()->create(['email_verified_at' => now(), 'is_active' => true]);
        $this->actingAs($u)->postJson(route('n2n.api.gesture-stat'), ['s' => [['g' => 'list_pin', 'o' => 'committed', 'd' => 'touch', 'n' => 2], ['g' => 'list_pin', 'o' => 'undone', 'd' => 'touch', 'n' => 1]]])->assertOk();
        $this->assertSame(3, (int) \DB::table('n2n_gesture_stats')->where('gesture', 'list_pin')->sum('n'));
        Livewire::actingAs($u)->test(\App\Livewire\N2N\GestureSettings::class)->assertSee('Swipe chats in your list')->set('listSwipe', false)->call('save');
        $this->assertFalse(ChatGesturePrefs::for($u->fresh())['list_swipe']);
    }
}
