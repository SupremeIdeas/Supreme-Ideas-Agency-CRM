<?php

namespace App\Console\Commands;

use App\Services\Kyc\KycCrossCheckService;
use Illuminate\Console\Command;

/**
 * kyc:cross-check (Oversight/Withdrawal blueprint Phase E2). Every 2 days,
 * batch every currently-escalated (pending, manual-review) KYC submission
 * through Claude for an advisory internal-consistency check. Never decides
 * anything — see KycCrossCheckService's own docblock for the full reasoning.
 */
class KycCrossCheckCommand extends Command
{
    protected $signature = 'kyc:cross-check';

    protected $description = 'Attach an advisory AI consistency assessment to every escalated (pending) KYC submission';

    public function handle(KycCrossCheckService $crossCheck): int
    {
        $assessed = $crossCheck->assessEscalated();
        $this->info("Assessed {$assessed} escalated KYC submission(s).");

        return self::SUCCESS;
    }
}
