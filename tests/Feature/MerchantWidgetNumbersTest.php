<?php

namespace Tests\Feature;

use App\Events\OtpReceived;
use App\Exceptions\OutOfStockException;
use App\Jobs\CreditWalletJob;
use App\Models\MerchantEarning;
use App\Models\MerchantWidgetOrder;
use App\Models\Setting;
use App\Models\SmsOrder;
use App\Models\User;
use App\Services\MerchantWidgets\MerchantWidgetCheckoutService;
use App\Services\Payments\PaymentGatewayInterface;
use App\Services\SMS\OtpStatus;
use App\Services\Wallet\WalletService;
use App\Support\MerchantWidgetSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Tests\Support\FakeSmsProvider;

/** Verification numbers through Naara Widgets (MASTER-ONLY): the catalogue, a live quote, the money path, and the earning that waits for the code. */
class MerchantWidgetNumbersTest extends MerchantWidgetTestCase
{
    private function numbersOn(): void
    {
        Setting::setValue(MerchantWidgetSettings::NUMBERS_ENABLED, true);
    }

    /** One working provider (5sim lane) at cost $0.20; every other provider in the lane is out of stock. */
    private function lane(float $cost = 0.20, bool $inStock = true): FakeSmsProvider
    {
        $fivesim = new FakeSmsProvider(
            price: $inStock ? $cost : new OutOfStockException('none'),
            buyResponse: ['provider_ref' => '5S-1', 'number' => '2348010000000', 'cost' => $cost, 'status' => OtpStatus::PENDING],
        );
        app()->instance('number.fivesim', $fivesim);
        foreach (['getatext', 'herosms', 'virtsms', 'smspool', 'onlinesim'] as $p) {
            app()->instance("number.{$p}", new FakeSmsProvider(price: new OutOfStockException('none')));
        }

        return $fivesim;
    }

    private function fakeGateway(): void
    {
        app()->instance('pay.stripe', new class implements PaymentGatewayInterface
        {
            public function name(): string
            {
                return 'stripe';
            }

            public function initialize(User $user, float $amount, string $currency, array $meta = []): array
            {
                return ['reference' => 'PAY-'.$user->id.'-'.(int) round($amount * 100), 'redirect_url' => 'https://pay.test/'.$meta['merchant_widget_order']];
            }

            public function verifySignature(Request $request): bool
            {
                return true;
            }

            public function parseWebhook(Request $request): ?\App\Services\Payments\PaymentEvent
            {
                return null;
            }
        });
        Setting::setValue('providers.stripe.enabled', true);
    }

    private function pay(MerchantWidgetOrder $order): void
    {
        $order = $order->fresh();
        (new CreditWalletJob($order->payment_gateway, $order->payment_reference, $order->buyer_user_id, (float) $order->merchant_price_usd, 'USD'))->handle(app(WalletService::class));
    }

    /** @return array{0: \App\Models\MerchantWidgetKey, 1: MerchantWidgetOrder} a paid-for-nothing-yet session on whatsapp / nigeria */
    private function widgetSession(string $idem = 'num-idem-0001'): array
    {
        ['key' => $key] = $this->key(null, ['esim', 'number', 'giftcard']);
        $s = app(MerchantWidgetCheckoutService::class)->createSession($key, 'number', 0, null, $idem, 'mystore.com', [], 'otp:whatsapp:nigeria');

        return [$key, $s['order']];
    }

    // ── catalogue and quote ─────────────────────────────────────────────────

    public function test_numbers_stay_out_of_the_catalogue_until_the_admin_switches_them_on_and_the_key_has_the_scope(): void
    {
        $this->plan();
        ['key' => $withScope] = $this->key(null, ['esim', 'number']);

        $this->getJson('/api/v1/merchant-widget/catalogue', $this->h($withScope))->assertOk()->assertJsonMissingPath('data.number');

        $this->numbersOn();
        $r = $this->getJson('/api/v1/merchant-widget/catalogue', $this->h($withScope))->assertOk();
        $this->assertContains('whatsapp', array_column($r->json('data.number'), 'id'));
        $this->assertNotEmpty($r->json('lookups.number_countries'));
        $this->assertSame(['esim', 'number'], array_column($r->json('kinds'), 'kind'), 'tabs come in one fixed, plain order');

        ['key' => $noScope] = $this->key($this->merchant('other'), ['esim']);
        $this->getJson('/api/v1/merchant-widget/catalogue', $this->h($noScope))->assertOk()->assertJsonMissingPath('data.number');
    }

    public function test_a_live_quote_is_a_merchant_price_above_retail_and_never_shows_cost_or_provider(): void
    {
        $this->numbersOn();
        $this->lane(0.20);
        ['key' => $key] = $this->key(null, ['number']);

        $r = $this->getJson('/api/v1/merchant-widget/number-quote?service=whatsapp&country=nigeria', $this->h($key))->assertOk()->assertJson(['available' => true, 'currency' => 'USD']);

        $this->assertGreaterThan(0.20, $r->json('price_usd'));
        $body = json_encode($r->json());
        foreach (['cost', 'provider', 'fivesim', 'wholesale'] as $leak) {
            $this->assertStringNotContainsString($leak, strtolower($body));
        }
        $this->assertSame(15, $r->json('refund_minutes'));
    }

    public function test_when_nothing_can_serve_a_pick_the_quote_says_so_plainly(): void
    {
        $this->numbersOn();
        $this->lane(inStock: false);
        ['key' => $key] = $this->key(null, ['number']);

        $this->getJson('/api/v1/merchant-widget/number-quote?service=whatsapp&country=nigeria', $this->h($key))->assertOk()->assertJson(['available' => false]);
    }

    public function test_the_quote_is_closed_when_numbers_are_off_or_the_key_lacks_the_scope_and_rejects_junk(): void
    {
        ['key' => $key] = $this->key(null, ['number']);
        $this->getJson('/api/v1/merchant-widget/number-quote?service=whatsapp&country=nigeria', $this->h($key))->assertNotFound();

        $this->numbersOn();
        ['key' => $esimOnly] = $this->key($this->merchant('b'), ['esim']);
        $this->getJson('/api/v1/merchant-widget/number-quote?service=whatsapp&country=nigeria', $this->h($esimOnly))->assertForbidden();
        $this->getJson('/api/v1/merchant-widget/number-quote?service=wha%20tsapp&country=nigeria', $this->h($key))->assertStatus(422);
        $this->getJson('/api/v1/merchant-widget/number-quote?service=notaservice&country=nigeria', $this->h($key))->assertOk()->assertJson(['available' => false]);
    }

    // ── sessions ────────────────────────────────────────────────────────────

    public function test_a_number_session_locks_the_live_price_and_the_pick(): void
    {
        $this->numbersOn();
        $this->lane(0.20);
        ['key' => $key] = $this->key(null, ['number']);

        $this->postJson('/api/v1/merchant-widget/checkout/sessions', ['kind' => 'number', 'service' => 'whatsapp', 'country' => 'nigeria', 'idempotency_key' => 'abcdefgh1'], $this->h($key))->assertCreated();

        $o = MerchantWidgetOrder::firstOrFail();
        $this->assertSame('number', $o->kind);
        $this->assertSame('otp:whatsapp:nigeria', $o->product_ref);
        $this->assertGreaterThan((float) $o->retail_price_usd, (float) $o->merchant_price_usd);
        $this->assertSame('pending_payment', $o->status);

        $this->postJson('/api/v1/merchant-widget/checkout/sessions', ['kind' => 'number', 'idempotency_key' => 'abcdefgh2'], $this->h($key))->assertStatus(422);
        $this->postJson('/api/v1/merchant-widget/checkout/sessions', ['kind' => 'number', 'service' => 'whatsapp', 'country' => 'atlantis', 'idempotency_key' => 'abcdefgh3'], $this->h($key))->assertStatus(422);
    }

    public function test_a_number_session_is_refused_while_numbers_are_off(): void
    {
        ['key' => $key] = $this->key(null, ['number']);
        $this->postJson('/api/v1/merchant-widget/checkout/sessions', ['kind' => 'number', 'service' => 'whatsapp', 'country' => 'nigeria', 'idempotency_key' => 'abcdefgh1'], $this->h($key))->assertStatus(422);
        $this->assertSame(0, MerchantWidgetOrder::count());
    }

    // ── money path ──────────────────────────────────────────────────────────

    public function test_a_paid_number_order_reserves_a_number_and_the_margin_waits_for_the_code(): void
    {
        $this->numbersOn();
        $this->lane(0.20);
        $this->fakeGateway();
        Queue::fake([\App\Jobs\PollSmsOtpJob::class]);
        [$key, $order] = $this->widgetSession();
        app(MerchantWidgetCheckoutService::class)->startPayment($order, 'buyer@example.com', 'stripe', 'USD');

        $this->pay($order);
        $this->pay($order);   // a replayed webhook changes nothing

        $order = $order->fresh();
        $this->assertSame('completed', $order->status);
        $this->assertSame(1, SmsOrder::count());
        $this->assertSame('number', $order->result['kind']);
        $this->assertSame('2348010000000', $order->result['number']);
        $this->assertSame(SmsOrder::first()->id, $order->sms_order_id);
        Queue::assertPushed(\App\Jobs\PollSmsOtpJob::class, 1);
        $this->assertSame(0, MerchantEarning::where('merchant_id', $key->merchant_id)->count(), 'no code yet, so no earning yet');
        $this->assertEqualsWithDelta(0.0, app(WalletService::class)->spendableUsd($order->buyer), 0.0001);
    }

    public function test_the_merchant_earns_once_when_the_code_arrives(): void
    {
        $this->numbersOn();
        $this->lane(0.20);
        $this->fakeGateway();
        Queue::fake([\App\Jobs\PollSmsOtpJob::class]);
        [$key, $order] = $this->widgetSession();
        app(MerchantWidgetCheckoutService::class)->startPayment($order, 'buyer@example.com', 'stripe', 'USD');
        $this->pay($order);
        $sms = SmsOrder::firstOrFail();
        $sms->update(['otp_code' => '482913', 'status' => 'completed']);

        OtpReceived::dispatch($sms->user_id, $sms->id, '482913', (string) $sms->phone_number);
        OtpReceived::dispatch($sms->user_id, $sms->id, '482913', (string) $sms->phone_number);   // a duplicate delivery

        $earnings = MerchantEarning::where('merchant_id', $key->merchant_id)->get();
        $this->assertCount(1, $earnings);
        $this->assertSame('widget:number', $earnings->first()->source_type);
        $this->assertNotNull($order->fresh()->earned_at);
    }

    public function test_no_number_available_at_fulfilment_refunds_everything_and_records_nothing(): void
    {
        $this->numbersOn();
        $this->lane(0.20);
        $this->fakeGateway();
        [$key, $order] = $this->widgetSession();
        $this->lane(inStock: false);   // stock vanished between the quote and the payment
        app(MerchantWidgetCheckoutService::class)->startPayment($order, 'buyer@example.com', 'stripe', 'USD');

        $this->pay($order);

        $order = $order->fresh();
        $this->assertSame('failed', $order->status);
        $this->assertSame(0, SmsOrder::count());
        $this->assertSame(0, MerchantEarning::where('merchant_id', $key->merchant_id)->count());
        $this->assertEqualsWithDelta((float) $order->merchant_price_usd, app(WalletService::class)->spendableUsd($order->buyer), 0.0001, 'never charged without delivering');
    }

    // ── what the shopper sees ───────────────────────────────────────────────

    public function test_the_status_shows_the_code_live_masks_everything_else_and_reports_a_timeout_as_refunded(): void
    {
        $this->numbersOn();
        $this->lane(0.20);
        $this->fakeGateway();
        Queue::fake([\App\Jobs\PollSmsOtpJob::class]);
        [$key, $order] = $this->widgetSession();
        app(MerchantWidgetCheckoutService::class)->startPayment($order, 'buyer@example.com', 'stripe', 'USD');
        $this->pay($order);
        $url = '/api/v1/merchant-widget/orders/'.rawurlencode($order->reference).'/status';

        $waiting = $this->getJson($url, $this->h($key))->assertOk()->assertJson(['status' => 'completed', 'kind' => 'number', 'result' => ['waiting' => true, 'code' => null]]);
        $this->assertNotEmpty($waiting->json('result.expires_at'));
        $this->assertStringNotContainsString('fivesim', strtolower(json_encode($waiting->json())));

        SmsOrder::first()->update(['otp_code' => '123456', 'status' => 'completed']);
        $this->getJson($url, $this->h($key))->assertJson(['result' => ['waiting' => false, 'code' => '123456']]);
    }

    public function test_a_number_that_times_out_shows_as_refunded(): void
    {
        $this->numbersOn();
        $this->lane(0.20);
        $this->fakeGateway();
        Queue::fake([\App\Jobs\PollSmsOtpJob::class]);
        [$key, $order] = $this->widgetSession();
        app(MerchantWidgetCheckoutService::class)->startPayment($order, 'buyer@example.com', 'stripe', 'USD');
        $this->pay($order);
        SmsOrder::first()->update(['status' => 'timeout']);

        $this->getJson('/api/v1/merchant-widget/orders/'.rawurlencode($order->reference).'/status', $this->h($key))->assertJson(['status' => 'refunded', 'result' => null]);
        $this->assertSame(0, MerchantEarning::where('merchant_id', $key->merchant_id)->count(), 'a number with no code never pays the merchant');
    }
}
