<?php

namespace Tests\Feature;

use App\Jobs\AlertAdminJob;
use App\Mail\MerchantWidgetShopperCodeMail;
use App\Models\EsimOrder;
use App\Models\MerchantWidgetDataRequest;
use App\Models\MerchantWidgetKey;
use App\Models\MerchantWidgetOrder;
use App\Models\MerchantWidgetShopperSession;
use App\Models\Setting;
use App\Support\MerchantWidgetSettings;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;

/** The returning shopper's "my orders" area behind a widget (MASTER-ONLY): sign-in by emailed code, scoped orders, export, deletion request, and no way to probe other shoppers. */
class MerchantWidgetShopperAccountTest extends MerchantWidgetTestCase
{
    private const EMAIL = 'buyer@example.com';

    private const BASE = '/api/v1/merchant-widget/account/';

    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clear('mwa:code:'.sha1('1|'.self::EMAIL));
    }

    /** A completed eSIM order for $email on $key's merchant. */
    private function order(MerchantWidgetKey $key, string $email = self::EMAIL, string $ref = 'r1'): MerchantWidgetOrder
    {
        $plan = \App\Models\EsimPlan::first() ?? $this->plan();
        $esim = EsimOrder::create(['user_id' => $key->merchant->owner_user_id, 'plan_id' => $plan->id, 'provider' => 'esimgo', 'qr_code_url' => 'https://qr.test/1', 'lpa_string' => 'LPA:1$smdp.test$ABC', 'status' => 'processing', 'price_charged' => 13, 'wholesale_cost' => 9.5, 'currency' => 'USD']);

        return MerchantWidgetOrder::create([
            'merchant_widget_key_id' => $key->id, 'merchant_id' => $key->merchant_id, 'buyer_email' => $email, 'kind' => 'esim', 'product_id' => $plan->id,
            'reference' => 'merchantwidget:'.$key->public_key.':'.$ref, 'status' => MerchantWidgetOrder::COMPLETED, 'retail_price_usd' => 13, 'merchant_price_usd' => 14.3, 'esim_order_id' => $esim->id,
            'result' => ['kind' => 'esim', 'plan' => 'Roam 5GB', 'qr_code_url' => 'https://qr.test/1', 'lpa' => 'LPA:1$smdp.test$ABC'],
        ]);
    }

    private function signIn(MerchantWidgetKey $key, string $email = self::EMAIL): string
    {
        Mail::fake();
        $code = null;
        $this->postJson(self::BASE.'code', ['email' => $email], $this->h($key))->assertOk();
        Mail::assertQueued(MerchantWidgetShopperCodeMail::class, function ($m) use (&$code) {
            $code = $m->code;

            return true;
        });
        $res = $this->postJson(self::BASE.'verify', ['email' => $email, 'code' => $code], $this->h($key))->assertOk()->assertJson(['ok' => true]);

        return $res->json('token');
    }

    private function auth(MerchantWidgetKey $key, string $token): array
    {
        return $this->h($key) + ['X-Naara-Shopper' => $token];
    }

    public function test_a_shopper_with_orders_gets_a_code_and_signs_in_and_sees_only_their_orders_masked(): void
    {
        ['key' => $key] = $this->key();
        $this->order($key);
        $this->order($key, 'someone@else.com', 'r2');
        $token = $this->signIn($key);

        $r = $this->getJson(self::BASE.'orders', $this->auth($key, $token))->assertOk();

        $this->assertSame(self::EMAIL, $r->json('email'));
        $this->assertCount(1, $r->json('orders'));
        $o = $r->json('orders.0');
        $this->assertSame('Roam 5GB', $o['title']);
        $this->assertSame('https://qr.test/1', $o['esim']['qr_code_url']);
        $this->assertStringStartsWith('https://esimsetup.apple.com/', $o['esim']['install_link']);
        $body = strtolower(json_encode($r->json()));
        foreach (['wholesale', 'esimgo', 'cost_price', 'someone@else'] as $leak) {
            $this->assertStringNotContainsString($leak, $body);
        }
    }

    public function test_asking_for_a_code_looks_identical_for_an_unknown_address_and_sends_nothing(): void
    {
        ['key' => $key] = $this->key();
        $this->order($key);
        Mail::fake();

        $known = $this->postJson(self::BASE.'code', ['email' => self::EMAIL], $this->h($key))->assertOk()->json();
        $unknown = $this->postJson(self::BASE.'code', ['email' => 'nobody@nowhere.com'], $this->h($key))->assertOk()->json();

        $this->assertSame($known, $unknown);
        Mail::assertQueuedCount(1);
    }

    public function test_a_wrong_code_is_refused_and_five_wrong_tries_burn_the_code(): void
    {
        ['key' => $key] = $this->key();
        $this->order($key);
        Mail::fake();
        $code = null;
        $this->postJson(self::BASE.'code', ['email' => self::EMAIL], $this->h($key))->assertOk();
        Mail::assertQueued(MerchantWidgetShopperCodeMail::class, function ($m) use (&$code) {
            $code = $m->code;

            return true;
        });
        $wrong = $code === '000000' ? '111111' : '000000';

        for ($i = 0; $i < 5; $i++) {
            $this->postJson(self::BASE.'verify', ['email' => self::EMAIL, 'code' => $wrong], $this->h($key))->assertStatus(422);
        }
        $this->postJson(self::BASE.'verify', ['email' => self::EMAIL, 'code' => $code], $this->h($key))->assertStatus(422);   // even the right one: it is burnt
    }

    public function test_an_expired_code_and_a_reused_code_are_refused(): void
    {
        ['key' => $key] = $this->key();
        $this->order($key);
        $token = $this->signIn($key);
        $this->assertNotEmpty($token);
        $row = MerchantWidgetShopperSession::whereNotNull('token_hash')->firstOrFail();
        $this->assertNull($row->code_hash, 'the code is gone once used');

        Mail::fake();
        $this->postJson(self::BASE.'code', ['email' => self::EMAIL], $this->h($key))->assertOk();
        MerchantWidgetShopperSession::whereNull('token_hash')->update(['code_expires_at' => now()->subMinute()]);
        $this->postJson(self::BASE.'verify', ['email' => self::EMAIL, 'code' => '123456'], $this->h($key))->assertStatus(422);
    }

    public function test_only_three_codes_per_address_per_window(): void
    {
        ['key' => $key] = $this->key();
        $this->order($key);
        Mail::fake();
        for ($i = 0; $i < 6; $i++) {
            $this->postJson(self::BASE.'code', ['email' => self::EMAIL], $this->h($key))->assertOk();
        }
        Mail::assertQueuedCount(3);
    }

    public function test_a_token_only_works_for_the_merchant_it_was_issued_by_and_never_without_the_header(): void
    {
        ['key' => $key] = $this->key();
        $this->order($key);
        $token = $this->signIn($key);
        ['key' => $other] = $this->key($this->merchant('other'), ['esim', 'giftcard'], ['mystore.com']);

        $this->getJson(self::BASE.'orders', $this->h($key))->assertStatus(401);
        $this->getJson(self::BASE.'orders', $this->auth($key, 'x'.$token))->assertStatus(401);
        $this->getJson(self::BASE.'orders', $this->auth($other, $token))->assertStatus(401);
        $this->getJson(self::BASE.'orders?token='.$token, $this->h($key))->assertStatus(401);   // the URL is never a way in
    }

    public function test_an_expired_session_and_a_logout_end_access(): void
    {
        ['key' => $key] = $this->key();
        $this->order($key);
        $token = $this->signIn($key);

        MerchantWidgetShopperSession::whereNotNull('token_hash')->update(['token_expires_at' => now()->subSecond()]);
        $this->getJson(self::BASE.'orders', $this->auth($key, $token))->assertStatus(401);

        $token2 = $this->signIn($key);
        $this->postJson(self::BASE.'logout', [], $this->auth($key, $token2))->assertOk();
        $this->getJson(self::BASE.'orders', $this->auth($key, $token2))->assertStatus(401);
    }

    public function test_a_shopper_can_download_everything_held_and_it_is_logged(): void
    {
        ['key' => $key] = $this->key();
        $this->order($key);
        $token = $this->signIn($key);

        $r = $this->getJson(self::BASE.'export', $this->auth($key, $token))->assertOk();

        $this->assertSame(self::EMAIL, $r->json('email'));
        $this->assertCount(1, $r->json('orders'));
        $this->assertArrayNotHasKey('wholesale_cost', $r->json('orders.0'));
        $this->assertSame(1, MerchantWidgetDataRequest::where('kind', 'export')->count());
    }

    public function test_a_deletion_request_is_queued_for_an_admin_once_and_nothing_is_deleted_by_the_shopper(): void
    {
        Queue::fake([AlertAdminJob::class]);
        ['key' => $key] = $this->key();
        $o = $this->order($key);
        $token = $this->signIn($key);

        $this->postJson(self::BASE.'delete-request', [], $this->auth($key, $token))->assertOk()->assertJson(['requested' => true, 'status' => 'open']);
        $this->postJson(self::BASE.'delete-request', [], $this->auth($key, $token))->assertOk();

        $this->assertSame(1, MerchantWidgetDataRequest::where('kind', 'delete')->where('status', 'open')->count());
        $this->assertNotNull(MerchantWidgetOrder::find($o->id), 'an admin decides; the shopper cannot erase records');
        Queue::assertPushed(AlertAdminJob::class, 1);
    }

    public function test_a_number_order_shows_its_code_live_in_the_account(): void
    {
        Setting::setValue(MerchantWidgetSettings::NUMBERS_ENABLED, true);
        ['key' => $key] = $this->key(null, ['esim', 'number']);
        $sms = \App\Models\SmsOrder::create(['user_id' => $key->merchant->owner_user_id, 'provider' => 'fivesim', 'service_id' => 'whatsapp', 'service_name' => 'whatsapp', 'country' => 'nigeria', 'type' => 'otp', 'getatext_id' => 'X1', 'phone_number' => '2348010000000', 'otp_code' => '654321', 'status' => 'completed', 'provider_cost' => 0.2, 'charged_to_user' => 0.4, 'ordered_at' => now()]);
        MerchantWidgetOrder::create([
            'merchant_widget_key_id' => $key->id, 'merchant_id' => $key->merchant_id, 'buyer_email' => self::EMAIL, 'kind' => 'number', 'product_id' => 0, 'product_ref' => 'otp:whatsapp:nigeria', 'sms_order_id' => $sms->id,
            'reference' => 'merchantwidget:'.$key->public_key.':n1', 'status' => MerchantWidgetOrder::COMPLETED, 'retail_price_usd' => 0.4, 'merchant_price_usd' => 0.5, 'earned_at' => now(),
            'result' => ['kind' => 'number', 'service' => 'WhatsApp', 'country' => 'Nigeria', 'number' => '2348010000000'],
        ]);
        $token = $this->signIn($key);

        $o = $this->getJson(self::BASE.'orders', $this->auth($key, $token))->assertOk()->json('orders.0');

        $this->assertSame('654321', $o['result']['code']);
        $this->assertSame('WhatsApp number (Nigeria)', $o['title']);
        $this->assertStringNotContainsString('fivesim', strtolower(json_encode($o)));
    }

    public function test_the_whole_area_is_off_when_the_admin_switches_it_off(): void
    {
        Setting::setValue(MerchantWidgetSettings::ACCOUNT_ENABLED, false);
        ['key' => $key] = $this->key();
        $this->postJson(self::BASE.'code', ['email' => self::EMAIL], $this->h($key))->assertNotFound();
        $this->getJson('/api/v1/merchant-widget/catalogue', $this->h($key))->assertJson(['account' => false]);
    }

    public function test_the_account_calls_are_origin_checked_like_every_widget_call(): void
    {
        ['key' => $key] = $this->key();
        $this->postJson(self::BASE.'code', ['email' => self::EMAIL], $this->h($key, 'https://evil.example'))->assertForbidden();
    }
}
