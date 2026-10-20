<?php

namespace Tests\Feature\Brain;

use App\Livewire\Brain\Chat;
use App\Models\Brain\BrainMessage;
use App\Models\Brain\BrainThread;
use App\Models\AiUsageLog;
use App\Models\Setting;
use App\Models\User;
use App\Support\Brain\BrainSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

/** NaaraBrain shell (master-only): switch, isolation, send/answer flow through the one gateway, limits, sidebar controls. */
class BrainChatTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.anthropic.api_key' => 'sk-test', 'services.anthropic.model' => 'claude-sonnet-5-5', 'ai.backoff_ms' => 0]);
        Setting::setValue(BrainSettings::FLAG, true, 'brain');
    }

    private function reply(string $text = 'Here is a **plan**.'): void
    {
        Http::fake(['*/messages' => Http::response(['model' => 'claude-sonnet-5-5', 'content' => [['type' => 'text', 'text' => $text]], 'usage' => ['input_tokens' => 400, 'output_tokens' => 80]])]);
    }

    public function test_when_switched_off_a_person_sees_the_coming_soon_sheet_not_a_404(): void
    {
        Setting::setValue(BrainSettings::FLAG, false, 'brain');

        $this->actingAs(User::factory()->create(['is_active' => true]))->get('/brain')->assertOk()->assertSee('NaaraBrain');
    }

    public function test_guests_are_sent_to_login(): void
    {
        $this->get('/brain')->assertRedirect();
    }

    public function test_the_page_renders_the_empty_state_with_the_chat_logo_and_the_exact_composer(): void
    {
        $this->actingAs(User::factory()->create(['is_active' => true]))->get('/brain')->assertOk()
            ->assertSee('naarabrain-chat.webp', false)->assertSee('naarabrain-mark.webp', false)
            ->assertSee('<naara-composer', false)->assertSee('data-brain', false);
    }

    public function test_sending_stores_the_question_answers_through_the_gateway_and_logs_the_cost(): void
    {
        $this->reply();
        $u = User::factory()->create();
        $this->actingAs($u);

        Livewire::test(Chat::class)->call('send', 'Plan my trip to Kenya')->assertSee('Plan my trip to Kenya')->assertSee('plan');

        $t = BrainThread::first();
        $this->assertSame($u->id, (int) $t->user_id);
        $this->assertSame('Plan my trip to Kenya', $t->title);
        $this->assertSame(['user', 'assistant'], $t->messages()->pluck('role')->all());
        $this->assertSame('done', $t->messages()->where('role', 'assistant')->first()->status);
        $row = AiUsageLog::where('feature', 'brain_chat')->first();
        $this->assertNotNull($row);
        $this->assertSame($u->id, (int) $row->user_id);
        Http::assertSent(fn ($r) => str_contains(json_encode($r->data()['system']), 'You are an AI model') && $r->data()['messages'][0]['content'] === 'Plan my trip to Kenya');
    }

    public function test_a_failed_answer_is_shown_kindly_and_never_leaks_the_error(): void
    {
        Http::fake(['*/messages' => Http::response(['error' => 'secret internal detail'], 400)]);
        $this->actingAs(User::factory()->create());

        Livewire::test(Chat::class)->call('send', 'hello')->assertSee(__('brain.reply_failed'))->assertDontSee('secret internal detail');
        $this->assertSame('error', BrainMessage::where('role', 'assistant')->first()->status);
    }

    public function test_nothing_is_sent_without_the_anthropic_key(): void
    {
        config(['services.anthropic.api_key' => '']);
        Http::fake();
        $this->actingAs(User::factory()->create());

        Livewire::test(Chat::class)->call('send', 'hello')->assertSee(__('brain.not_connected'));
        Http::assertNothingSent();
        $this->assertSame(0, BrainThread::count());
    }

    public function test_the_daily_ceiling_blocks_the_next_question(): void
    {
        $this->reply();
        Setting::setValue('brain.daily_messages', 1, 'brain');
        $this->actingAs(User::factory()->create());

        Livewire::test(Chat::class)->call('send', 'one')->call('send', 'two');

        $this->assertSame(1, BrainMessage::where('role', 'user')->count());
        Http::assertSentCount(1);
    }

    public function test_an_overlong_message_is_refused(): void
    {
        Http::fake();
        $this->actingAs(User::factory()->create());

        Livewire::test(Chat::class)->call('send', str_repeat('a', BrainSettings::int('max_chars') + 1));

        Http::assertNothingSent();
        $this->assertSame(0, BrainMessage::count());
    }

    public function test_a_second_question_while_one_is_pending_is_refused(): void
    {
        Http::fake();
        $u = User::factory()->create();
        $t = BrainThread::create(['user_id' => $u->id, 'title' => 't']);
        BrainMessage::create(['thread_id' => $t->id, 'user_id' => $u->id, 'role' => 'assistant', 'body' => '', 'status' => 'pending']);
        $this->actingAs($u);

        Livewire::test(Chat::class, ['threadId' => $t->id])->call('send', 'again');

        $this->assertSame(1, BrainMessage::count());
    }

    public function test_a_person_can_never_open_change_or_delete_someone_elses_conversation(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $theirs = BrainThread::create(['user_id' => $owner->id, 'title' => 'Private plan', 'depth' => 'balanced']);
        BrainMessage::create(['thread_id' => $theirs->id, 'user_id' => $owner->id, 'role' => 'user', 'body' => 'my secret question', 'status' => 'done']);
        $this->actingAs($other);

        Livewire::test(Chat::class)->call('open', $theirs->id)->assertSet('threadId', null)->assertDontSee('my secret question')->assertDontSee('Private plan')
            ->call('delete', $theirs->id)->call('togglePin', $theirs->id)->call('archive', $theirs->id);
        Livewire::test(Chat::class, ['threadId' => $theirs->id])->assertSet('threadId', null)->assertDontSee('my secret question');

        $theirs->refresh();
        $this->assertFalse($theirs->pinned);
        $this->assertNull($theirs->archived_at);
        $this->assertSame(1, BrainThread::count());
    }

    public function test_the_sidebar_groups_history_searches_and_applies_controls(): void
    {
        $u = User::factory()->create();
        $a = BrainThread::create(['user_id' => $u->id, 'title' => 'Kenya eSIM question', 'last_message_at' => now()]);
        $b = BrainThread::create(['user_id' => $u->id, 'title' => 'Old invoice chat', 'last_message_at' => now()->subDays(40)]);
        BrainMessage::create(['thread_id' => $b->id, 'user_id' => $u->id, 'role' => 'user', 'body' => 'about the zebra', 'status' => 'done']);
        $this->actingAs($u);

        Livewire::test(Chat::class)->assertSee(__('brain.g_today'))->assertSee('Kenya eSIM question')->assertSee('Old invoice chat')
            ->set('q', 'zebra')->assertSee('Old invoice chat')->assertDontSee('Kenya eSIM question')
            ->set('q', '')->call('open', $a->id)->call('setDepth', 'quick')->call('setLength', 'concise')->call('togglePin', $a->id);

        $a->refresh();
        $this->assertSame('quick', $a->depth);
        $this->assertSame('concise', $a->length);
        $this->assertTrue($a->pinned);

        Livewire::test(Chat::class)->call('archive', $a->id)->assertDontSee('Kenya eSIM question');
        $this->assertNotNull($a->fresh()->archived_at);
    }

    public function test_depth_picks_the_configured_model_class_when_the_plan_allows_it(): void
    {
        config(['ai.models.top' => 'claude-opus-5-5']);
        $this->reply();
        $this->seed(\Database\Seeders\RoleSeeder::class);
        $u = User::factory()->create();
        $u->assignRole('super_admin');   // admins are never limited, so every depth is open
        $t = BrainThread::create(['user_id' => $u->id, 'title' => 'x', 'depth' => 'deep']);
        $this->actingAs($u);

        Livewire::test(Chat::class, ['threadId' => $t->id])->call('send', 'hard one');

        Http::assertSent(fn ($r) => $r->data()['model'] === 'claude-opus-5-5');
    }

    public function test_the_answer_is_rendered_as_safe_markdown(): void
    {
        $this->reply("Hello **bold** <script>alert(1)</script> [x](javascript:alert(1))");
        $this->actingAs(User::factory()->create());

        $html = Livewire::test(Chat::class)->call('send', 'hi')->html();

        $this->assertStringContainsString('<strong>bold</strong>', $html);
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringNotContainsString('href="javascript:', $html);
    }

    public function test_the_view_carries_no_hard_coded_colour(): void
    {
        $blade = file_get_contents(resource_path('views/livewire/brain/chat.blade.php'));
        $layout = file_get_contents(resource_path('views/components/layouts/brain.blade.php'));

        foreach ([$blade, $layout] as $src) {
            $this->assertSame(0, preg_match('/#[0-9a-fA-F]{3,8}\b/', preg_replace('/\{\{--.*?--\}\}/s', '', $src)), 'no hex colour in a Brain view');
            $this->assertSame(0, preg_match('/\b(bg|text|border)-(slate|gray|zinc|red|green|blue|indigo|teal|navy)-\d{2,3}\b/', $src), 'no Tailwind palette class');
            $this->assertSame(0, preg_match('/\bdark:/', $src), 'no per-element dark: colour');
        }
    }

    public function test_the_dedicated_admin_page_controls_the_switch_and_the_widget(): void
    {
        $this->seed(\Database\Seeders\RoleSeeder::class);
        $a = User::factory()->create(['is_active' => true]);
        $a->assignRole('super_admin');
        $this->actingAs($a)->get(route('admin.brain'))->assertOk()->assertSee('Floating button');

        Livewire::test(\App\Livewire\Admin\Brain\Settings::class)->set('on', true)->set('daily', 12)->set('side', 'right')->set('offset', 140)
            ->set('sections', ['dashboard', 'numbers'])->set('hours', 6)->call('save')->assertHasNoErrors();

        $this->assertTrue(BrainSettings::enabled());
        $this->assertSame(12, BrainSettings::int('daily_messages'));
        $this->assertSame('right', BrainSettings::widgetSide());
        $this->assertSame(140, BrainSettings::widgetOffset());
        $this->assertSame(['dashboard', 'numbers'], BrainSettings::widgetSections());
        $this->assertSame(6, BrainSettings::dismissHours());

        Livewire::test(\App\Livewire\Admin\Brain\Settings::class)->set('on', false)->set('side', 'middle')->call('save')->assertHasErrors('side');
        Livewire::test(\App\Livewire\Admin\Brain\Settings::class)->set('offset', 5)->call('save')->assertHasErrors('offset');
        Livewire::test(\App\Livewire\Admin\Brain\Settings::class)->set('sections', ['nope'])->call('save')->assertHasErrors('sections.0');
    }

    public function test_the_admin_page_is_closed_to_members(): void
    {
        $this->seed(\Database\Seeders\RoleSeeder::class);
        $this->actingAs(User::factory()->create(['is_active' => true]))->get(route('admin.brain'))->assertStatus(404);
        Livewire::actingAs(User::factory()->create())->test(\App\Livewire\Admin\Brain\Settings::class)->assertForbidden();
    }

    public function test_the_widget_shows_only_in_ticked_sections_on_the_chosen_side_and_never_in_excluded_places(): void
    {
        Setting::setValue('brain.widget_side', 'right', 'brain');
        Setting::setValue('brain.widget_offset', 120, 'brain');
        Setting::setValue('brain.widget_sections', ['dashboard', 'esim'], 'brain');
        $u = User::factory()->create(['is_active' => true]);

        $home = $this->actingAs($u)->get('/dashboard');
        $home->assertOk()->assertSee('data-brain-widget="right"', false)->assertSee('--brn-fab-b: 120px', false)->assertSee(route('brain.chat'), false);
        $this->actingAs($u)->get('/catalogue')->assertSee('data-brain-widget', false);
        $this->actingAs($u)->get('/wallet')->assertDontSee('data-brain-widget', false);   // section not ticked
        $this->actingAs($u)->get('/brain')->assertDontSee('data-brain-widget', false);    // never inside Brain itself

        Setting::setValue('brain.widget_enabled', false, 'brain');
        $this->actingAs($u)->get('/dashboard')->assertDontSee('data-brain-widget', false);
        Setting::setValue('brain.widget_enabled', true, 'brain');
        Setting::setValue(BrainSettings::FLAG, false, 'brain');
        $this->actingAs($u)->get('/dashboard')->assertDontSee('data-brain-widget', false);
    }

    public function test_brain_has_its_own_entry_in_the_more_menu_only_while_it_is_on(): void
    {
        $u = User::factory()->create(['is_active' => true]);
        $routes = fn () => array_column(\App\Support\BottomNav::resolveMain($u)['more'], 'route');

        $this->assertContains('brain.chat', $routes());
        Setting::setValue(BrainSettings::FLAG, false, 'brain');
        $this->assertNotContains('brain.chat', $routes());
    }
}
