<?php

namespace App\Support\Verify\Checks;

use App\Models\N2N\UserPhone;
use App\Support\Verify\Check;
use App\Support\Verify\CheckResult;

/** Blueprint 4.3: a number is only discoverable once ownership is verified (otherwise people could claim someone else's number). */
class ChatDiscoveryIsOptIn implements Check
{
    public function area(): string
    {
        return 'chat';
    }

    public function name(): string
    {
        return 'Only verified numbers are discoverable';
    }

    public function run(): CheckResult
    {
        $n = UserPhone::query()->where('discoverable', true)->whereNull('verified_at')->count();

        return $n === 0
            ? CheckResult::pass('No unverified number is discoverable.')
            : CheckResult::fail($n.' unverified number(s) are marked discoverable.', ['count' => $n]);
    }
}
