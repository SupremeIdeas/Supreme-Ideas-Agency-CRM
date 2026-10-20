<?php

namespace App\Console\Commands\Bootstrap;

use App\Services\Bootstrap\Bootstrapper;
use Illuminate\Console\Command;

class BootstrapVerifyCommand extends Command
{
    protected $signature = 'naara:bootstrap:verify {--only=}';

    protected $description = 'CI-safe: run every bootstrap verify() and exit non-zero on any failure (changes nothing)';

    public function handle(Bootstrapper $b): int
    {
        $bad = 0;
        foreach ($b->verify(app()->environment('production') ? 'production' : 'local', $this->option('only') ?: null) as $step => $fails) {
            $this->line(($fails ? '<fg=red>FAIL</>' : '<fg=green>ok  </>')." {$step}".($fails ? ' ('.count($fails).')' : ''));
            foreach ($fails as $f) {
                $this->line("     {$f}");
                $bad++;
            }
        }

        return $bad ? self::FAILURE : self::SUCCESS;
    }
}
