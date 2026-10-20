<?php

namespace Tests\Feature;

use App\Jobs\CreditWalletJob;
use App\Models\EsimOrder;
use App\Models\MerchantEarning;
use App\Models\MerchantWidgetOrder;
use App\Models\Setting;
use App\Models\User;
use App\Services\MerchantWidgets\MerchantWidgetCheckoutService;
use App\Services\Payments\PaymentGatewayInterface;
use App\Services\Wallet\WalletService;
use App\Support\MerchantWidgetSettings;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Tests\Support\FakeEsimProvider;

/** Naara Widgets money path (blueprint §7, §14): idempotency, guest provisioning, fulfil-after-credit, orphan reversal, accrual. */
class MerchantWidgetCheckoutTest extends MerchantWidgetTestCase
{
    private function widgetSession(string $idem = 'idem-0001'): array
    {
        $plan = $this->plan();
        ['key' => $key] = $this->key();

        return [$key, app(MerchantWidgetCheckoutService::class)->createSession($key, 'esim', $plan->id, null, $idem, 'mystore.com'), $plan];
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
                \Illuminate\Support\Facades\Cache::put('widget-test-meta', $meta, 60);

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

    /** Simulate the webhook-verified credit: exactly what PaymentWebhookController -> CreditWalletJob does. */
    private function pay(MerchantWidgetOrder $order): void
    {
        $order = $order->fresh();
        (new CreditWalletJob($order->payment_gateway, $order->payment_reference, $order->buyer_user_id, (float) $order->merchant_price_usd, 'USD'))
            ->handle(app(WalletService::class));
    }

    public function test_replaying_a_session_create_never_makes_a_second_order(): void
    {
        [$key, $first, $plan] = $this->widgetSession('same-key-1');

        $again = app(MerchantWidgetCheckoutService::class)->createSession($key, 'esim', $plan->id, null, 'same-key-1', 'mystore.com');

        $this->assertSame($first['order']->id, $again['order']->id);
        $this->assertSame(1, MerchantWidgetOrder::count());
        $this->assertNotSame($first['token'], $again['token'], 'a replay re-issues the single-use token');
        $this->assertNull(app(MerchantWidgetCheckoutService::class)->orderForToken($first['token']), 'the old token stops working');
    }

    public function test_the_session_endpoint_creates_a_priced_pending_order_and_a_number_needs_the_admin_switch(): void
    {
        $plan = $this->plan();
        ['key' => $key] = $this->key();

        $res = $this->postJson('/api/v1/merchant-widget/checkout/sessions', ['kind' => 'esim', 'product_id' => $plan->id, 'idempotency_key' => 'abcdefgh1'], $this->h($key))
            ->assertCreated()->assertJsonStructure(['reference', 'status', 'checkout_url', 'expires_at']);

        $order = MerchantWidgetOrder::firstOrFail();
        $this->assertSame('pending_payment', $order->status);
        $this->assertGreaterThan((float) $order->retail_price_usd, (float) $order->merchant_price_usd);
        $this->assertStringContainsString('/widget/checkout/', $res->json('checkout_url'));

        $this->postJson('/api/v1/merchant-widget/checkout/sessions', ['kind' => 'number', 'product_id' => 1, 'idempotency_key' => 'abcdefgh2'], $this->h($key))->assertStatus(422);   // numbers are off by default
    }

    public function test_a_new_email_creates_exactly_one_attributed_guest_and_an_existing_one_is_reused(): void
    {
        [$key, $s] = $this->widgetSession();
        $svc = app(MerchantWidgetCheckoutService::class);

        $a = $svc->resolveBuyer($s['order'], 'Shopper@Example.com');
        $b = $svc->resolveBuyer($s['order']->fresh(), 'shopper@example.com');

        $this->assertSame($a->id, $b->id);
        $this->assertSame(1, User::where('email', 'shopper@example.com')->count());
        $this->assertTrue((bool) $a->fresh()->is_widget_guest);
        $this->assertSame('widget', $a->fresh()->signup_channel);
        $this->assertSame($key->merchant_id, $a->fresh()->merchant_id);
    }

    public function test_a_second_merchants_widget_never_reassigns_an_existing_customer(): void
    {
        [$key, $s] = $this->widgetSession();
        $other = $this->merchant('other');
        $existing = User::factory()->create(['email' => 'linked@example.com', 'is_active' => true]);
        $existing->forceFill(['merchant_id' => $other->id])->save();

        app(MerchantWidgetCheckoutService::class)->resolveBuyer($s['order'], 'linked@example.com');

        $this->assertSame($other->id, $existing->fresh()->merchant_id);
        $this->assertFalse((bool) $existing->fresh()->is_widget_guest);
    }

    public function test_starting_payment_locks_one_payment_per_session_and_links_it_to_the_order(): void
    {
        $this->fakeGateway();
        [, $s] = $this->widgetSession();
        $svc = app(MerchantWidgetCheckoutService::class);

        $url = $svc->startPayment($s['order'], 'buyer@example.com', 'stripe', 'USD');

        $this->assertStringStartsWith('https://pay.test/', $url);
        $this->assertNotNull($s['order']->fresh()->payment_reference);
        $this->expectException(InvalidArgumentException::class);
        $svc->startPayment($s['order']->fresh(), 'buyer@example.com', 'stripe', 'USD');
    }

    public function test_a_first_time_guest_above_the_admin_cap_is_stopped_before_any_payment(): void
    {
        $this->fakeGateway();
        Setting::setValue(MerchantWidgetSettings::FIRST_TIME_GUEST_CAP_USD, 5);
        [, $s] = $this->widgetSession();

        try {
            app(MerchantWidgetCheckoutService::class)->startPayment($s['order'], 'big@example.com', 'stripe', 'USD');
            $this->fail('expected the first-time guest cap to block this');
        } catch (\RuntimeException) {
            $this->assertNull($s['order']->fresh()->payment_reference);
        }
    }

    public function test_a_paid_order_is_fulfilled_and_the_merchant_margin_is_accrued_once(): void
    {
        $this->fakeGateway();
        app()->instance('esim.esimgo', new FakeEsimProvider(orderResponse: ['iccid' => '8944', 'orderReference' => 'W1', 'qr_code' => 'https://qr.test/1']));
        [$key, $s] = $this->widgetSession();
        $order = $s['order'];
        app(MerchantWidgetCheckoutService::class)->startPayment($order, 'buyer@example.com', 'stripe', 'USD');

        $this->pay($order);   // webhook-verified credit -> hook -> FulfilMerchantWidgetOrderJob
        $this->pay($order);   // a replayed webhook must change nothing

        $order = $order->fresh();
        $this->assertSame('completed', $order->status);
        $this->assertSame(1, EsimOrder::count());
        $this->assertSame('esim', $order->result['kind']);
        $this->assertArrayNotHasKey('wholesale_cost', $order->result);

        $earnings = MerchantEarning::where('merchant_id', $key->merchant_id)->get();
        $this->assertCount(1, $earnings);
        $this->assertSame('widget:esim', $earnings->first()->source_type);
        $this->assertEqualsWithDelta((float) $order->merchant_price_usd - (float) $order->retail_price_usd, (float) $earnings->first()->amount, 0.0001);
        $this->assertEqualsWithDelta(0.0, app(WalletService::class)->spendableUsd($order->buyer), 0.0001, 'the credit was consumed by the purchase');
    }

    public function test_a_provider_failure_after_a_paid_credit_refunds_and_records_no_earning(): void
    {
        $this->fakeGateway();
        app()->instance('esim.esimgo', new FakeEsimProvider(shouldThrow: true));
        [$key, $s] = $this->widgetSession();
        $order = $s['order'];
        app(MerchantWidgetCheckoutService::class)->startPayment($order, 'buyer@example.com', 'stripe', 'USD');

        $this->pay($order);

        $order = $order->fresh();
        $this->assertSame('failed', $order->status);
        $this->assertSame(0, EsimOrder::count());
        $this->assertSame(0, MerchantEarning::where('merchant_id', $key->merchant_id)->count());
        $this->assertEqualsWithDelta((float) $order->merchant_price_usd, app(WalletService::class)->spendableUsd($order->buyer), 0.0001, 'never charged without delivering');
    }

    public function test_the_status_endpoint_is_masked_and_scoped_to_the_creating_key(): void
    {
        $this->fakeGateway();
        app()->instance('esim.esimgo', new FakeEsimProvider(orderResponse: ['iccid' => '1', 'orderReference' => 'W2']));
        [$key, $s] = $this->widgetSession();
        app(MerchantWidgetCheckoutService::class)->startPayment($s['order'], 'b@example.com', 'stripe', 'USD');
        $this->pay($s['order']);

        $body = $this->getJson('/api/v1/merchant-widget/orders/'.$s['order']->reference.'/status', $this->h($key))->assertOk()->assertJsonPath('status', 'completed')->getContent();
        foreach (['wholesale', 'cost', 'buyer_email', 'provider_order_ref'] as $leak) {
            $this->assertStringNotContainsString($leak, $body);
        }

        ['key' => $otherKey] = $this->key($this->merchant('rival'));
        $this->getJson('/api/v1/merchant-widget/orders/'.$s['order']->reference.'/status', $this->h($otherKey))->assertNotFound();
    }

    public function test_velocity_on_a_single_key_is_capped_by_an_admin_setting(): void
    {
        Setting::setValue(MerchantWidgetSettings::VELOCITY_PER_KEY_HOUR, 2);
        $plan = $this->plan();
        ['key' => $key] = $this->key();
        $svc = app(MerchantWidgetCheckoutService::class);
        $svc->createSession($key, 'esim', $plan->id, null, 'velocity-1', null);
        $svc->createSession($key, 'esim', $plan->id, null, 'velocity-2', null);

        $this->expectException(\RuntimeException::class);
        $svc->createSession($key, 'esim', $plan->id, null, 'velocity-3', null);
    }

    public function test_the_gateway_is_given_a_signed_return_page_and_that_page_shows_status_only(): void
    {
        $this->fakeGateway();
        [, $s] = $this->widgetSession();
        app(MerchantWidgetCheckoutService::class)->startPayment($s['order'], 'buyer@example.com', 'stripe', 'USD');

        $meta = \Illuminate\Support\Facades\Cache::get('widget-test-meta');
        foreach (['return_url', 'redirect_url', 'success_url', 'cancel_url', 'callback_url'] as $k) {
            $this->assertSame($meta['return_url'], $meta[$k], $k);
        }
        $this->assertStringContainsString('/widget/return/', $meta['return_url']);

        $this->get($meta['return_url'])->assertOk()->assertSee('Finishing your order')->assertSee('Back to Sahara Connect')
            ->assertHeader('Referrer-Policy', 'no-referrer');

        // Unsigned or tampered URLs are refused.
        $this->get('/widget/return/'.$s['order']->id)->assertForbidden();
        $this->get($meta['return_url'].'x')->assertForbidden();

        // Once delivered it says so, still without leaking the delivery payload.
        $s['order']->forceFill(['status' => MerchantWidgetOrder::COMPLETED, 'result' => ['lpa_string' => 'LPA:1$SECRET']])->save();
        $this->get($meta['return_url'])->assertOk()->assertSee('All done. Check your email.')->assertDontSee('SECRET');
    }

    public function test_the_hosted_checkout_only_offers_working_gateway_and_currency_pairs_and_explains_the_order(): void
    {
        config(['services.stripe.secret_key' => 'sk_test_x']);
        $this->fakeGateway();
        [, $s, $plan] = $this->widgetSession();

        $html = $this->get('/widget/checkout/'.$s['token'])->assertOk()
            ->assertSee('Secure checkout')->assertSee($plan->name)->assertSee('What happens next')->assertSee('NW-'.$s['order']->id)
            ->assertSee('refunded automatically')->getContent();

        // The page ships the per-gateway currency map the shopper's choices are built from: nothing outside the matrix can be picked.
        $this->assertMatchesRegularExpression('/var GATEWAYS = \{"stripe":\[[^\]]*"USD"/', $html);
        $this->assertStringNotContainsString('cost_price', $html);
    }

    public function test_a_used_or_unknown_checkout_link_is_still_a_404_but_says_what_happened(): void
    {
        $this->get('/widget/checkout/not-a-real-token')->assertNotFound()->assertSee('This checkout has ended')->assertSee('been charged', false);
    }

    public function test_the_widget_can_force_a_theme_and_an_accent_colour_on_the_hosted_page(): void
    {
        config(['services.stripe.secret_key' => 'sk_test_x']);
        $this->fakeGateway();
        [, $s] = $this->widgetSession();

        $dark = $this->get('/widget/checkout/'.$s['token'].'?theme=dark&color=%23FFD60A')->assertOk()->getContent();
        $this->assertStringContainsString('--bg:#0b1624', $dark);
        $this->assertStringContainsString('--brand:#FFD60A', $dark);
        $this->assertStringContainsString('--on-brand:#0d1b2a', $dark, 'a pale accent gets dark text, not white');
        $this->get('/widget/checkout/'.$s['token'].'?color=javascript:alert(1)')->assertOk()->assertDontSee('javascript:alert');
    }

    public function test_with_no_active_gateway_the_page_says_payments_are_paused_instead_of_an_empty_form(): void
    {
        config(['services.stripe.secret_key' => null]);
        [, $s] = $this->widgetSession();

        $this->get('/widget/checkout/'.$s['token'])->assertOk()->assertSee('Payments are paused')->assertDontSee('id="pay"', false);
    }

    public function test_a_held_order_is_explained_on_the_return_page(): void
    {
        $this->fakeGateway();
        [, $s] = $this->widgetSession();
        $svc = app(MerchantWidgetCheckoutService::class);
        $svc->startPayment($s['order'], 'buyer@example.com', 'stripe', 'USD');
        $url = \Illuminate\Support\Facades\Cache::get('widget-test-meta')['return_url'];
        $s['order']->forceFill(['status' => MerchantWidgetOrder::HELD])->save();

        $this->get($url)->assertOk()->assertSee('needs a quick check')->assertDontSee('Finishing your order');
    }
}
