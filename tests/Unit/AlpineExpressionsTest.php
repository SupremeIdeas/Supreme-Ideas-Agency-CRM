<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Alpine compiles a bare expression as `__self.result = <expression>`, so an attribute that STARTS with a statement keyword (`try`, `for`, `while`, `switch`, `throw`)
 * is a SyntaxError in the browser ("Unexpected token 'try'") and silently kills that component. Wrap it in an arrow function: `(() => { try { ... } catch (e) {} })()`.
 */
class AlpineExpressionsTest extends TestCase
{
    public function test_no_alpine_attribute_starts_with_a_statement_keyword(): void
    {
        $bad = [];
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(dirname(__DIR__, 2).'/resources/views', \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $file) {
            if (! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }
            $src = (string) file_get_contents($file->getPathname());
            if (preg_match_all('/\s(?:x-init|x-effect|x-data|x-on:[\w.:-]+|@[\w.:-]+|x-bind:[\w.:-]+|:[\w-]+)="\s*(?:try|for|while|switch|throw|do)\b[^"]*"/', $src, $m)) {
                foreach ($m[0] as $hit) {
                    $bad[] = str_replace(dirname(__DIR__, 2).'/', '', $file->getPathname()).': '.substr(trim($hit), 0, 80);
                }
            }
        }
        $this->assertSame([], $bad, "Alpine expressions that start with a statement keyword:\n".implode("\n", $bad));
    }
}
