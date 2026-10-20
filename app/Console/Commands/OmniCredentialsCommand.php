<?php

namespace App\Console\Commands;

use App\Services\Omni\OmniCredentials;
use Illuminate\Console\Command;

/** Lists every Naara Respond credential with its status (DEFAULT / SET / MISSING) and the addresses to paste into Meta and Telegram (MASTER-ONLY). Never prints a secret. */
class OmniCredentialsCommand extends Command
{
    protected $signature = 'naara:respond:credentials';

    protected $description = 'Show which Naara Respond credentials are default, set or missing, and the callback addresses to paste into Meta';

    public function handle(): int
    {
        $this->table(['Credential', 'Env name', 'Status', 'Needed for', 'Where to get it'], array_map(fn ($r) => [$r['label'], $r['env'], $r['status'], $r['needed_for'], $r['where']], OmniCredentials::status()));
        $url = rtrim((string) config('app.url'), '/');
        $this->line('APP_URL = '.$url.(str_starts_with($url, 'https://') ? '' : '   (not https: Meta and Telegram will refuse it)'));
        $this->line('Meta webhook callback (each connection shows its own address in Channels): '.$url.'/webhooks/meta/{connection}');
        $this->line('Omni webhook pattern: '.$url.'/webhooks/omni/{channel}/{publicId}');

        return self::SUCCESS;
    }
}
