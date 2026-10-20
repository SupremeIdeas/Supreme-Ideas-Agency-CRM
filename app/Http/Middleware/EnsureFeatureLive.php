<?php

namespace App\Http\Middleware;

use App\Support\ComingSoon;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** `feature.live:<key>`: the page shows the friendly "Coming soon" sheet while the feature's switch is off (see App\Support\ComingSoon). */
class EnsureFeatureLive
{
    public function handle(Request $request, Closure $next, string $key): Response
    {
        return ComingSoon::responseIfOff($request, $key) ?? $next($request);
    }
}
