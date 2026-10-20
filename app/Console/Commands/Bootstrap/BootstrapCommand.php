<?php

namespace App\Console\Commands\Bootstrap;

use App\Services\Bootstrap\Bootstrapper;
use Illuminate\Console\Command;

class BootstrapCommand extends Command
{
    protected $signature = 'naara:bootstrap {--profile= : local|staging|production (default: from APP_ENV)} {--only= : run a single step} {--dry-run : show what would be created, change nothing}';

    protected $description = 'Apply safe, documented defaults (settings, coupons drafts, journey goals). Idempotent; never overwrites an edited value or invents a credential.';

    public function handle(Bootstrapper $b): int
    {
        $profile = $this->option('profile') ?: (app()->environment('production') ? 'production' : (app()->environment('staging') ? 'staging' : 'local'));
        if (! in_array($profile, Bootstrapper::PROFILES, true)) {
            $this->error('Unknown profile. Use local, staging or production.');

            return self::INVALID;
        }
        $dry = (bool) $this->option('dry-run');
        $this->info(($dry ? 'DRY RUN: ' : '')."bootstrap profile={$profile}");

        $bad = 0;
        foreach ($b->run($profile, $this->option('only') ?: null, $dry) as $step => $r) {
            if ($dry) {
                $this->line("<fg=cyan>{$step}</>: would create ".count($r['plan']));
                foreach (array_slice($r['plan'], 0, 12) as $p) {
                    $this->line("   + {$p['kind']} {$p['item']}  ({$p['note']})");
                }
                if (count($r['plan']) > 12) {
                    $this->line('   ... and '.(count($r['plan']) - 12).' more');
                }

                continue;
            }
            $x = $r['result'];
            $this->line("<fg=cyan>{$step}</>: created {$x['created']}, kept {$x['preserved']} existing".($r['failures'] ? ', <fg=red>'.count($r['failures']).' check(s) failed</>' : ', ok'));
            foreach ($r['failures'] as $f) {
                $this->line("   ! {$f}");
                $bad++;
            }
        }

        return $bad ? self::FAILURE : self::SUCCESS;
    }
}
