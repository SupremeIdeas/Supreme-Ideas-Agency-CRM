<?php

namespace App\Support\Omni;

/** Adds the Facebook origins the Embedded Signup popup needs, only on the Business > Channels page and only while Connect with Meta is set up. MASTER-ONLY. */
class MetaSignupCsp
{
    public const ADD = [
        'script-src' => ['https://connect.facebook.net'],
        'frame-src' => ['https://www.facebook.com', 'https://web.facebook.com', 'https://staticxx.facebook.com'],
        'connect-src' => ['https://graph.facebook.com', 'https://www.facebook.com'],
    ];

    public static function apply(string $policy, $request = null): string
    {
        try {
            $request ??= request();
            if (! $request || ! $request->routeIs('business.channels') || ! \App\Services\Omni\Meta\EmbeddedSignup::available()) {
                return $policy;
            }
        } catch (\Throwable) {
            return $policy;   // no database or config yet: the strict default policy stands, headers must never fail
        }
        $directives = array_map('trim', explode(';', $policy));
        $present = [];
        foreach ($directives as $i => $d) {
            foreach (self::ADD as $name => $origins) {
                if (str_starts_with($d, $name.' ')) {
                    $present[$name] = true;
                    foreach ($origins as $o) {
                        if (! str_contains($directives[$i], $o)) {
                            $directives[$i] .= ' '.$o;
                        }
                    }
                }
            }
        }
        foreach (self::ADD as $name => $origins) {
            if (empty($present[$name])) {
                $directives[] = $name." 'self' ".implode(' ', $origins);
            }
        }

        return implode('; ', array_filter($directives));
    }
}
