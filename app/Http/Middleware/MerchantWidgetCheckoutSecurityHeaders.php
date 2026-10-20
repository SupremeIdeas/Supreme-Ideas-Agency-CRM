<?php

namespace App\Http\Middleware;

use App\Services\MerchantWidgets\MerchantWidgetCheckoutService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * THE one place the app may be framed by a third party (MASTER-ONLY, blueprint §5.4). Computes `frame-ancestors` per request from the
 * session's own widget key (`'self'` plus that key's exact allowed origins), never `*`, never another merchant's domain, never cached.
 * Every other route keeps the global `frame-ancestors 'self'` + X-Frame-Options untouched: SecurityHeaders skips ONLY the two framing
 * headers for a request flagged here, and everything else in the global policy stays.
 */
class MerchantWidgetCheckoutSecurityHeaders
{
    public function __construct(private MerchantWidgetCheckoutService $service) {}

    public function handle(Request $request, Closure $next): Response
    {
        $order = $this->service->orderForToken((string) $request->route('token'));
        $ancestors = ["'self'"];
        if ($order !== null && $order->key !== null && $order->key->usable()) {
            foreach ($order->key->allowedDomains() as $host) {
                $ancestors[] = 'https://'.$host;
                if (str_starts_with($host, 'localhost')) {
                    $ancestors[] = 'http://'.$host;
                }
            }
        }

        $request->attributes->set('merchant_widget_frame_ancestors', implode(' ', $ancestors));
        $response = $next($request);

        $response->headers->set('Content-Security-Policy', $this->policy($request, $ancestors));
        $response->headers->remove('X-Frame-Options');
        $response->headers->set('Cache-Control', 'no-store, private');
        $response->headers->set('Referrer-Policy', 'no-referrer');

        return $response;
    }

    /** The global policy with only the frame-ancestors directive replaced by this session's allowlist. */
    private function policy(Request $request, array $ancestors): string
    {
        $base = (string) config('security.csp.policy');
        $directives = array_values(array_filter(array_map('trim', explode(';', $base)), fn ($d) => $d !== '' && ! str_starts_with($d, 'frame-ancestors')));
        $directives[] = 'frame-ancestors '.implode(' ', $ancestors);

        return implode('; ', $directives);
    }
}
