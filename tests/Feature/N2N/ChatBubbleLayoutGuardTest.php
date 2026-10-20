<?php

namespace Tests\Feature\N2N;

use PHPUnit\Framework\TestCase;

/**
 * Regression guard (blueprint A): the chat bubble class `.ncb` was once ALSO the root of the video-call "Beauty looks" sheet
 * (position:absolute; bottom:0), so every bubble stacked over the composer. A class that styles the call sheet must never be the
 * bubble's class, and `.ncb` itself must never be absolutely positioned.
 */
class ChatBubbleLayoutGuardTest extends TestCase
{
    public function test_ncb_is_never_absolutely_positioned_by_a_bare_rule(): void
    {
        foreach (glob(dirname(__DIR__, 3).'/resources/css/*.css') as $file) {
            foreach (preg_split('/\R/', file_get_contents($file)) as $n => $line) {
                if (preg_match('/(^|[},]\s*)\.ncb\s*\{[^}]*position:\s*(absolute|fixed|sticky)/', $line)) {
                    $this->fail(basename($file).':'.($n + 1).' positions the chat bubble class .ncb out of the flow');
                }
            }
        }
        $this->addToAssertionCount(1);
    }

    public function test_the_call_overlay_does_not_reuse_the_bubble_class(): void
    {
        $overlay = file_get_contents(dirname(__DIR__, 3).'/resources/views/n2n/call-overlay.blade.php');

        $this->assertDoesNotMatchRegularExpression('/class="[^"]*\bncb\b/', $overlay);
        $this->assertStringContainsString('class="nc-beauty"', $overlay);
    }
}
