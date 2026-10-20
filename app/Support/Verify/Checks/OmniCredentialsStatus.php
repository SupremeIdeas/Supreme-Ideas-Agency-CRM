<?php

namespace App\Support\Verify\Checks;

use App\Services\Omni\OmniCredentials;
use App\Support\Verify\Check;
use App\Support\Verify\CheckResult;

/** MASTER-ONLY. `naara:verify omni`: which Respond credentials are default, set or missing. Never prints a value. A missing secret is a warning, not a failure, because every feature that needs it shows "Coming soon" instead of half-running. */
class OmniCredentialsStatus implements Check
{
    public function area(): string
    {
        return 'omni';
    }

    public function name(): string
    {
        return 'Respond credentials (default / set / missing)';
    }

    public function run(): CheckResult
    {
        $rows = OmniCredentials::status();
        $missing = array_values(array_filter($rows, fn ($r) => $r['status'] === 'MISSING'));
        $defaults = count(array_filter($rows, fn ($r) => $r['status'] === 'DEFAULT'));
        $set = count(array_filter($rows, fn ($r) => $r['status'] === 'SET'));
        if ($missing !== []) {
            return CheckResult::warn(count($missing).' missing: '.implode(', ', array_column($missing, 'env')).". {$set} set, {$defaults} on a safe default.", ['missing' => array_column($missing, 'env')]);
        }

        return CheckResult::pass("All credentials present ({$set} set, {$defaults} on a default you may override).");
    }
}
