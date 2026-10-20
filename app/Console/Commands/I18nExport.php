<?php

namespace App\Console\Commands;

use App\Support\SiteContent;
use Illuminate\Console\Command;

/**
 * Dumps every translatable string (lang/en/* groups + the shipped marketing
 * defaults) as one flat JSON map, the hand-off format for adding a language:
 * translate the VALUES, then `php artisan i18n:import {locale} file.json`.
 *
 *   key shape:  "lang:{group}:{dot.path}"   (lang/en/{group}.php)
 *               "site:{page}.{section}.{field}[.n.field]"  (SiteContent defaults)
 */
class I18nExport extends Command
{
    protected $signature = 'i18n:export {--out= : Write to this file instead of stdout}';

    protected $description = 'Export every translatable English string as flat JSON (for translators / language packs).';

    public function handle(): int
    {
        $out = [];
        foreach (glob(lang_path('en/*.php')) as $file) {
            $group = basename($file, '.php');
            if ($group === 'site') {
                continue;
            }
            foreach (self::flatten((array) require $file) as $k => $v) {
                $out["lang:{$group}:{$k}"] = $v;
            }
        }
        foreach (SiteContent::defaults() as $page => $sections) {
            foreach (self::flatten($sections, $page.'.') as $k => $v) {
                if (self::isCopy($k, $v)) {
                    $out['site:'.$k] = $v;
                }
            }
        }

        $json = json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        if ($path = $this->option('out')) {
            file_put_contents($path, $json);
            $this->info(count($out).' strings -> '.$path);
        } else {
            $this->line($json);
        }

        return self::SUCCESS;
    }

    /** @return array<string, string> */
    public static function flatten(array $a, string $prefix = ''): array
    {
        $o = [];
        foreach ($a as $k => $v) {
            if (is_array($v)) {
                $o += self::flatten($v, $prefix.$k.'.');
            } elseif (is_string($v)) {
                $o[$prefix.$k] = $v;
            }
        }

        return $o;
    }

    /** Human-readable copy only: not URLs, asset paths, icon/route names, colours, flags or ids. */
    public static function isCopy(string $key, string $value): bool
    {
        $leaf = substr($key, (int) strrpos($key, '.') + 1);
        if (in_array($leaf, ['image', 'icon', 'route', 'href', 'url', 'radius_top', 'bg', 'style', 'variant', 'slug', 'id', 'key', 'color', 'colour', 'target', 'type', 'size', 'align', 'layout', 'anchor', 'img', 'video', 'poster', 'src'], true)) {
            return false;
        }
        if (! preg_match('/\p{L}{2}/u', $value)) {
            return false;
        }

        return ! preg_match('#^(https?://|mailto:|tel:|/|\#|[\w./-]+\.(webp|png|jpe?g|svg|gif|mp4)$)#i', $value);
    }
}
