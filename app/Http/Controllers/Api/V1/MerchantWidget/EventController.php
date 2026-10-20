<?php

namespace App\Http\Controllers\Api\V1\MerchantWidget;

use App\Http\Controllers\Controller;
use App\Models\MerchantWidgetEvent;
use App\Models\MerchantWidgetKey;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Fire-and-forget funnel beacon (MASTER-ONLY, blueprint §4.4/§9). Append-only, outside every money transaction. A navigator.sendBeacon
 * cannot set headers, so the PUBLIC key travels in the JSON body for this one route; the Origin allowlist still applies.
 */
class EventController extends Controller
{
    public const TYPES = ['impression', 'catalogue_view', 'checkout_opened', 'checkout_abandoned'];

    public function store(Request $request): Response
    {
        /** @var MerchantWidgetKey $key */
        $key = $request->attributes->get('merchant_widget_key');
        $type = (string) $request->input('type');
        if (in_array($type, self::TYPES, true)) {
            MerchantWidgetEvent::query()->insert([
                'merchant_widget_key_id' => $key->id,
                'event_type' => $type,
                'metadata' => json_encode(array_intersect_key((array) $request->input('meta', []), array_flip(['kind', 'product_id']))),
                'created_at' => now(),
            ]);
        }

        return response()->noContent();
    }
}
