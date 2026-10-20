<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Business Suite master switch: off shows the friendly "Coming soon" sheet on page loads (404 for webhooks/API, and on any non-master install). */
class EnsureBusinessSuiteEnabled extends EnsureFeatureLive
{
    public function handle(Request $request, Closure $next, string $key = 'business_suite'): Response
    {
        return parent::handle($request, $next, 'business_suite');
    }
}
