<?php

namespace Tests\Feature;

use App\Livewire\Account\SoundSettings;
use App\Livewire\Admin\NotificationTones as AdminTones;
use App\Models\NotificationTone;
use App\Models\User;
use App\Models\UserSoundPref;
use App\Support\MediaStorage;
use App\Support\NotificationTones;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/** Batch 2: notification sound + ringtone library, per-member picks, and the foreground playback config. */
class NotificationTonesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(MediaStorage::disk());
        NotificationTones::forget();
    }

    private function wav(int $ms = 200): string
    {
        $rate = 8000;
        $n = (int) ($rate * $ms / 1000);
        $data = str_repeat("\x00\x10", $n);

        return 'RIFF'.pack('V', 36 + strlen($data)).'WAVEfmt '.pack('VvvVVvv', 16, 1, 1, $rate, $rate * 2, 2, 16).'data'.pack('V', strlen($data)).$data;
    }

    private function admin(): User
    {
        $this->seed(RoleSeeder::class);
        $u = User::factory()->create(['is_active' => true]);
        $u->assignRole('admin');

        return $u;
    }

    private function add(string $cat, string $slot, string $label, bool $builtin = false): NotificationTone
    {
        return NotificationTones::storeBytes($this->wav(), $cat, $slot, $label, null, $builtin);
    }

    public function test_the_first_tone_in_a_slot_becomes_its_default_and_only_one_default_exists(): void
    {
        $a = $this->add('credit_alert', 'default', 'A');
        $b = $this->add('credit_alert', 'default', 'B');
        $this->assertTrue($a->fresh()->is_default);
        $this->assertFalse($b->fresh()->is_default);

        NotificationTones::setDefault($b);
        $this->assertFalse($a->fresh()->is_default);
        $this->assertTrue($b->fresh()->is_default);
        $this->assertSame(1, NotificationTone::where('category', 'credit_alert')->where('is_default', true)->count());
    }

    public function test_defaults_are_per_slot_so_message_slots_stay_distinct(): void
    {
        $g = $this->add('message', 'general', 'G');
        $n = $this->add('message', 'naara', 'N');
        $l = $this->add('message', 'naaraline', 'L');
        $this->assertTrue($g->fresh()->is_default && $n->fresh()->is_default && $l->fresh()->is_default);
    }

    public function test_deleting_the_default_promotes_another_tone_and_removes_the_file(): void
    {
        $a = $this->add('push', 'default', 'A');
        $b = $this->add('push', 'default', 'B');
        $path = $a->file_path;
        NotificationTones::delete($a);

        $this->assertTrue($b->fresh()->is_default);
        Storage::disk(MediaStorage::disk())->assertMissing($path);
    }

    public function test_uploads_are_identified_by_content_and_size_capped(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        NotificationTones::storeBytes('not audio at all, just text pretending', 'push', 'default', 'Fake');
    }

    public function test_an_oversize_tone_is_refused(): void
    {
        try {
            NotificationTones::storeBytes($this->wav(1000).str_repeat('x', NotificationTones::MAX_KB * 1024), 'push', 'default', 'Big');
            $this->fail('expected refusal');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('under', $e->getMessage());
        }
    }

    public function test_an_unknown_category_or_slot_is_refused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        NotificationTones::upload(UploadedFile::fake()->createWithContent('a.wav', $this->wav()), 'message', 'nope', 'X');
    }

    public function test_a_member_without_a_pick_hears_the_admin_default_and_a_pick_wins(): void
    {
        $default = $this->add('message', 'naara', 'Default');
        $other = $this->add('message', 'naara', 'Other');
        $alice = User::factory()->create(['is_active' => true]);
        $bob = User::factory()->create(['is_active' => true]);

        $this->assertStringEndsWith('/'.$default->id, NotificationTones::clientConfig($alice)['tones']['message.naara']);

        NotificationTones::savePrefs($bob, ['message.naara' => $other->id], true, true);
        $this->assertStringEndsWith('/'.$other->id, NotificationTones::clientConfig($bob)['tones']['message.naara']);
        $this->assertStringEndsWith('/'.$default->id, NotificationTones::clientConfig($alice)['tones']['message.naara']);
    }

    public function test_a_pick_for_a_deleted_tone_falls_back_to_the_default(): void
    {
        $default = $this->add('reward', 'default', 'Default');
        $mine = $this->add('reward', 'default', 'Mine');
        $u = User::factory()->create(['is_active' => true]);
        NotificationTones::savePrefs($u, ['reward.default' => $mine->id], true, true);
        NotificationTones::delete($mine);

        $this->assertStringEndsWith('/'.$default->id, NotificationTones::clientConfig($u)['tones']['reward.default']);
    }

    public function test_a_pick_from_the_wrong_category_is_ignored(): void
    {
        $push = $this->add('push', 'default', 'Push');
        $this->add('reward', 'default', 'Reward');
        $u = User::factory()->create(['is_active' => true]);
        NotificationTones::savePrefs($u, ['reward.default' => $push->id], true, true);

        $this->assertSame([], UserSoundPref::where('user_id', $u->id)->first()->tones);
    }

    public function test_the_switches_reach_the_client_config(): void
    {
        $this->add('push', 'default', 'P');
        $u = User::factory()->create(['is_active' => true]);
        NotificationTones::savePrefs($u, [], false, false);
        $cfg = NotificationTones::clientConfig($u);

        $this->assertFalse($cfg['enabled']);
        $this->assertFalse($cfg['vibrate']);
    }

    public function test_the_builtin_library_covers_every_slot_with_a_default(): void
    {
        $this->assertGreaterThan(0, NotificationTones::seedBuiltin());
        foreach (NotificationTones::keys() as $key) {
            [$cat, $slot] = explode('.', $key, 2);
            $this->assertSame(1, NotificationTone::where('category', $cat)->where('slot', $slot)->where('is_default', true)->count(), "{$key} needs exactly one default");
        }
        $this->assertSame(0, NotificationTones::seedBuiltin(), 'seeding is idempotent');
    }

    public function test_the_admin_page_is_admin_only_and_a_member_cannot_reach_it(): void
    {
        $this->actingAs(User::factory()->create(['is_active' => true]))->get(route('admin.notification-tones'))->assertStatus(404);
    }

    public function test_an_admin_can_upload_set_default_and_delete_through_the_page(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);
        Livewire::test(AdminTones::class)
            ->set('category', 'push')->set('label', 'Golden')
            ->set('file', UploadedFile::fake()->createWithContent('g.wav', $this->wav()))
            ->call('upload')->assertSet('error', null);
        $tone = NotificationTone::where('label', 'Golden')->firstOrFail();
        $this->assertTrue($tone->is_default);

        Livewire::test(AdminTones::class)->call('remove', $tone->id);
        $this->assertNull(NotificationTone::find($tone->id));
    }

    public function test_an_admin_upload_of_a_text_file_is_rejected_with_a_message(): void
    {
        $this->actingAs($this->admin());
        Livewire::test(AdminTones::class)
            ->set('category', 'push')->set('label', 'Nope')
            ->set('file', UploadedFile::fake()->createWithContent('n.wav', 'plain text, not audio'))
            ->call('upload')->assertSet('error', 'That is not an audio file we can play (WAV, MP3, OGG, M4A or WebM).');
        $this->assertSame(0, NotificationTone::count());
    }

    public function test_the_member_settings_page_saves_a_pick_and_the_switches(): void
    {
        $a = $this->add('credit_alert', 'default', 'A');
        $b = $this->add('credit_alert', 'default', 'B');
        $u = User::factory()->create(['is_active' => true]);
        $this->actingAs($u);

        Livewire::test(SoundSettings::class)
            ->set('picks.credit_alert__default', (string) $b->id)->set('vibrate', false);
        $pref = UserSoundPref::where('user_id', $u->id)->firstOrFail();
        $this->assertSame($b->id, $pref->tones['credit_alert.default']);
        $this->assertFalse($pref->vibrate_enabled);
    }

    public function test_the_tone_stream_serves_audio_from_our_origin_to_members_only(): void
    {
        $t = $this->add('push', 'default', 'P');
        $this->get(route('sounds.tone', $t))->assertRedirect();   // guests go to login

        $res = $this->actingAs(User::factory()->create(['is_active' => true]))->get(route('sounds.tone', $t));
        $res->assertOk();
        $this->assertSame('audio/wav', $res->headers->get('Content-Type'));
        $this->assertSame('nosniff', $res->headers->get('X-Content-Type-Options'));
    }

    public function test_the_page_head_carries_the_members_sound_config(): void
    {
        $this->add('push', 'default', 'P');
        $this->actingAs(User::factory()->create(['is_active' => true]))->get(route('account.sounds'))->assertOk()->assertSee('name="nx-sound"', false);
    }

    public function test_the_zayon_whyte_ringtones_and_the_beeps_ship_in_the_built_in_library(): void
    {
        $made = NotificationTones::seedBuiltin();
        $this->assertGreaterThanOrEqual(9, $made);

        $rings = NotificationTone::where('category', 'ringtone')->whereNotNull('credit')->orderBy('label')->get();
        $this->assertSame(['Boss Line', 'LAND & CONNECT', 'Wallet Gang'], $rings->pluck('label')->all());
        foreach ($rings as $r) {
            $this->assertSame('Zayon Whyte · NAARA Circus (2026)', $r->credit);
            $this->assertSame('audio/mpeg', $r->mime);
            $this->assertEqualsWithDelta(30000, $r->duration_ms, 150, 'the length is read from the MP3 itself');
            $this->assertLessThan(NotificationTones::MAX_KB_RINGTONE * 1024, $r->size_bytes);
            $this->assertFalse($r->is_default, 'the owner chooses which ringtone is the platform default');
            $this->assertNotNull($r->contents());
        }
        $this->assertSame(1, NotificationTone::where('category', 'ringtone')->where('is_default', true)->count());

        foreach (['send', 'error'] as $slot) {
            $beeps = NotificationTone::where('category', 'beep')->where('slot', $slot)->get();
            $this->assertCount(3, $beeps);
            $this->assertSame(1, $beeps->where('is_default', true)->count());
        }
        $this->assertSame(0, NotificationTones::seedBuiltin(), 'seeding again adds nothing');
    }

    public function test_a_ringtone_may_be_a_short_clip_but_other_tones_stay_small(): void
    {
        $mp3 = "ID3\x04\x00\x00\x00\x00\x00\x00".str_repeat("\xFF\xFB\x90\x00".str_repeat("\x00", 413), 1000);   // ~415 KB of MPEG frames
        $this->assertSame(2048, NotificationTones::maxKb('ringtone'));
        $this->assertSame(1024, NotificationTones::maxKb('beep'));
        $big = str_repeat("\x00", 1500 * 1024);
        $ringBig = "ID3\x04\x00\x00\x00\x00\x00\x00".$big;
        $this->assertSame('ringtone', NotificationTones::storeBytes($ringBig, 'ringtone', 'default', 'Clip', null, false, 'Someone')->category);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('under 1024 KB');
        NotificationTones::storeBytes($ringBig, 'beep', 'send', 'Too big');
    }

    public function test_members_see_credits_and_can_pick_a_beep_and_the_browser_gets_it(): void
    {
        NotificationTones::seedBuiltin();
        $u = User::factory()->create(['is_active' => true, 'email_verified_at' => now()]);
        $boss = NotificationTone::where('label', 'Boss Line')->firstOrFail();
        $tick = NotificationTone::where('category', 'beep')->where('label', 'Tick')->firstOrFail();

        Livewire::actingAs($u)->test(SoundSettings::class)
            ->assertSee('Boss Line')->assertSee('Zayon Whyte · NAARA Circus (2026)')->assertSee('Message sent')->assertSee('Something went wrong')
            ->set('picks.ringtone__default', (string) $boss->id)->set('picks.beep__send', (string) $tick->id);

        $cfg = NotificationTones::clientConfig($u->fresh());
        $this->assertSame(route('sounds.tone', $boss->id), $cfg['tones']['ringtone.default']);
        $this->assertSame(route('sounds.tone', $tick->id), $cfg['tones']['beep.send']);
        $this->assertArrayHasKey('beep.error', $cfg['tones'], 'an unpicked slot follows the platform default');

        $this->actingAs($u)->get(route('sounds.tone', $boss->id))->assertOk()->assertHeader('Content-Type', 'audio/mpeg');
    }

    public function test_an_admin_uploads_a_credited_ringtone_and_makes_it_the_default_for_everyone(): void
    {
        $admin = $this->admin();
        NotificationTones::seedBuiltin();
        $boss = NotificationTone::where('label', 'Boss Line')->firstOrFail();

        Livewire::actingAs($admin)->test(AdminTones::class)->set('category', 'beep')->assertSee('Message sent')
            ->set('category', 'ringtone')->set('label', 'My jingle')->set('credit', 'Our studio')
            ->set('file', UploadedFile::fake()->createWithContent('jingle.wav', $this->wav(600)))->call('upload')->assertSee('Added "My jingle"')
            ->call('makeDefault', $boss->id)->assertSee('is now the default');

        $this->assertSame('Our studio', NotificationTone::where('label', 'My jingle')->value('credit'));
        $this->assertTrue($boss->fresh()->is_default);
        $stranger = User::factory()->create(['is_active' => true, 'email_verified_at' => now()]);
        $this->assertSame(route('sounds.tone', $boss->id), NotificationTones::clientConfig($stranger)['tones']['ringtone.default']);
    }

    public function test_the_sound_engine_plays_beeps_for_a_sent_and_a_failed_message(): void
    {
        $js = file_get_contents(resource_path('js/notification-sound.js'));
        $this->assertStringContainsString("addEventListener('cc:sent', () => engine.play('beep.send'))", $js);
        $this->assertStringContainsString("engine.play('beep.error')", $js);
        $this->assertStringContainsString("'beep.send':", $js);
    }
}
