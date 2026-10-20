<?php

namespace App\Services\N2N\Group;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * The server's only door to the SFU (N2N Batch 11, MASTER-ONLY). Implements the LiveKit server API we need: signed join tokens and the
 * admin calls (change a participant's permissions, mute, remove, delete a room). The API secret never leaves this class and the server;
 * a browser only ever gets a short-lived join token whose grants this server chose. Listeners get `canPublish=false`, so a tampered client
 * cannot publish: the SFU itself refuses it.
 */
class SfuClient
{
    public static function configured(): bool
    {
        return self::url() !== '' && self::wsUrl() !== '' && self::key() !== '' && self::secret() !== '';
    }

    public static function url(): string
    {
        return rtrim((string) config('n2n.sfu.url', ''), '/');
    }

    public static function wsUrl(): string
    {
        return rtrim((string) config('n2n.sfu.ws_url', ''), '/');
    }

    private static function key(): string
    {
        return (string) config('n2n.sfu.key', '');
    }

    private static function secret(): string
    {
        return (string) config('n2n.sfu.secret', '');
    }

    // ---- tokens ----------------------------------------------------------------------------------------------

    /** HS256 JWT, no external library. */
    private static function jwt(array $claims, int $ttl): string
    {
        $b64 = fn (string $s) => rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
        $now = time();
        $payload = $claims + ['iss' => self::key(), 'nbf' => $now - 5, 'exp' => $now + $ttl];
        $head = $b64((string) json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $body = $b64((string) json_encode($payload));

        return $head.'.'.$body.'.'.$b64(hash_hmac('sha256', $head.'.'.$body, self::secret(), true));
    }

    public static function identity(int $userId): string
    {
        return 'u'.$userId;
    }

    /**
     * The token a participant connects with. $canPublish is the ONLY thing that lets a person speak: it is true for the host/speakers the host
     * appointed and false for everyone else, and it is enforced by the SFU, not by the page.
     */
    public function joinToken(string $room, int $userId, string $name, bool $canPublish, string $role, int $ttl = 600): string
    {
        return self::jwt([
            'sub' => self::identity($userId),
            'name' => mb_substr($name, 0, 60),
            'metadata' => (string) json_encode(['u' => $userId, 'role' => $role]),
            'video' => [
                'room' => $room, 'roomJoin' => true, 'canSubscribe' => true, 'canPublish' => $canPublish, 'canPublishData' => false,
                'canPublishSources' => $canPublish ? ['microphone'] : [],
            ],
        ], $ttl);
    }

    // ---- admin API ---------------------------------------------------------------------------------------------

    /** @return array<string, mixed>|null the decoded JSON answer, or null when the SFU refused or could not be reached */
    private function twirp(string $method, array $body, string $room = ''): ?array
    {
        if (! self::configured()) {
            return null;
        }
        $grant = ['roomAdmin' => true, 'roomCreate' => true, 'roomList' => true];
        if ($room !== '') {
            $grant['room'] = $room;
        }
        try {
            $res = Http::withToken(self::jwt(['video' => $grant], 60))->acceptJson()->asJson()->timeout(5)->post(self::url().'/twirp/livekit.RoomService/'.$method, $body === [] ? new \stdClass : $body);   // an empty list would encode as [] and be refused

            return $res->successful() ? ($res->json() ?? []) : null;
        } catch (\Throwable) {
            return null;
        }
    }

    public function createRoom(string $room, int $maxParticipants): bool
    {
        return $this->twirp('CreateRoom', ['name' => $room, 'empty_timeout' => 600, 'max_participants' => $maxParticipants], $room) !== null;
    }

    /** Change what a participant may do, live. Revoking publish makes the SFU drop their published tracks within a moment. */
    public function setCanPublish(string $room, int $userId, bool $canPublish): bool
    {
        return $this->twirp('UpdateParticipant', [
            'room' => $room, 'identity' => self::identity($userId),
            'permission' => ['can_subscribe' => true, 'can_publish' => $canPublish, 'can_publish_data' => false, 'can_publish_sources' => $canPublish ? ['MICROPHONE'] : []],
        ], $room) !== null;
    }

    /** Mute a speaker's microphone on the server (the appointment, and so the billing, is unchanged). */
    public function mute(string $room, int $userId): bool
    {
        $list = $this->twirp('ListParticipants', ['room' => $room], $room);
        $done = false;
        foreach (($list['participants'] ?? []) as $p) {
            if (($p['identity'] ?? '') !== self::identity($userId)) {
                continue;
            }
            foreach (($p['tracks'] ?? []) as $t) {
                if (($t['type'] ?? '') === 'AUDIO' || ($t['type'] ?? '') === 1) {
                    $done = $this->twirp('MutePublishedTrack', ['room' => $room, 'identity' => self::identity($userId), 'track_sid' => $t['sid'], 'muted' => true], $room) !== null || $done;
                }
            }
        }

        return $done;
    }

    public function remove(string $room, int $userId): bool
    {
        return $this->twirp('RemoveParticipant', ['room' => $room, 'identity' => self::identity($userId)], $room) !== null;
    }

    public function deleteRoom(string $room): bool
    {
        return $this->twirp('DeleteRoom', ['room' => $room], $room) !== null;
    }

    /** @return list<string> identities currently in the room (used by tests and the health page) */
    public function identities(string $room): array
    {
        $list = $this->twirp('ListParticipants', ['room' => $room], $room);

        return array_values(array_map(fn ($p) => (string) ($p['identity'] ?? ''), $list['participants'] ?? []));
    }

    // ---- health ------------------------------------------------------------------------------------------------

    /** @return array{ok: bool, detail: string} cached for 20 s so the page and the feature gate never hammer the SFU */
    public static function health(bool $fresh = false): array
    {
        if (! self::configured()) {
            return ['ok' => false, 'detail' => 'No SFU is configured (N2N_SFU_URL, N2N_SFU_WS_URL, N2N_SFU_KEY, N2N_SFU_SECRET).'];
        }
        if ($fresh) {
            Cache::forget('n2n:sfu:health');
        }

        return Cache::remember('n2n:sfu:health', 20, function () {
            try {
                $ping = Http::timeout(3)->get(self::url().'/');
                if (! $ping->successful()) {
                    return ['ok' => false, 'detail' => 'The SFU did not answer its health check (HTTP '.$ping->status().').'];
                }
                $list = (new self)->twirp('ListRooms', []);
            } catch (\Throwable $e) {
                return ['ok' => false, 'detail' => 'The SFU could not be reached.'];
            }

            return $list === null ? ['ok' => false, 'detail' => 'The SFU is up but refused our API key (check N2N_SFU_KEY and N2N_SFU_SECRET).'] : ['ok' => true, 'detail' => 'The SFU is healthy.'];
        });
    }
}
