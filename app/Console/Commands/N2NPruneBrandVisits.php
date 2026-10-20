<?php

namespace App\Console\Commands;

use App\Services\N2N\Brands\BrandVisitService;
use Illuminate\Console\Command;

/** MASTER-ONLY: keep the brand profile visit log bounded. */
class N2NPruneBrandVisits extends Command
{
    protected $signature = 'n2n:prune-brand-visits';

    protected $description = 'Delete brand profile visit rows older than the retention period';

    public function handle(BrandVisitService $visits): int
    {
        $this->info('Removed '.$visits->prune().' old visit rows.');

        return self::SUCCESS;
    }
}
