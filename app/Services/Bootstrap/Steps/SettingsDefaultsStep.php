<?php

namespace App\Services\Bootstrap\Steps;

use App\Models\Bootstrap\BootstrapEntry;
use App\Models\Setting;
use App\Services\Bootstrap\BootstrapStep;
use App\Support\Bootstrap\Census;

/**
 * Bootstrap Phase 2: makes every admin-editable Setting explicit. The registry (config/bootstrap/settings.php) lists each key with a plain literal default; the value
 * equals the code fallback unless the entry says `differs_from_code`, so seeding is behaviour-neutral. A key that already exists is NEVER touched.
 */
class SettingsDefaultsStep implements BootstrapStep
{
    public function name(): string
    {
        return 'settings';
    }

    /** @return array<string, array<string, mixed>> */
    private function registry(): array
    {
        $all = (array) config('bootstrap.settings.settings', []);
        // N2N settings belong to the master-only module; only seed them where that module exists.
        if (! class_exists(\App\Support\N2N\N2NSettings::class)) {
            $all = array_filter($all, fn ($k) => ! str_starts_with($k, 'n2n.'), ARRAY_FILTER_USE_KEY);
        }

        return $all;
    }

    public function plan(string $profile): array
    {
        $rows = [];
        foreach ($this->registry() as $key => $def) {
            if (Setting::query()->where('key', $key)->exists()) {
                continue;
            }
            $rows[] = ['kind' => 'setting', 'item' => $key, 'note' => json_encode($def['value']).' ('.$def['risk'].')'.(! empty($def['differs_from_code']) ? ' DIFFERS from code fallback' : '')];
        }

        return $rows;
    }

    public function apply(string $profile): array
    {
        $created = $preserved = 0;
        foreach ($this->registry() as $key => $def) {
            if (Setting::query()->where('key', $key)->exists()) {
                $preserved++;

                continue;
            }
            Setting::setValue($key, $def['value'], $def['group'], $def['label'].' (DEFAULT, tune)', isPublic: false);
            BootstrapEntry::updateOrCreate(
                ['step' => $this->name(), 'kind' => 'setting', 'item' => $key],
                ['checksum' => BootstrapEntry::checksum($def['value']), 'admin_modified_at' => null],
            );
            $created++;
        }

        return ['created' => $created, 'preserved' => $preserved, 'skipped' => 0];
    }

    public function verify(string $profile): array
    {
        $fail = [];
        foreach ($this->registry() as $key => $def) {
            if (! Setting::query()->where('key', $key)->exists()) {
                $fail[] = "setting {$key} is missing (run naara:bootstrap)";
            }
        }

        // Every Setting key the code reads must be classified, so a new key cannot ship without an explicit default or a conscious "manual".
        $known = array_merge(
            array_keys((array) config('bootstrap.settings.settings', [])),
            array_keys((array) config('bootstrap.settings.slots', [])),
            array_keys((array) config('bootstrap.settings.manual', [])),
        );
        foreach (Census::settings() as $key => $row) {
            if ($row['seeded'] || in_array($key, $known, true)) {
                continue;
            }
            $fail[] = "setting {$key} is read in code but has no bootstrap entry (run naara:bootstrap:registry --merge)";
        }

        return $fail;
    }
}
