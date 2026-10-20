<?php

namespace App\Console\Commands;

use App\Models\ClientError;
use Illuminate\Console\Command;

class PruneClientErrors extends Command
{
    protected $signature = 'client-errors:prune';

    protected $description = 'Delete browser error reports older than 30 days';

    public function handle(): int
    {
        $this->info('Pruned '.ClientError::query()->where('created_at', '<', now()->subDays(30))->delete().' row(s).');

        return self::SUCCESS;
    }
}
