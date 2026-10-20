<?php

namespace App\Support\Verify\Checks;

use App\Models\User;
use App\Support\Verify\Check;
use App\Support\Verify\CheckResult;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Throwable;

/**
 * Blueprint 6.1 "Nav Integrity": every admin page that opens on a plain GET and appears in the admin navigation must actually open for a super admin
 * (no Blade/Livewire exception, no 404/500). A nav entry that errors is how "Brand Profile" shipped broken once. Runs each page in-process through the
 * real HTTP kernel as the first super admin, with an array session so nothing is written. Read-only: only GET pages are requested.
 */
class MenusAdminLinksOpen implements Check
{
    public function area(): string
    {
        return 'menus';
    }

    public function name(): string
    {
        return 'Every admin menu link opens';
    }

    public function run(): CheckResult
    {
        try {
            $admin = User::query()->role('super_admin')->where('is_active', true)->first();
        } catch (Throwable) {
            $admin = null;   // roles not seeded yet
        }
        if ($admin === null) {
            return CheckResult::warn('There is no active super admin to open the pages as, so the menu could not be checked.');
        }
        $layout = (string) @file_get_contents(resource_path('views/components/layouts/admin.blade.php'));
        preg_match_all("/'route'\s*=>\s*'(admin\.[a-z0-9_.\-]+)'/", $layout, $m);
        $names = array_values(array_unique($m[1]));

        // An array session writes nothing, and lifting the admin throttle for this run keeps ~100 quick requests from tripping it.
        $prev = [config('session.driver'), config('admin.throttle')];
        config(['session.driver' => 'array', 'admin.throttle' => 100000]);
        $kernel = app(Kernel::class);
        $bad = [];
        $checked = 0;
        foreach ($names as $name) {
            $route = Route::getRoutes()->getByName($name);
            if ($route === null || ! in_array('GET', $route->methods(), true) || preg_match('/\{(?!adminGateway)[^}?]+\}/', $route->uri())) {
                continue;    // not a plain page (or the nav names a route this install does not have: the reachability test covers that)
            }
            $checked++;
            try {
                Auth::guard('web')->setUser($admin);
                $res = $kernel->handle(Request::create(route($name, [], false), 'GET'));
                $code = $res->getStatusCode();
                if ($code >= 400) {
                    $bad[$name] = 'HTTP '.$code;
                }
            } catch (Throwable $e) {
                $bad[$name] = class_basename($e).': '.mb_substr($e->getMessage(), 0, 120);
            }
        }
        Auth::guard('web')->logout();
        config(['session.driver' => $prev[0], 'admin.throttle' => $prev[1]]);

        return $bad === []
            ? CheckResult::pass("All {$checked} admin menu pages open.", ['checked' => $checked])
            : CheckResult::fail(count($bad).' admin menu page(s) fail to open: '.implode(', ', array_keys($bad)), ['broken' => $bad, 'checked' => $checked]);
    }
}
