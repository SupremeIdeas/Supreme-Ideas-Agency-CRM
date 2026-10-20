<?php

namespace App\Services\Business;

use App\Jobs\QueueProbeJob;
use App\Models\GifLibraryItem;
use App\Models\NotificationTone;
use App\Services\Gifs\GifLibrary;
use App\Services\Inbox\InboxReadiness;
use App\Services\Media\FfmpegManager;
use App\Support\Business\BusinessSettings;
use App\Support\HostingGuide;
use App\Support\MediaStorage;
use App\Support\NotificationTones;
use App\Support\ProviderStatus;
use App\Support\QueueHealth;
use App\Support\SchedulerHealth;
use App\Support\Permissions\BusinessRole;
use Database\Seeders\BusinessRoleSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

/**
 * Everything the Business Suite needs from the server, checked in one place and explained in plain words (MASTER-ONLY): PHP and its extensions, storage, the cron and the
 * queue worker (shared hosting and VPS), public access for providers, the media converter, seeded content, provider keys and the VPS-only voice agent. Each check says what it
 * found, what to do, and, where the platform can do it itself, offers a button (fix()). The same checks run from the terminal: php artisan business:doctor [--fix].
 *
 * @phpstan-type Check array{id: string, label: string, status: string, detail: string, fix: ?string, action: ?array{id: string, label: string}}
 */
class SetupHealth
{
    /** @return array<string, array{title: string, blurb: string, checks: list<array<string,mixed>>}> */
    public function groups(): array
    {
        $inbox = collect(InboxReadiness::checks())->keyBy('id');
        $g = [
            'server' => ['title' => 'Server', 'blurb' => 'PHP, extensions, folders and the ability to run programs.', 'checks' => $this->server($inbox)],
            'background' => ['title' => 'Background jobs (cron and queue)', 'blurb' => 'Scheduled sends, recordings, webhooks and every call to a provider run in the background. Shared hosting uses one cron entry; a VPS also runs a queue worker.', 'checks' => $this->background($inbox)],
            'public' => ['title' => 'Public access', 'blurb' => 'WhatsApp, Telegram, Instagram and Twilio download files from, and send events to, this site.', 'checks' => $this->publicAccess($inbox)],
            'media' => ['title' => 'Media conversion', 'blurb' => 'GIFs, video and voice notes sometimes need converting before a channel will take them.', 'checks' => $this->media()],
            'content' => ['title' => 'Built-in content and roles', 'blurb' => 'Roles, GIF library, stickers, ringtones and beeps that ship with the platform.', 'checks' => $this->content()],
            'providers' => ['title' => 'Providers and modules', 'blurb' => 'Keys the switched-on modules need.', 'checks' => $this->providers($inbox)],
        ];
        if (BusinessSettings::module('ai_voice')) {
            $g['voice'] = ['title' => 'AI voice agent (VPS only)', 'blurb' => 'The agent runs as its own long-lived process behind a secure (wss://) address. Shared hosting cannot run it.', 'checks' => $this->voice()];
        }

        return $g;
    }

    /** @return array{ok: int, check: int, fix: int} */
    public function counts(array $groups): array
    {
        $c = ['ok' => 0, 'check' => 0, 'fix' => 0];
        foreach ($groups as $grp) {
            foreach ($grp['checks'] as $ch) {
                $c[['ok' => 'ok', 'warn' => 'check', 'fail' => 'fix'][$ch['status']] ?? 'check']++;
            }
        }

        return $c;
    }

    private function c(string $id, string $label, string $status, string $detail, ?string $fix = null, ?array $action = null): array
    {
        return compact('id', 'label', 'status', 'detail', 'fix', 'action');
    }

    // ── server ──────────────────────────────────────────────────────────────

    private function server($inbox): array
    {
        $out = [];
        $out[] = version_compare(PHP_VERSION, '8.2.0', '>=')
            ? $this->c('php', 'PHP version', 'ok', 'PHP '.PHP_VERSION.'.')
            : $this->c('php', 'PHP version', 'fail', 'PHP '.PHP_VERSION.' is too old. The platform needs PHP 8.2 or newer.', 'Switch the site to PHP 8.2+ in your host\'s PHP selector (cPanel > Select PHP Version / MultiPHP Manager).');

        $need = ['openssl', 'pdo', 'mbstring', 'curl', 'fileinfo', 'json', 'tokenizer', 'xml', 'ctype', 'zip'];
        $driver = config('database.default');
        $need[] = $driver === 'sqlite' ? 'pdo_sqlite' : 'pdo_mysql';
        $missing = array_values(array_filter($need, fn ($e) => ! extension_loaded($e)));
        $out[] = $missing === []
            ? $this->c('ext', 'Required PHP extensions', 'ok', 'All present.')
            : $this->c('ext', 'Required PHP extensions', 'fail', 'Missing: '.implode(', ', $missing).'.', 'Enable them in your host\'s PHP extension list (cPanel > Select PHP Version > Extensions), then reload this page.');
        $rec = [];
        extension_loaded('gd') || extension_loaded('imagick') || $rec[] = 'gd (or imagick)';
        extension_loaded('intl') || $rec[] = 'intl';
        extension_loaded('bcmath') || $rec[] = 'bcmath';
        $out[] = $rec === []
            ? $this->c('ext_rec', 'Recommended PHP extensions', 'ok', 'gd, intl and bcmath are present.')
            : $this->c('ext_rec', 'Recommended PHP extensions', 'warn', 'Not installed: '.implode(', ', $rec).'. The Suite works, but image handling and number formatting are better with them.', 'Enable them in your host\'s PHP extension list when convenient.');

        $mem = $this->bytes((string) ini_get('memory_limit'));
        $out[] = ($mem === 0 || $mem >= 256 * 1048576)
            ? $this->c('memory', 'PHP memory', 'ok', 'memory_limit is '.ini_get('memory_limit').'.')
            : $this->c('memory', 'PHP memory', 'warn', 'memory_limit is '.ini_get('memory_limit').'. Call recordings and large uploads can need more.', 'Raise memory_limit to 256M or more in your host\'s PHP settings.');

        $x = app(FfmpegManager::class)->execStatus();
        $out[] = $x['ok']
            ? $this->c('exec', 'Running programs (proc_open or exec)', 'ok', 'Allowed. The media converter and the voice agent can run.')
            : $this->c('exec', 'Running programs (proc_open or exec)', 'warn', (string) $x['reason'], 'Ask the host to remove proc_open (or exec) from disable_functions. Everything else works without it.');

        $w = is_writable(storage_path()) && is_writable(base_path('bootstrap/cache'));
        $out[] = $w ? $this->c('writable', 'Writable folders', 'ok', 'storage/ and bootstrap/cache are writable.')
            : $this->c('writable', 'Writable folders', 'fail', 'storage/ or bootstrap/cache is not writable by the web user.', 'Set both folders to permission 775 (cPanel File Manager > Permissions) and make sure they belong to the web user.');

        $link = public_path('storage');
        $linked = is_link($link) || is_dir($link);
        $out[] = $linked ? $this->c('storage_link', 'Public storage link', 'ok', 'public/storage is in place, so uploaded GIFs, stickers and tones are reachable.')
            : $this->c('storage_link', 'Public storage link', 'fail', 'public/storage does not exist, so uploaded GIFs, stickers and tones would not show.', 'Press the button (it runs php artisan storage:link). If your host blocks symbolic links, create the link by hand: public/storage must point to storage/app/public.', ['id' => 'storage_link', 'label' => 'Create the storage link']);

        $free = @disk_free_space(base_path());
        $out[] = ($free === false || $free > 500 * 1048576)
            ? $this->c('disk', 'Free disk space', 'ok', $free === false ? 'Not reported by this host.' : round($free / 1073741824, 1).' GB free.')
            : $this->c('disk', 'Free disk space', 'warn', round($free / 1048576).' MB free. Recordings and media will fill this quickly.', 'Free some space, or move media to object storage (Admin > Settings > Storage).');

        foreach (['storage', 'php'] as $id) {
            if ($inbox->has($id)) {
                $out[] = $inbox[$id] + ['action' => null];
            }
        }

        return $out;
    }

    // ── background ──────────────────────────────────────────────────────────

    private function background($inbox): array
    {
        $out = [];
        $mode = HostingGuide::mode();
        $out[] = $this->c('mode', 'Hosting mode', 'ok', $mode === 'vps' ? 'VPS: Redis queue with Horizon workers, plus the one-minute cron.' : 'Shared hosting: database queue drained by the one-minute cron. No worker to keep alive.');

        // cron heartbeat
        $anyRun = collect(SchedulerHealth::tasks())->keys()->contains(fn ($t) => SchedulerHealth::lastRun($t) !== null && SchedulerHealth::lastRun($t)->gt(now()->subMinutes(10)));
        $out[] = $anyRun
            ? $this->c('cron', 'Cron (scheduler)', 'ok', 'The scheduler ran within the last 10 minutes.')
            : $this->c('cron', 'Cron (scheduler)', 'fail', 'No scheduled task has run in the last 10 minutes, so scheduled messages, recording clean-up and (on shared hosting) the whole queue are not running.', 'Add this ONE cron entry, every minute (cPanel > Cron Jobs): '.HostingGuide::cronLine());
        if ($inbox->has('scheduler')) {
            $out[] = $inbox['scheduler'] + ['action' => null];
        }
        $overdue = collect(SchedulerHealth::report())->where('overdue', true)->count();
        if ($anyRun && $overdue > 0) {
            $out[] = $this->c('overdue_tasks', 'Scheduled tasks', 'warn', "{$overdue} scheduled task(s) are overdue. Open System Health for the list.", 'If it persists, check that the cron entry runs every minute and the PHP path in it is right.');
        }

        // queue
        $v = QueueHealth::workerVerdict();
        $out[] = $v['healthy']
            ? $this->c('queue_driver', 'Queue', 'ok', 'Driver: '.QueueHealth::driver().'.')
            : $this->c('queue_driver', 'Queue', 'fail', (string) $v['reason'], $mode === 'vps' ? 'Start Horizon under Supervisor (see the VPS steps below).' : 'Set QUEUE_CONNECTION=database in .env; the cron then drains it.');
        $age = SchedulerHealth::oldestJobAgeSeconds();
        if ($age !== null && $age > 180) {
            $out[] = $this->c('queue_age', 'Waiting jobs', 'fail', 'The oldest waiting job is '.round($age / 60).' minutes old. Nothing is processing the queue.', $mode === 'vps' ? 'Check Horizon is running (Supervisor).' : 'The cron is not running or has stopped: check the cron entry above.');
        }
        $failed = QueueHealth::failedCount();
        if ($failed > 0) {
            $out[] = $this->c('failed_jobs', 'Failed jobs', 'warn', "{$failed} job(s) have failed. Open System Health to read why, then retry or clear them.", null);
        }
        $out[] = $this->probeCheck();

        return $out;
    }

    private function probeCheck(): array
    {
        $s = Cache::get('setup.queue_probe');
        $btn = ['id' => 'queue_probe', 'label' => 'Test the queue now'];
        if (! is_array($s)) {
            return $this->c('queue_probe', 'Queue worker proof', 'warn', 'Not tested yet. This sends one harmless job and waits to see it processed.', 'Press the button.', $btn);
        }
        if (isset($s['done_at'])) {
            $secs = (int) abs(\Illuminate\Support\Carbon::parse($s['done_at'])->diffInSeconds(\Illuminate\Support\Carbon::parse($s['sent_at'])));

            return $this->c('queue_probe', 'Queue worker proof', 'ok', 'A test job was processed in '.$secs.' second(s) ('.\Illuminate\Support\Carbon::parse($s['done_at'])->diffForHumans().').', null, $btn);
        }
        $age = (int) abs(now()->diffInSeconds(\Illuminate\Support\Carbon::parse($s['sent_at'])));

        return $age < 75
            ? $this->c('queue_probe', 'Queue worker proof', 'warn', 'Test job sent '.$age.' seconds ago, waiting to be processed. On shared hosting the cron runs the queue once a minute, so allow up to a minute.', null, $btn)
            : $this->c('queue_probe', 'Queue worker proof', 'fail', 'The test job was sent '.round($age / 60, 1).' minutes ago and nothing processed it. No worker is running.', HostingGuide::mode() === 'vps' ? 'Start Horizon under Supervisor.' : 'The cron entry is not running: add it (see Cron above).', $btn);
    }

    /** Send the probe job. @return array{ok: bool, message: string} */
    public function startQueueProbe(): array
    {
        $token = Str::random(16);
        Cache::put('setup.queue_probe', ['token' => $token, 'sent_at' => now()->toIso8601String()], 86400);
        QueueProbeJob::dispatch($token);
        $done = Cache::get('setup.queue_probe')['done_at'] ?? null;

        return $done ? ['ok' => true, 'message' => 'Processed immediately (the queue runs inline on this server).'] : ['ok' => true, 'message' => 'Test job sent. Refresh in a few seconds.'];
    }

    // ── public access ───────────────────────────────────────────────────────

    private function publicAccess($inbox): array
    {
        $out = [];
        if ($inbox->has('url')) {
            $out[] = $inbox['url'] + ['action' => null];
        }
        $url = rtrim((string) config('app.url'), '/');
        try {
            $r = Http::timeout(6)->withOptions(['verify' => ! app()->environment('local')])->get($url.'/up');
            $out[] = $r->successful()
                ? $this->c('self', 'Site answers at its public address', 'ok', "{$url}/up answered.")
                : $this->c('self', 'Site answers at its public address', 'warn', "{$url}/up answered ".$r->status().'.', 'Open the address from your phone. If it loads, this is only this server unable to call itself and you can ignore it.');
        } catch (\Throwable) {
            $out[] = $this->c('self', 'Site answers at its public address', 'warn', "This server could not open {$url}/up itself.", 'Some hosts block a site from calling its own address. Open it from your phone: if it loads, providers can reach you and this is fine.');
        }

        return $out;
    }

    // ── media ───────────────────────────────────────────────────────────────

    private function media(): array
    {
        $ff = app(FfmpegManager::class);
        $i = $ff->info();
        $out = [];
        if ($i['available'] && $i['h264'] && $i['aac']) {
            $out[] = $this->c('ffmpeg', 'Media converter (ffmpeg)', 'ok', 'ffmpeg '.$i['version'].' ('.$i['source'].') with H.264 and AAC. GIFs go to WhatsApp as video, any video becomes MP4, any voice note is converted.');
        } elseif ($i['available']) {
            $out[] = $this->c('ffmpeg', 'Media converter (ffmpeg)', 'warn', 'ffmpeg '.$i['version'].' runs but lacks '.(! $i['h264'] ? 'H.264 ' : '').(! $i['aac'] ? 'AAC ' : '').'encoding, which the channels need.', 'Install the managed copy below; it has both.', $i['can_install'] ? ['id' => 'ffmpeg_install', 'label' => 'Install the managed ffmpeg'] : null);
        } else {
            $out[] = $this->c('ffmpeg', 'Media converter (ffmpeg)', 'warn',
                'Not installed. Everything still works: voice notes to WhatsApp and Telegram are re-wrapped in PHP, and a GIF on WhatsApp or an unusual video format is sent as a file. Instagram voice notes and WhatsApp GIF-as-video need ffmpeg.',
                $i['can_install'] ? 'Press the button: it downloads a verified static ffmpeg into storage/ (no root access needed).' : (string) $i['install_blocker'],
                $i['can_install'] ? ['id' => 'ffmpeg_install', 'label' => 'Install ffmpeg now'] : null);
        }
        $out[] = $this->c('voice', 'Voice notes', 'ok', 'WhatsApp and Telegram: Chrome, Edge and Android voice notes are converted to Ogg Opus in PHP (lossless, no ffmpeg); Safari voice notes need no conversion. Instagram: needs ffmpeg.');

        return $out;
    }

    // ── seeded content and roles ────────────────────────────────────────────

    private function content(): array
    {
        $out = [];
        $roles = Role::whereIn('name', array_map([BusinessRole::class, 'spatieName'], BusinessRole::all()))->count();
        $rolesOk = $roles >= count(BusinessRole::all());
        $out[] = $rolesOk ? $this->c('roles', 'Workspace roles (owner, admin, agent, viewer)', 'ok', 'Seeded.')
            : $this->c('roles', 'Workspace roles (owner, admin, agent, viewer)', 'fail', 'Not seeded, so workspace members cannot be given a role.', 'Press the button.', ['id' => 'seed_all', 'label' => 'Seed everything']);
        $gifs = GifLibraryItem::count();
        $out[] = $gifs > 0 ? $this->c('gifs', 'GIF and sticker library', 'ok', $gifs.' items on '.count(app(GifLibrary::class)->categories()).' shelves.')
            : $this->c('gifs', 'GIF and sticker library', 'warn', 'Empty. The chat GIF panel would show nothing built in.', 'Press the button.', ['id' => 'seed_all', 'label' => 'Seed everything']);
        $tones = NotificationTone::count();
        $out[] = $tones > 0 ? $this->c('tones', 'Sounds (ringtones, alerts, beeps)', 'ok', $tones.' tones in the library.')
            : $this->c('tones', 'Sounds (ringtones, alerts, beeps)', 'warn', 'Empty. Members would hear nothing for calls and alerts.', 'Press the button.', ['id' => 'seed_all', 'label' => 'Seed everything']);

        return $out;
    }

    // ── providers / modules ─────────────────────────────────────────────────

    private function providers($inbox): array
    {
        $out = [];
        $needsVoice = BusinessSettings::module('call_flows') || BusinessSettings::module('recording') || BusinessSettings::module('ai_voice');
        $tw = ProviderStatus::isActive('twilio');
        $out[] = $tw ? $this->c('twilio', 'Twilio (calls and numbers)', 'ok', 'Keys saved.')
            : $this->c('twilio', 'Twilio (calls and numbers)', $needsVoice ? 'fail' : 'warn', 'No Twilio keys. Call flows, recording and the voice agent need them.', 'Add them in Admin > Provider keys > Voice.');
        if (BusinessSettings::module('ai_voice') || BusinessSettings::module('intelligence')) {
            $k = filled(config('services.anthropic.api_key'));
            $out[] = $k ? $this->c('anthropic', 'Anthropic key (AI agent and call intelligence)', 'ok', 'Saved.')
                : $this->c('anthropic', 'Anthropic key (AI agent and call intelligence)', 'fail', 'No key, so the AI agent and call summaries cannot run.', 'Add it in Admin > Provider keys.');
        }
        if ($inbox->has('connections')) {
            $out[] = $inbox['connections'] + ['action' => null];
        }
        $mods = [];
        foreach (['call_flows' => 'Call flows', 'recording' => 'Recording', 'intelligence' => 'Intelligence', 'inbox' => 'Shared inbox', 'ai_voice' => 'AI voice agent', 'integrations' => 'Integrations'] as $k => $l) {
            $mods[] = $l.': '.(BusinessSettings::module($k) ? 'on' : 'off');
        }
        $out[] = $this->c('modules', 'Module switches', 'ok', implode(' · ', $mods).'. Change them in the Settings tab.');

        return $out;
    }

    // ── voice agent ─────────────────────────────────────────────────────────

    private function voice(): array
    {
        $out = [];
        $out[] = function_exists('pcntl_fork')
            ? $this->c('pcntl', 'Process control (pcntl)', 'ok', 'Available.')
            : $this->c('pcntl', 'Process control (pcntl)', 'fail', 'The pcntl extension is missing, so the agent server cannot start. Shared hosting cannot run it.', 'Use a VPS with the CLI pcntl extension (Ubuntu: apt install php-cli, which includes it).');
        $url = BusinessSettings::aiText('server_url');
        $out[] = str_starts_with($url, 'wss://') ? $this->c('wss', 'Public wss:// address', 'ok', $url)
            : $this->c('wss', 'Public wss:// address', 'fail', 'No wss:// address set. Twilio connects calls to this address.', 'Set it in Settings > AI voice agent (for example wss://agent.example.com/relay) and put a TLS proxy (nginx or Caddy) in front of port 8089.');
        $alive = Cache::get('business.ai_voice.alive');
        $out[] = (is_array($alive) && now()->timestamp - $alive['at'] < 90)
            ? $this->c('agent', 'Agent process', 'ok', 'Running ('.$alive['calls'].' call(s) now, listening on '.$alive['host'].':'.$alive['port'].').')
            : $this->c('agent', 'Agent process', 'fail', 'Not running (no heartbeat in the last 90 seconds).', 'Start it under Supervisor: php artisan business:ai-voice-server --host=127.0.0.1 --port=8089 (see the voice agent steps below).');

        return $out;
    }

    // ── the VPS-only voice agent guide ──────────────────────────────────────

    /** @return list<array{0:string,1:string,2?:string}> */
    public static function voiceGuide(): array
    {
        $app = HostingGuide::appPath();
        $php = HostingGuide::phpBinary();
        $sup = "[program:naarasim-ai-voice]\nprocess_name=%(program_name)s\ncommand=$php $app/artisan business:ai-voice-server --host=127.0.0.1 --port=8089\ndirectory=$app\nautostart=true\nautorestart=true\nstopwaitsecs=30\nuser=www-data\nredirect_stderr=true\nstdout_logfile=$app/storage/logs/ai-voice.log";
        $nginx = "server {\n    listen 443 ssl http2;\n    server_name agent.example.com;\n    # ssl_certificate / ssl_certificate_key from Let's Encrypt (certbot)\n    location /relay {\n        proxy_pass http://127.0.0.1:8089;\n        proxy_http_version 1.1;\n        proxy_set_header Upgrade \$http_upgrade;\n        proxy_set_header Connection \"upgrade\";\n        proxy_read_timeout 3600s;\n    }\n}";

        return [
            ['1. Run the agent under Supervisor', 'The agent is a long-lived process: one per server. Save this as /etc/supervisor/conf.d/naarasim-ai-voice.conf, then run: supervisorctl reread && supervisorctl update.', $sup],
            ['2. Put a secure address in front of it', 'Twilio only connects to wss:// (TLS). Point a sub-domain (for example agent.example.com) at this server, get a certificate (certbot), and proxy it to port 8089.', $nginx],
            ['3. Tell the platform the address', 'Admin > Business Suite > Settings > AI voice agent > Server address: wss://agent.example.com/relay', null],
            ['4. Restart it after each deploy', 'The agent keeps the old code in memory, so restart it with your other workers.', "supervisorctl restart naarasim-ai-voice"],
        ];
    }

    // ── automatic fixes ─────────────────────────────────────────────────────

    /** @return array{ok: bool, message: string} */
    public function fix(string $id): array
    {
        try {
            switch ($id) {
                case 'storage_link':
                    if (is_link(public_path('storage')) || is_dir(public_path('storage'))) {
                        return ['ok' => true, 'message' => 'The storage link already exists.'];
                    }
                    if (! function_exists('symlink')) {
                        return ['ok' => false, 'message' => 'This host blocks symbolic links. Create public/storage pointing to storage/app/public by hand (cPanel Terminal: ln -s ../storage/app/public public/storage).'];
                    }
                    Artisan::call('storage:link');

                    return is_link(public_path('storage')) || is_dir(public_path('storage'))
                        ? ['ok' => true, 'message' => 'Storage link created.']
                        : ['ok' => false, 'message' => 'The link could not be created: '.trim(Artisan::output())];
                case 'seed_all':
                    (new BusinessRoleSeeder)->run();
                    $g = app(GifLibrary::class)->syncSeeds();
                    $t = NotificationTones::seedBuiltin();

                    return ['ok' => true, 'message' => "Roles checked; {$g} GIF(s) and {$t} tone(s) added."];
                case 'queue_probe':
                    return $this->startQueueProbe();
                case 'ffmpeg_install':
                    $s = app(FfmpegManager::class)->installRun();

                    return ['ok' => $s['phase'] === 'done', 'message' => $s['phase'] === 'done' ? 'ffmpeg '.($s['version'] ?? '').' installed.' : (string) ($s['error'] ?? 'Install failed.')];
            }
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }

        return ['ok' => false, 'message' => 'Unknown action.'];
    }

    private function bytes(string $v): int
    {
        $v = trim($v);
        if ($v === '' || $v === '-1') {
            return 0;
        }

        return (int) $v * match (strtolower(substr($v, -1))) {
            'g' => 1073741824, 'm' => 1048576, 'k' => 1024, default => 1,
        };
    }
}
