<?php

namespace App\Support\Verify\Checks;

use App\Support\Verify\Check;
use App\Support\Verify\CheckResult;

class ResponsiveAuditExists implements Check
{
    public function area(): string
    {
        return 'responsive';
    }

    public function name(): string
    {
        return 'Responsive audit script is present';
    }

    public function run(): CheckResult
    {
        return is_file(base_path('scripts/responsive/audit.mjs'))
            ? CheckResult::pass('scripts/responsive/audit.mjs is present (run it with node against a running site).')
            : CheckResult::warn('scripts/responsive/audit.mjs is missing.');
    }
}
