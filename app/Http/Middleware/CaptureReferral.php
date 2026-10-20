<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Symfony\Component\HttpFoundation\Response;

/**
 * Remembers WHO sent a visitor (`?ref=CODE`) for 30 days, so that when they sign up the account is attributed to that member
 * (see AttributeReferral). Referral links, N2N invites, credit gifts and group-call links all carry `?ref=`; before this, nothing
 * ever read it, so no sign-up was ever attributed. Only a well-formed code is kept; it is never trusted beyond a lookup.
 */
class CaptureReferral
{
    public const COOKIE = 'naara_ref';

    public const KIND_COOKIE = 'naara_ref_kind';

    public const DAYS = 30;

    public function handle(Request $request, Closure $next): Response
    {
        $code = $request->isMethod('GET') ? $request->query('ref') : null;
        if (is_string($code) && preg_match('/^[A-Za-z0-9]{6,12}$/', $code) === 1 && $this->shouldKeep($request)) {
            if (class_exists(\App\Support\BusinessCardReferralPrecedence::class)) {
                Cookie::queue(Cookie::make(self::KIND_COOKIE, $request->is('b/*') ? 'brand' : 'personal', self::DAYS * 24 * 60, '/', null, $request->isSecure(), true, false, 'lax'));
            }
            Cookie::queue(Cookie::make(self::COOKIE, strtoupper($code), self::DAYS * 24 * 60, '/', null, $request->isSecure(), true, false, 'lax'));
        }

        return $next($request);
    }

    /** Whether this ?ref link should replace one already remembered (admin setting; a fork without the precedence module always lets the newest win). */
    private function shouldKeep(Request $request): bool
    {
        if (! class_exists(\App\Support\BusinessCardReferralPrecedence::class)) {
            return true;
        }
        $kind = $request->cookie(self::KIND_COOKIE);

        return \App\Support\BusinessCardReferralPrecedence::replaces($request->cookie(self::COOKIE) !== null, is_string($kind) ? $kind : null, $request->is('b/*') ? 'brand' : 'personal');
    }
}
