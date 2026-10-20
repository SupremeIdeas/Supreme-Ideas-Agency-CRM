<?php

namespace App\Services\MerchantWidgets;

use App\Exceptions\EsimProviderException;
use App\Exceptions\InsufficientBalanceException;
use App\Exceptions\SmsException;
use App\Jobs\PollSmsOtpJob;
use App\Jobs\AlertAdminJob;
use App\Models\EsimOrder;
use App\Models\EsimPlan;
use App\Models\GiftCardOrder;
use App\Models\GiftCardProduct;
use App\Models\MerchantWidgetOrder;
use App\Services\eSIM\ProviderRouter;
use App\Services\GiftCards\GiftCardException;
use App\Services\GiftCards\GiftCardOrderService;
use App\Models\SmsOrder;
use App\Services\Merchants\MerchantEarningsService;
use App\Services\SMS\NumberRequest;
use App\Services\SMS\SmsNumberRouter;
use App\Support\MerchantWidgetNumbers;
use App\Services\Wallet\WalletService;
use App\Support\Auditor;
use App\Support\Niche\LpaActivation;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Fulfils a PAID Naara Widgets order (MASTER-ONLY, blueprint §7.1 steps 6-7). Runs after the wallet was credited by the existing,
 * unmodified top-up pipeline. It debits exactly the merchant price (idempotent), delivers through the same ProviderRouter /
 * GiftCardOrderService the retail checkout uses, then accrues the merchant's margin through the same MerchantEarningsService call.
 * Failure philosophy is the retail one: refund, mark failed, alert. A replayed run NEVER re-orders at a provider (money rule 7).
 */
class MerchantWidgetFulfilmentService
{
    public function __construct(
        private WalletService $wallet,
        private ProviderRouter $router,
        private GiftCardOrderService $giftCards,
        private MerchantEarningsService $earnings,
        private ?SmsNumberRouter $numbers = null,
    ) {
        $this->numbers ??= app(SmsNumberRouter::class);
    }

    public function fulfil(int $orderId): ?MerchantWidgetOrder
    {
        return Cache::lock('merchant-widget-fulfil:'.$orderId, 120)->block(5, function () use ($orderId) {
            $order = MerchantWidgetOrder::query()->with(['buyer', 'merchant'])->find($orderId);
            if ($order === null || $order->status !== MerchantWidgetOrder::PENDING || $order->buyer === null) {
                return $order; // already handled (or never paid): a replay is a no-op
            }
            $order->forceFill(['status' => MerchantWidgetOrder::FULFILLING])->save();

            $buyer = $order->buyer;
            $amount = (float) $order->merchant_price_usd;

            // Gift cards take the merchant price inside GiftCardOrderService under its own idempotent reference, so the wallet is
            // debited exactly once there. eSIMs are debited here, then ordered through the same ProviderRouter as the retail checkout.
            if ($order->kind === 'giftcard') {
                try {
                    return $this->giftCard($order, $buyer, $amount);
                } catch (Throwable $e) {
                    report($e);

                    return $this->fail($order, 'fulfilment_error', true);
                }
            }

            $debitRef = 'merchantwidget_debit:'.$order->reference;
            try {
                $debit = $this->wallet->debit($buyer, $amount, 'USD', ['reference' => $debitRef, 'description' => 'Widget purchase: '.$order->kind]);
            } catch (InsufficientBalanceException) {
                // The credit was smaller than the quoted price (should not happen: the USD amount is locked). Leave the credit in the
                // wallet for admin reconciliation, mark failed, alert — never fulfil an unpaid order.
                return $this->fail($order, 'underpaid', true);
            }
            if (! $debit->wasRecentlyCreated) {
                // A previous run already debited and may have ordered at the provider before it died: do NOT order again.
                $order->forceFill(['status' => MerchantWidgetOrder::HELD, 'failure_reason' => 'replayed_after_debit'])->save();
                AlertAdminJob::dispatch('merchant_widget_replay_held', "Widget order {$order->id} was debited before and needs a manual check.", ['order_id' => $order->id]);

                return $order;
            }

            try {
                return $order->kind === 'number' ? $this->number($order, $buyer, $amount, $debitRef) : $this->esim($order, $buyer, $amount, $debitRef);
            } catch (Throwable $e) {
                report($e);

                return $this->refundAndFail($order, $buyer, $amount, $debitRef, 'fulfilment_error');
            }
        });
    }

    private function esim(MerchantWidgetOrder $order, $buyer, float $amount, string $debitRef): MerchantWidgetOrder
    {
        $plan = EsimPlan::find($order->product_id);
        if ($plan === null) {
            return $this->refundAndFail($order, $buyer, $amount, $debitRef, 'plan_missing');
        }

        try {
            $result = $this->router->orderPlan((string) $plan->id, $buyer, 'USD', $amount, $buyer, []);
        } catch (EsimProviderException) {
            // ProviderRouter already refunded the wallet (the amount charged).
            return $this->fail($order, 'no_provider', false);
        }

        try {
            $esim = EsimOrder::create([
                'user_id' => $buyer->id,
                'plan_id' => $plan->id,
                'provider' => $result->provider,
                'provider_order_ref' => data_get($result->payload, 'orderReference') ?? data_get($result->payload, 'id'),
                'iccid' => data_get($result->payload, 'iccid') ?? data_get($result->payload, 'esims.0.iccid'),
                'bundle_name' => $result->providerPlanId,
                'qr_code_url' => data_get($result->payload, 'qr_code') ?? data_get($result->payload, 'qrCodeUrl'),
                'lpa_string' => LpaActivation::fromPayload($result->payload),
                'status' => 'processing',
                'price_charged' => $amount,
                'wholesale_cost' => $result->cost,
                'currency' => 'USD',
            ]);
        } catch (Throwable) {
            // Orphan-charge guard: charged + ordered, but not saved. Refund the money and alert (same as the retail path).
            $this->wallet->refund($buyer, $amount, 'USD', ['reference' => 'refund:'.$debitRef, 'description' => 'Widget eSIM order could not be saved']);
            AlertAdminJob::dispatch('merchant_widget_esim_save_failed', "Widget eSIM order {$order->id} ordered at {$result->provider} but failed to persist; wallet refunded.", ['order_id' => $order->id]);

            return $this->fail($order, 'save_failed', false);
        }

        return $this->complete($order, ['esim_order_id' => $esim->id], [
            'kind' => 'esim',
            'plan' => $plan->name,
            'qr_code_url' => $esim->qr_code_url,
            'lpa' => $esim->lpa_string,
        ], 'esim');
    }

    /**
     * A verification number. The wallet was just debited; the SMS lane reserves a number with the amount as the margin ceiling (a provider whose live cost would
     * eat the margin is skipped). Nothing reserved = full refund, never a charge without a number. The merchant's margin is NOT booked here: it is accrued once the
     * code actually arrives (accrueNumber), so a number that never gets a code never pays the merchant.
     */
    private function number(MerchantWidgetOrder $order, $buyer, float $amount, string $debitRef): MerchantWidgetOrder
    {
        $pick = MerchantWidgetNumbers::parse($order->product_ref);
        if ($pick === null) {
            return $this->refundAndFail($order, $buyer, $amount, $debitRef, 'product_missing');
        }

        try {
            $result = $this->numbers->attempt(new NumberRequest($pick['country'], NumberRequest::TYPE_OTP, $pick['service'], $buyer, 'USD', $amount));
        } catch (SmsException) {
            return $this->refundAndFail($order, $buyer, $amount, $debitRef, 'no_number');
        }

        try {
            $sms = $result->order;
            PollSmsOtpJob::dispatch($sms->id, 'USD');   // fetches the code in the background and refunds on timeout
            $order->forceFill(['sms_order_id' => $sms->id])->save();
        } catch (Throwable $e) {
            report($e);
            // The number is reserved at our cost but we could not record it: refund and alert, never leave the shopper charged with nothing.
            $this->wallet->refund($buyer, $amount, 'USD', ['reference' => 'refund:'.$debitRef, 'description' => 'Widget number order could not be saved']);
            AlertAdminJob::dispatch('merchant_widget_number_save_failed', "Widget number order {$order->id} was reserved but could not be saved; wallet refunded.", ['order_id' => $order->id]);

            return $this->fail($order, 'save_failed', false);
        }

        return $this->complete($order, ['sms_order_id' => $sms->id], [
            'kind' => 'number',
            'service' => MerchantWidgetNumbers::serviceLabel($pick['service']),
            'country' => MerchantWidgetNumbers::countryLabel($pick['country']),
            'number' => (string) $sms->phone_number,
        ], 'number', accrue: false);
    }

    /** The code arrived: book the merchant's margin, once (idempotent on the order reference). */
    public function accrueNumber(MerchantWidgetOrder $order): void
    {
        if ($order->kind !== 'number' || $order->earned_at !== null || $order->status !== MerchantWidgetOrder::COMPLETED) {
            return;
        }
        $this->earnings->accrue($order->merchant, $order->buyer, 'widget:number', (float) $order->retail_price_usd, (float) $order->merchant_price_usd, 'earn:'.$order->reference);
        $order->forceFill(['earned_at' => now()])->save();
        Auditor::log('merchant_widget.number_earned', MerchantWidgetOrder::class, $order->id, []);
    }

    /** A number whose code never arrived is refunded by the OTP poller; mirror that on the widget order so the shopper sees the truth. */
    public function syncNumberOutcome(MerchantWidgetOrder $order): MerchantWidgetOrder
    {
        if ($order->kind !== 'number' || $order->sms_order_id === null || $order->status !== MerchantWidgetOrder::COMPLETED || $order->earned_at !== null) {
            return $order;
        }
        $sms = SmsOrder::find($order->sms_order_id);
        if ($sms !== null && in_array($sms->status, ['timeout', 'cancelled', 'canceled', 'refunded'], true)) {
            $order->forceFill(['status' => MerchantWidgetOrder::REFUNDED, 'failure_reason' => 'no_code'])->save();
        }

        return $order;
    }

    private function giftCard(MerchantWidgetOrder $order, $buyer, float $amount): MerchantWidgetOrder
    {
        $product = GiftCardProduct::find($order->product_id);
        if ($product === null) {
            return $this->fail($order, 'product_missing', false);
        }
        $fields = (array) $order->buyer_fields;
        foreach ((array) $product->required_fields as $f) {
            $k = $f['key'] ?? null;
            if ($k && ! isset($fields[$k]) && str_contains(strtolower($k), 'email')) {
                $fields[$k] = $order->buyer_email; // the shopper's email is the natural delivery address
            }
        }

        try {
            $gift = $this->giftCards->purchase($buyer, $product, (float) $order->face_value, $fields, $amount, 'merchantwidget_gift:'.$order->reference);
        } catch (GiftCardException) {
            return $this->fail($order, 'gift_failed', false); // the service refunds its own charge on a provider failure
        }

        if ($gift->status === GiftCardOrder::STATUS_REVIEW) {
            $order->forceFill(['status' => MerchantWidgetOrder::HELD, 'gift_card_order_id' => $gift->id, 'failure_reason' => 'manual_review'])->save();

            return $order;
        }

        return $this->complete($order, ['gift_card_order_id' => $gift->id], [
            'kind' => 'giftcard',
            'brand' => $product->brand_name,
            'face_value' => (float) $order->face_value,
            'status' => $gift->status,
            'receipt' => $gift->receipt,
        ], 'giftcard');
    }

    private function complete(MerchantWidgetOrder $order, array $links, array $result, string $kind, bool $accrue = true): MerchantWidgetOrder
    {
        $order->forceFill([...$links, 'status' => MerchantWidgetOrder::COMPLETED, 'result' => $result])->save();
        // The SAME call the co-branded storefront checkout makes. Idempotent on the reference; a non-positive margin records nothing.
        if ($accrue) {
            $this->earnings->accrue($order->merchant, $order->buyer, 'widget:'.$kind, (float) $order->retail_price_usd, (float) $order->merchant_price_usd, 'earn:'.$order->reference);
        }
        Auditor::log('merchant_widget.order_completed', MerchantWidgetOrder::class, $order->id, ['kind' => $kind]);

        return $order;
    }

    private function refundAndFail(MerchantWidgetOrder $order, $buyer, float $amount, string $debitRef, string $reason): MerchantWidgetOrder
    {
        $this->wallet->refund($buyer, $amount, 'USD', ['reference' => 'refund:'.$debitRef, 'description' => 'Widget purchase could not be completed']);

        return $this->fail($order, $reason, false);
    }

    private function fail(MerchantWidgetOrder $order, string $reason, bool $alert): MerchantWidgetOrder
    {
        $order->forceFill(['status' => MerchantWidgetOrder::FAILED, 'failure_reason' => $reason])->save();
        Auditor::log('merchant_widget.order_failed', MerchantWidgetOrder::class, $order->id, ['reason' => $reason]);
        if ($alert || $reason === 'fulfilment_error') {
            AlertAdminJob::dispatch('merchant_widget_order_failed', "Widget order {$order->id} failed: {$reason}.", ['order_id' => $order->id, 'reason' => $reason]);
        }

        return $order;
    }
}
