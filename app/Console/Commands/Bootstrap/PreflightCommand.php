<?php

namespace App\Console\Commands\Bootstrap;

use App\Support\Bootstrap\Preflight;
use Illuminate\Console\Command;

class PreflightCommand extends Command
{
    protected $signature = 'naara:preflight';

    protected $description = 'Config Doctor: side-effect-free checks for an invalid setup. Exits non-zero only on a critical failure.';

    public function handle(): int
    {
        $checks = Preflight::checks();
        foreach ($checks as $c) {
            $tag = ['ok' => '<fg=green>ok     </>', 'pending' => '<fg=yellow>waiting</>', 'warn' => '<fg=yellow>warn   </>', 'fail' => '<fg=red>FAIL   </>'][$c['status']];
            $this->line("{$tag} {$c['label']}");
            if ($c['status'] !== 'ok' && $c['fix'] !== '') {
                $this->line("         fix: {$c['fix']}");
            }
        }
        $ready = Preflight::ready($checks);
        $this->newLine();
        $this->line($ready ? '<fg=green>No critical failures.</> Waiting items are things only you can do (cron, credentials, mail).' : '<fg=red>Not ready: fix the FAIL items above.</>');

        return $ready ? self::SUCCESS : self::FAILURE;
    }
}
