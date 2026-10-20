<?php

namespace Tests\Feature\BusinessSuite;

use App\Livewire\Admin\BusinessSuiteSettingsPage;
use App\Livewire\Messages;
use App\Models\ChannelConnection;
use App\Models\MessageThread;
use App\Models\OutboundMessage;
use App\Models\ScheduledChannelMessage;
use App\Models\Setting;
use App\Models\User;
use App\Models\WalletGroup;
use App\Services\Inbox\InboxMedia;
use App\Services\Inbox\InboxReadiness;
use App\Services\Permissions\WorkspaceRoleService;
use App\Support\MediaStorage;
use App\Support\Permissions\BusinessRole;
use Database\Seeders\BusinessRoleSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/** The Naara composer inside the Business shared inbox: files, GIFs, voice, location / contact / poll, scheduling, per-channel limits and the admin controls. */
class InboxComposerSendTest extends TestCase
{
    use \Tests\Concerns\EnablesBusinessSuite;
    use RefreshDatabase;

    private User $owner;

    private WalletGroup $group;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(BusinessRoleSeeder::class);
        Setting::setValue('business.module.inbox', true, 'business');
        Storage::fake(MediaStorage::privateDisk());
        \Cache::flush();
        RateLimiter::clear('inbox-send:1');
        $this->owner = User::factory()->create(['is_active' => true, 'email_verified_at' => now()]);
        $this->group = WalletGroup::create(['owner_user_id' => $this->owner->id, 'name' => 'Acme Ltd', 'kind' => WalletGroup::KIND_BUSINESS]);
        app(WorkspaceRoleService::class)->assignRole($this->group, $this->owner, BusinessRole::OWNER);
        foreach (['whatsapp' => 'PN1', 'telegram' => 'BOT1', 'instagram' => 'IG1'] as $ch => $ext) {
            ChannelConnection::create(['wallet_group_id' => $this->group->id, 'channel' => $ch, 'external_id' => $ext, 'label' => $ch, 'credentials' => ['access_token' => 'tok-'.str_repeat('x', 30), 'bot_token' => '123:ABC'], 'webhook_secret' => 'verify-secret-123', 'status' => ChannelConnection::ACTIVE]);
        }
    }

    private function thread(string $channel, string $who = '2348011112222', ?WalletGroup $g = null): MessageThread
    {
        return MessageThread::create(['user_id' => $this->owner->id, 'channel' => $channel, 'wallet_group_id' => ($g ?? $this->group)->id, 'counterpart_number' => $who, 'last_body' => 'hi', 'last_direction' => 'in', 'last_at' => now()]);
    }

    private function send(string $channel, string $who, array $meta, array $files = [], ?UploadedFile $voice = null, string $key = 'k1', ?User $as = null)
    {
        $meta += ['chat' => "inbox-{$channel}-{$who}", 'clientId' => $key, 'text' => '', 'items' => []];
        $data = ['meta' => json_encode($meta)] + ($files ? ['files' => $files] : []) + ($voice ? ['voice' => $voice] : []);

        return $this->actingAs($as ?? $this->owner)->withSession(['active_wallet_group_id' => $this->group->id])
            ->post(route('business.inbox.send'), $data, ['Idempotency-Key' => $key, 'Accept' => 'application/json']);
    }

    private function fileItems(array $names): array
    {
        return array_map(fn ($n) => ['type' => 'file', 'name' => $n], $names);
    }

    private function gif(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('wave.gif', "GIF89a\x01\x00\x01\x00\x80\x00\x00\x00\x00\x00\xff\xff\xff!\xf9\x04\x01\x00\x00\x00\x00,\x00\x00\x00\x00\x01\x00\x01\x00\x00\x02\x02D\x01\x00;");
    }

    private function pdf(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('quote.pdf', "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF");
    }

    private function wa(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.OUT']], 'message_id' => 'm_ig'], 200)]);
    }

    private function tg(): void
    {
        $n = 100;
        Http::fake(['api.telegram.org/*' => function () use (&$n) {
            return Http::response(['ok' => true, 'result' => ['message_id' => ++$n]], 200);
        }]);
    }

    // ── sending ─────────────────────────────────────────────────────────────

    public function test_a_photo_with_a_caption_goes_to_whatsapp_as_one_image_message_with_a_short_lived_link(): void
    {
        $this->wa();
        $this->thread('whatsapp');

        $r = $this->send('whatsapp', '2348011112222', ['text' => 'Here is your *quote*', 'items' => $this->fileItems(['a.jpg'])], [UploadedFile::fake()->image('a.jpg', 40, 40)]);

        $r->assertOk()->assertJson(['status' => 'sent', 'count' => 1]);
        Http::assertSentCount(1);
        Http::assertSent(function ($req) {
            $b = $req->data();

            return str_contains($req->url(), '/PN1/messages') && $b['type'] === 'image' && str_starts_with($b['image']['link'], 'http') && str_contains($b['image']['link'], 'inbox-out/'.$this->group->id.'/')
                && $b['image']['caption'] === 'Here is your *quote*';
        });
        $row = OutboundMessage::query()->where('wallet_group_id', $this->group->id)->firstOrFail();
        $this->assertSame('image', $row->attachment_kind);
        $this->assertSame('whatsapp', $row->channel);
    }

    public function test_a_gif_goes_to_telegram_as_an_animation_and_a_pdf_as_a_document(): void
    {
        $this->tg();
        $this->thread('telegram', '555');

        $this->send('telegram', '555', ['items' => $this->fileItems(['wave.gif', 'quote.pdf']), 'text' => 'Hi'], [$this->gif(), $this->pdf()])->assertOk()->assertJson(['count' => 2]);

        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/sendAnimation') && $r['parse_mode'] === 'HTML' && $r['caption'] === 'Hi');
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/sendDocument') && ! isset($r['caption']));
    }

    public function test_location_contact_and_a_poll_reach_telegram_and_a_poll_is_refused_on_whatsapp(): void
    {
        $this->tg();
        $this->thread('telegram', '555');
        $items = [['type' => 'location', 'label' => 'x', 'lat' => 6.45, 'lng' => 3.39], ['type' => 'contact', 'label' => 'Ada', 'phone' => '+234 801 111 2222'], ['type' => 'poll', 'label' => 'Lunch?', 'options' => ['Rice', 'Beans'], 'multiple' => false]];
        $this->send('telegram', '555', ['items' => $items])->assertOk()->assertJson(['count' => 3]);
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/sendLocation') && $r['latitude'] == 6.45);
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/sendContact') && $r['phone_number'] === '+2348011112222');
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/sendPoll') && count($r['options']) === 2);

        $this->thread('whatsapp');
        Http::fake();
        $this->send('whatsapp', '2348011112222', ['items' => [$items[2]]], key: 'k2')->assertStatus(422)->assertJsonPath('message', fn ($m) => str_contains($m, 'Polls are only available on Telegram'));
        Http::assertNothingSent();
    }

    public function test_instagram_refuses_a_document_before_anything_is_sent_and_the_stored_copy_is_removed(): void
    {
        Http::fake();
        $this->thread('instagram', 'ig-1');

        $this->send('instagram', 'ig-1', ['items' => $this->fileItems(['quote.pdf'])], [$this->pdf()])->assertStatus(422)->assertJsonPath('message', fn ($m) => str_contains($m, "can't carry documents"));

        Http::assertNothingSent();
        $this->assertSame([], Storage::disk(MediaStorage::privateDisk())->allFiles('inbox-out'));
        $this->assertSame(0, OutboundMessage::count());
    }

    /** Make this test independent of whether the machine running it has ffmpeg: pretend there is none. */
    private function withoutFfmpeg(): void
    {
        \Cache::put('ffmpeg.binary.v1', ['path' => null, 'source' => null], 600);
        \Cache::put('inbox.ffmpeg', false, 600);
    }

    private function webmVoice(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('voice.webm', (string) file_get_contents(base_path('tests/fixtures/voice-opus.webm')));
    }

    public function test_a_whatsapp_gif_without_ffmpeg_is_sent_as_a_file_instead_of_failing(): void
    {
        $this->withoutFfmpeg();
        $this->wa();
        $this->thread('whatsapp');

        $this->send('whatsapp', '2348011112222', ['items' => $this->fileItems(['wave.gif'])], [$this->gif()])->assertOk()->assertJson(['status' => 'sent']);

        Http::assertSent(fn ($r) => $r['type'] === 'document' && str_ends_with($r['document']['filename'], '.gif'));
    }

    public function test_a_browser_voice_note_reaches_whatsapp_as_ogg_opus_with_no_ffmpeg_at_all(): void
    {
        $this->withoutFfmpeg();
        $this->wa();
        $this->thread('whatsapp');

        $this->send('whatsapp', '2348011112222', ['items' => []], [], $this->webmVoice())->assertOk();

        Http::assertSent(fn ($r) => $r['type'] === 'audio' && str_contains($r['audio']['link'], '.ogg'));
        $stored = Storage::disk(MediaStorage::privateDisk())->allFiles('inbox-out');
        $this->assertCount(1, array_filter($stored, fn ($f) => str_ends_with($f, '.ogg')));
        $ogg = Storage::disk(MediaStorage::privateDisk())->get(array_values(array_filter($stored, fn ($f) => str_ends_with($f, '.ogg')))[0]);
        $this->assertStringStartsWith('OggS', $ogg);
        $this->assertStringContainsString('OpusHead', $ogg);
    }

    public function test_a_browser_voice_note_reaches_telegram_as_a_voice_message_with_no_ffmpeg(): void
    {
        $this->withoutFfmpeg();
        $this->tg();
        $this->thread('telegram', '555');

        $this->send('telegram', '555', ['items' => []], [], $this->webmVoice())->assertOk();

        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/sendVoice'));
    }

    public function test_instagram_voice_without_ffmpeg_is_refused_in_plain_words_and_nothing_is_sent(): void
    {
        $this->withoutFfmpeg();
        Http::fake();
        $this->thread('instagram', 'ig-1');

        $this->send('instagram', 'ig-1', ['items' => []], [], $this->webmVoice())->assertStatus(422)
            ->assertJsonPath('message', fn ($m) => str_contains($m, 'media converter') && str_contains($m, 'Setup and health'));

        Http::assertNothingSent();
    }

    public function test_a_channel_refusal_comes_back_as_a_sentence_and_nothing_is_recorded(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['error' => ['message' => 'Re-engagement']], 400)]);
        $this->thread('whatsapp');

        $this->send('whatsapp', '2348011112222', ['text' => 'hello'])->assertStatus(422)->assertJsonPath('message', fn ($m) => str_contains($m, '24 hours'));
        $this->assertSame(0, OutboundMessage::count());
    }

    public function test_the_same_idempotency_key_never_delivers_twice(): void
    {
        $this->wa();
        $this->thread('whatsapp');

        $this->send('whatsapp', '2348011112222', ['text' => 'once'], key: 'same')->assertOk()->assertJson(['status' => 'sent']);
        $this->send('whatsapp', '2348011112222', ['text' => 'once'], key: 'same')->assertOk()->assertJson(['status' => 'duplicate']);

        Http::assertSentCount(1);
        $this->assertSame(1, OutboundMessage::count());
    }

    // ── limits and the admin controls ───────────────────────────────────────

    public function test_admin_limits_are_enforced_and_each_refusal_is_plain(): void
    {
        Http::fake();
        $this->thread('telegram', '555');
        $files = fn (int $n) => array_map(fn ($i) => UploadedFile::fake()->image("p$i.jpg", 10, 10), range(1, $n));

        Setting::setValue('business.inbox.max_files', 2, 'business');
        $this->send('telegram', '555', ['items' => $this->fileItems(['1', '2', '3'])], $files(3))->assertStatus(422)->assertJsonPath('message', fn ($m) => str_contains($m, 'up to 2 files'));

        Setting::setValue('business.inbox.max_files', 5, 'business');
        Setting::setValue('business.inbox.max_file_mb', 1, 'business');
        $big = UploadedFile::fake()->create('big.pdf', 2048, 'application/pdf');
        $this->send('telegram', '555', ['items' => $this->fileItems(['big.pdf'])], [$big], key: 'b')->assertStatus(422)->assertJsonPath('message', fn ($m) => str_contains($m, '1 MB limit'));

        Setting::setValue('business.inbox.attachments', false, 'business');
        $this->send('telegram', '555', ['items' => $this->fileItems(['a'])], $files(1), key: 'c')->assertStatus(422)->assertJsonPath('message', fn ($m) => str_contains($m, 'switched off'));
        Http::assertNothingSent();
    }

    public function test_a_burst_of_sends_is_slowed_down(): void
    {
        $this->wa();
        $this->thread('whatsapp');
        Setting::setValue('business.inbox.sends_per_minute', 1, 'business');
        RateLimiter::clear('inbox-send:'.$this->owner->id);

        $this->send('whatsapp', '2348011112222', ['text' => 'one'], key: 'r1')->assertOk();
        $this->send('whatsapp', '2348011112222', ['text' => 'two'], key: 'r2')->assertStatus(422)->assertJsonPath('message', fn ($m) => str_contains($m, 'very quickly'));
    }

    public function test_someone_outside_the_workspace_gets_a_404_and_another_workspaces_thread_is_not_reachable(): void
    {
        $this->wa();
        $this->thread('whatsapp');
        $stranger = User::factory()->create(['is_active' => true, 'email_verified_at' => now()]);
        $this->send('whatsapp', '2348011112222', ['text' => 'hi'], as: $stranger)->assertNotFound();

        $other = WalletGroup::create(['owner_user_id' => $stranger->id, 'name' => 'Other', 'kind' => WalletGroup::KIND_BUSINESS]);
        $this->thread('whatsapp', '2349990000000', $other);
        $this->send('whatsapp', '2349990000000', ['text' => 'hi'], key: 'x')->assertStatus(422)->assertJsonPath('message', 'That conversation is not available.');
        Http::assertNothingSent();
    }

    // ── scheduling ──────────────────────────────────────────────────────────

    public function test_a_scheduled_message_waits_then_is_delivered_once_by_the_command(): void
    {
        $this->wa();
        $this->thread('whatsapp');
        $at = now()->addHours(2)->getTimestampMs();

        $this->send('whatsapp', '2348011112222', ['text' => 'later', 'scheduledAt' => $at], key: 's1')->assertOk()->assertJson(['status' => 'scheduled']);
        $this->send('whatsapp', '2348011112222', ['text' => 'later', 'scheduledAt' => $at], key: 's1')->assertOk();   // a retried request does not queue twice
        $this->assertSame(1, ScheduledChannelMessage::count());
        Http::assertNothingSent();

        $this->artisan('business:send-scheduled')->assertSuccessful();
        Http::assertNothingSent();   // not due yet

        ScheduledChannelMessage::query()->update(['send_at' => now()->subMinute()]);
        $this->artisan('business:send-scheduled')->assertSuccessful();
        $this->artisan('business:send-scheduled')->assertSuccessful();   // a second run must not resend

        Http::assertSentCount(1);
        $row = ScheduledChannelMessage::firstOrFail();
        $this->assertSame('sent', $row->status);
        $this->assertSame(1, OutboundMessage::count());
    }

    public function test_a_scheduled_message_the_channel_rejects_is_marked_failed_with_the_reason_and_never_retried(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['error' => ['message' => 'x']], 400)]);
        $this->thread('whatsapp');
        $this->send('whatsapp', '2348011112222', ['text' => 'later', 'scheduledAt' => now()->addHour()->getTimestampMs()], key: 's2')->assertJson(['status' => 'scheduled']);
        ScheduledChannelMessage::query()->update(['send_at' => now()->subMinute()]);

        $this->artisan('business:send-scheduled')->assertSuccessful();
        $this->artisan('business:send-scheduled')->assertSuccessful();

        $row = ScheduledChannelMessage::firstOrFail();
        $this->assertSame('failed', $row->status);
        $this->assertStringContainsString('24 hours', (string) $row->error);
        Http::assertSentCount(1);
    }

    public function test_scheduling_can_be_switched_off_and_is_capped_in_days(): void
    {
        $this->wa();
        $this->thread('whatsapp');
        Setting::setValue('business.inbox.max_schedule_days', 2, 'business');
        $this->send('whatsapp', '2348011112222', ['text' => 'x', 'scheduledAt' => now()->addDays(5)->getTimestampMs()], key: 'd')->assertStatus(422)->assertJsonPath('message', fn ($m) => str_contains($m, 'up to 2 days'));

        Setting::setValue('business.inbox.scheduling', false, 'business');
        $this->send('whatsapp', '2348011112222', ['text' => 'x', 'scheduledAt' => now()->addHour()->getTimestampMs()], key: 'e')->assertStatus(422)->assertJsonPath('message', fn ($m) => str_contains($m, 'switched off'));
        $this->assertSame(0, ScheduledChannelMessage::count());
    }

    public function test_the_team_can_cancel_a_pending_scheduled_message_but_only_in_their_workspace(): void
    {
        $this->wa();
        $t = $this->thread('whatsapp');
        $this->send('whatsapp', '2348011112222', ['text' => 'later', 'scheduledAt' => now()->addHour()->getTimestampMs()], key: 's3');
        $row = ScheduledChannelMessage::firstOrFail();

        $this->actingAs($this->owner)->withSession(['active_wallet_group_id' => $this->group->id]);
        Livewire::test(Messages::class)->set('active', '2348011112222')->set('activeChannel', 'whatsapp')->assertSee('Scheduled for')->call('cancelScheduled', $row->id);
        $this->assertSame('cancelled', $row->fresh()->status);
    }

    // ── showing files, and serving them ─────────────────────────────────────

    public function test_sent_files_show_in_the_thread_and_the_signed_link_serves_them_but_nothing_else(): void
    {
        $this->wa();
        $this->thread('whatsapp');
        $this->send('whatsapp', '2348011112222', ['items' => $this->fileItems(['quote.pdf'])], [$this->pdf()])->assertOk();
        $row = OutboundMessage::firstOrFail();

        $this->actingAs($this->owner)->withSession(['active_wallet_group_id' => $this->group->id]);
        Livewire::test(Messages::class)->set('active', '2348011112222')->set('activeChannel', 'whatsapp')->assertSee('bz-file', false)->assertSee('quote.pdf');

        $url = \URL::temporarySignedRoute('business.inbox.media', now()->addMinutes(5), ['path' => rtrim(strtr(base64_encode($row->attachment_url), '+/', '-_'), '=')]);
        auth()->logout();
        $this->get($url)->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff')->assertHeader('Content-Type', 'application/pdf');
        $this->get(preg_replace('/signature=[^&]+/', 'signature=bad', $url))->assertForbidden();

        $evil = rtrim(strtr(base64_encode('../../../.env'), '+/', '-_'), '=');
        $this->get(\URL::temporarySignedRoute('business.inbox.media', now()->addMinutes(5), ['path' => $evil]))->assertNotFound();
        $wrongDir = rtrim(strtr(base64_encode('exports/'.str_repeat('a', 36).'.pdf'), '+/', '-_'), '=');
        $this->get(\URL::temporarySignedRoute('business.inbox.media', now()->addMinutes(5), ['path' => $wrongDir]))->assertNotFound();
    }

    public function test_the_composer_is_trimmed_to_what_each_channel_can_carry(): void
    {
        $wa = \App\Services\Inbox\InboxComposerConfig::for('whatsapp');
        $tg = \App\Services\Inbox\InboxComposerConfig::for('telegram');
        $ig = \App\Services\Inbox\InboxComposerConfig::for('instagram');

        $this->assertNotContains('poll', $wa['attachKinds']);
        $this->assertContains('poll', $tg['attachKinds']);
        $this->assertNotContains('document', $ig['attachKinds']);
        $this->assertNotContains('location', $ig['attachKinds']);
        $this->assertSame(1000, $ig['maxChars']);
        $this->assertSame(route('business.inbox.send'), $wa['endpoint']);
        $this->assertContains('gif', $wa['features']);
        $this->assertContains('schedule', $wa['features']);

        Setting::setValue('business.inbox.attachments', false, 'business');
        Setting::setValue('business.inbox.scheduling', false, 'business');
        $off = \App\Services\Inbox\InboxComposerConfig::for('telegram');
        $this->assertSame([], $off['attachKinds']);
        $this->assertNotContains('attach', $off['features']);
        $this->assertNotContains('gif', $off['features']);
        $this->assertNotContains('schedule', $off['features']);
        $this->assertContains('emoji', $off['features']);
    }

    // ── admin readiness ─────────────────────────────────────────────────────

    public function test_readiness_flags_a_local_address_and_the_admin_tab_renders_every_check(): void
    {
        config(['app.url' => 'http://localhost']);
        $byId = collect(InboxReadiness::checks())->keyBy('id');
        $this->assertSame('fail', $byId['url']['status']);
        $this->assertNotEmpty($byId['url']['fix']);
        $this->assertSame('ok', $byId['storage']['status']);
        $this->assertSame('ok', $byId['module']['status']);

        config(['app.url' => 'https://naarasim.example']);
        $this->assertSame('ok', collect(InboxReadiness::checks())->firstWhere('id', 'url')['status']);

        $sa = User::factory()->create(['is_active' => true, 'email_verified_at' => now()]);
        $sa->assignRole('super_admin');
        Livewire::actingAs($sa)->test(BusinessSuiteSettingsPage::class)->set('tab', 'readiness')
            ->assertSee('Setup and health')->assertSee('Public address for provider downloads')->assertSee('ffmpeg');
    }

    public function test_the_sending_settings_are_in_the_control_panel_with_safe_defaults(): void
    {
        $f = \App\Support\Business\BusinessSettingsSchema::flat();
        foreach (['attachments', 'scheduling', 'max_file_mb', 'max_files', 'max_schedule_days', 'link_minutes', 'sends_per_minute'] as $k) {
            $this->assertArrayHasKey('business.inbox.'.$k, $f);
        }
        $this->assertTrue(\App\Support\Business\BusinessSettings::inbox('attachments'));
        $this->assertSame(5, \App\Support\Business\BusinessSettings::inbox('max_files'));
    }
}
