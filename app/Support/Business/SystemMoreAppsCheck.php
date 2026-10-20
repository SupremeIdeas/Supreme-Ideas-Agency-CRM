<?php

namespace App\Support\Business;

use App\Models\MoreApp;
use App\Support\Verify\Check;
use App\Support\Verify\CheckResult;
use Illuminate\Support\Facades\Route;
use Throwable;

/** MASTER-ONLY. `naara:verify menus`: the three built-in More apps exist (self-healed if missing), their routes resolve, and their logo files are present. */
class SystemMoreAppsCheck implements Check
{
    public function area(): string
    {
        return 'menus';
    }

    public function name(): string
    {
        return 'Built-in More apps are present with working routes and logos';
    }

    public function run(): CheckResult
    {
        try {
            $healed = SystemMoreApps::ensure();
            $problems = [];
            foreach (SystemMoreApps::APPS as $key => [$label, $route, $logo]) {
                $row = MoreApp::where('system_key', $key)->first();
                if (! $row) {
                    $problems[] = "$label is missing";
                    continue;
                }
                if (! Route::has($route)) {
                    $problems[] = "$label: route $route does not exist";
                }
                $path = str_starts_with((string) $row->icon_url, '/images/') ? public_path(ltrim($row->icon_url, '/')) : null;
                if ($path !== null && ! is_file($path)) {
                    $problems[] = "$label: logo file is missing";
                }
            }
        } catch (Throwable $e) {
            return CheckResult::fail('Could not check built-in More apps: '.$e->getMessage());
        }

        return $problems !== []
            ? CheckResult::fail(count($problems).' problem(s): '.implode('; ', $problems), ['problems' => $problems])
            : CheckResult::pass('Business Suite, NaaraBrain and Naara Chat are in More Apps'.($healed ? " ($healed restored just now)" : '').'.');
    }
}
