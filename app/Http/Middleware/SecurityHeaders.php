<?php

namespace App\Http\Middleware;

use App\Support\BrandSettings;
use App\Support\ConvaiWidget;
use App\Support\SecuritySettings;
use App\Support\Turnstile;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Security response headers (blueprint Sections 19.2 & 30): the baseline set,
 * plus a Content-Security-Policy tuned for the TALL stack and HSTS over HTTPS.
 * The CSP + HSTS are config-driven (config/security.php) so they can be tuned
 * without a code change.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $headers = [
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'SAMEORIGIN',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'X-Permitted-Cross-Domain-Policies' => 'none',
            // microphone=(self): the support-chat voice-note feature
            // (resources/js/support-voice.js, getUserMedia({audio: true}))
            // needs it for same-origin content. geolocation=(self): the chat
            // composer's Location button and Naara live location use
            // navigator.geolocation ("()" blocked them silently). camera stays
            // locked at () — attach-camera is a native file picker.
            'Permissions-Policy' => 'geolocation=(self), microphone=(self), camera=(self)',
        ];

        // CSP + HSTS are admin-toggleable at runtime (config is the default).
        if (SecuritySettings::cspEnabled() && filled(config('security.csp.policy'))) {
            $policy = $this->policyWithTurnstile((string) config('security.csp.policy'));
            $policy = $this->policyWithConvai($policy);
            $policy = $this->policyWithGoogleFonts($policy);
            $policy = $this->policyWithTrustpilot($policy);
            // N2N brand-profile players (MASTER-ONLY): adds Spotify/SoundCloud frame-src only while N2N is on. Guarded so a stray copy is inert.
            if (class_exists(\App\Support\N2N\N2NEmbedCsp::class)) {
                $policy = \App\Support\N2N\N2NEmbedCsp::apply($policy);
            }
            if (class_exists(\App\Support\Omni\MetaSignupCsp::class)) {
                $policy = \App\Support\Omni\MetaSignupCsp::apply($policy, $request);   // Respond (MASTER-ONLY): Meta Embedded Signup popup on the Channels page
            }
            $headers['Content-Security-Policy'] = $policy;
        }

        if (SecuritySettings::hstsEnabled() && $request->secure()) {
            $headers['Strict-Transport-Security'] = 'max-age='.config('security.hsts.max_age').'; includeSubDomains';
        }

        // Naara Widgets hosted checkout (MASTER-ONLY): MerchantWidgetCheckoutSecurityHeaders owns CSP + framing for that ONE route.
        if ($request->attributes->has('merchant_widget_frame_ancestors')) {
            unset($headers['X-Frame-Options'], $headers['Content-Security-Policy']);
        }

        foreach ($headers as $key => $value) {
            if (! $response->headers->has($key)) {
                $response->headers->set($key, $value);
            }
        }

        return $response;
    }

    /**
     * When Turnstile is active, whitelist Cloudflare's origin in exactly the two
     * directives its widget needs (script-src + frame-src) and nowhere else, so
     * the challenge loads without loosening the policy for anything else.
     */
    private function policyWithTurnstile(string $policy): string
    {
        if (! Turnstile::active()) {
            return $policy;
        }

        $origin = Turnstile::ORIGIN;
        $directives = array_map('trim', explode(';', $policy));
        $sawFrame = false;

        foreach ($directives as $i => $directive) {
            if (str_starts_with($directive, 'script-src ') && ! str_contains($directive, $origin)) {
                $directives[$i] = $directive.' '.$origin;
            }
            if (str_starts_with($directive, 'frame-src ')) {
                $sawFrame = true;
                if (! str_contains($directive, $origin)) {
                    $directives[$i] = $directive.' '.$origin;
                }
            }
        }

        if (! $sawFrame) {
            $directives[] = "frame-src 'self' ".$origin;
        }

        return implode('; ', array_filter($directives));
    }

    /**
     * When the ElevenLabs Convai widget is active, add exactly the ElevenLabs
     * origins its embed needs (script/connect/frame/font + a blob worker) and
     * nowhere else. Gated on ConvaiWidget::active(), so the strict default policy
     * is untouched until an admin turns the widget on. (Task #17.)
     */
    private function policyWithConvai(string $policy): string
    {
        if (! ConvaiWidget::active()) {
            return $policy;
        }

        $add = ConvaiWidget::CSP;
        $directives = array_map('trim', explode(';', $policy));
        $present = [];

        foreach ($directives as $i => $directive) {
            foreach ($add as $name => $origins) {
                if (str_starts_with($directive, $name.' ')) {
                    $present[$name] = true;
                    foreach ($origins as $origin) {
                        if (! str_contains($directive, $origin)) {
                            $directive .= ' '.$origin;
                        }
                    }
                    $directives[$i] = $directive;
                }
            }
        }

        // Directives not already in the policy (e.g. worker-src) are appended.
        foreach ($add as $name => $origins) {
            if (empty($present[$name])) {
                $directives[] = $name.' '.implode(' ', $origins);
            }
        }

        return implode('; ', array_filter($directives));
    }

    /**
     * When the admin font system (Branding page) has a Google Font selected,
     * allow exactly Google's two font-serving origins — the stylesheet from
     * fonts.googleapis.com (style-src) and the actual font files from
     * fonts.gstatic.com (font-src) — and nowhere else. Gated on
     * BrandSettings::usesGoogleFont(), so the strict default policy is
     * untouched on an unconfigured install.
     */
    /** Trustpilot widget origins (admin-editable list), added only while the reviews layer is live. */
    private function policyWithTrustpilot(string $policy): string
    {
        if (! \App\Support\Trustpilot\TrustpilotSettings::cspActive()) {
            return $policy;
        }
        $add = \App\Support\Trustpilot\TrustpilotSettings::cspHosts();
        $directives = array_map('trim', explode(';', $policy));
        $present = [];
        foreach ($directives as $i => $directive) {
            foreach ($add as $name => $origins) {
                if (str_starts_with($directive, $name.' ')) {
                    $present[$name] = true;
                    foreach ($origins as $origin) {
                        if (! str_contains($directive, $origin)) {
                            $directives[$i] .= ' '.$origin;
                        }
                    }
                }
            }
        }
        foreach ($add as $name => $origins) {
            if (empty($present[$name]) && $origins !== []) {
                $directives[] = $name." 'self' ".implode(' ', $origins);
            }
        }

        return implode('; ', array_filter($directives));
    }

    private function policyWithGoogleFonts(string $policy): string
    {
        if (! BrandSettings::usesGoogleFont()) {
            return $policy;
        }

        $add = [
            'style-src' => ['https://fonts.googleapis.com'],
            'font-src' => ['https://fonts.gstatic.com'],
        ];
        $directives = array_map('trim', explode(';', $policy));
        $present = [];

        foreach ($directives as $i => $directive) {
            foreach ($add as $name => $origins) {
                if (str_starts_with($directive, $name.' ')) {
                    $present[$name] = true;
                    foreach ($origins as $origin) {
                        if (! str_contains($directive, $origin)) {
                            $directive .= ' '.$origin;
                        }
                    }
                    $directives[$i] = $directive;
                }
            }
        }

        foreach ($add as $name => $origins) {
            if (empty($present[$name])) {
                $directives[] = $name.' '.implode(' ', $origins);
            }
        }

        return implode('; ', array_filter($directives));
    }
}
