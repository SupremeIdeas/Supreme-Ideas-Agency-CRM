<?php

namespace App\Support\Verify\Checks;

use App\Support\Verify\Check;
use App\Support\Verify\CheckResult;

/** Blueprint 4.1: every composer panel and its toggle button is dismissed by the others (the "stuck controls" bug was a panel missing from this list). */
class ChatComposerPanelsSingleOpen implements Check
{
    public function area(): string
    {
        return 'chat';
    }

    public function name(): string
    {
        return 'Composer panels are single-open';
    }

    public function run(): CheckResult
    {
        $js = (string) @file_get_contents(base_path('resources/js/composer/composer.js'));
        if (! preg_match('/closePanels\(\) \{(.*?)\n    \}/s', $js, $m)) {
            return CheckResult::fail('composer.js has no closePanels(), so panels cannot be dismissed together.');
        }
        preg_match_all("/panel\('(p[A-Za-z]+)'/", implode("\n", array_map(fn ($f) => (string) file_get_contents($f), glob(base_path('resources/js/composer/*.js')) ?: [])), $panels);
        $missing = array_values(array_filter(array_unique($panels[1]), fn ($p) => ! str_contains($m[1], "'".$p."'")));

        return $missing === []
            ? CheckResult::pass('All '.count(array_unique($panels[1])).' composer panels close together.')
            : CheckResult::fail('Composer panels not covered by closePanels(): '.implode(', ', $missing), ['missing' => $missing]);
    }
}
