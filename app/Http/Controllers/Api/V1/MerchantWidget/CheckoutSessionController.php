<?php

namespace App\Http\Controllers\Api\V1\MerchantWidget;

use App\Http\Controllers\Controller;
use App\Models\MerchantWidgetKey;
use App\Services\MerchantWidgets\MerchantWidgetCheckoutService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use RuntimeException;

/** Create a hosted-checkout session (MASTER-ONLY, blueprint §6). Idempotent on the client-supplied key; the page does the paying. */
class CheckoutSessionController extends Controller
{
    public function store(Request $request, MerchantWidgetCheckoutService $service): JsonResponse
    {
        /** @var MerchantWidgetKey $key */
        $key = $request->attributes->get('merchant_widget_key');
        $data = $request->validate([
            'kind' => 'required|in:esim,giftcard,number',
            'product_id' => 'required_unless:kind,number|nullable|integer|min:1',
            'service' => ['required_if:kind,number', 'nullable', 'string', 'max:40', 'regex:/^[a-z0-9_\-]+$/'],
            'country' => ['required_if:kind,number', 'nullable', 'string', 'max:40', 'regex:/^[a-z0-9_\-]+$/'],
            'face_value' => 'nullable|numeric|gt:0',
            'idempotency_key' => ['required', 'string', 'min:8', 'max:64', 'regex:/^[A-Za-z0-9_\-]+$/'],
            'fields' => 'nullable|array|max:6',
            'fields.*' => 'nullable|string|max:200',
        ]);

        try {
            ['order' => $order, 'token' => $token] = $service->createSession(
                $key, $data['kind'], (int) ($data['product_id'] ?? 0), isset($data['face_value']) ? (float) $data['face_value'] : null,
                $data['idempotency_key'], $request->attributes->get('merchant_widget_origin'), (array) ($data['fields'] ?? []),
                $data['kind'] === 'number' ? \App\Support\MerchantWidgetNumbers::ref((string) $data['service'], (string) $data['country']) : null,
            );
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => 'invalid', 'message' => $e->getMessage()], 422);
        } catch (RuntimeException $e) {
            return response()->json(['error' => 'rate_limited', 'message' => $e->getMessage()], 429);
        }

        return response()->json([
            'reference' => $order->reference,
            'status' => $order->status,
            'checkout_url' => $token !== '' ? route('merchant-widget.checkout', ['token' => $token]) : null,
            'expires_at' => $order->session_expires_at?->toIso8601String(),
        ], 201);
    }
}
