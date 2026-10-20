<?php

namespace App\Support\Verify\Checks;

use App\Models\User;
use App\Services\N2N\Identity\NaaraId;
use App\Support\Verify\Check;
use App\Support\Verify\CheckResult;
use Illuminate\Support\Facades\DB;
use Throwable;

/** MASTER-ONLY. `naara:verify brand`: one Naara ID per person, no duplicates, every ID well formed (checksum), and how many accounts still wait for one. */
class N2NNaaraIdsSound implements Check
{
    public function area(): string
    {
        return 'brand';
    }

    public function name(): string
    {
        return 'Naara IDs are unique, well formed and issued';
    }

    public function run(): CheckResult
    {
        try {
            $dupes = DB::table('users')->select('naara_id')->whereNotNull('naara_id')->groupBy('naara_id')->havingRaw('COUNT(*) > 1')->get()->count();
            $bad = 0;
            User::query()->whereNotNull('naara_id')->orderBy('id')->select('id', 'naara_id')->chunkById(1000, function ($rows) use (&$bad) {
                foreach ($rows as $r) {
                    if (NaaraId::normalise($r->naara_id) !== $r->naara_id) {
                        $bad++;
                    }
                }
            });
            $missing = User::query()->whereNull('naara_id')->where('is_active', true)->whereNull('anonymized_at')->count();
        } catch (Throwable $e) {
            return CheckResult::fail('Naara IDs could not be checked: '.$e->getMessage());
        }
        if ($dupes > 0 || $bad > 0) {
            return CheckResult::fail("$dupes duplicated Naara ID(s) and $bad malformed one(s). An ID is a handle people have saved: fix by hand, never regenerate silently.", ['duplicates' => $dupes, 'malformed' => $bad]);
        }

        return $missing > 0
            ? CheckResult::warn("$missing active account(s) have no Naara ID yet. Run `php artisan naara-id:backfill` (it also runs nightly).", ['missing' => $missing])
            : CheckResult::pass('Every active account has exactly one valid, unique Naara ID.');
    }
}
