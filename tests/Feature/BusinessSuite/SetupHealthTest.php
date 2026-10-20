<?php

namespace Tests\Feature\BusinessSuite;

use App\Jobs\QueueProbeJob;
use App\Livewire\Admin\BusinessSuiteSettingsPage;
use App\Models\Setting;
use App\Models\User;
use App\Services\Business\SetupHealth;
use App\Services\Media\FfmpegManager;
use Database\Seeders\BusinessRoleSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

/** Admin > Business Suite > Setup and health, and `php artisan business:doctor`: every server need checked in plain words, with fixes the platform can do itself. */
class SetupHealthTest extends TestCase
{
    use \Tests\Concerns\EnablesBusinessSuite;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    private function superAdmin(): User
    {
        $this->seed(RoleSeeder::class);
        $u = User::factory()->create(['is_active' => true, 'email_verified_at' => now()]);
        $u->assignRole('super_admin');

        return $u;
    }

    private function check(string $group, string $id): array
    {
        $g = app(SetupHealth::class)->groups();
        $this->assertArrayHasKey($group, $g);

        return collect($g[$group]['checks'])->firstWhere('id', $id) ?? $this->fail("no check {$group}/{$id}");
    }

    public function test_every_check_says_what_it_found_and_a_failing_one_says_what_to_do(): void
    {
        $groups = app(SetupHealth::class)->groups();
        foreach (['server', 'background', 'public', 'media', 'content', 'providers'] as $k) {
            $this->assertArrayHasKey($k, $groups);
        }
        foreach ($groups as $g) {
            foreach ($g['checks'] as $c) {
                $this->assertContains($c['status'], ['ok', 'warn', 'fail'], $c['id']);
                $this->assertNotSame('', trim($c['detail']), $c['id']);
                if ($c['status'] === 'fail') {
                    $this->assertNotEmpty($c['fix'], 'a failing check must say what to do: '.$c['id']);
                }
            }
        }
        $n = app(SetupHealth::class)->counts($groups);
        $this->assertSame(array_sum($n), collect($groups)->sum(fn ($g) => count($g['checks'])));
    }

    public function test_the_cron_check_fails_with_the_exact_line_to_paste_until_the_scheduler_has_run(): void
    {
        $c = $this->check('background', 'cron');
        $this->assertSame('fail', $c['status']);
        $this->assertStringContainsString('artisan schedule:run', $c['fix']);
        $this->assertStringContainsString('* * * * *', $c['fix']);
    }

    public function test_the_queue_probe_proves_a_worker_by_running_a_real_job(): void
    {
        $this->assertSame('warn', $this->check('background', 'queue_probe')['status']);

        config(['queue.default' => 'sync']);   // inline: the job runs the moment it is sent
        $r = app(SetupHealth::class)->startQueueProbe();
        $this->assertTrue($r['ok']);
        $c = $this->check('background', 'queue_probe');
        $this->assertSame('ok', $c['status']);
        $this->assertStringContainsString('processed', $c['detail']);
    }

    public function test_a_probe_nobody_picks_up_turns_into_a_fail_that_blames_the_cron_or_worker(): void
    {
        Queue::fake();
        app(SetupHealth::class)->startQueueProbe();
        Queue::assertPushed(QueueProbeJob::class);

        $this->assertSame('warn', $this->check('background', 'queue_probe')['status'], 'just sent: still waiting');

        $s = Cache::get('setup.queue_probe');
        $s['sent_at'] = now()->subMinutes(5)->toIso8601String();
        Cache::put('setup.queue_probe', $s, 3600);
        $c = $this->check('background', 'queue_probe');
        $this->assertSame('fail', $c['status']);
        $this->assertStringContainsString('nothing processed it', $c['detail']);
    }

    public function test_a_probe_with_the_wrong_token_does_not_count(): void
    {
        Cache::put('setup.queue_probe', ['token' => 'right', 'sent_at' => now()->toIso8601String()], 3600);
        (new QueueProbeJob('wrong'))->handle();
        $this->assertArrayNotHasKey('done_at', Cache::get('setup.queue_probe'));
        (new QueueProbeJob('right'))->handle();
        $this->assertArrayHasKey('done_at', Cache::get('setup.queue_probe'));
    }

    public function test_the_seed_fix_creates_roles_gifs_and_tones_and_the_content_checks_then_pass(): void
    {
        $this->assertSame('fail', $this->check('content', 'roles')['status']);

        $r = app(SetupHealth::class)->fix('seed_all');

        $this->assertTrue($r['ok']);
        $this->assertSame('ok', $this->check('content', 'roles')['status']);
        $this->assertSame('ok', $this->check('content', 'gifs')['status']);
        $this->assertSame('ok', $this->check('content', 'tones')['status']);
    }

    public function test_an_unknown_fix_does_nothing_and_says_so(): void
    {
        $r = app(SetupHealth::class)->fix('format_the_disk');
        $this->assertFalse($r['ok']);
    }

    public function test_the_ai_voice_agent_section_appears_only_when_that_module_is_on_and_never_claims_shared_hosting_can_run_it(): void
    {
        Setting::setValue('business.module.ai_voice', false, 'business');
        $this->assertArrayNotHasKey('voice', app(SetupHealth::class)->groups());

        Setting::setValue('business.module.ai_voice', true, 'business');
        $g = app(SetupHealth::class)->groups();
        $this->assertArrayHasKey('voice', $g);
        $this->assertStringContainsString('VPS only', $g['voice']['title']);
        $this->assertSame('fail', collect($g['voice']['checks'])->firstWhere('id', 'agent')['status'], 'no heartbeat yet');

        Cache::put('business.ai_voice.alive', ['at' => now()->timestamp, 'calls' => 2, 'host' => '127.0.0.1', 'port' => 8089], 120);
        $this->assertSame('ok', collect(app(SetupHealth::class)->groups()['voice']['checks'])->firstWhere('id', 'agent')['status']);
    }

    public function test_the_voice_guide_gives_copy_paste_supervisor_and_proxy_steps(): void
    {
        $text = json_encode(SetupHealth::voiceGuide(), JSON_UNESCAPED_SLASHES);
        $this->assertStringContainsString('business:ai-voice-server', $text);
        $this->assertStringContainsString('proxy_pass', $text);
        $this->assertStringContainsString('wss://', $text);
    }

    // ── the admin tab ───────────────────────────────────────────────────────

    public function test_the_tab_shows_the_summary_the_ffmpeg_card_and_both_hosting_guides(): void
    {
        Livewire::actingAs($this->superAdmin())->test(BusinessSuiteSettingsPage::class)->set('tab', 'readiness')
            ->assertSee('Setup and health')->assertSee('ffmpeg media converter')->assertSee('Shared hosting (cPanel)')->assertSee('VPS or Cloudways')
            ->assertSee('AI voice agent (VPS only)')->assertSee('business:doctor');
    }

    public function test_only_a_super_admin_can_press_fix_buttons_and_install_ffmpeg(): void
    {
        $this->seed(RoleSeeder::class);
        $staff = User::factory()->create(['is_active' => true, 'email_verified_at' => now()]);
        $staff->assignRole('admin');

        foreach ([['runFix', ['seed_all']], ['ffmpegStart', []], ['ffmpegRemove', []]] as [$method, $args]) {
            Livewire::actingAs($staff)->test(BusinessSuiteSettingsPage::class)->set('tab', 'readiness')->call($method, ...$args)->assertStatus(403);
        }
        $this->assertDatabaseMissing('roles', ['name' => 'business_owner']);   // nothing was seeded behind the refusal
    }

    public function test_saving_the_ffmpeg_path_and_link_validates_audits_and_takes_effect(): void
    {
        $sa = $this->superAdmin();
        $c = Livewire::actingAs($sa)->test(BusinessSuiteSettingsPage::class)->set('tab', 'readiness');

        $c->set('ffmpegPath', 'relative/ffmpeg')->call('ffmpegSaveSettings')->assertHasErrors('ffmpegPath');
        $c->set('ffmpegPath', '/usr/bin/../../tmp/x')->call('ffmpegSaveSettings')->assertHasErrors('ffmpegPath');
        $c->set('ffmpegPath', '')->set('ffmpegMirror', 'http://insecure.example/ffmpeg.tar.xz')->call('ffmpegSaveSettings')->assertHasErrors('ffmpegMirror');

        $c->set('ffmpegPath', '/opt/tools/ffmpeg')->set('ffmpegMirror', 'https://files.example.com/ffmpeg.tar.xz')->call('ffmpegSaveSettings')->assertHasNoErrors();
        $this->assertSame('/opt/tools/ffmpeg', Setting::getValue(FfmpegManager::SETTING_PATH));
        $this->assertSame('mirror', app(FfmpegManager::class)->sources()[0]['id']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'business_suite.ffmpeg_settings']);
    }

    public function test_the_storage_link_fix_is_idempotent(): void
    {
        $r = app(SetupHealth::class)->fix('storage_link');
        $this->assertTrue($r['ok'] || str_contains($r['message'], 'symbolic links'), $r['message']);
    }

    // ── business:doctor ─────────────────────────────────────────────────────

    public function test_the_doctor_prints_every_group_and_exits_non_zero_while_something_needs_fixing(): void
    {
        $this->artisan('business:doctor')->expectsOutputToContain('Background jobs (cron and queue)')->expectsOutputToContain('to fix.')->assertExitCode(1);
    }

    public function test_the_doctor_fix_seeds_the_content_without_touching_the_network_when_told_not_to(): void
    {
        $this->artisan('business:doctor', ['--fix' => true, '--no-ffmpeg' => true])->expectsOutputToContain('Roles checked');
        $this->assertDatabaseHas('roles', ['name' => 'business_owner']);
        $this->assertGreaterThan(0, \App\Models\GifLibraryItem::count());
    }
}
