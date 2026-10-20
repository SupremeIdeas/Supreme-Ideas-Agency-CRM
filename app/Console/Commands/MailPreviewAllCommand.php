<?php

namespace App\Console\Commands;

use App\Support\MailTemplates;
use Illuminate\Console\Command;

/**
 * Renders every Email Studio template with its sample data and reports any that fail, so a broken email is found before a customer receives it.
 * `--write=DIR` also saves each as an .html file to eyeball in a browser. Read-only: nothing is sent.
 */
class MailPreviewAllCommand extends Command
{
    protected $signature = 'naara:mail:preview-all {--write= : Directory to save each rendered email as HTML}';

    protected $description = 'Render every Email Studio email with sample data and report failures (nothing is sent)';

    public function handle(): int
    {
        $dir = $this->option('write');
        if ($dir && ! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $failed = 0;
        foreach (MailTemplates::templates() as $key => $t) {
            try {
                $html = view($t['view'], $t['sample'])->render();
                if (trim($html) === '' || ! str_contains($html, '</html>')) {
                    throw new \RuntimeException('rendered an incomplete document');
                }
                if ($dir) {
                    file_put_contents(rtrim($dir, '/')."/{$key}.html", $html);
                }
                $this->line("  ok    {$key}");
            } catch (\Throwable $e) {
                $failed++;
                $this->error("  FAIL  {$key}: ".$e->getMessage());
            }
        }
        $this->newLine();
        $failed === 0 ? $this->info('All '.count(MailTemplates::templates()).' emails render.') : $this->error("{$failed} email(s) failed to render.");

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
