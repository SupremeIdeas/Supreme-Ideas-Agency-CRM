<?php

namespace App\Services\MerchantWidgets;

use App\Models\EsimPlan;
use App\Models\GiftCardProduct;
use App\Models\MerchantWidgetKey;
use App\Models\MerchantWidgetOrder;
use App\Models\TopUpIntent;
use App\Models\User;
use App\Services\Pricing\CurrencyService;
use App\Services\Pricing\PricingEngine;
use App\Support\Auditor;
use App\Support\GiftCardPricing;
use App\Support\GatewayCurrencyMatrix;
use App\Support\MerchantWidgetFraud;
use App\Support\MerchantWidgetNumbers;
use App\Support\MerchantWidgetSettings;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Guest checkout for Naara Widgets (MASTER-ONLY, blueprint §7). A widget purchase rides the EXISTING wallet rails: the shopper pays
 * through the normal gateway (webhook-verified, TopUpIntent-locked), the wallet is credited idempotently, then FulfilMerchantWidgetOrderJob
 * debits it and fulfils exactly as the retail checkout does. Nothing here touches money directly.
 */
class MerchantWidgetCheckoutService
{
    public function __construct(
        private PricingEngine $pricing,
        private GiftCardPricing $giftPricing,
        private CurrencyService $fx,
    ) {}

    /**
     * Create (or idempotently return) a pending order and a fresh single-use hosted-checkout token.
     *
     * @return array{order: MerchantWidgetOrder, token: string}
     */
    public function createSession(MerchantWidgetKey $key, string $kind, int $productId, ?float $face, string $idempotencyKey, ?string $origin, array $fields = [], ?string $productRef = null): array
    {
        if ($kind === 'number' && ! MerchantWidgetSettings::numbersEnabled()) {
            throw new InvalidArgumentException('Verification numbers are not available through this widget right now.');
        }
        if (! in_array($kind, ['esim', 'giftcard', 'number'], true) || ! $key->hasScope($kind)) {
            throw new InvalidArgumentException('This widget key is not enabled for that product type.');
        }

        $reference = 'merchantwidget:'.$key->public_key.':'.$idempotencyKey;
        $existing = MerchantWidgetOrder::query()->where('reference', $reference)->first();
        if ($existing !== null) {
            // Idempotent replay: never a second order. A still-pending order gets a fresh token (the old one stops working).
            return ['order' => $existing, 'token' => $existing->status === MerchantWidgetOrder::PENDING ? $this->issueToken($existing) : ''];
        }

        MerchantWidgetFraud::assertSessionAllowed($key);

        [$retail, $merchantPrice, $faceValue] = $this->price($key, $kind, $productId, $face, $productRef);

        $order = MerchantWidgetOrder::create([
            'merchant_widget_key_id' => $key->id,
            'merchant_id' => $key->merchant_id,
            'kind' => $kind,
            'product_id' => $productId,
            'product_ref' => $kind === 'number' ? $productRef : null,
            'face_value' => $faceValue,
            'buyer_fields' => $fields ?: null,
            'reference' => $reference,
            'status' => MerchantWidgetOrder::PENDING,
            'retail_price_usd' => $retail,
            'merchant_price_usd' => $merchantPrice,
            'currency' => 'USD',
            'origin_domain' => $origin,
        ]);
        Auditor::log('merchant_widget.session_created', MerchantWidgetOrder::class, $order->id, ['kind' => $kind, 'key' => $key->id]);

        return ['order' => $order, 'token' => $this->issueToken($order)];
    }

    /** Resolve a hosted-checkout token to its live, unexpired, unused order — or null (the page then 404s). */
    public function orderForToken(string $token): ?MerchantWidgetOrder
    {
        if ($token === '') {
            return null;
        }
        $order = MerchantWidgetOrder::query()->with(['key.merchant'])->where('session_token_hash', hash('sha256', $token))->first();
        if ($order === null || $order->status !== MerchantWidgetOrder::PENDING) {
            return null;
        }
        if ($order->session_expires_at === null || $order->session_expires_at->isPast()) {
            return null;
        }

        return $order;
    }

    /** First email wins; a returning shopper reuses their account; merchant attribution is set ONCE, for NEW guests only (§7.3). */
    public function resolveBuyer(MerchantWidgetOrder $order, string $email): User
    {
        $email = Str::lower(trim($email));
        $user = User::query()->where('email', $email)->first();
        if ($user === null) {
            $user = new User(['name' => Str::title(Str::before($email, '@')) ?: 'Guest', 'email' => $email]);
            $user->forceFill([
                'password' => Hash::make(Str::random(48)), // unusable; a guest signs in later by the normal passwordless/reset path
                'is_active' => true,
                'merchant_id' => $order->merchant_id,
                'is_widget_guest' => true,
                'signup_channel' => 'widget',
            ])->save();
            Auditor::log('merchant_widget.guest_created', User::class, $user->id, ['merchant_id' => $order->merchant_id]);
        }
        if ($order->buyer_user_id !== $user->id) {
            $order->forceFill(['buyer_user_id' => $user->id, 'buyer_email' => $email])->save();
        }

        return $user;
    }

    /**
     * Start payment on the chosen gateway. Returns the provider redirect URL. The USD amount is LOCKED here for any non-USD charge
     * (TopUpIntent), exactly like a wallet top-up, so the webhook credits what we quoted.
     */
    public function startPayment(MerchantWidgetOrder $order, string $email, string $gateway, string $currency): string
    {
        $currency = strtoupper($currency);
        if (! GatewayCurrencyMatrix::supports($gateway, $currency)) {
            throw new InvalidArgumentException(ucfirst($gateway)." doesn't accept {$currency}.");
        }
        if ($order->payment_reference !== null) {
            // One payment per session: a second start would orphan a payment the shopper may already have made.
            throw new InvalidArgumentException('Payment was already started for this order. Please start again from the shop.');
        }
        $buyer = $this->resolveBuyer($order, $email);
        MerchantWidgetFraud::assertGuestWithinCap($buyer, $order);

        $usd = (float) $order->merchant_price_usd;
        $charge = $currency === 'USD' ? $usd : round($this->fx->usdToLocal($usd, $currency), 2);

        $result = app("pay.{$gateway}")->initialize($buyer, $charge, $currency, [
            'purpose' => 'merchant_widget_checkout', 'merchant_widget_order' => $order->reference,
        ] + $this->returnMeta($order));
        $paymentRef = (string) ($result['reference'] ?? '');
        if ($paymentRef === '' || empty($result['redirect_url'])) {
            throw new InvalidArgumentException('The payment could not be started.');
        }
        if ($currency !== 'USD') {
            TopUpIntent::create([
                'user_id' => $buyer->id, 'gateway' => $gateway, 'reference' => $paymentRef,
                'charge_amount' => $charge, 'charge_currency' => $currency,
                'usd_amount' => $usd, 'rate_usd_to_local' => $this->fx->rate($currency), 'status' => 'pending',
            ]);
        }
        $order->forceFill(['payment_gateway' => $gateway, 'payment_reference' => $paymentRef, 'session_used_at' => now()])->save();
        Auditor::log('merchant_widget.payment_started', MerchantWidgetOrder::class, $order->id, ['gateway' => $gateway, 'currency' => $currency]);

        return (string) $result['redirect_url'];
    }

    /**
     * Where each gateway sends the shopper back to: a signed, expiring Naara page that shows the order's status (never the wallet, which a
     * guest cannot open). Gateways read different keys, so every name they use is set to the same URL.
     *
     * @return array<string, string>
     */
    private function returnMeta(MerchantWidgetOrder $order): array
    {
        $url = URL::temporarySignedRoute('merchant-widget.return', now()->addHours(24), ['order' => $order->id]);

        return ['return_url' => $url, 'redirect_url' => $url, 'success_url' => $url, 'cancel_url' => $url, 'callback_url' => $url];
    }

    /** @return array{0: float, 1: float, 2: ?float} [retail, merchant price, face value] */
    private function price(MerchantWidgetKey $key, string $kind, int $productId, ?float $face, ?string $productRef = null): array
    {
        if ($kind === 'number') {
            $pick = MerchantWidgetNumbers::parse($productRef);
            if ($pick === null) {
                throw new InvalidArgumentException('Pick a service and a country.');
            }
            try {
                $q = MerchantWidgetNumbers::quote($key, $pick['service'], $pick['country']);
            } catch (\App\Exceptions\SmsException $e) {
                throw new InvalidArgumentException('No number is available for that service in that country right now.');
            }

            return [$q['retail'], $q['price'], null];
        }

        if ($kind === 'esim') {
            $plan = EsimPlan::query()->where('is_active', true)->find($productId);
            if ($plan === null) {
                throw new InvalidArgumentException('Unknown or inactive plan.');
            }

            return [
                round($this->pricing->calculateRetail($plan, log: false), 4),
                round($this->pricing->merchantEsimPrice($plan, $key->merchant, log: false), 4),
                null,
            ];
        }

        $card = GiftCardProduct::query()->storefront()->find($productId);
        if ($card === null || $face === null) {
            throw new InvalidArgumentException('Unknown gift card or amount.');
        }
        $face = round($face, 2);
        $valid = $card->isRange()
            ? $face >= (float) $card->min_amount && $face <= (float) $card->max_amount
            : in_array($face, array_map('floatval', (array) $card->fixed_denominations), true);
        if (! $valid) {
            throw new InvalidArgumentException('That amount is not available for this card.');
        }

        return [
            round($this->giftPricing->retail($card, $face), 4),
            round($this->giftPricing->merchant($card, $face, $key->merchant), 4),
            $face,
        ];
    }

    private function issueToken(MerchantWidgetOrder $order): string
    {
        $token = Str::random(48);
        $order->forceFill([
            'session_token_hash' => hash('sha256', $token),
            'session_expires_at' => now()->addMinutes(MerchantWidgetSettings::sessionTtlMinutes()),
            'session_used_at' => null,
        ])->save();

        return $token;
    }
}
