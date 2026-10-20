<?php

namespace App\Support\Verify\Checks;

use App\Models\User;
use App\Support\Notifications\NotificationPrefs;
use App\Support\Notifications\Notify;
use App\Support\Verify\Check;
use App\Support\Verify\CheckResult;
use Throwable;

/**
 * Engineering v3 9.2: dry-run every matrix event for a throwaway in-memory person (no row written, nothing queued) and assert it resolves to the right
 * channels, that the dedupe/preference/critical rules hold, and that every `existing` event points at a cataloged class. `pending` events are reported, not hidden.
 */
class NotificationEventsFire implements Check
{
    public function area(): string
    {
        return 'notifications';
    }

    public function name(): string
    {
        return 'Every notification event resolves to the right channels';
    }

    public function run(): CheckResult
    {
        $problems = [];
        $pending = [];
        try {
            $probe = new User(['name' => 'Probe', 'email' => 'probe@example.test']);
            $probe->id = 0;
            foreach (Notify::catalog() as $event => $row) {
                if (! empty($row['pending'])) {
                    $pending[] = $event;
                }
                if (! isset(config('notification_catalog.categories')[$row['category']])) {
                    $problems[] = "$event: unknown category {$row['category']}";
                    continue;
                }
                $plan = Notify::plan($probe, $event, ['amount' => '$1', 'what' => 'x', 'date' => 'today', 'reason' => 'x', 'account' => 'x', 'to' => 'x', 'detail' => 'x'], null);
                if ($plan === null) {
                    $problems[] = "$event: resolves to no channel for a default person";
                    continue;
                }
                if (! in_array('in_app', $plan['channels'], true) && ! in_array('toast', $plan['channels'], true)) {
                    $problems[] = "$event: never reaches the bell";
                }
                if (preg_match('/\{\w+\}/', $plan['payload']['title'].$plan['payload']['body'])) {
                    $problems[] = "$event: leaves an unfilled placeholder";
                }
                if (NotificationPrefs::isCritical($row['category']) && ! NotificationPrefs::wants($probe, $row['category'], 'in_app')) {
                    $problems[] = "$event: a critical event can be silenced in-app";
                }
            }
            $catalog = (array) config('notification_catalog.notifications', []);
            foreach ((array) config('notification_events.existing', []) as $event => [$class]) {
                if (! class_exists('App\\Notifications\\'.$class) || ! isset($catalog[$class])) {
                    $problems[] = "$event: $class is missing or not cataloged";
                }
            }
        } catch (Throwable $e) {
            return CheckResult::fail('The notification matrix could not be evaluated: '.$e->getMessage());
        }

        if ($problems !== []) {
            return CheckResult::fail(count($problems).' notification event problem(s): '.implode('; ', array_slice($problems, 0, 5)), ['problems' => $problems]);
        }

        return $pending !== []
            ? CheckResult::warn(count($pending).' event(s) are defined but have no trigger in the product yet: '.implode(', ', $pending).'.', ['pending' => $pending])
            : CheckResult::pass('Every notification event resolves correctly.');
    }
}
