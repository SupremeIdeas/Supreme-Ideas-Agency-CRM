<?php

namespace App\Console\Commands;

use App\Services\N2N\Group\GroupCallService;
use Illuminate\Console\Command;

class N2NGroupSweep extends Command
{
    protected $signature = 'n2n:group-sweep';

    protected $description = 'N2N (master only): settle group-call participants who stopped sending heartbeats, step down speakers who ran out of credit, close empty rooms';

    public function handle(GroupCallService $groups): int
    {
        $n = $groups->sweep();
        $this->info("Group-call changes: {$n}");

        return self::SUCCESS;
    }
}
