<?php

namespace Tests\Feature;

use App\Exceptions\SmsException;
use App\Models\Setting;
use App\Services\SMS\Gateways\MontyMobileGateway;
use App\Services\SMS\Gateways\RegionalSmsRouter;
use App\Support\ProviderStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Monty Mobile as a send-only SMS gateway: documented request shape, failure handling, allow-list and routing. */
class MontyMobileGatewayTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.montymobile.sms_api_key' => 'MM_KEY', 'services.montymobile.tenant' => 'tenant-uuid', 'services.montymobile.sender_id' => 'NAARA']);
    }

    private function gw(): MontyMobileGateway
    {
        return app('sms.montymobile');
    }

    private function ok(string $id = 'mm_1'): array
    {
        return ['message' => 'Success', 'code' => '8_10_200', 'success' => true, 'data' => ['messageId' => $id, 'errorCode' => 0, 'description' => null, 'destination' => '2348012345678'], 'version' => '2'];
    }

    public function test_it_posts_the_documented_json_with_the_api_key_and_tenant_headers(): void
    {
        Http::fake(['*' => Http::response($this->ok(), 200)]);
        $out = $this->gw()->send('+2348012345678', 'Your code is 123456');
        $this->assertSame(['provider_ref' => 'mm_1', 'status' => 'sent', 'parts' => 1], $out);
        Http::assertSent(fn ($r) => $r->url() === 'https://sms-notification.montymobile.com/api/v2/sms/send-sms'
            && $r->hasHeader('api-key', 'MM_KEY') && $r->hasHeader('Tenant', 'tenant-uuid')
            && $r['Source'] === 'NAARA' && $r['Message'] === 'Your code is 123456' && $r['Destination'] === '2348012345678');
    }

    public function test_a_verification_message_uses_the_otp_endpoint(): void
    {
        Http::fake(['*' => Http::response($this->ok(), 200)]);
        $this->gw()->send('+96171123456', 'Code 1234', 'NAARA', true);
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/api/v2/sms/send-otp'));
    }

    public function test_provider_errors_and_bad_input_are_failures_the_caller_refunds(): void
    {
        Http::fake(['*' => Http::response(['success' => false, 'message' => 'Invalid Sender', 'code' => '8_10_400', 'data' => ['errorCode' => 2, 'messageId' => null]], 200)]);
        try {
            $this->gw()->send('+2348012345678', 'x');
            $this->fail('expected SmsException');
        } catch (SmsException) {
            $this->addToAssertionCount(1);
        }
        Http::fake(['*' => Http::response(['Status' => 401, 'Code' => '-0_0_401'], 401)]);
        $this->expectException(SmsException::class);
        $this->gw()->send('+2348012345678', 'x');
    }

    public function test_it_rejects_a_malformed_number_without_calling_out(): void
    {
        Http::fake();
        $this->expectException(SmsException::class);
        try {
            $this->gw()->send('08012345678', 'x');
        } finally {
            Http::assertNothingSent();
        }
    }

    public function test_it_carries_traffic_only_when_enabled_and_the_dial_code_is_listed_and_the_router_picks_it(): void
    {
        $this->assertFalse($this->gw()->coversDestination('+96171123456'));
        Setting::setValue('sms.monty.enabled', true);
        Setting::setValue('sms.monty.dial_codes', '961, 971');
        $this->assertTrue($this->gw()->coversDestination('+96171123456'));
        $this->assertFalse($this->gw()->coversDestination('+2348012345678'));
        $this->assertSame('montymobile_sms', app(RegionalSmsRouter::class)->gatewayFor('+96171123456')?->key());
        $this->assertTrue(ProviderStatus::isActive('montymobile_sms'));
        config(['services.montymobile.tenant' => '']);
        $this->assertFalse(ProviderStatus::isActive('montymobile_sms'));
    }

    public function test_cost_comes_from_admin_settings_per_country_then_flat_then_the_config_fallback(): void
    {
        $this->assertSame(0.05, $this->gw()->costPerPart('+96171123456'));
        Setting::setValue('pricing.sms_send_cost.montymobile', 0.02);
        Setting::setValue('pricing.sms_send_cost.montymobile.cc961', 0.011);
        $this->assertSame(0.011, $this->gw()->costPerPart('+96171123456'));
        $this->assertSame(0.02, $this->gw()->costPerPart('+971501234567'));
    }
}
