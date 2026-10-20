<?php

namespace App\Http\Middleware;

use App\Support\MerchantWidgetSettings;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Platform kill switch for the whole Naara Widgets surface (MASTER-ONLY, blueprint §12). Mirrors EnsureDeveloperApiEnabled. */
class EnsureMerchantWidgetsEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! MerchantWidgetSettings::enabled()) {
            abort(404);
        }

        return $next($request);
    }
}
