<?php

namespace App\Services\N2N\Realtime;

use App\Models\N2N\RealtimeEvent;
use Illuminate\Support\Facades\Cache;

/**
 * What an admin needs to see about realtime (N2N, MASTER-ONLY). It reports measurements and says "not measured" when it has none:
 * connection capacity depends on the VPS and its file-descriptor limit, so no number is asserted here.
 */
class RealtimeHealth
{
    /** Count one poll request (cheap, per-minute bucket) so poll load is visible. */
    public static function notePoll(): void
    {
        $key = 'n2n:poll:'.now()->format('YmdHi');
        Cache::add($key, 0, 600);
        Cache::increment($key);
    }

    public function report(): array
    {
        $driver = Realtime::driver();
        $reverb = $driver === 'reverb' ? $this->reverbReachable() : null;
        $oldest = RealtimeEvent::query()->min('created_at');

        return [
            'driver' => $driver,
            'reverb_reachable' => $reverb,
            'events_logged' => RealtimeEvent::query()->count(),
            'events_last_5m' => RealtimeEvent::query()->where('created_at', '>=', now()->subMinutes(5))->count(),
            'oldest_event_age_minutes' => $oldest ? (int) \Illuminate\Support\Carbon::parse($oldest)->diffInMinutes(now()) : null,
            'polls_last_minute' => (int) Cache::get('n2n:poll:'.now()->subMinute()->format('YmdHi'), 0),
            'polls_this_minute' => (int) Cache::get('n2n:poll:'.now()->format('YmdHi'), 0),
            'fd_limit' => $this->fdLimit(),
            'capacity' => 'Not measured. Connections per process depend on this server and its open-file limit; measure on the target VPS before promising a number.',
            'matrix' => ['reverb' => 'instant delivery + calling (VPS only)', 'poll' => 'chat works, a few seconds slower; no calling (cPanel-safe)', 'null' => 'realtime off'],
        ];
    }

    private function reverbReachable(): bool
    {
        $cfg = config('n2n.realtime.reverb');
        $errno = $errstr = null;
        $sock = @fsockopen((string) $cfg['host'], (int) $cfg['port'], $errno, $errstr, 1.5);
        if ($sock === false) {
            return false;
        }
        fclose($sock);

        return true;
    }

    private function fdLimit(): ?string
    {
        if (! function_exists('shell_exec') || in_array('shell_exec', array_map('trim', explode(',', (string) ini_get('disable_functions'))), true)) {
            return null;   // many shared hosts disable it: report "unknown", never guess
        }
        $out = @shell_exec('ulimit -n 2>/dev/null');

        return $out ? trim($out) : null;
    }
}
