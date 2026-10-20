<?php

namespace Tests\Feature\BusinessSuite;

use App\Jobs\RecordChannelMessageJob;
use App\Livewire\BusinessChannels;
use App\Livewire\Messages;
use App\Models\ChannelConnection;
use App\Models\InboundMessage;
use App\Models\MessageThread;
use App\Models\OutboundMessage;
use App\Models\Setting;
use App\Models\User;
use App\Models\WalletGroup;
use App\Models\WalletGroupMember;
use App\Services\Inbox\ChannelConnector;
use App\Services\Inbox\ChannelSendException;
use App\Services\Inbox\MessageDispatcher;
use App\Services\Permissions\WorkspaceRoleService;
use App\Support\Permissions\BusinessRole;
use App\Support\Workspace;
use Database\Seeders\BusinessRoleSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

/** Business Suite Prompt 16: the shared inbox (channels on the existing inbox, per-workspace connections, signed webhooks, one reply dispatcher). */
class SharedInboxTest extends TestCase
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

        $this->owner = User::factory()->create(['is_active' => true, 'email_verified_at' => now()]);
        $this->group = WalletGroup::create(['owner_user_id' => $this->owner->id, 'name' => 'Acme', 'kind' => WalletGroup::KIND_BUSINESS]);
        app(WorkspaceRoleService::class)->assignRole($this->group, $this->owner, BusinessRole::OWNER);
    }

    private function member(string $role): User
    {
        $u = User::factory()->create(['is_active' => true, 'email_verified_at' => now()]);
        WalletGroupMember::create(['wallet_group_id' => $this->group->id, 'user_id' => $u->id, 'role' => $role, 'status' => 'active', 'invited_at' => now(), 'accepted_at' => now()]);
        app(WorkspaceRoleService::class)->assignRole($this->group, $u, $role);

        return $u;
    }

    private function connection(string $channel, string $externalId, array $creds = [], string $secret = 'verify-secret-123'): ChannelConnection
    {
        return ChannelConnection::create([
            'wallet_group_id' => $this->group->id, 'channel' => $channel, 'external_id' => $externalId, 'label' => 'Test',
            'credentials' => $creds + ['access_token' => 'tok-'.str_repeat('x', 30), 'bot_token' => '123:ABC', 'app_secret' => 'app-secret-1234567890'],
            'webhook_secret' => $secret, 'status' => ChannelConnection::ACTIVE,
        ]);
    }

    private function metaPost(ChannelConnection $c, array $payload, ?string $secret = null)
    {
        $raw = json_encode($payload);
        $sig = 'sha256='.hash_hmac('sha256', $raw, $secret ?? 'app-secret-1234567890');

        return $this->call('POST', route('webhooks.meta', ['connection' => $c->id]), [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_HUB_SIGNATURE_256' => $sig], $raw);
    }

    // ── schema / threads ────────────────────────────────────────────────────

    public function test_existing_rows_are_sms_and_one_person_on_two_channels_is_two_threads(): void
    {
        $sms = MessageThread::record($this->owner->id, '+2348012345678', 'hi', 'in', null);
        $wa = MessageThread::record($this->owner->id, '+2348012345678', 'hello', 'in', null, channel: 'whatsapp', walletGroupId: $this->group->id);

        $this->assertSame('sms', $sms->fresh()->channel);
        $this->assertNotSame($sms->id, $wa->id);
        $this->assertSame(2, MessageThread::where('user_id', $this->owner->id)->count());
        $this->assertSame($sms->id, MessageThread::record($this->owner->id, '+2348012345678', 'again', 'in', null)->id);   // sms still collapses into its own thread
    }

    // ── webhooks ────────────────────────────────────────────────────────────

    public function test_telegram_needs_the_connection_secret_and_files_only_private_text(): void
    {
        Queue::fake();
        $c = $this->connection('telegram', '777');
        $update = ['update_id' => 9001, 'message' => ['chat' => ['id' => 5551, 'type' => 'private'], 'from' => ['is_bot' => false], 'text' => 'hello shop', 'date' => 1700000000]];

        $this->postJson(route('webhooks.telegram', ['connection' => $c->id]), $update)->assertStatus(401);
        $this->postJson(route('webhooks.telegram', ['connection' => $c->id]), $update, ['X-Telegram-Bot-Api-Secret-Token' => 'wrong'])->assertStatus(401);
        Queue::assertNothingPushed();

        $this->postJson(route('webhooks.telegram', ['connection' => $c->id]), $update, ['X-Telegram-Bot-Api-Secret-Token' => 'verify-secret-123'])->assertOk();
        Queue::assertPushed(RecordChannelMessageJob::class, fn ($j) => $j->counterpart === '5551' && $j->providerRef === 'tg:'.$c->id.':9001' && $j->body === 'hello shop');

        $group = $update;
        $group['update_id'] = 9002;
        $group['message']['chat']['type'] = 'group';
        $this->postJson(route('webhooks.telegram', ['connection' => $c->id]), $group, ['X-Telegram-Bot-Api-Secret-Token' => 'verify-secret-123'])->assertOk();
        Queue::assertPushed(RecordChannelMessageJob::class, 1);
    }

    public function test_with_the_module_off_a_webhook_is_acknowledged_but_nothing_is_filed(): void
    {
        Queue::fake();
        $c = $this->connection('telegram', '777');
        Setting::setValue('business.module.inbox', false, 'business');

        $this->postJson(route('webhooks.telegram', ['connection' => $c->id]), ['update_id' => 1, 'message' => ['chat' => ['id' => 1, 'type' => 'private'], 'text' => 'x']], ['X-Telegram-Bot-Api-Secret-Token' => 'verify-secret-123'])->assertOk();
        Queue::assertNothingPushed();
    }

    public function test_the_meta_handshake_uses_this_connections_own_verify_token(): void
    {
        $c = $this->connection('whatsapp', '1055');
        $url = route('webhooks.meta', ['connection' => $c->id]);

        $this->get($url.'?hub_verify_token=verify-secret-123&hub_challenge=abc')->assertOk()->assertSee('abc');
        $this->get($url.'?hub_verify_token=nope&hub_challenge=abc')->assertForbidden();
    }

    public function test_a_whatsapp_message_needs_this_connections_app_secret_and_the_right_number(): void
    {
        Queue::fake();
        $c = $this->connection('whatsapp', '1055');
        $payload = ['entry' => [['changes' => [['value' => [
            'metadata' => ['phone_number_id' => '1055'],
            'messages' => [['from' => '2348012345678', 'id' => 'wamid.AAA', 'type' => 'text', 'text' => ['body' => 'Price?'], 'timestamp' => '1700000000']],
        ]]]]]];

        $this->metaPost($c, $payload, 'some-other-secret')->assertStatus(401);
        Queue::assertNothingPushed();

        $this->metaPost($c, $payload)->assertOk();
        Queue::assertPushed(RecordChannelMessageJob::class, fn ($j) => $j->counterpart === '2348012345678' && $j->providerRef === 'wamid.AAA' && $j->body === 'Price?');

        $payload['entry'][0]['changes'][0]['value']['metadata']['phone_number_id'] = '9999';   // addressed to a different number of the same app
        $this->metaPost($c, $payload)->assertOk();
        Queue::assertPushed(RecordChannelMessageJob::class, 1);
    }

    public function test_instagram_events_are_filed_and_echoes_are_ignored(): void
    {
        Queue::fake();
        $c = $this->connection('instagram', '17841');
        $event = fn (array $m) => ['entry' => [['messaging' => [['sender' => ['id' => 'IGSID1'], 'recipient' => ['id' => '17841'], 'timestamp' => 1700000000000, 'message' => $m]]]]];

        $this->metaPost($c, $event(['mid' => 'm1', 'text' => 'hi there']))->assertOk();
        $this->metaPost($c, $event(['mid' => 'm2', 'text' => 'our reply', 'is_echo' => true]))->assertOk();

        Queue::assertPushed(RecordChannelMessageJob::class, 1);
        Queue::assertPushed(RecordChannelMessageJob::class, fn ($j) => $j->counterpart === 'IGSID1' && $j->providerRef === 'm1');
    }

    // ── ingest ──────────────────────────────────────────────────────────────

    public function test_a_channel_message_lands_in_the_workspace_inbox_once_and_notifies_the_owner(): void
    {
        $c = $this->connection('telegram', '777');
        $job = fn () => (new RecordChannelMessageJob($c->id, '5551', 'hello', 'tg:'.$c->id.':1', 1700000000))->handle();
        $job();
        $job(); // a re-delivered webhook

        $this->assertSame(1, InboundMessage::count());
        $m = InboundMessage::firstOrFail();
        $this->assertSame(['telegram', $this->group->id, $this->owner->id], [$m->channel, $m->wallet_group_id, $m->user_id]);
        $t = MessageThread::firstOrFail();
        $this->assertSame(['telegram', '5551', 1], [$t->channel, $t->counterpart_number, $t->unread_count]);
        $this->assertSame(1, $this->owner->notifications()->count());
        $this->assertNotNull($c->fresh()->last_event_at);
    }

    public function test_stop_closes_the_conversation_and_writing_again_reopens_it(): void
    {
        $c = $this->connection('telegram', '777');
        (new RecordChannelMessageJob($c->id, '5551', '/stop', 'tg:1', null))->handle();
        $thread = MessageThread::firstOrFail();
        $this->assertNotNull($thread->opted_out_at);

        try {
            app(MessageDispatcher::class)->sendReply($thread, 'are you there?', $this->owner);
            $this->fail('should have refused');
        } catch (ChannelSendException $e) {
            $this->assertStringContainsString('asked not to be messaged', $e->getMessage());
        }

        (new RecordChannelMessageJob($c->id, '5551', 'actually hello', 'tg:2', null))->handle();
        $this->assertNull($thread->fresh()->opted_out_at);
    }

    // ── dispatcher ──────────────────────────────────────────────────────────

    private function thread(string $channel, string $counterpart): MessageThread
    {
        return MessageThread::record($this->owner->id, $counterpart, 'hi', 'in', null, channel: $channel, walletGroupId: $this->group->id);
    }

    public function test_a_whatsapp_reply_is_sent_from_the_workspaces_own_number_and_recorded(): void
    {
        $this->connection('whatsapp', '1055');
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.OUT']]], 200)]);

        $out = app(MessageDispatcher::class)->sendReply($this->thread('whatsapp', '2348012345678'), 'We have it in stock', $this->owner);

        Http::assertSent(fn ($r) => str_contains((string) $r->url(), '/1055/messages') && $r['type'] === 'text' && $r['to'] === '2348012345678' && $r->hasHeader('Authorization'));
        $this->assertInstanceOf(OutboundMessage::class, $out);
        $this->assertSame(['whatsapp', $this->group->id], [$out->channel, $out->wallet_group_id]);
        $this->assertSame(0, (int) MessageThread::firstOrFail()->unread_count);
    }

    public function test_telegram_and_instagram_replies_go_to_their_own_clients(): void
    {
        $this->connection('telegram', '777');
        $this->connection('instagram', '17841');
        Http::fake([
            'api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 42]], 200),
            'graph.facebook.com/*' => Http::response(['message_id' => 'ig.OUT'], 200),
        ]);

        app(MessageDispatcher::class)->sendReply($this->thread('telegram', '5551'), 'hello', $this->owner);
        app(MessageDispatcher::class)->sendReply($this->thread('instagram', 'IGSID1'), 'hi', $this->owner);

        Http::assertSent(fn ($r) => str_contains((string) $r->url(), 'api.telegram.org/bot123:ABC/sendMessage') && $r['chat_id'] === '5551');
        Http::assertSent(fn ($r) => str_contains((string) $r->url(), '/17841/messages') && $r['recipient']['id'] === 'IGSID1');
        $this->assertSame(2, OutboundMessage::count());
    }

    public function test_a_rejected_send_writes_nothing_and_explains_the_24_hour_rule(): void
    {
        $this->connection('whatsapp', '1055');
        Http::fake(['graph.facebook.com/*' => Http::response(['error' => ['message' => 'Re-engagement message']], 400)]);

        try {
            app(MessageDispatcher::class)->sendReply($this->thread('whatsapp', '2348012345678'), 'late reply', $this->owner);
            $this->fail('should have failed');
        } catch (ChannelSendException $e) {
            $this->assertStringContainsString('24 hours', $e->getMessage());
        }
        $this->assertSame(0, OutboundMessage::count());
    }

    public function test_a_paused_connection_or_a_switched_off_module_cannot_send(): void
    {
        $c = $this->connection('telegram', '777');
        Http::fake();
        $thread = $this->thread('telegram', '5551');

        $c->update(['status' => ChannelConnection::PAUSED]);
        $this->expectException(ChannelSendException::class);
        app(MessageDispatcher::class)->sendReply($thread, 'x', $this->owner);
    }

    // ── connect / disconnect ────────────────────────────────────────────────

    public function test_connecting_telegram_verifies_the_token_registers_the_webhook_and_encrypts_it(): void
    {
        Http::fake([
            'api.telegram.org/botGOOD/getMe' => Http::response(['ok' => true, 'result' => ['id' => 888, 'username' => 'acme_bot']], 200),
            'api.telegram.org/botGOOD/setWebhook' => Http::response(['ok' => true], 200),
            'api.telegram.org/botBAD/getMe' => Http::response(['ok' => false], 401),
        ]);
        $connector = app(ChannelConnector::class);

        $c = $connector->connectTelegram($this->group, 'GOOD');
        $this->assertSame(['telegram', '888', '@acme_bot'], [$c->channel, $c->external_id, $c->label]);
        Http::assertSent(fn ($r) => str_contains((string) $r->url(), 'setWebhook') && $r['secret_token'] === $c->webhook_secret && str_contains($r['url'], '/webhooks/telegram/'.$c->id));

        $raw = (string) \DB::table('channel_connections')->where('id', $c->id)->value('credentials');
        $this->assertStringNotContainsString('GOOD', $raw);                         // encrypted at rest
        $this->assertArrayNotHasKey('credentials', $c->toArray());                  // never serialised

        $this->expectException(ChannelSendException::class);
        $connector->connectTelegram($this->group, 'BAD');
    }

    public function test_one_account_cannot_be_connected_to_two_workspaces_and_the_limit_holds(): void
    {
        $this->connection('telegram', '888');
        $other = WalletGroup::create(['owner_user_id' => User::factory()->create()->id, 'name' => 'Other', 'kind' => WalletGroup::KIND_BUSINESS]);
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['id' => 888, 'username' => 'x']], 200)]);

        try {
            app(ChannelConnector::class)->connectTelegram($other, 'GOOD');
            $this->fail('should refuse');
        } catch (ChannelSendException $e) {
            $this->assertStringContainsString('another workspace', $e->getMessage());
        }

        Setting::setValue('business.limit.channels', 1, 'business');
        $this->expectException(ChannelSendException::class);
        $this->expectExceptionMessage('limit of 1');
        app(ChannelConnector::class)->connectTelegram($this->group, 'GOOD');
    }

    public function test_meta_connections_check_the_account_and_get_a_verify_token(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['display_phone_number' => '+234 801 234 5678'], 200)]);
        $c = app(ChannelConnector::class)->connectWhatsApp($this->group, '1055123', str_repeat('t', 30), str_repeat('s', 20));

        $this->assertSame('+234 801 234 5678', $c->label);
        $this->assertGreaterThanOrEqual(32, strlen($c->webhook_secret));
        $again = app(ChannelConnector::class)->connectWhatsApp($this->group, '1055123', str_repeat('u', 30), str_repeat('s', 20));
        $this->assertSame($c->webhook_secret, $again->webhook_secret);               // reconnecting does not break the webhook already pasted into Meta
    }

    // ── pages ───────────────────────────────────────────────────────────────

    public function test_the_channels_page_is_owner_or_admin_only_and_clears_secrets_after_connecting(): void
    {
        $this->actingAs($this->owner)->get('/business/channels')->assertOk()->assertSee('Inbox channels');
        $this->actingAs($this->member(BusinessRole::ADMIN))->get('/business/channels')->assertOk();
        $this->actingAs($this->member(BusinessRole::AGENT))->get('/business/channels')->assertNotFound();

        Http::fake([
            'api.telegram.org/*/getMe' => Http::response(['ok' => true, 'result' => ['id' => 5, 'username' => 'b']], 200),
            'api.telegram.org/*/setWebhook' => Http::response(['ok' => true], 200),
        ]);
        Livewire::actingAs($this->owner)->test(BusinessChannels::class)->set('telegramToken', '12:SECRET')->call('connectTelegram')
            ->assertSet('telegramToken', '')->assertSee('Telegram bot connected')->assertDontSee('12:SECRET');
    }

    public function test_the_page_shows_coming_soon_when_the_module_is_off(): void
    {
        Setting::setValue('business.module.inbox', false, 'business');
        $this->actingAs($this->owner)->get('/business/channels')->assertOk()->assertSee('Shared inbox is coming soon');
    }

    private function seedThreads(): void
    {
        MessageThread::record($this->owner->id, '+2348011110000', 'personal sms', 'in', null);
        $c = $this->connection('telegram', '777');
        (new RecordChannelMessageJob($c->id, '5551', 'team telegram', 'tg:1', null))->handle();
    }

    public function test_messages_keeps_personal_and_workspace_conversations_apart(): void
    {
        $this->seedThreads();

        // Personal: only the SMS thread, no channel tabs, exactly as before.
        Livewire::actingAs($this->owner)->test(Messages::class)->assertSee('personal sms')->assertDontSee('team telegram');

        // Workspace: the workspace's threads, with channel tabs and a badge.
        Workspace::setActive($this->group);
        Livewire::actingAs($this->owner)->test(Messages::class)->assertSee('team telegram')->assertSee('Telegram')->assertDontSee('personal sms');
    }

    public function test_a_seat_with_workspace_access_sees_and_answers_the_shared_inbox_but_a_viewer_does_not(): void
    {
        $this->seedThreads();
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 7]], 200)]);

        $agent = $this->member(BusinessRole::AGENT);
        Workspace::setActive($this->group);
        Livewire::actingAs($agent)->test(Messages::class)->call('openThread', '5551', 'telegram')
            ->assertSee('team telegram')->set('reply', 'On it')->call('sendChannelReply')->assertSet('replyError', null)->assertSet('reply', '');
        $this->assertSame(1, OutboundMessage::where('channel', 'telegram')->count());

        $viewer = $this->member(BusinessRole::VIEWER);
        Workspace::setActive($this->group);
        Livewire::actingAs($viewer)->test(Messages::class)->assertDontSee('team telegram');
    }

    public function test_the_channel_filter_narrows_the_list(): void
    {
        $this->seedThreads();
        $wa = $this->connection('whatsapp', '1055');
        (new RecordChannelMessageJob($wa->id, '2348099990000', 'whatsapp ask', 'wamid.1', null))->handle();
        Workspace::setActive($this->group);

        Livewire::actingAs($this->owner)->test(Messages::class)->set('channel', 'whatsapp')->assertSee('whatsapp ask')->assertDontSee('team telegram');
    }

    public function test_with_the_module_off_workspace_channel_threads_are_hidden_everywhere(): void
    {
        $this->seedThreads();
        Setting::setValue('business.module.inbox', false, 'business');

        Livewire::actingAs($this->owner)->test(Messages::class)->assertSee('personal sms')->assertDontSee('team telegram');
    }
}
