<?php

namespace App\Console\Commands;

use App\Services\Media\FfmpegManager;
use Illuminate\Console\Command;

/** Show which ffmpeg the platform will use and whether it can do what the channels need. */
class FfmpegStatus extends Command
{
    protected $signature = 'ffmpeg:status';

    protected $description = 'Show the ffmpeg the platform uses for media conversion';

    public function handle(FfmpegManager $ff): int
    {
        $i = $ff->info(true);
        $this->table(['Check', 'Result'], [
            ['Programs can run (proc_open/exec)', $i['exec']['ok'] ? 'yes' : 'NO: '.$i['exec']['reason']],
            ['ffmpeg found', $i['available'] ? "yes: {$i['path']} ({$i['source']})" : 'no'],
            ['Version', $i['version'] ?? '-'],
            ['H.264 video', $i['h264'] ? 'yes' : 'no'],
            ['AAC audio', $i['aac'] ? 'yes' : 'no'],
            ['Opus audio', $i['opus'] ? 'yes' : 'no'],
            ['Can install a managed copy', $i['can_install'] ? 'yes (php artisan ffmpeg:install)' : 'no: '.$i['install_blocker']],
        ]);

        return $i['available'] ? self::SUCCESS : self::FAILURE;
    }
}
