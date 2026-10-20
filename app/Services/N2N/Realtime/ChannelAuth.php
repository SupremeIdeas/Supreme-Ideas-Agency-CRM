<?php

namespace App\Services\N2N\Realtime;

use App\Models\User;

/**
 * Who may subscribe to which private channel (N2N, MASTER-ONLY). `n2n.user.{id}` is personal: only that user. Other channel types
 * (conversation, call) register their own authoriser when their batch lands; until then they are DENIED, never open.
 */
class ChannelAuth
{
    /** @var array<string, callable(User, int): bool> */
    private static array $custom = [];

    public static function register(string $kind, callable $authoriser): void
    {
        self::$custom[$kind] = $authoriser;
    }

    public static function can(?User $user, string $kind, int $id): bool
    {
        if ($user === null || ! $user->is_active) {
            return false;
        }
        if ($kind === 'user') {
            return $user->id === $id;
        }

        return isset(self::$custom[$kind]) && (bool) (self::$custom[$kind])($user, $id);
    }
}
