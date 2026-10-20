<?php

namespace App\Http\Middleware;

use App\Models\MerchantWidgetKey;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Product-scope gate for a widget key (MASTER-ONLY, blueprint §5.2). Mirrors ApiScope: `merchant.widget.scope:esim,giftcard` = any-of. */
class MerchantWidgetScope
{
    public function handle(Request $request, Closure $next, string ...$scopes): Response
    {
        /** @var MerchantWidgetKey|null $key */
        $key = $request->attributes->get('merchant_widget_key');

        foreach ($scopes as $scope) {
            if ($key?->hasScope($scope)) {
                return $next($request);
            }
        }

        abort(403, 'This widget key is not enabled for that product type.');
    }
}
