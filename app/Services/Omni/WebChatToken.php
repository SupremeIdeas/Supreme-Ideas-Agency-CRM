<?php

namespace App\Services\Omni;

use App\Models\ChannelConnection;

/**
 * The web chat visitor's credential (MASTER-ONLY): base64url(json{c: connection id, v: visitor id, e: expiry}) + "." + HMAC-SHA256 keyed by the app key and the
 * connection's own secret. It names no person, only a random visitor id the browser keeps in localStorage; it cannot be used on another site's chat (the
 * connection id is inside the signature) and it expires. Compared in constant time.
 */
final class WebChatToken
{
    public const TTL_DAYS = 30;

    public static function issue(ChannelConnection $c, ?string $visitorId = null): string
    {
        $payload = self::b64(json_encode(['c' => $c->id, 'v' => $visitorId ?: bin2hex(random_bytes(12)), 'e' => now()->addDays(self::TTL_DAYS)->timestamp]));

        return $payload.'.'.self::sign($c, $payload);
    }

    /** @return ?string the visitor id, or null for anything forged, expired or issued for another connection */
    public static function visitor(ChannelConnection $c, ?string $token): ?string
    {
        if (! is_string($token) || substr_count($token, '.') !== 1) {
            return null;
        }
        [$payload, $sig] = explode('.', $token, 2);
        if (! hash_equals(self::sign($c, $payload), $sig)) {
            return null;
        }
        $data = json_decode(self::unb64($payload), true);
        if (! is_array($data) || (int) ($data['c'] ?? 0) !== (int) $c->id || (int) ($data['e'] ?? 0) < now()->timestamp || ! preg_match('/^[a-f0-9]{16,64}$/', (string) ($data['v'] ?? ''))) {
            return null;
        }

        return (string) $data['v'];
    }

    private static function sign(ChannelConnection $c, string $payload): string
    {
        return self::b64(hash_hmac('sha256', $payload, (string) config('app.key').'|'.(string) $c->webhook_secret, true));
    }

    private static function b64(string $s): string
    {
        return rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
    }

    private static function unb64(string $s): string
    {
        return (string) base64_decode(strtr($s, '-_', '+/'));
    }
}
