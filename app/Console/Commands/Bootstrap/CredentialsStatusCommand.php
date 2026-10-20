<?php

namespace App\Console\Commands\Bootstrap;

use App\Support\Bootstrap\CredentialRegistry;
use Illuminate\Console\Command;

class CredentialsStatusCommand extends Command
{
    protected $signature = 'naara:credentials:status {--urls : print the webhook / callback URLs to paste into each provider console}';

    protected $description = 'List every credential slot (empty / saved) and, with --urls, the generated webhook and OAuth callback URLs';

    public function handle(): int
    {
        $slots = CredentialRegistry::slots();
        if ($this->option('urls')) {
            $this->warn('APP_URL = '.config('app.url').(str_starts_with((string) config('app.url'), 'https://') ? '' : '   (not https: providers will reject this)'));
            foreach ($slots as $s) {
                foreach ($s['webhooks'] as $w) {
                    $this->line(str_pad($s['id'], 14).str_pad($w['label'], 40).$w['url']);
                }
            }

            return self::SUCCESS;
        }
        $rows = [];
        foreach ($slots as $s) {
            $rows[] = [$s['group_label'], $s['id'], $s['status'], count(array_filter($s['fields'], fn ($f) => $f['set'])).'/'.count($s['fields'])];
        }
        $this->table(['Group', 'Provider', 'Status', 'Fields set'], $rows);
        $saved = count(array_filter($slots, fn ($s) => $s['status'] === 'saved'));
        $this->info("{$saved} of ".count($slots).' integrations have credentials. Status is empty/saved only; a no-spend test per provider is not built yet.');
        foreach (CredentialRegistry::ELSEWHERE as $p => $where) {
            $this->line("  {$p}: {$where}");
        }

        return self::SUCCESS;
    }
}
