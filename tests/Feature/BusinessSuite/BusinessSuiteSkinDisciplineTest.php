<?php

namespace Tests\Feature\BusinessSuite;

use App\Console\Commands\NxCoverage;
use Tests\TestCase;

/**
 * Standing rule: nothing in the Business Suite ships un-skinned. Every Blade view whose path mentions the Suite (business, call-flow, recording,
 * inbox, voice-agent, crm, softphone) or the Coming-soon sheet must be registered in config('appearance.coverage'), be built from the skin kit and
 * carry no hard-coded colour. A new Suite view that forgets this fails here, before anyone has to check it by eye.
 */
class BusinessSuiteSkinDisciplineTest extends TestCase
{
    private const PATHS = '#(business|call-flow|callflow|recording|transcript|omnichannel|voice-agent|ai-voice|crm|softphone|coming-soon)#i';

    public function test_every_suite_view_is_registered_converted_and_clean(): void
    {
        $registered = [];
        foreach ((array) config('appearance.coverage.batches', []) as $b) {
            foreach ($b['views'] as $v) {
                $registered[$v] = true;
            }
        }
        $scanner = new NxCoverage;
        $problems = [];
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(resource_path('views')));
        foreach ($it as $f) {
            $path = (string) $f;
            if (! str_ends_with($path, '.blade.php')) {
                continue;
            }
            $view = substr(str_replace(resource_path('views').'/', '', $path), 0, -10);
            if (! preg_match(self::PATHS, $view) || preg_match('#^(emails|mail|pdf|errors|marketing|components/theme-sections|components/layouts)#', $view)) {
                continue;
            }
            $src = file_get_contents($path);
            if (! isset($registered[$view])) {
                $problems[] = "$view: not registered in config/appearance.php coverage";
            }
            if (! $scanner->isConverted($src)) {
                $problems[] = "$view: not on the skin kit (add x-nx.page / nx:converted)";
            }
            foreach ($scanner->scan($src) as $o) {
                $problems[] = "$view: hard-coded colour ($o)";
            }
        }
        $this->assertSame([], $problems, implode("\n", $problems));
    }
}
