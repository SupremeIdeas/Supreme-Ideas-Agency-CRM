<?php

namespace App\Console\Commands;

use App\Models\Setting;
use App\Models\WhiteLabelProjectIntake;
use App\Support\Auditor;
use Illuminate\Console\Command;

/** Erase own-server logins once setup is finished (MASTER-ONLY). Runs daily; the grace period is the admin setting wl.secrets.purge_days (default 7). */
class WlSecretsPurgeCommand extends Command
{
    protected $signature = 'wl-secrets:purge';

    protected $description = 'Erase stored server logins of White Label setups completed more than N days ago';

    public function handle(): int
    {
        $days = max(1, (int) Setting::getValue('wl.secrets.purge_days', 7));
        $n = 0;
        WhiteLabelProjectIntake::query()->whereNotNull('deploy_completed_at')->where('deploy_completed_at', '<=', now()->subDays($days))
            ->where(fn ($q) => $q->whereNotNull('hosting_host')->orWhereNotNull('hosting_username')->orWhereNotNull('hosting_password')->orWhereNotNull('hosting_notes'))
            ->get()->each(function (WhiteLabelProjectIntake $i) use (&$n) {
                $i->forceFill(['hosting_host' => null, 'hosting_username' => null, 'hosting_password' => null, 'hosting_notes' => null])->save();
                Auditor::log('wl.funnel_secrets_purged', WhiteLabelProjectIntake::class, $i->id, []);
                $n++;
            });
        $this->info("Erased server details from {$n} finished setup(s).");

        return self::SUCCESS;
    }
}
