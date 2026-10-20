<?php

namespace App\Services\N2N\Realtime;

use App\Models\N2N\Presence;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Presence, last seen and typing (N2N, MASTER-ONLY). One privacy switch: a user who hides their presence can neither be seen nor see
 * anyone else's last seen / typing. Typing is ephemeral and never stored.
 */
class PresenceService
{
    private const ONLINE_SECONDS = 70;

    /** A client heartbeat. Hidden users are not recorded at all. */
    public function touch(User $user): void
    {
        if ($user->n2n_hide_presence) {
            return;
        }
        Presence::query()->updateOrInsert(['user_id' => $user->id], ['last_seen_at' => now()]);
    }

    /** @return array{visible: bool, online: bool, last_seen: ?string} */
    public function seenBy(User $viewer, User $target): array
    {
        if ($viewer->n2n_hide_presence || $target->n2n_hide_presence) {
            return ['visible' => false, 'online' => false, 'last_seen' => null];
        }
        $at = Presence::query()->where('user_id', $target->id)->value('last_seen_at');
        $when = $at ? Carbon::parse($at) : null;

        return ['visible' => true, 'online' => $when !== null && $when->gt(now()->subSeconds(self::ONLINE_SECONDS)), 'last_seen' => $when?->toIso8601String()];
    }

    /** Tell `$to` that `$from` is typing. Silently dropped when either side hides presence. */
    public function typing(User $from, User $to, string $context = ''): bool
    {
        if ($from->n2n_hide_presence || $to->n2n_hide_presence) {
            return false;
        }
        Realtime::publish($to->id, 'typing', ['from' => $from->naara_id, 'context' => mb_substr($context, 0, 40)]);

        return true;
    }
}
