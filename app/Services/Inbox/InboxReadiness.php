<?php

namespace App\Services\Inbox;

use App\Models\ChannelConnection;
use App\Models\ScheduledChannelMessage;
use App\Support\Business\BusinessSettings;
use App\Support\MediaStorage;
use App\Support\SchedulerHealth;
use Illuminate\Support\Facades\Storage;

/**
 * Can this server actually deliver what the shared inbox composer promises? (MASTER-ONLY, shown in Admin > Business Suite > Sending readiness.)
 * Each check says what it found and, when it is not fine, the one thing to change. Nothing here sends a message or contacts a provider.
 *
 * @phpstan-type Check array{id: string, label: string, status: string, detail: string, fix: ?string}
 */
class InboxReadiness
{
    /** @return list<array{id: string, label: string, status: string, detail: string, fix: ?string}> status: ok | warn | fail */
    public static function checks(): array
    {
        $out = [];
        $add = function (string $id, string $label, string $status, string $detail, ?string $fix = null) use (&$out) {
            $out[] = compact('id', 'label', 'status', 'detail', 'fix');
        };

        // 1. Is the module on?
        BusinessSettings::module('inbox')
            ? $add('module', 'Shared inbox is switched on', 'ok', 'Workspaces can connect channels and reply.')
            : $add('module', 'Shared inbox is switched on', 'fail', 'The module is off, so nothing below is reachable by a workspace.', 'Turn on "Shared inbox" under Modules (and the Business Suite master switch).');

        // 2. Providers fetch media from a link: it has to be public and ideally https.
        $url = (string) config('app.url');
        $host = (string) parse_url($url, PHP_URL_HOST);
        $local = $host === '' || in_array($host, ['localhost', '127.0.0.1', '::1'], true) || str_ends_with($host, '.test') || str_ends_with($host, '.local') || str_ends_with($host, '.localhost');
        if ($local) {
            $add('url', 'Public address for provider downloads', 'fail', "APP_URL is {$url}. WhatsApp, Telegram and Instagram download each file from a link on this address, and they cannot reach a local address.", 'Set APP_URL in .env to the public https address of this site, then clear the config cache.');
        } elseif (! str_starts_with($url, 'https://')) {
            $add('url', 'Public address for provider downloads', 'warn', "APP_URL is {$url}. WhatsApp requires https for media links; Telegram and Instagram usually do too.", 'Serve the site over https and set APP_URL to the https address.');
        } else {
            $add('url', 'Public address for provider downloads', 'ok', "File links will use {$url}.");
        }

        // 3. Private storage round trip.
        $disk = MediaStorage::privateDisk();
        try {
            $probe = 'inbox-out/_probe/'.bin2hex(random_bytes(6)).'.txt';
            Storage::disk($disk)->put($probe, 'ok');
            $good = Storage::disk($disk)->get($probe) === 'ok';
            Storage::disk($disk)->delete($probe);
            $good ? $add('storage', 'Private file storage', 'ok', "Files are stored privately on the \"{$disk}\" disk and handed out through short-lived links.")
                  : $add('storage', 'Private file storage', 'fail', "The \"{$disk}\" disk accepted a test file but did not return it.", 'Check the storage credentials and permissions in Settings > Storage.');
        } catch (\Throwable $e) {
            $add('storage', 'Private file storage', 'fail', 'A test file could not be written to the "'.$disk.'" disk.', 'Check the storage credentials and folder permissions (storage/app must be writable).');
        }

        // 4. ffmpeg: GIF to WhatsApp, voice notes, non-MP4 video.
        InboxMedia::ffmpegAvailable()
            ? $add('ffmpeg', 'ffmpeg (media conversion)', 'ok', 'GIFs for WhatsApp, voice notes and non-MP4 video can be converted.')
            : $add('ffmpeg', 'ffmpeg (media conversion)', 'warn', 'ffmpeg is not installed. Photos, MP4 video, documents, locations, GIFs and (on WhatsApp and Telegram) voice notes all still work. A GIF on WhatsApp or an unusual video format is sent as a file, and Instagram voice notes cannot be sent.', 'Press "Install ffmpeg now" in Setup and health: it downloads a copy into storage/ and needs no root access.');

        // 5. PHP upload limits vs the admin ceiling.
        $need = (int) BusinessSettings::inbox('max_file_mb');
        $up = self::bytes((string) ini_get('upload_max_filesize'));
        $post = self::bytes((string) ini_get('post_max_size'));
        $cap = (int) floor(min($up ?: PHP_INT_MAX, $post ?: PHP_INT_MAX) / 1048576);
        $cap >= $need
            ? $add('php', 'PHP upload limits', 'ok', "upload_max_filesize and post_max_size allow at least {$need} MB.")
            : $add('php', 'PHP upload limits', 'warn', "PHP only accepts about {$cap} MB per request but the largest file is set to {$need} MB. Bigger files fail before they reach the app.", "Raise upload_max_filesize and post_max_size in php.ini (or lower \"Largest file\" to {$cap} MB).");

        // 6. Queue and scheduler, for scheduled sends.
        if (BusinessSettings::inbox('scheduling')) {
            $last = SchedulerHealth::lastRun('business:send-scheduled');
            if ($last === null) {
                $add('scheduler', 'Scheduler (scheduled messages)', 'warn', 'The scheduled-message task has not run yet. Scheduled messages will wait until the scheduler runs.', 'Make sure the cron entry "php artisan schedule:run" runs every minute.');
            } elseif ($last->diffInSeconds(now()) > 300) {
                $add('scheduler', 'Scheduler (scheduled messages)', 'fail', 'The scheduled-message task last ran '.$last->diffForHumans().'. Scheduled messages are being delayed.', 'Check that the cron entry "php artisan schedule:run" is running every minute.');
            } else {
                $add('scheduler', 'Scheduler (scheduled messages)', 'ok', 'Last ran '.$last->diffForHumans().'.');
            }
            SchedulerHealth::queueIsSync()
                ? $add('queue', 'Queue worker', 'warn', 'The queue is "sync", so scheduled sends run inside the scheduler process. It works, but a slow provider will hold the scheduler up.', 'Use the database or Redis queue with a worker for production.')
                : $add('queue', 'Queue worker', 'ok', 'Queue connection: '.SchedulerHealth::queueConnection().'.');
            $stuck = ScheduledChannelMessage::query()->where('status', ScheduledChannelMessage::PENDING)->where('send_at', '<', now()->subMinutes(10))->count();
            if ($stuck > 0) {
                $add('overdue', 'Scheduled messages waiting', 'fail', "{$stuck} scheduled message(s) are more than 10 minutes overdue.", 'Check the scheduler and queue worker above.');
            }
        } else {
            $add('scheduler', 'Scheduled messages', 'ok', 'Switched off in settings, so no scheduler is needed for the inbox.');
        }

        // 7. Connections.
        $total = ChannelConnection::query()->count();
        $bad = ChannelConnection::query()->where('status', ChannelConnection::ERROR)->count();
        $total === 0
            ? $add('connections', 'Connected channels', 'warn', 'No workspace has connected a channel yet.', null)
            : ($bad > 0
                ? $add('connections', 'Connected channels', 'warn', "{$total} connected, {$bad} reporting an error. Workspace owners see the reason on their Channels page.", null)
                : $add('connections', 'Connected channels', 'ok', "{$total} connected, none reporting errors."));

        return $out;
    }

    private static function bytes(string $v): int
    {
        $v = trim($v);
        if ($v === '' || $v === '-1') {
            return 0;
        }
        $n = (int) $v;

        return match (strtolower(substr($v, -1))) {
            'g' => $n * 1073741824,
            'm' => $n * 1048576,
            'k' => $n * 1024,
            default => $n,
        };
    }
}
