<?php

namespace App\Support\Verify\Checks;

use App\Support\CustomPreloader;
use App\Support\PreloaderSettings;
use App\Support\Verify\Check;
use App\Support\Verify\CheckResult;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Blueprint 6.2 verification: every preloader section resolves to a valid theme and a drawable preset, every uploaded animation file still exists
 * (a missing one would show a blank overlay), and the overlay still removes itself (a stuck overlay blocks every click on the page).
 */
class PreloaderConfigSound implements Check
{
    public function area(): string
    {
        return 'preloader';
    }

    public function name(): string
    {
        return 'Preloader sections, themes and files are sound';
    }

    public function run(): CheckResult
    {
        $problems = [];
        foreach (array_keys(PreloaderSettings::pageTypes()) as $slug) {
            $cfg = PreloaderSettings::forPageType($slug);
            if (! array_key_exists($cfg['theme'] ?? 'auto', PreloaderSettings::THEMES)) {
                $problems[] = "$slug: unknown theme";
            }
            if (($cfg['preset'] ?? '') === PreloaderSettings::CUSTOM) {
                foreach ((array) ($cfg['custom'] ?? []) as $mode => $meta) {
                    if (! empty($meta['path']) && ! $this->exists($meta)) {
                        $problems[] = "$slug: the $mode animation file is missing from storage";
                    }
                }
            } elseif (! PreloaderSettings::isLegacy((string) $cfg['preset']) && ! view()->exists('components.preloaders.'.$cfg['preset'])) {
                $problems[] = "$slug: preset {$cfg['preset']} has no template";
            }
        }
        $view = (string) @file_get_contents(resource_path('views/components/brand-preloader.blade.php'));
        if (! str_contains($view, 'setTimeout') || ! str_contains($view, "addEventListener('load'")) {
            $problems[] = 'the overlay no longer removes itself on load with a hard timeout';
        }

        return $problems === []
            ? CheckResult::pass('Every preloader section resolves, its theme is valid and its files exist.')
            : CheckResult::fail(count($problems).' preloader problem(s): '.implode('; ', array_slice($problems, 0, 5)), ['problems' => $problems]);
    }

    private function exists(array $meta): bool
    {
        try {
            return Storage::disk($meta['disk'] ?? \App\Support\MediaStorage::disk())->exists((string) $meta['path']);
        } catch (Throwable) {
            return false;
        }
    }
}
