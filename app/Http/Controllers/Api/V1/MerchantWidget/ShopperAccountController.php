<?php

namespace App\Http\Controllers\Api\V1\MerchantWidget;

use App\Http\Controllers\Controller;
use App\Models\MerchantWidgetKey;
use App\Models\MerchantWidgetShopperSession;
use App\Services\MerchantWidgets\MerchantWidgetShopperAccount;
use App\Support\MerchantWidgetSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The shopper's "my orders" area behind a widget (MASTER-ONLY). Same key + origin checks as every widget call; on top of that a shopper token (header
 * `X-Naara-Shopper`, never the URL) that only ever opens the shopper's OWN orders placed through THIS merchant.
 */
class ShopperAccountController extends Controller
{
    public function __construct(private MerchantWidgetShopperAccount $account) {}

    private function key(Request $r): MerchantWidgetKey
    {
        abort_unless(MerchantWidgetSettings::accountEnabled(), 404);

        return $r->attributes->get('merchant_widget_key');
    }

    private function session(Request $r): MerchantWidgetShopperSession
    {
        $s = $this->account->resolve($this->key($r)->merchant, $r->header('X-Naara-Shopper'));
        abort_if($s === null, 401, 'Please sign in again.');

        return $s;
    }

    public function requestCode(Request $request): JsonResponse
    {
        $key = $this->key($request);
        $data = $request->validate(['email' => 'required|email:rfc|max:190']);
        $this->account->requestCode($key->merchant, $data['email'], $request->ip());

        // Identical whether or not that address has orders: nobody can use this to find out who shops here.
        return response()->json(['sent' => true, 'minutes' => MerchantWidgetSettings::shopperCodeMinutes()]);
    }

    public function verify(Request $request): JsonResponse
    {
        $key = $this->key($request);
        $data = $request->validate(['email' => 'required|email:rfc|max:190', 'code' => 'required|string|regex:/^\d{6}$/']);
        $out = $this->account->verify($key->merchant, $data['email'], $data['code']);
        if ($out === null) {
            return response()->json(['ok' => false, 'message' => 'That code is not right, or it has expired. Ask for a new one.'], 422);
        }

        return response()->json(['ok' => true] + $out)->header('Cache-Control', 'no-store');
    }

    public function orders(Request $request): JsonResponse
    {
        $s = $this->session($request);

        return response()->json(['email' => $s->email, 'orders' => $this->account->orders($s)])->header('Cache-Control', 'no-store');
    }

    public function export(Request $request): JsonResponse
    {
        return response()->json($this->account->export($this->session($request)))->header('Cache-Control', 'no-store');
    }

    public function requestDeletion(Request $request): JsonResponse
    {
        $req = $this->account->requestDeletion($this->session($request));

        return response()->json(['requested' => true, 'status' => $req->status, 'message' => 'We have your request. Our team will review it and email you when it is done.']);
    }

    public function logout(Request $request): JsonResponse
    {
        $this->account->logout($this->session($request));

        return response()->json(['ok' => true]);
    }
}
