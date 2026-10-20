<?php

namespace App\Support\Verify\Checks;

use App\Support\Verify\Check;
use App\Support\Verify\CheckResult;

class NotificationsCataloged implements Check
{
    public function area(): string
    {
        return 'notifications';
    }

    public function name(): string
    {
        return 'Every notification class is in the catalog';
    }

    public function run(): CheckResult
    {
        $catalog = array_keys((array) config('notification_catalog.notifications', []));
        $classes = array_map(fn ($f) => basename($f, '.php'), glob(app_path('Notifications/*.php')) ?: []);
        $missing = array_values(array_diff($classes, $catalog));

        return $missing === []
            ? CheckResult::pass(count($classes).' notification classes, all cataloged.')
            : CheckResult::fail('Notification classes missing from config/notification_catalog.php.', ['missing' => $missing]);
    }
}
