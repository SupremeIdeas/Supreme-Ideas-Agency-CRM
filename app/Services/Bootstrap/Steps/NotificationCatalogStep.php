<?php

namespace App\Services\Bootstrap\Steps;

use App\Services\Bootstrap\BootstrapStep;
use App\Support\Bootstrap\Census;

/**
 * Bootstrap section 10 (verification only): config/notification_catalog.php must list every Notification class with a category and the channels it really uses.
 * A new notification without a deliberate default fails here (and in CI), instead of shipping with accidental behaviour.
 */
class NotificationCatalogStep implements BootstrapStep
{
    public function name(): string
    {
        return 'notification-catalog';
    }

    public function plan(string $profile): array
    {
        return [];
    }

    public function apply(string $profile): array
    {
        return ['created' => 0, 'preserved' => 0, 'skipped' => 0];
    }

    public function verify(string $profile): array
    {
        $fail = [];
        $catalog = (array) config('notification_catalog.notifications', []);
        $categories = (array) config('notification_catalog.categories', []);

        foreach (Census::notifications() as $name => $row) {
            if (! isset($catalog[$name])) {
                $fail[] = "notification {$name} is not in config/notification_catalog.php";

                continue;
            }
            if (! isset($categories[$catalog[$name]['category']])) {
                $fail[] = "notification {$name} has an unknown category '{$catalog[$name]['category']}'";
            }
            $declared = $catalog[$name]['channels'];
            sort($declared);
            $actual = $row['channels'];
            sort($actual);
            if ($actual !== [] && $declared !== $actual) {
                $fail[] = "notification {$name} sends via [".implode(',', $actual).'] but the catalog says ['.implode(',', $declared).']';
            }
        }
        foreach (array_keys($catalog) as $name) {
            if (! isset(Census::notifications()[$name])) {
                $fail[] = "catalog lists {$name} but no such Notification class exists";
            }
        }

        return $fail;
    }
}
