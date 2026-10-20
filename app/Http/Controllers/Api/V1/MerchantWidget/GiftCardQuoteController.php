<?php

namespace App\Http\Controllers\Api\V1\MerchantWidget;

use App\Http\Controllers\Controller;
use App\Models\GiftCardProduct;
use App\Models\MerchantWidgetKey;
use App\Support\GiftCardPricing;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/** Quote a gift-card denomination in the merchant lane (MASTER-ONLY, blueprint §6). */
class GiftCardQuoteController extends Controller
{
    public function store(Request $request, int $product, GiftCardPricing $pricing): JsonResponse
    {
        /** @var MerchantWidgetKey $key */
        $key = $request->attributes->get('merchant_widget_key');
        $data = $request->validate(['face_value' => 'required|numeric|gt:0']);

        $card = GiftCardProduct::query()->storefront()->find($product);
        if ($card === null) {
            throw ValidationException::withMessages(['product' => 'Unknown or unavailable gift card.']);
        }
        $face = round((float) $data['face_value'], 2);
        $valid = $card->isRange()
            ? $face >= (float) $card->min_amount && $face <= (float) $card->max_amount
            : in_array($face, array_map('floatval', (array) $card->fixed_denominations), true);
        if (! $valid) {
            throw ValidationException::withMessages(['face_value' => 'That amount is not available for this card.']);
        }

        return response()->json([
            'product_id' => $card->id,
            'face_value' => $face,
            'face_currency' => $card->currency,
            'price_usd' => $pricing->merchant($card, $face, $key->merchant),
            'currency' => 'USD',
        ]);
    }
}
