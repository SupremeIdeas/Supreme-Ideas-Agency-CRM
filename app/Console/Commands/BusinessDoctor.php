<?php

namespace App\Console\Commands;

use App\Services\Business\SetupHealth;
use Illuminate\Console\Command;

/**
 * php artisan business:doctor [--fix] — the Admin > Business Suite > Setup and health page, in the terminal (MASTER-ONLY). With --fix it repairs what the platform can repair
 * itself: seeds roles, the GIF library and the tones, creates the storage link, installs the managed ffmpeg. Exit code 1 when something still needs fixing, so a deploy script can gate on it.
 */
class BusinessDoctor extends Command
{
    protected $signature = 'business:doctor {--fix : Repair what can be repaired automatically} {--no-ffmpeg : With --fix, skip the ffmpeg download}';

    protected $description = 'Check everything the Business Suite needs (cron, queue, storage, ffmpeg, seeded content, providers) and optionally fix it';

    public function handle(SetupHealth $health): int
    {
        if ($this->option('fix')) {
            foreach (['storage_link', 'seed_all'] as $id) {
                $r = $health->fix($id);
                $this->line(($r['ok'] ? '  fixed  ' : '  failed ').$r['message']);
            }
            if (! $this->option('no-ffmpeg') && ! app(\App\Services\Media\FfmpegManager::class)->available()) {
                $this->line('  installing ffmpeg (this can take a minute)...');
                $r = $health->fix('ffmpeg_install');
                $this->line(($r['ok'] ? '  fixed  ' : '  skipped ').$r['message']);
            }
            $this->newLine();
        }

        $groups = $health->groups();
        foreach ($groups as $g) {
            $this->info($g['title']);
            foreach ($g['checks'] as $c) {
                $tag = ['ok' => 'ok   ', 'warn' => 'check', 'fail' => 'FIX  '][$c['status']] ?? 'check';
                $this->line("  [{$tag}] {$c['label']}: {$c['detail']}");
                if ($c['status'] !== 'ok' && $c['fix']) {
                    $this->line('          -> '.$c['fix']);
                }
            }
        }
        $n = $health->counts($groups);
        $this->newLine();
        $this->line("{$n['ok']} ready, {$n['check']} to check, {$n['fix']} to fix.");

        return $n['fix'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
