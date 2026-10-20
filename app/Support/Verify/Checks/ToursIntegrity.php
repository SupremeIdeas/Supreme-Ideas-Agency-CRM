<?php

namespace App\Support\Verify\Checks;

use App\Support\Tours\TourIntegrity;
use App\Support\Tours\TourRegistry;
use App\Support\Verify\Check;
use App\Support\Verify\CheckResult;
use Throwable;

/** Phase 10.7: `naara:verify tours`. Self-heals the seed first (a tour that was never synced is not a failure of the UI), then checks every step. */
class ToursIntegrity implements Check
{
    public function area(): string
    {
        return 'tours';
    }

    public function name(): string
    {
        return 'Every guided-tour step still points at a real element';
    }

    public function run(): CheckResult
    {
        try {
            TourRegistry::sync();
            $problems = TourIntegrity::problems();
        } catch (Throwable $e) {
            return CheckResult::fail('Tour integrity could not run: '.$e->getMessage());
        }

        return $problems === []
            ? CheckResult::pass('All tour anchors, routes and copy are present.')
            : CheckResult::fail(count($problems).' tour problem(s): '.implode('; ', array_slice($problems, 0, 5)), ['problems' => $problems]);
    }
}
