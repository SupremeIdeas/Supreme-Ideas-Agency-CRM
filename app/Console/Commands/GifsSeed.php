<?php

namespace App\Console\Commands;

use App\Services\Gifs\GifLibrary;
use Illuminate\Console\Command;

/** Register the GIFs and stickers shipped under public/images/gifs in the composer library. Safe to run again. */
class GifsSeed extends Command
{
    protected $signature = 'gifs:seed';

    protected $description = 'Register the shipped GIF and sticker library for the chat composer';

    public function handle(GifLibrary $library): int
    {
        $this->info('Added '.$library->syncSeeds().' GIF(s).');

        return self::SUCCESS;
    }
}
