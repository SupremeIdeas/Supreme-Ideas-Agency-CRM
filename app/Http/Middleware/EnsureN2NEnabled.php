<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** N2N is master-only and admin-switched: off shows the friendly "Coming soon" sheet on page loads (404 for API/realtime/media, and on any non-master install). */
class EnsureN2NEnabled extends EnsureFeatureLive
{
    public function handle(Request $request, Closure $next, string $key = 'n2n'): Response
    {
        return parent::handle($request, $next, 'n2n');
    }
}
