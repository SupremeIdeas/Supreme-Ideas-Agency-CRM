<?php

namespace App\Support\Verify\Checks;

use App\Support\Verify\Check;
use App\Support\Verify\CheckResult;

/** Placeholder rows for the areas whose real checks arrive with their blueprint phase. Never a pass. */
class NotBuiltYet implements Check
{
    /** Empty now: wallet and rewards have real checks. A new area that is listed in Verifier::AREAS before its check exists goes here, so it is never mistaken for a pass. */
    public const PENDING = [];

    public function __construct(private readonly string $area = 'wallet', private readonly string $phase = 'Phase 3')
    {
    }

    public function area(): string
    {
        return $this->area;
    }

    public function name(): string
    {
        return 'Area checks';
    }

    public function run(): CheckResult
    {
        return CheckResult::notBuilt($this->phase);
    }
}
