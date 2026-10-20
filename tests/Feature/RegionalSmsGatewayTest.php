<?php

namespace Tests\Feature;

use App\Exceptions\SmsException;
use App\Models\OutboundMessage;
use App\Models\Setting;
use App\Models\User;
use App\Models\VirtualNumber;
use App\Services\SMS\Gateways\AfricasTalkingGateway;
use App\Services\SMS\Gateways\RegionalSmsRouter;
use App\Support\ProviderStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Africa's Talking as a send-only regional gateway: request shape, failure handling, allow-list, cost, delivery reports. */
class RegionalSmsGatewayTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.africastalking.username' => 'sandbox', 'services.africastalking.api_key' => 'AT_KEY',
            'services.africastalking.sender_id' => 'NAARA', 'services.africastalking.callback_token' => 'cb-secret',
            'services.africastalking.base_url' => 'https://api.sandbox.africastalking.com',
        ]);
    }

    private function gw(): AfricasTalkingGateway
    {
        return app('sms.africastalking');
    }

    public function test_it_posts_the_documented_form_with_the_api_key_header_and_returns_the_message_id(): void
    {
        Http::fake(['*' => Http::response(['SMSMessageData' => ['Recipients' => [
            ['statusCode' => 101, 'number' => '+2348012345678', 'status' => 'Success', 'cost' => 'NGN 4.0000', 'messageId' => 'ATXid_1', 'messageParts' => 2],
        ]]], 201)]);

        $out = $this->gw()->send('+2348012345678', 'Your code is 123456');

        $this->assertSame(['provider_ref' => 'ATXid_1', 'status' => 'sent', 'parts' => 2], $out);
        Http::assertSent(fn ($r) => $r->url() === 'https://api.sandbox.africastalking.com/version1/messaging'
            && $r->hasHeader('apiKey', 'AT_KEY') && $r->hasHeader('Accept', 'application/json')
            && $r['username'] === 'sandbox' && $r['to'] === '+2348012345678' && $r['message'] === 'Your code is 123456' && $r['from'] === 'NAARA');
    }

    public function test_a_non_101_status_or_http_error_throws_so_the_caller_can_refund(): void
    {
        Http::fake(['*' => Http::response(['SMSMessageData' => ['Recipients' => [['statusCode' => 403, 'status' => 'InvalidPhoneNumber', 'number' => '+234', 'messageId' => 'None']]]], 201)]);
        try {
            $this->gw()->send('+2348012345678', 'x');
            $this->fail('expected SmsException');
        } catch (SmsException $e) {
            $this->assertStringNotContainsString('AT_KEY', $e->getMessage());
        }

        Http::fake(['*' => Http::response('nope', 500)]);
        $this->expectException(SmsException::class);
        $this->gw()->send('+2348012345678', 'x');
    }

    public function test_a_malformed_destination_never_reaches_the_provider(): void
    {
        Http::fake();
        $this->expectException(SmsException::class);
        try {
            $this->gw()->send('08012345678', 'x');
        } finally {
            Http::assertNothingSent();
        }
    }

    public function test_nothing_is_routed_until_the_admin_enables_it_and_lists_dial_codes(): void
    {
        $router = new RegionalSmsRouter;
        $this->assertNull($router->gatewayFor('+2348012345678'), 'off by default');

        Setting::setValue('sms.regional.enabled', true);
        $this->assertNull($router->gatewayFor('+2348012345678'), 'enabled but no dial codes = still none');

        Setting::setValue('sms.regional.dial_codes', '234, +254');
        $this->assertSame($this->gw(), $router->gatewayFor('+2348012345678'));
        $this->assertSame($this->gw(), $router->gatewayFor('+254700000000'));
        $this->assertNull($router->gatewayFor('+233200000000'), 'Ghana is not on the list');

        config(['services.africastalking.api_key' => null]);
        $this->assertNull($router->gatewayFor('+2348012345678'), 'no credentials = no route');
    }

    public function test_cost_is_admin_set_never_invented_and_prefers_the_longest_dial_code(): void
    {
        config(['services.africastalking.default_sms_cost' => 0.05]);
        $this->assertSame(0.05, $this->gw()->costPerPart('+2348012345678'));

        Setting::setValue('pricing.sms_send_cost.africastalking', 0.03);
        Setting::setValue('pricing.sms_send_cost.africastalking.cc234', 0.012);
        $this->assertSame(0.012, $this->gw()->costPerPart('+2348012345678'));
        $this->assertSame(0.03, $this->gw()->costPerPart('+254700000000'));
    }

    public function test_it_is_active_only_with_both_credentials(): void
    {
        $this->assertTrue(ProviderStatus::isActive('africastalking'));
        config(['services.africastalking.username' => null]);
        $this->assertFalse(ProviderStatus::isActive('africastalking'));
    }

    public function test_a_delivery_report_needs_the_token_and_updates_the_message_idempotently(): void
    {
        $user = User::factory()->create();
        $line = VirtualNumber::create([
            'user_id' => $user->id, 'provider' => 'twilio', 'phone_number' => '+15005550006',
            'sid' => 'PN1', 'status' => 'active', 'monthly_cost' => 1.0, 'monthly_retail' => 3.0,
        ]);
        $msg = OutboundMessage::create([
            'user_id' => $user->id, 'virtual_number_id' => $line->id, 'to_number' => '+2348012345678', 'body' => 'hi',
            'provider' => 'africastalking', 'provider_ref' => 'ATXid_1', 'status' => 'sent', 'reference' => 'sms-send:t:1',
        ]);
        $url = route('webhooks.sms-delivery', 'africastalking');

        $this->postJson($url, ['id' => 'ATXid_1', 'status' => 'Delivered'])->assertStatus(401);
        $this->assertSame('sent', $msg->fresh()->status);
        $this->assertDatabaseHas('webhook_logs', ['provider' => 'africastalking:delivery', 'verified' => false]);

        $this->postJson($url.'?token=cb-secret', ['id' => 'ATXid_1', 'status' => 'Delivered'])->assertOk();
        $this->postJson($url.'?token=cb-secret', ['id' => 'ATXid_1', 'status' => 'Delivered'])->assertOk();
        $this->assertSame('delivered', $msg->fresh()->status);

        $this->postJson($url.'?token=cb-secret', ['id' => 'ATXid_1', 'status' => 'Submitted'])->assertOk();
        $this->assertSame('delivered', $msg->fresh()->status, 'a non-final report never downgrades a final one');
        $this->postJson($url.'?token=cb-secret', ['id' => 'unknown', 'status' => 'Failed'])->assertOk();
    }

    public function test_an_unknown_provider_is_a_404(): void
    {
        $this->postJson(route('webhooks.sms-delivery', 'nope'), [])->assertStatus(404);
    }
}
