<?php

namespace Tests\Feature\Omni;

use App\Jobs\Omni\ProcessOmniRawEvent;
use App\Livewire\BusinessChannels;
use App\Models\ChannelConnection;
use App\Models\Omni\OmniMessage;
use App\Models\Setting;
use App\Models\User;
use App\Models\WalletGroup;
use App\Services\Inbox\ChannelSendException;
use App\Services\Omni\AdapterRegistry;
use App\Services\Omni\Dto\OutboundDraft;
use App\Services\Omni\Meta\EmbeddedSignup;
use App\Services\Permissions\WorkspaceRoleService;
use App\Support\Omni\MetaSignupCsp;
use App\Support\Permissions\BusinessRole;
use App\Support\Workspace;
use Database\Seeders\BusinessRoleSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/** WhatsApp, Instagram and Messenger on the omni pipeline: signed webhooks, payload shapes, sends, the Meta handshake and Embedded Signup. */
class MetaAdaptersTest extends TestCase
{
    use \Tests\Concerns\EnablesBusinessSuite;
    use RefreshDatabase;

    private User $owner;

    private WalletGroup $ws;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(BusinessRoleSeeder::class);
        Setting::setValue('business.module.inbox', true, 'business');
        Setting::setValue('business.module.omni', true, 'business');
        $this->owner = User::factory()->create(['is_active' => true, 'email_verified_at' => now()]);
        $this->ws = WalletGroup::create(['owner_user_id' => $this->owner->id, 'name' => 'Acme', 'kind' => WalletGroup::KIND_BUSINESS]);
        app(WorkspaceRoleService::class)->assignRole($this->ws, $this->owner, BusinessRole::OWNER);
        Workspace::setActive($this->ws);
        config(['omni.meta.app_secret' => 'a1b2c3d4e5f60718293a4b5c6d7e8f90', 'omni.meta.verify_token' => 'platform-verify-token-0123456789abc']);
    }

    private function conn(string $channel, string $externalId, array $creds = ['access_token' => 'EAAJ-token-0123456789abcdef', 'app_secret' => 'ownsecret0123456789abcdef']): ChannelConnection
    {
        $c = ChannelConnection::create(['wallet_group_id' => $this->ws->id, 'channel' => $channel, 'external_id' => $externalId, 'status' => 'active', 'label' => $channel, 'credentials' => $creds, 'webhook_secret' => 'verify-'.$channel.'-0123456789']);
        $c->forceFill(['public_id' => (string) Str::uuid()])->save();

        return $c;
    }

    private function waPayload(array $messages = [], array $statuses = [], string $phoneId = '109876543210987'): array
    {
        return ['object' => 'whatsapp_business_account', 'entry' => [['id' => 'WABA1', 'changes' => [['field' => 'messages', 'value' => [
            'messaging_product' => 'whatsapp', 'metadata' => ['display_phone_number' => '15550001111', 'phone_number_id' => $phoneId],
            'contacts' => [['profile' => ['name' => 'Ada Obi'], 'wa_id' => '2348012345678']], 'messages' => $messages, 'statuses' => $statuses,
        ]]]]]];
    }

    private function sign(array $payload, string $secret): array
    {
        $body = json_encode($payload);

        return [$body, ['X-Hub-Signature-256' => 'sha256='.hash_hmac('sha256', $body, $secret), 'Content-Type' => 'application/json']];
    }

    private function adapter(string $channel)
    {
        return app(AdapterRegistry::class)->for($channel);
    }

    public function test_all_three_meta_channels_are_registered_and_need_the_24_hour_window(): void
    {
        foreach (['whatsapp', 'instagram', 'messenger'] as $ch) {
            $this->assertNotNull($this->adapter($ch), $ch);
            $this->assertTrue($this->adapter($ch)->requiresWindow(), $ch);
        }
    }

    public function test_whatsapp_text_media_and_interactive_messages_are_normalised(): void
    {
        $c = $this->conn('whatsapp', '109876543210987');
        $e = $this->adapter('whatsapp')->events($this->waPayload([
            ['from' => '2348012345678', 'id' => 'wamid.1', 'timestamp' => '1792410000', 'type' => 'text', 'text' => ['body' => 'Is the red one in stock?']],
            ['from' => '2348012345678', 'id' => 'wamid.2', 'timestamp' => '1792410001', 'type' => 'image', 'image' => ['id' => 'MEDIA9', 'mime_type' => 'image/jpeg', 'caption' => 'this one']],
            ['from' => '2348012345678', 'id' => 'wamid.3', 'timestamp' => '1792410002', 'type' => 'interactive', 'interactive' => ['type' => 'button_reply', 'button_reply' => ['id' => 'b1', 'title' => 'Yes please']]],
            ['from' => '2348012345678', 'id' => 'wamid.4', 'timestamp' => '1792410003', 'type' => 'reaction', 'reaction' => ['emoji' => 'x']],
        ]), $c);
        $this->assertCount(3, $e, 'a reaction carries nothing to show');
        $this->assertSame('Is the red one in stock?', $e[0]->text);
        $this->assertSame('2348012345678', $e[0]->externalId);
        $this->assertSame('+2348012345678', $e[0]->phone);
        $this->assertSame('Ada Obi', $e[0]->name);
        $this->assertSame('this one', $e[1]->text);
        $this->assertSame('MEDIA9', $e[1]->media[0]['file_id']);
        $this->assertSame('image', $e[1]->media[0]['type']);
        $this->assertSame('Yes please', $e[2]->text);
    }

    public function test_a_number_of_another_connection_is_not_filed_here(): void
    {
        $c = $this->conn('whatsapp', '109876543210987');
        $e = $this->adapter('whatsapp')->events($this->waPayload([['from' => '2348012345678', 'id' => 'wamid.9', 'type' => 'text', 'text' => ['body' => 'hi']]], [], '555555555555555'), $c);
        $this->assertSame([], $e);
    }

    public function test_whatsapp_statuses_move_a_message_forward_and_failures_carry_the_reason(): void
    {
        $e = $this->adapter('whatsapp')->events($this->waPayload([], [
            ['id' => 'wamid.OUT1', 'status' => 'delivered', 'timestamp' => '1792410100', 'recipient_id' => '2348012345678'],
            ['id' => 'wamid.OUT2', 'status' => 'failed', 'timestamp' => '1792410101', 'recipient_id' => '2348012345678', 'errors' => [['code' => 131047, 'title' => 'Re-engagement message']]],
        ]));
        $this->assertSame('status', $e[0]->type);
        $this->assertSame('delivered', $e[0]->status);
        $this->assertSame('failed', $e[1]->status);
        $this->assertSame('Re-engagement message', $e[1]->error);
    }

    public function test_the_signature_uses_the_connections_own_secret_or_the_platform_secret(): void
    {
        $own = $this->conn('whatsapp', '1', ['access_token' => 'EAAJ-token-0123456789abcdef', 'app_secret' => 'ownsecret0123456789abcdef']);
        $platform = $this->conn('instagram', '2', ['access_token' => 'EAAJ-token-0123456789abcdef']);
        $payload = $this->waPayload([['from' => '1', 'id' => 'a', 'type' => 'text', 'text' => ['body' => 'x']]]);

        [$body, $headers] = $this->sign($payload, 'ownsecret0123456789abcdef');
        $req = Request::create('/', 'POST', [], [], [], ['HTTP_X_HUB_SIGNATURE_256' => $headers['X-Hub-Signature-256']], $body);
        $this->assertTrue($this->adapter('whatsapp')->verify($req, $own));
        $this->assertFalse($this->adapter('instagram')->verify($req, $platform), 'the connection without its own secret needs the platform one');

        [$body2, $h2] = $this->sign($payload, 'a1b2c3d4e5f60718293a4b5c6d7e8f90');
        $req2 = Request::create('/', 'POST', [], [], [], ['HTTP_X_HUB_SIGNATURE_256' => $h2['X-Hub-Signature-256']], $body2);
        $this->assertTrue($this->adapter('instagram')->verify($req2, $platform));
        $this->assertFalse($this->adapter('whatsapp')->verify($req2, $own));

        config(['omni.meta.app_secret' => '']);
        $this->assertFalse($this->adapter('instagram')->verify($req2, $platform), 'no secret anywhere means no proof');
    }

    public function test_a_signed_webhook_files_the_message_and_a_bad_signature_files_nothing(): void
    {
        Queue::fake();
        $c = $this->conn('whatsapp', '109876543210987');
        $payload = $this->waPayload([['from' => '2348012345678', 'id' => 'wamid.77', 'timestamp' => '1792410000', 'type' => 'text', 'text' => ['body' => 'Hello Acme']]]);
        $url = route('omni.webhook', ['channel' => 'whatsapp', 'publicId' => $c->public_id]);

        [$body, $headers] = $this->sign($payload, 'wrongsecret0123456789abcdef');
        $this->call('POST', $url, [], [], [], $this->server($headers), $body)->assertStatus(403);
        Queue::assertNothingPushed();

        [$body, $headers] = $this->sign($payload, 'ownsecret0123456789abcdef');
        $this->call('POST', $url, [], [], [], $this->server($headers), $body)->assertOk();
        Queue::assertPushed(ProcessOmniRawEvent::class);

        // and the pipeline files it into this workspace only
        (new ProcessOmniRawEvent(\App\Models\Omni\OmniRawEvent::firstOrFail()->id))->handle(app(\App\Services\Omni\Pipeline::class));
        $m = OmniMessage::where('workspace_id', $this->ws->id)->firstOrFail();
        $this->assertSame('Hello Acme', $m->body);
    }

    private function server(array $headers): array
    {
        $s = [];
        foreach ($headers as $k => $v) {
            $s['HTTP_'.strtoupper(str_replace('-', '_', $k))] = $v;
        }
        $s['CONTENT_TYPE'] = 'application/json';

        return $s;
    }

    public function test_the_meta_verification_handshake_answers_only_the_right_token(): void
    {
        $c = $this->conn('whatsapp', '109876543210987');
        $base = '/webhooks/omni/whatsapp/'.$c->public_id;
        $this->get($base.'?hub.mode=subscribe&hub.verify_token='.$c->webhook_secret.'&hub.challenge=CHALLENGE123')->assertOk()->assertSeeText('CHALLENGE123');
        $this->get($base.'?hub.mode=subscribe&hub.verify_token=platform-verify-token-0123456789abc&hub.challenge=C2')->assertOk()->assertSeeText('C2');
        $this->get($base.'?hub.mode=subscribe&hub.verify_token=wrong&hub.challenge=C3')->assertStatus(403);
        $this->get($base.'?hub.mode=unsubscribe&hub.verify_token='.$c->webhook_secret.'&hub.challenge=C4')->assertStatus(403);
        $tg = $this->conn('telegram', '9', ['bot_token' => '1:abc']);
        $this->get('/webhooks/omni/telegram/'.$tg->public_id.'?hub.mode=subscribe&hub.verify_token=x&hub.challenge=C')->assertNotFound();
    }

    public function test_instagram_and_messenger_messages_are_normalised_and_echoes_dropped(): void
    {
        foreach (['instagram' => '17841400000000000', 'messenger' => '100000123456789'] as $ch => $id) {
            $c = $this->conn($ch, $id);
            $payload = ['object' => $ch === 'instagram' ? 'instagram' : 'page', 'entry' => [['id' => $id, 'time' => 1792410000000, 'messaging' => [
                ['sender' => ['id' => 'CUST1'], 'recipient' => ['id' => $id], 'timestamp' => 1792410000000, 'message' => ['mid' => 'm1', 'text' => 'Do you ship to Accra?']],
                ['sender' => ['id' => $id], 'recipient' => ['id' => 'CUST1'], 'timestamp' => 1792410001000, 'message' => ['mid' => 'm2', 'text' => 'our own reply', 'is_echo' => true]],
                ['sender' => ['id' => 'CUST1'], 'recipient' => ['id' => $id], 'timestamp' => 1792410002000, 'message' => ['mid' => 'm3', 'attachments' => [['type' => 'image', 'payload' => ['url' => 'https://cdn.example/x.jpg']]]]],
                ['sender' => ['id' => 'CUST1'], 'recipient' => ['id' => $id], 'timestamp' => 1792410003000, 'read' => ['watermark' => 1]],
                ['sender' => ['id' => 'CUST9'], 'recipient' => ['id' => 'SOMEONE-ELSE'], 'timestamp' => 1792410004000, 'message' => ['mid' => 'm4', 'text' => 'wrong account']],
            ]]]];
            $e = $this->adapter($ch)->events($payload, $c);
            $this->assertCount(2, $e, $ch);
            $this->assertSame('CUST1', $e[0]->externalId);
            $this->assertSame('Do you ship to Accra?', $e[0]->text);
            $this->assertSame('image', $e[1]->media[0]['type']);
        }
    }

    public function test_sends_use_the_graph_api_and_explain_refusals_in_plain_words(): void
    {
        $c = $this->conn('whatsapp', '109876543210987');
        Http::fake(['graph.facebook.com/*/109876543210987/messages' => Http::sequence()
            ->push(['messages' => [['id' => 'wamid.SENT1']]], 200)
            ->push(['error' => ['code' => 131047, 'message' => 'Re-engagement message']], 400)
            ->push(['error' => ['code' => 190, 'message' => 'Invalid OAuth access token']], 401)]);
        $a = $this->adapter('whatsapp');
        $this->assertSame('wamid.SENT1', $a->send($c, new OutboundDraft('2348012345678', 'Yes, in stock.')));
        Http::assertSent(fn ($r) => $r->hasHeader('Authorization', 'Bearer EAAJ-token-0123456789abcdef') && $r['to'] === '2348012345678' && $r['text']['body'] === 'Yes, in stock.' && $r['messaging_product'] === 'whatsapp');
        try {
            $a->send($c, new OutboundDraft('2348012345678', 'late'));
            $this->fail('expected a refusal');
        } catch (ChannelSendException $e) {
            $this->assertStringContainsString('24 hours', $e->getMessage());
        }
        try {
            $a->send($c, new OutboundDraft('2348012345678', 'x'));
            $this->fail('expected a refusal');
        } catch (ChannelSendException $e) {
            $this->assertStringContainsString('access token', $e->getMessage());
            $this->assertStringNotContainsString('EAAJ', $e->getMessage());
        }
        $this->expectException(ChannelSendException::class);
        $a->send($c, new OutboundDraft('2348012345678', '   '));
    }

    public function test_instagram_and_messenger_send_through_the_send_api(): void
    {
        Http::fake([
            'graph.facebook.com/*/17841400000000000/messages' => Http::response(['recipient_id' => 'CUST1', 'message_id' => 'mid.instagram'], 200),
            'graph.facebook.com/*/100000123456789/messages' => Http::response(['recipient_id' => 'CUST1', 'message_id' => 'mid.messenger'], 200),
        ]);
        foreach (['instagram' => '17841400000000000', 'messenger' => '100000123456789'] as $ch => $id) {
            $c = $this->conn($ch, $id);
            $this->assertSame('mid.'.$ch, $this->adapter($ch)->send($c, new OutboundDraft('CUST1', 'Hello')));
            Http::assertSent(fn ($r) => str_contains($r->url(), "/{$id}/messages") && $r['recipient']['id'] === 'CUST1' && $r['message']['text'] === 'Hello');
        }
    }

    public function test_connecting_with_the_omni_switch_on_gives_the_card_the_omni_callback(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['display_phone_number' => '+1 555 000 1111', 'name' => 'Acme page', 'username' => 'acme'], 200)]);
        Livewire::actingAs($this->owner)->test(BusinessChannels::class)
            ->set('waNumberId', '109876543210987')->set('waToken', 'EAAJ-token-0123456789abcdef')->set('waSecret', 'ownsecret0123456789abcdef')->call('connectWhatsApp')->assertSet('error', null)
            ->set('msPageId', '100000123456789')->set('msToken', 'EAAJ-token-0123456789abcdef')->set('msSecret', 'ownsecret0123456789abcdef')->call('connectMessenger')->assertSet('error', null)
            ->assertSee('/webhooks/omni/whatsapp/', false)->assertSee('/webhooks/omni/messenger/', false);
        $this->assertNotEmpty(ChannelConnection::where('channel', 'messenger')->firstOrFail()->public_id);
    }

    public function test_embedded_signup_exchanges_the_code_registers_the_number_and_points_the_webhook_at_the_connection(): void
    {
        config(['omni.meta.config_id' => '1234567890123456', 'omni.meta.app_id' => '2195469598059043']);
        $this->assertTrue(EmbeddedSignup::available());
        Http::fake([
            'graph.facebook.com/*/oauth/access_token*' => Http::response(['access_token' => 'EAAJ-business-token-0123456789'], 200),
            'graph.facebook.com/*/109876543210987/register' => Http::response(['success' => true], 200),
            'graph.facebook.com/*/104938271650321/subscribed_apps' => Http::response(['success' => true], 200),
            'graph.facebook.com/*/109876543210987*' => Http::response(['display_phone_number' => '+1 555 000 1111', 'verified_name' => 'Acme Shop'], 200),
        ]);
        $conn = app(EmbeddedSignup::class)->finish($this->ws, 'AQD-code', '109876543210987', '104938271650321');
        $this->assertSame('Acme Shop', $conn->label);
        $this->assertSame('EAAJ-business-token-0123456789', $conn->secret('access_token'));
        $this->assertNull($conn->secret('app_secret'), 'the platform secret is never copied onto the connection');
        Http::assertSent(fn ($r) => str_contains($r->url(), '/oauth/access_token') && $r['client_id'] === '2195469598059043' && $r['code'] === 'AQD-code');
        Http::assertSent(fn ($r) => str_contains($r->url(), '/104938271650321/subscribed_apps')
            && str_contains((string) $r['override_callback_uri'], '/webhooks/omni/whatsapp/'.$conn->public_id) && $r['verify_token'] === $conn->webhook_secret);
        Http::assertSent(fn ($r) => str_contains($r->url(), '/109876543210987/register') && preg_match('/^\d{6}$/', (string) $r['pin']) === 1);
    }

    public function test_embedded_signup_rolls_back_and_refuses_cleanly(): void
    {
        config(['omni.meta.config_id' => '1234567890123456', 'omni.meta.app_id' => '2195469598059043']);
        Http::fake(['graph.facebook.com/*/oauth/access_token*' => Http::response(['error' => ['message' => 'code expired']], 400)]);
        try {
            app(EmbeddedSignup::class)->finish($this->ws, 'bad', '109876543210987', '104938271650321');
            $this->fail('expected a refusal');
        } catch (ChannelSendException $e) {
            $this->assertStringContainsString('try again', strtolower($e->getMessage()));
        }
        $this->assertSame(0, ChannelConnection::count());

        Http::fake([
            'graph.facebook.com/*/oauth/access_token*' => Http::response(['access_token' => 'EAAJ-business-token-0123456789'], 200),
            'graph.facebook.com/*/109876543210987/register' => Http::response(['success' => true], 200),
            'graph.facebook.com/*/104938271650321/subscribed_apps' => Http::response(['error' => ['message' => 'no']], 400),
            'graph.facebook.com/*/109876543210987*' => Http::response(['verified_name' => 'Acme Shop'], 200),
        ]);
        try {
            app(EmbeddedSignup::class)->finish($this->ws, 'ok', '109876543210987', '104938271650321');
            $this->fail('expected a refusal');
        } catch (ChannelSendException) {
            $this->assertSame(0, ChannelConnection::count(), 'never left half connected');
        }
        $other = WalletGroup::create(['owner_user_id' => User::factory()->create()->id, 'name' => 'Other', 'kind' => WalletGroup::KIND_BUSINESS]);
        ChannelConnection::create(['wallet_group_id' => $other->id, 'channel' => 'whatsapp', 'external_id' => '109876543210987', 'status' => 'active', 'label' => 'x', 'credentials' => ['access_token' => 'x']]);
        $this->expectException(ChannelSendException::class);
        $this->expectExceptionMessage('already connected to another workspace');
        app(EmbeddedSignup::class)->finish($this->ws, 'ok', '109876543210987', '104938271650321');
    }

    public function test_the_connect_with_meta_button_shows_only_when_everything_is_configured_and_the_csp_follows(): void
    {
        config(['omni.meta.config_id' => '', 'omni.meta.app_secret' => '']);
        Livewire::actingAs($this->owner)->test(BusinessChannels::class)->assertDontSee('data-meta-signup', false);
        config(['omni.meta.config_id' => '1234567890123456', 'omni.meta.app_id' => '2195469598059043', 'omni.meta.app_secret' => 'a1b2c3d4e5f60718293a4b5c6d7e8f90']);
        Livewire::actingAs($this->owner)->test(BusinessChannels::class)->assertSee('data-meta-signup', false)->assertSee('Continue with Meta')->assertSee('1234567890123456', false);

        $req = Request::create('/business/channels');
        $route = \Illuminate\Support\Facades\Route::getRoutes()->getByName('business.channels');
        $req->setRouteResolver(fn () => $route->bind($req));
        $csp = MetaSignupCsp::apply("default-src 'self'; script-src 'self'; frame-src 'self'", $req);
        $this->assertStringContainsString('https://connect.facebook.net', $csp);
        $this->assertStringContainsString('connect-src', $csp);
        $other = Request::create('/dashboard');
        $this->assertSame("default-src 'self'; script-src 'self'", MetaSignupCsp::apply("default-src 'self'; script-src 'self'", $other), 'other pages keep the strict policy');
    }
}
