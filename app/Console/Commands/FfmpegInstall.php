<?php

namespace App\Console\Commands;

use App\Models\Setting;
use App\Services\Media\FfmpegManager;
use Illuminate\Console\Command;

/** Install (or remove) the managed ffmpeg the platform uses for media conversion. Works on shared hosting and VPS alike; see docs/business-suite/SETUP-AND-HOSTING.md. */
class FfmpegInstall extends Command
{
    protected $signature = 'ffmpeg:install {--remove : delete the managed copy} {--url= : download ffmpeg from this link instead (a .tar.xz, .tgz, .gz or the raw static binary)} {--path= : use an ffmpeg that already exists at this path}';

    protected $description = 'Install, point to, or remove the ffmpeg used for GIF, video and voice-note conversion';

    public function handle(FfmpegManager $ff): int
    {
        if ($this->option('remove')) {
            $ff->uninstall();
            $this->info('Managed ffmpeg removed.');

            return self::SUCCESS;
        }
        if ($p = $this->option('path')) {
            Setting::setValue(FfmpegManager::SETTING_PATH, $p, 'media');
            $ff->forget();
            $i = $ff->info(true);
            $i['available'] ? $this->info("Using {$p} (ffmpeg {$i['version']}).") : $this->error("{$p} does not run on this server.");

            return $i['available'] ? self::SUCCESS : self::FAILURE;
        }
        if ($u = $this->option('url')) {
            Setting::setValue(FfmpegManager::SETTING_MIRROR, $u, 'media');
        }
        $info = $ff->info(true);
        if ($info['available'] && $info['source'] === 'managed' && ! $this->option('url')) {
            $this->info("Already installed: ffmpeg {$info['version']} at {$info['path']}.");

            return self::SUCCESS;
        }
        if ($blocker = $ff->installBlocker()) {
            $this->error($blocker);

            return self::FAILURE;
        }
        $last = '';
        $s = $ff->installRun(function (array $s) use (&$last) {
            $line = end($s['log']) ?: '';
            if ($line !== $last) {
                $this->line($line);
                $last = $line;
            }
        });
        if ($s['phase'] === 'done') {
            $this->info('Done. ffmpeg '.($s['version'] ?? '').' is ready.');

            return self::SUCCESS;
        }
        $this->error((string) ($s['error'] ?? 'Install failed.'));

        return self::FAILURE;
    }
}
