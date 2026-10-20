<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * The header language chip once shipped translucent and read as a greyed-out, disabled control on dark skins (owner screenshot, 2026-10-05).
 * Pixel-level proof lives in tests/visual/skins/chip-contrast.cjs (35 skins x light/dark x accents); this guard keeps the cause from coming back:
 * the chip's background must be an opaque token, and its text must use the primary text token.
 */
class LanguageChipContrastGuardTest extends TestCase
{
    public function test_the_chip_never_uses_a_translucent_background_or_the_secondary_text_token(): void
    {
        $blade = file_get_contents(dirname(__DIR__, 2).'/resources/views/components/lang-switcher.blade.php');
        $this->assertDoesNotMatchRegularExpression('/\bbg-white\/\d+/', $blade);
        $this->assertDoesNotMatchRegularExpression('/\bbg-\[rgb\(var\(--nx-[a-z0-9-]+\)\s*\/[^\]]*\)\]\s+text-\[rgb\(var\(--nx-text-2\)\)\]/', $blade);

        $css = file_get_contents(dirname(__DIR__, 2).'/resources/css/nx-layout.css');
        preg_match('/html\[data-nx-skin\] \.nx-lang-chip \{([^}]*)\}/', $css, $m);
        $rule = $m[1] ?? '';
        $this->assertNotSame('', $rule, 'the skin rule for .nx-lang-chip must exist');
        $this->assertStringContainsString('color: rgb(var(--nx-text))', $rule);
        $this->assertDoesNotMatchRegularExpression('/background:[^;]*\/\s*\.\d/', $rule, 'the chip background must be opaque');
    }
}
