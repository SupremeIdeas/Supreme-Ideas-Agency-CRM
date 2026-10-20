<?php

namespace App\Services\N2N\Realtime;

use App\Events\N2N\RealtimeBroadcast;
use App\Models\N2N\RealtimeEvent;
use Illuminate\Support\Facades\Log;

/**
 * Primitive P3 (N2N, MASTER-ONLY): the ONE way the server pushes to a browser. Application code only ever calls Realtime::publish().
 * Both drivers deliver the same envelope {id, type, payload, at}. Logged events can be replayed after a disconnect and are the whole
 * feed for the poll driver; ephemeral events (typing) are pushed only and never stored.
 */
class Realtime
{
    /** Types that are never written to the log: they are only meaningful live. */
    public const EPHEMERAL = ['typing'];

    public static function driver(): string
    {
        $d = (string) \App\Models\Setting::getValue('n2n.realtime_driver', config('n2n.realtime.driver', 'poll'));

        return in_array($d, ['reverb', 'poll', 'null'], true) ? $d : 'poll';
    }

    /** @return int|null the event id (cursor), or null for an ephemeral/disabled publish */
    public static function publish(int $userId, string $type, array $payload = []): ?int
    {
        $driver = self::driver();
        if ($driver === 'null') {
            return null;
        }
        $ephemeral = in_array($type, self::EPHEMERAL, true);
        $event = null;
        if (! $ephemeral) {
            $event = RealtimeEvent::query()->create(['user_id' => $userId, 'type' => $type, 'payload' => $payload, 'created_at' => now()]);
        }
        if ($driver === 'reverb') {
            try {
                $envelope = $event?->envelope() ?? ['id' => 0, 'type' => $type, 'payload' => (object) $payload, 'at' => now()->toIso8601String()];
                event(new RealtimeBroadcast($userId, $envelope));
            } catch (\Throwable $e) {
                // A dead Reverb server must never break a send: the log row above lets the client catch up by polling.
                Log::warning('N2N realtime broadcast failed', ['type' => $type, 'error' => class_basename($e)]);
            }
        }

        return $event?->id;
    }

    /** Events for one user after a cursor, oldest first. */
    public static function since(int $userId, int $cursor, int $limit = 100): array
    {
        $rows = RealtimeEvent::query()->where('user_id', $userId)->where('id', '>', $cursor)->orderBy('id')->limit($limit + 1)->get();
        $more = $rows->count() > $limit;

        return ['events' => $rows->take($limit)->map->envelope()->values()->all(), 'has_more' => $more];
    }

    public static function latestCursor(int $userId): int
    {
        return (int) RealtimeEvent::query()->where('user_id', $userId)->max('id');
    }

    public static function prune(): int
    {
        $hours = max(1, (int) \App\Models\Setting::getValue('n2n.realtime_ttl_hours', config('n2n.realtime.event_ttl_hours', 48)));

        return RealtimeEvent::query()->where('created_at', '<', now()->subHours($hours))->delete();
    }
}
