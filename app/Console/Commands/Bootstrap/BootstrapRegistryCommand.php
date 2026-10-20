<?php

namespace App\Console\Commands\Bootstrap;

use App\Support\Bootstrap\Census;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * One-off generator for config/bootstrap/settings.php (the explicit default of every Setting key that has a plain literal code default and is not already seeded).
 * After the first run the file is HAND-CURATED: `naara:bootstrap:verify` fails when a key appears in the code that is in neither list, so new keys cannot slip through.
 * Re-running with --merge only APPENDS keys that are new in the code; it never rewrites an existing entry.
 */
class BootstrapRegistryCommand extends Command
{
    protected $signature = 'naara:bootstrap:registry {--merge : Append only keys that are new in the code}';

    protected $description = 'Generate (or extend) config/bootstrap/settings.php from the code census';

    private const WHY = [
        'money' => 'Money path. Equal to the code fallback so nothing changes; tune on the Pricing/Credits screens. Must keep passing MarginGuard.',
        'public' => 'Visible to visitors or customers. Equal to the code fallback; review the copy/behaviour on first day.',
        'safety' => 'Operational safety knob. Equal to the code fallback; change only with a reason.',
        'low' => 'Low impact. Equal to the code fallback.',
    ];

    public function handle(): int
    {
        $path = config_path('bootstrap/settings.php');
        $existing = is_file($path) ? (array) require $path : ['settings' => [], 'slots' => [], 'manual' => []];
        $have = array_merge(array_keys($existing['settings'] ?? []), array_keys($existing['slots'] ?? []), array_keys($existing['manual'] ?? []));

        $settings = $existing['settings'] ?? [];
        $slots = $existing['slots'] ?? [];
        $manual = $existing['manual'] ?? [];
        $added = 0;

        foreach (Census::settings() as $key => $row) {
            if ($row['seeded'] || in_array($key, $have, true)) {
                continue;
            }
            $added++;
            if (! $row['has_literal']) {
                $manual[$key] = $row['defaults'] === [] || $row['defaults'] === [''] ? 'no code default' : 'default is an expression: '.implode(' | ', $row['defaults']);

                continue;
            }
            if ($row['literal'] === '' || $row['literal'] === null || $row['literal'] === []) {
                $slots[$key] = 'empty by design (a slot, not a value)';

                continue;
            }
            $settings[$key] = [
                'value' => $row['literal'],
                'group' => $row['group'],
                'risk' => $row['risk'],
                'label' => Str::of(Str::afterLast($key, '.'))->replace(['_', '-'], ' ')->ucfirst()->toString(),
                'why' => self::WHY[$row['risk']],
            ];
        }

        ksort($settings);
        ksort($slots);
        ksort($manual);

        $out = "<?php\n\n/**\n * Explicit defaults for every admin-editable Setting key (Bootstrap Phase 2). GENERATED once by `naara:bootstrap:registry`, then curated by hand.\n *\n"
            ." * settings: key => value seeded when the key is missing. The value equals the code fallback, so seeding is behaviour-neutral; change it deliberately, not here by accident.\n"
            ." * slots:    keys whose default is empty on purpose (logos, colours, URLs): they stay unset and show as empty slots.\n"
            ." * manual:   keys with no plain default (constant/expression/none): an owner decision, never auto-seeded.\n"
            ." * Every number is a hypothesis labelled DEFAULT (tune); nothing here is claimed as market-proven.\n */\n\nreturn [\n    'settings' => ".self::export($settings, 1).",\n    'slots' => ".self::export($slots, 1).",\n    'manual' => ".self::export($manual, 1).",\n];\n";
        @mkdir(dirname($path), 0775, true);
        file_put_contents($path, $out);
        $this->info("config/bootstrap/settings.php: {$added} new keys; ".count($settings).' seedable, '.count($slots).' slots, '.count($manual).' manual.');

        return self::SUCCESS;
    }

    private static function export(array $a, int $depth): string
    {
        $pad = str_repeat('    ', $depth);
        $lines = ['['];
        foreach ($a as $k => $v) {
            $val = is_array($v) ? self::export($v, $depth + 1) : var_export($v, true);
            $lines[] = $pad.'    '.var_export($k, true).' => '.$val.',';
        }
        $lines[] = $pad.']';

        return implode("\n", $lines);
    }
}
