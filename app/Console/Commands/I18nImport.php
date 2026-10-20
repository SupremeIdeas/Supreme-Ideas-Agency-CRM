<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Builds lang/{locale}/*.php from a flat JSON map produced by `i18n:export`
 * (values translated). Missing keys fall back to English, so a partial pack
 * still renders completely. Existing translations are kept unless --overwrite.
 *
 *   php artisan i18n:import pt translated.json [--overwrite]
 */
class I18nImport extends Command
{
    protected $signature = 'i18n:import {locale : e.g. pt} {file : JSON map from i18n:export, values translated} {--overwrite : Replace existing translations too}';

    protected $description = 'Write lang/{locale} files from a translated flat JSON map.';

    public function handle(): int
    {
        $locale = (string) $this->argument('locale');
        $map = json_decode((string) file_get_contents((string) $this->argument('file')), true);
        if (! is_array($map)) {
            $this->error('Not a JSON object.');

            return self::FAILURE;
        }

        @mkdir(lang_path($locale), 0755, true);
        $written = 0;

        foreach (glob(lang_path('en/*.php')) as $file) {
            $group = basename($file, '.php');
            if ($group === 'site') {
                continue;
            }
            $en = (array) require $file;
            $existingFile = lang_path("{$locale}/{$group}.php");
            $existing = (is_file($existingFile) && ! $this->option('overwrite')) ? self::flat((array) require $existingFile) : [];
            // A value identical to English is an untranslated fallback, not a reviewed translation: let a new import replace it.
            $enFlat = self::flat($en);
            $existing = array_filter($existing, fn ($v, $k) => ($enFlat[$k] ?? null) !== $v, ARRAY_FILTER_USE_BOTH);
            $rebrand = self::rebrandKeys((string) file_get_contents($file));

            $out = $this->build($en, '', $group, $map, $existing, $rebrand);
            if (is_file($existingFile) && ! $this->option('overwrite') && self::flat($out) == self::flat((array) require $existingFile)) {
                continue; // nothing new for this group: leave the reviewed file byte-for-byte alone
            }
            file_put_contents($existingFile, "<?php\n\nreturn ".self::dump($out, 1, $rebrand).";\n");
            $written++;
        }

        $site = [];
        foreach ($map as $k => $v) {
            if (str_starts_with($k, 'site:') && is_string($v) && $v !== '') {
                $site[substr($k, 5)] = $v;
            }
        }
        if ($site !== []) {
            ksort($site);
            file_put_contents(lang_path("{$locale}/site.php"), "<?php\n\n// Marketing-site defaults translated for this locale (page.section.field => text). Admin edits always win.\nreturn ".self::dump($site, 1, []).";\n");
            $written++;
        }

        $this->info("{$locale}: {$written} files written.");

        return self::SUCCESS;
    }

    private function build(array $en, string $path, string $group, array $map, array $existing, array $rebrand): array
    {
        $out = [];
        foreach ($en as $k => $v) {
            $p = $path.$k;
            if (is_array($v)) {
                $out[$k] = $this->build($v, $p.'.', $group, $map, $existing, $rebrand);

                continue;
            }
            $out[$k] = $existing[$p] ?? (isset($map["lang:{$group}:{$p}"]) && is_string($map["lang:{$group}:{$p}"]) && $map["lang:{$group}:{$p}"] !== '' ? $map["lang:{$group}:{$p}"] : $v);
        }

        return $out;
    }

    /** @return array<string, string> */
    private static function flat(array $a, string $prefix = ''): array
    {
        return I18nExport::flatten($a, $prefix);
    }

    /** Leaf keys that the English file wraps in BrandSettings::rebrand(). */
    private static function rebrandKeys(string $source): array
    {
        preg_match_all("/'([^']+)'\s*=>\s*\\\\App\\\\Support\\\\BrandSettings::rebrand\(/", $source, $m);

        return array_fill_keys($m[1], true);
    }

    private static function dump(array $a, int $level, array $rebrand): string
    {
        $pad = str_repeat('    ', $level);
        $lines = ["["];
        foreach ($a as $k => $v) {
            $key = var_export($k, true);
            if (is_array($v)) {
                $lines[] = "{$pad}{$key} => ".self::dump($v, $level + 1, $rebrand).',';
            } else {
                $s = self::quote((string) $v);
                if (isset($rebrand[$k])) {
                    $s = '\\App\\Support\\BrandSettings::rebrand('.$s.')';
                }
                $lines[] = "{$pad}{$key} => {$s},";
            }
        }
        $lines[] = str_repeat('    ', $level - 1).']';

        return implode("\n", $lines);
    }

    private static function quote(string $s): string
    {
        return "'".str_replace(["\\", "'"], ["\\\\", "\\'"], $s)."'";
    }
}
