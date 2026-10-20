<?php

namespace App\Support\N2N;

/** Adds the audio-player origins brand profiles may embed (N2N Batch 8) to `frame-src`, and the group-call SFU (Batch 11) to `connect-src`, only while N2N is enabled. MASTER-ONLY. */
class N2NEmbedCsp
{
    public const ORIGINS = ['https://open.spotify.com', 'https://w.soundcloud.com', 'https://embed.music.apple.com', 'https://www.boomplay.com', 'https://music.amazon.com'];

    public static function apply(string $policy): string
    {
        try {
            if (! N2NSettings::enabled()) {
                return $policy;
            }
        } catch (\Throwable) {
            return $policy;      // no database yet (installer / outage): the strict default policy stands, headers must never fail
        }
        $directives = array_map('trim', explode(';', $policy));
        $saw = false;
        foreach ($directives as $i => $d) {
            if (str_starts_with($d, 'frame-src ')) {
                $saw = true;
                foreach (self::ORIGINS as $o) {
                    if (! str_contains($d, $o)) {
                        $d .= ' '.$o;
                    }
                }
                $directives[$i] = $d;
            }
        }
        if (! $saw) {
            $directives[] = "frame-src 'self' ".implode(' ', self::ORIGINS);
        }

        $sfu = self::sfuOrigins();
        if ($sfu !== []) {
            $hit = false;
            foreach ($directives as $i => $d) {
                if (str_starts_with($d, 'connect-src ')) {
                    $hit = true;
                    $directives[$i] = $d.' '.implode(' ', array_filter($sfu, fn ($o) => ! str_contains($d, $o)));
                }
            }
            if (! $hit) {
                $directives[] = "connect-src 'self' ".implode(' ', $sfu);
            }
        }

        return implode('; ', array_filter($directives));
    }

    /** The browser's connection to the SFU (signalling websocket plus its HTTP validate call). Empty when no SFU is configured. @return list<string> */
    private static function sfuOrigins(): array
    {
        $ws = (string) config('n2n.sfu.ws_url', '');
        $p = parse_url($ws);
        if (empty($p['host']) || ! in_array($p['scheme'] ?? '', ['ws', 'wss'], true)) {
            return [];
        }
        $host = $p['host'].(isset($p['port']) ? ':'.$p['port'] : '');
        $secure = $p['scheme'] === 'wss';

        return [($secure ? 'wss://' : 'ws://').$host, ($secure ? 'https://' : 'http://').$host];
    }
}
