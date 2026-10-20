<?php

namespace App\Http\Controllers\Api\V1\MerchantWidget;

use App\Http\Controllers\Controller;
use App\Models\MerchantWidgetKey;
use App\Models\MerchantWidgetOrder;
use App\Models\SmsOrder;
use App\Services\MerchantWidgets\MerchantWidgetFulfilmentService;
use App\Support\MerchantWidgetNumbers;
use App\Support\Niche\LpaActivation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Masked order status for the widget (MASTER-ONLY, blueprint §6). Only orders created by THIS key; never cost, supplier or buyer PII. */
class OrderStatusController extends Controller
{
    public function show(Request $request, string $reference, MerchantWidgetFulfilmentService $fulfilment): JsonResponse
    {
        /** @var MerchantWidgetKey $key */
        $key = $request->attributes->get('merchant_widget_key');
        $order = MerchantWidgetOrder::query()->where('merchant_widget_key_id', $key->id)->where('reference', $reference)->first();
        abort_if($order === null, 404);

        if ($order->kind === 'number') {
            $order = $fulfilment->syncNumberOutcome($order);
        }

        return response()->json([
            'reference' => $order->reference,
            'status' => $order->status,
            'kind' => $order->kind,
            'result' => $order->status === MerchantWidgetOrder::COMPLETED ? self::result($order) : null,
        ])->header('Cache-Control', 'no-store');
    }

    /** The delivery details a shopper sees. For a number this is LIVE: the code appears the moment it arrives. */
    public static function result(MerchantWidgetOrder $order): ?array
    {
        $r = $order->result;
        if ($order->kind === 'esim' && is_array($r) && ! empty($r['lpa'])) {
            $r['install_link'] = LpaActivation::universalLink((string) $r['lpa']);   // one-tap install on a phone
        }
        if ($order->kind !== 'number' || ! is_array($r)) {
            return $r;
        }
        $sms = $order->sms_order_id ? SmsOrder::find($order->sms_order_id) : null;
        $r['code'] = $sms?->otp_code ?: null;
        $r['waiting'] = $r['code'] === null;
        $r['expires_at'] = $sms?->ordered_at?->copy()->addMinutes(MerchantWidgetNumbers::refundWindowMinutes())->toIso8601String();
        $r['refund_minutes'] = MerchantWidgetNumbers::refundWindowMinutes();

        return $r;
    }
}
