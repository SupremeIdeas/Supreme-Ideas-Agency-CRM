<?php

namespace App\Http\Middleware;

use App\Models\MerchantWidgetKey;
use App\Support\Auditor;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticates a widget request (MASTER-ONLY, blueprint §5.1/§5.2). The public key is read from the `X-Naara-Widget-Key` header
 * (never the query string, so it stays out of access logs and Referer). Every request is checked LIVE: key active, merchant active,
 * and the Origin (Referer fallback) host is in the key's exact-domain allowlist. A miss is a logged 403 — never a silent pass.
 * CORS is computed here, after the allowlist check, and echoes only the matched origin (never `*`, never blind reflection).
 */
class EnsureMerchantWidgetKeyUsable
{
    public function handle(Request $request, Closure $next): Response
    {
        // CORS preflight carries no key header. Answer it only for an origin some ACTIVE key actually allows.
        if ($request->isMethod('OPTIONS')) {
            $origin = $this->originOf($request);
            if ($origin !== null && $this->anyActiveKeyAllows($origin['host'])) {
                return $this->withCors(response('', 204), $origin['raw']);
            }

            return response('', 403);
        }

        $public = (string) $request->header('X-Naara-Widget-Key', '');
        // sendBeacon cannot set headers: ONLY the funnel-beacon route may carry the (public) key in the JSON body.
        if ($public === '' && $request->routeIs('merchant-widget.events')) {
            $public = (string) $request->input('key', '');
        }
        $key = $public !== '' ? MerchantWidgetKey::with('merchant')->where('public_key', $public)->first() : null;

        if ($key === null || ! $key->usable()) {
            $this->reject('merchant_widget.key_rejected', $request, $key);
        }

        $origin = $this->originOf($request);
        if ($origin === null || ! $key->allowsHost($origin['host'])) {
            $this->reject('merchant_widget.origin_rejected', $request, $key, ['origin' => $origin['raw'] ?? null]);
        }

        $key->forceFill(['last_seen_at' => now()])->saveQuietly();
        $request->attributes->set('merchant_widget_key', $key);
        $request->attributes->set('merchant_widget_origin', $origin['host']);

        $response = $next($request);

        return $this->withCors($response, $origin['raw']);
    }

    /** @return array{raw: string, host: string}|null */
    private function originOf(Request $request): ?array
    {
        foreach (['Origin', 'Referer'] as $header) {
            $value = (string) $request->header($header, '');
            if ($value === '') {
                continue;
            }
            $parts = parse_url($value);
            if (! is_array($parts) || empty($parts['host']) || ! in_array($parts['scheme'] ?? '', ['https', 'http'], true)) {
                continue;
            }
            $host = strtolower($parts['host']).(isset($parts['port']) ? ':'.$parts['port'] : '');

            return ['raw' => ($parts['scheme']).'://'.$host, 'host' => $host];
        }

        return null;
    }

    private function anyActiveKeyAllows(string $host): bool
    {
        return MerchantWidgetKey::query()->where('status', MerchantWidgetKey::ACTIVE)->whereJsonContains('allowed_domains', $host)->exists();
    }

    private function withCors(Response $response, string $origin): Response
    {
        $response->headers->set('Access-Control-Allow-Origin', $origin);
        $response->headers->set('Vary', 'Origin');
        $response->headers->set('Access-Control-Allow-Headers', 'X-Naara-Widget-Key, X-Naara-Shopper, Content-Type, Accept');
        $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
        $response->headers->set('Access-Control-Max-Age', '600');

        return $response;
    }

    private function reject(string $action, Request $request, ?MerchantWidgetKey $key, array $extra = []): never
    {
        Auditor::log($action, MerchantWidgetKey::class, $key?->id, ['path' => $request->path(), ...$extra]);

        abort(403, 'This widget key is not permitted here.');
    }
}
