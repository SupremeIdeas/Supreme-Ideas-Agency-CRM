<?php

namespace App\Support\Verify\Checks;

use App\Support\Verify\Check;
use App\Support\Verify\CheckResult;

/** Blueprint 4.5: the call screen is mounted globally (every signed-in page) and a TURN relay is configured (without one, calls fail behind strict NATs). */
class CallsLayerAndTurn implements Check
{
    public function area(): string
    {
        return 'calls';
    }

    public function name(): string
    {
        return 'Call screen mounted and TURN relay configured';
    }

    public function run(): CheckResult
    {
        $layout = (string) @file_get_contents(resource_path('views/components/layouts/app.blade.php'));
        if (! str_contains($layout, 'n2n.call-overlay') || ! str_contains($layout, 'global-calls.js')) {
            return CheckResult::fail('The app layout no longer mounts the global call layer, so incoming calls cannot ring outside chat pages.');
        }
        if (trim((string) config('n2n.calls.turn.urls')) === '' || trim((string) config('n2n.calls.turn.secret')) === '') {
            return CheckResult::warn('No TURN relay is configured (N2N_TURN_URLS / N2N_TURN_SECRET). Calls will fail for people behind strict networks.');
        }

        return CheckResult::pass('Call screen is mounted and a TURN relay is configured.');
    }
}
