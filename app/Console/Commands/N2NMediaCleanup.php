<?php

namespace App\Console\Commands;

use App\Services\N2N\Media\MediaService;
use Illuminate\Console\Command;

/** Deletes uploads that were staged but never attached to a message, once their short lifetime is over (N2N, MASTER-ONLY). */
class N2NMediaCleanup extends Command
{
    protected $signature = 'n2n:media-cleanup';

    protected $description = 'Delete expired, never-sent N2N uploads (master only)';

    public function handle(MediaService $media): int
    {
        $this->info('Removed '.$media->cleanupStaged().' orphan upload(s).');

        return self::SUCCESS;
    }
}
