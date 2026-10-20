<?php

namespace App\Http\Controllers\Api\V1\MerchantWidget;

use App\Exceptions\SmsException;
use App\Http\Controllers\Controller;
use App\Models\MerchantWidgetKey;
use App\Support\MerchantWidgetNumbers;
use App\Support\MerchantWidgetSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Live shopper price for one verification number (MASTER-ONLY). Never returns cost or provider; a miss says "not available right now", honestly. */
class NumberQuoteController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        /** @var MerchantWidgetKey $key */
        $key = $request->attributes->get('merchant_widget_key');
        abort_unless(MerchantWidgetSettings::numbersEnabled() && $key->hasScope('number'), 404);

        $data = $request->validate(['service' => ['required', 'string', 'max:40', 'regex:/^[a-z0-9_\-]+$/'], 'country' => ['required', 'string', 'max:40', 'regex:/^[a-z0-9_\-]+$/']]);

        try {
            $q = MerchantWidgetNumbers::quote($key, $data['service'], $data['country']);
        } catch (SmsException) {
            return response()->json(['available' => false, 'message' => 'No number is available for that service in that country right now. Try another country.'], 200);
        }

        return response()->json([
            'available' => true,
            'price_usd' => $q['price'],
            'currency' => 'USD',
            'service' => MerchantWidgetNumbers::serviceLabel($data['service']),
            'country' => MerchantWidgetNumbers::countryLabel($data['country']),
            'refund_minutes' => MerchantWidgetNumbers::refundWindowMinutes(),
        ])->header('Cache-Control', 'no-store');
    }
}
