<?php

namespace App\Services\N2N\Calls;

use App\Models\N2N\Call;
use App\Models\Setting;

/**
 * ICE servers for one call (N2N Batch 10, MASTER-ONLY). TURN uses coturn's time-limited REST credentials (`use-auth-secret`): the username is
 * `<expiry>:<user>:<call>` and the credential is base64(HMAC-SHA1(secret, username)). The secret never leaves the server and no static
 * credential is ever shipped in JavaScript; a stolen credential expires with the call window.
 */
class TurnCredentials
{
    public static function secret(): string
    {
        return (string) config('n2n.calls.turn.secret', '');
    }

    /** @return list<string> */
    public static function turnUrls(): array
    {
        return array_values(array_filter(array_map('trim', explode(',', (string) config('n2n.calls.turn.urls', '')))));
    }

    /** @return list<string> */
    public static function stunUrls(): array
    {
        $own = array_values(array_filter(array_map('trim', explode(',', (string) config('n2n.calls.turn.stun', '')))));
        $fallback = (bool) Setting::getValue('n2n.calls_public_stun', false) || CallsAvailability::devMode() ? ['stun:stun.l.google.com:19302'] : [];

        return array_merge($own, $fallback);
    }

    /** @return list<array{urls: string|list<string>, username?: string, credential?: string}> */
    public static function iceServers(int $userId, Call $call): array
    {
        $servers = [];
        if (($stun = self::stunUrls()) !== []) {
            $servers[] = ['urls' => $stun];
        }
        if (self::secret() !== '' && ($turn = self::turnUrls()) !== []) {
            $ttl = max(300, (int) config('n2n.calls.turn.ttl', 3600));
            $username = (now()->timestamp + $ttl).':'.$userId.':'.$call->id;
            $servers[] = ['urls' => $turn, 'username' => $username, 'credential' => base64_encode(hash_hmac('sha1', $username, self::secret(), true))];
        }

        return $servers;
    }
}
