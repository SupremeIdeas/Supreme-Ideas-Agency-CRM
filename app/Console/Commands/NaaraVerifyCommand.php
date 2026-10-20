<?php

namespace App\Console\Commands;

use App\Support\Verify\Verifier;
use Illuminate\Console\Command;

/** One entry point for every "is it really working" check (engineering blueprint 0.4). Read-only; safe on cPanel (cron-able). */
class NaaraVerifyCommand extends Command
{
    protected $signature = 'naara:verify {area? : One of '.'payouts, wallet, rewards, chat, calls, messages, preloader, media, menus, notifications, email, responsive} {--json : Machine-readable output} {--trigger=cli}';

    protected $description = 'Run the platform verification checks and store the results';

    public function handle(): int
    {
        $area = $this->argument('area');
        if ($area !== null && ! in_array($area, Verifier::AREAS, true)) {
            $this->error('Unknown area. Use one of: '.implode(', ', Verifier::AREAS));

            return self::INVALID;
        }

        $rows = Verifier::run($area, (string) $this->option('trigger'));

        if ($this->option('json')) {
            $this->line(json_encode($rows, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
        } else {
            foreach ($rows as $r) {
                $this->line(sprintf('[%s] %-13s %s: %s', strtoupper($r['status']), $r['area'], $r['check'], $r['detail']));
            }
        }

        return collect($rows)->contains(fn ($r) => $r['status'] === 'fail') ? self::FAILURE : self::SUCCESS;
    }
}
