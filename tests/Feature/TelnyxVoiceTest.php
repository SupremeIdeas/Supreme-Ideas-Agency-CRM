<?php

namespace Tests\Feature;

use App\Jobs\LogCallEventJob;
use App\Jobs\RecordVoicemailJob;
use App\Jobs\SyncVoiceWebhookJob;
use App\Livewire\CallForwarding;
use App\Models\CallForwardingRule;
use App\Models\Setting;
use App\Models\User;
use App\Models\VirtualNumber;
use App\Services\SMS\Numbers\TelnyxVoiceService;
use App\Services\Voice\VoiceProviders;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

/** Telnyx voice (TeXML) behind VoiceProviderInterface: signatures, API calls, forwarding and voicemail, lane isolation. */
class TelnyxVoiceTest extends TestCase
{
    use RefreshDatabase;

    private string $secretKey;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $pair = sodium_crypto_sign_keypair();
        $this->secretKey = sodium_crypto_sign_secretkey($pair);
        config([
            'services.telnyx.api_key' => 'KEY_test', 'services.telnyx.texml_app_id' => 'app-1',
            'services.telnyx.account_sid' => 'acct-1', 'services.telnyx.webrtc_connection_id' => 'conn-1',
            'services.telnyx.public_key' => base64_encode(sodium_crypto_sign_publickey($pair)),
            'services.twilio.account_sid' => null, 'services.twilio.auth_token' => null,
        ]);
    }

    private function svc(): TelnyxVoiceService
    {
        return app('voice.telnyx');
    }

    /** @return array<string, string> signed headers for a raw body */
    private function signed(string $body, ?int $ts = null, ?string $tamperWith = null): array
    {
        $ts ??= time();
        $sig = sodium_crypto_sign_detached($ts.'|'.($tamperWith ?? $body), $this->secretKey);

        return ['telnyx-signature-ed25519' => base64_encode($sig), 'telnyx-timestamp' => (string) $ts];
    }

    private function telnyxNumber(User $user, string $provider = 'telnyx'): VirtualNumber
    {
        return VirtualNumber::create([
            'user_id' => $user->id, 'provider' => $provider, 'phone_number' => '+2349000000001',
            'sid' => 'num-1', 'status' => 'active', 'monthly_cost' => 1.0, 'monthly_retail' => 3.0,
        ]);
    }

    private function request(string $body, array $headers): Request
    {
        $r = Request::create('/webhooks/telnyx/voice', 'POST', [], [], [], [], $body);
        foreach ($headers as $k => $v) {
            $r->headers->set($k, $v);
        }

        return $r;
    }

    public function test_it_is_a_voice_provider_and_resolvable_by_name(): void
    {
        $this->assertTrue(VoiceProviders::supports('telnyx'));
        $this->assertTrue(VoiceProviders::supports('twilio'));
        $this->assertFalse(VoiceProviders::supports('vonage'));
        $this->assertInstanceOf(TelnyxVoiceService::class, VoiceProviders::for('telnyx'));
        $this->assertTrue($this->svc()->available());
    }

    public function test_a_correct_ed25519_signature_verifies_and_a_tampered_or_stale_one_does_not(): void
    {
        $body = 'To=%2B2349000000001&From=%2B15551112222';

        $this->assertTrue($this->svc()->verifyWebhook($this->request($body, $this->signed($body)), ''));
        $this->assertFalse($this->svc()->verifyWebhook($this->request($body, $this->signed($body, null, 'other body')), ''));
        $this->assertFalse($this->svc()->verifyWebhook($this->request($body, $this->signed($body, time() - 3600)), ''), 'stale timestamp is a replay');
        $this->assertFalse($this->svc()->verifyWebhook($this->request($body, []), ''), 'unsigned');
        $this->assertFalse($this->svc()->verifyWebhook($this->request($body, ['telnyx-signature-ed25519' => 'not-base64!!', 'telnyx-timestamp' => (string) time()]), ''));

        config(['services.telnyx.public_key' => '']);
        $this->assertFalse($this->svc()->verifyWebhook($this->request($body, $this->signed($body)), ''), 'no key configured never trusts');
    }

    public function test_attach_points_the_texml_app_at_our_webhook_and_assigns_the_number(): void
    {
        Http::fake(['*' => Http::response(['data' => []], 200)]);

        $this->svc()->attachVoiceWebhook('num-1', 'https://naara.test/webhooks/telnyx/voice');

        Http::assertSent(fn ($r) => $r->method() === 'PATCH' && str_ends_with($r->url(), '/texml_applications/app-1')
            && $r['voice_url'] === 'https://naara.test/webhooks/telnyx/voice' && $r->hasHeader('Authorization', 'Bearer KEY_test'));
        Http::assertSent(fn ($r) => $r->method() === 'PATCH' && str_ends_with($r->url(), '/phone_numbers/num-1/voice') && $r['connection_id'] === 'app-1');

        Http::fake(['*' => Http::response([], 200)]);
        $this->svc()->detachVoiceWebhook('num-1');
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/phone_numbers/num-1/voice') && array_key_exists('connection_id', $r->data()) && $r['connection_id'] === null);
    }

    public function test_bridge_call_uses_the_texml_outbound_endpoint(): void
    {
        Http::fake(['*' => Http::response(['call_sid' => 'call-9'], 200)]);

        $sid = $this->svc()->bridgeCall('+2349000000001', '+15551112222', 'https://naara.test/x');

        $this->assertSame('call-9', $sid);
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/texml/Accounts/acct-1/Calls')
            && $r['To'] === '+15551112222' && $r['From'] === '+2349000000001' && $r['ApplicationSid'] === 'app-1' && $r['Url'] === 'https://naara.test/x');
    }

    public function test_access_token_creates_a_credential_then_mints_the_jwt(): void
    {
        Http::fake([
            '*/telephony_credentials/cred-1/token' => Http::response('eyJ.jwt.token', 200),
            '*/telephony_credentials' => Http::response(['data' => ['id' => 'cred-1']], 201),
        ]);

        $this->assertSame('eyJ.jwt.token', $this->svc()->accessToken('user-5'));
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/telephony_credentials') && $r['connection_id'] === 'conn-1' && $r['name'] === 'user-5');
    }

    public function test_forward_xml_dials_the_target_keeps_caller_id_and_falls_through_to_a_recording(): void
    {
        $xml = $this->svc()->forwardTwiml('+2348012345678', '+15551112222', '+2348099999999', 'https://naara.test/rec');

        $this->assertStringContainsString('<Dial callerId="+15551112222" timeout="20"><Number>+2348012345678</Number></Dial>', $xml);
        $this->assertStringContainsString('<Number>+2348099999999</Number>', $xml);
        $this->assertStringContainsString('<Record maxLength="120" playBeep="true" action="https://naara.test/rec"', $xml);
    }

    public function test_voice_rate_never_invents_a_price_and_prefers_the_longest_dial_code_key(): void
    {
        config(['services.telnyx.default_voice_cost' => 0.02]);
        $this->assertSame(0.02, $this->svc()->voiceRate('+2348012345678'), 'config fallback = the Twilio fallback');

        Setting::setValue('pricing.voice_cost.telnyx.default', 0.011);
        $this->assertSame(0.011, $this->svc()->voiceRate('+2348012345678'));

        Setting::setValue('pricing.voice_cost.telnyx.cc234', 0.007);
        $this->assertSame(0.007, $this->svc()->voiceRate('+2348012345678'));
        $this->assertSame(0.011, $this->svc()->voiceRate('+447700900123'), 'another country keeps the default');
    }

    public function test_a_verified_telnyx_call_forwards_and_logs_it(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $number = $this->telnyxNumber($user);
        CallForwardingRule::create([
            'user_id' => $user->id, 'virtual_number_id' => $number->id, 'twilio_number' => $number->phone_number,
            'forward_to_number' => '+2348012345678', 'status' => 'active',
        ]);

        $body = http_build_query(['To' => '+2349000000001', 'From' => '+15551112222', 'CallSid' => 'c1', 'CallStatus' => 'ringing']);
        $res = $this->call('POST', route('webhooks.telnyx.voice'), $this->parsed($body), [], [], $this->server($this->signed($body)), $body);

        $res->assertOk();
        $res->assertSee('<Number>+2348012345678</Number>', false);
        $res->assertSee('/webhooks/telnyx/recording', false);
        Queue::assertPushed(LogCallEventJob::class);
    }

    public function test_an_unsigned_telnyx_call_is_rejected(): void
    {
        $body = http_build_query(['To' => '+2349000000001', 'From' => '+15551112222']);

        $this->call('POST', route('webhooks.telnyx.voice'), [], [], [], $this->server([]), $body)->assertStatus(403);
        $this->assertDatabaseHas('webhook_logs', ['provider' => 'telnyx:voice', 'verified' => false]);
    }

    public function test_lanes_never_cross_a_twilio_rule_is_not_served_by_the_telnyx_webhook(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $number = $this->telnyxNumber($user, 'twilio');
        CallForwardingRule::create([
            'user_id' => $user->id, 'virtual_number_id' => $number->id, 'twilio_number' => $number->phone_number,
            'forward_to_number' => '+2348012345678', 'status' => 'active',
        ]);

        $body = http_build_query(['To' => '+2349000000001', 'From' => '+15551112222']);
        $res = $this->call('POST', route('webhooks.telnyx.voice'), $this->parsed($body), [], [], $this->server($this->signed($body)), $body);

        $res->assertSee('<Reject', false);
        $res->assertDontSee('<Number>', false);
    }

    public function test_the_telnyx_recording_webhook_queues_a_voicemail_tagged_telnyx(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $number = $this->telnyxNumber($user);
        CallForwardingRule::create([
            'user_id' => $user->id, 'virtual_number_id' => $number->id, 'twilio_number' => $number->phone_number,
            'forward_to_number' => '+2348012345678', 'status' => 'active',
        ]);

        $body = http_build_query(['To' => '+2349000000001', 'From' => '+15551112222', 'RecordingUrl' => 'https://s3.example/rec.mp3', 'RecordingSid' => 'r1', 'RecordingDuration' => 12]);
        $this->call('POST', route('webhooks.telnyx.recording'), $this->parsed($body), [], [], $this->server($this->signed($body)), $body)->assertOk();

        Queue::assertPushed(RecordVoicemailJob::class, fn ($j) => $j->provider === 'telnyx' && $j->recordingSid === 'r1');
    }

    public function test_a_telnyx_voicemail_downloads_from_the_signed_link_without_credentials_and_is_stored_as_telnyx(): void
    {
        \Illuminate\Support\Facades\Storage::fake(\App\Support\MediaStorage::privateDisk());
        Http::fake(['s3.example/*' => Http::response('MP3BYTES', 200)]);
        Queue::fake([\App\Jobs\TranscribeVoicemailJob::class]);
        $user = User::factory()->create();
        $number = $this->telnyxNumber($user);

        (new RecordVoicemailJob($user->id, $number->id, '+15551112222', $number->phone_number, 'https://s3.example/rec.mp3', 'r2', 9, 'telnyx'))->handle();

        $this->assertDatabaseHas('inbound_messages', ['provider' => 'telnyx', 'provider_ref' => 'voicemail:r2']);
        Http::assertSent(fn ($r) => $r->url() === 'https://s3.example/rec.mp3' && ! $r->hasHeader('Authorization'));
    }

    public function test_forwarding_is_set_up_on_the_provider_that_owns_the_number(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $number = $this->telnyxNumber($user);

        Livewire::actingAs($user)->test(CallForwarding::class)
            ->set('numberId', $number->id)->set('forwardTo', '+2348012345678')->call('save');

        Queue::assertPushed(SyncVoiceWebhookJob::class, fn ($j) => $j->provider === 'telnyx' && $j->attach && str_ends_with($j->voiceUrl, '/webhooks/telnyx/voice'));
    }

    public function test_a_number_on_a_provider_without_voice_cannot_be_forwarded(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $number = $this->telnyxNumber($user, 'vonage');

        Livewire::actingAs($user)->test(CallForwarding::class)
            ->set('numberId', $number->id)->set('forwardTo', '+2348012345678')->call('save')
            ->assertSet('error', 'Call forwarding isn’t available on this number yet.');

        Queue::assertNotPushed(SyncVoiceWebhookJob::class);
        $this->assertDatabaseCount('call_forwarding_rules', 0);
    }

    /** The form fields a real request would also expose, parsed from the same raw body that is signed. */
    private function parsed(string $body): array
    {
        parse_str($body, $out);

        return $out;
    }

    private function server(array $headers): array
    {
        $server = [];
        foreach ($headers as $k => $v) {
            $server['HTTP_'.strtoupper(str_replace('-', '_', $k))] = $v;
        }
        $server['CONTENT_TYPE'] = 'application/x-www-form-urlencoded';

        return $server;
    }
}
