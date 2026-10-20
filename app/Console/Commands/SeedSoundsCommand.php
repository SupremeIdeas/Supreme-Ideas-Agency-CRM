<?php

namespace App\Console\Commands;

use App\Support\NotificationTones;
use Illuminate\Console\Command;

class SeedSoundsCommand extends Command
{
    protected $signature = 'sounds:seed';

    protected $description = 'Install the built-in notification tones and ringtones (idempotent)';

    public function handle(): int
    {
        $this->info('Added '.NotificationTones::seedBuiltin().' built-in tone(s).');

        return self::SUCCESS;
    }
}
