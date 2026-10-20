<?php

namespace App\Console\Commands;

use App\Models\WlFunnelInstance;
use App\Services\WhiteLabel\Funnel\WlFunnel;
use App\Services\WhiteLabel\Funnel\WlFunnelStates;
use Illuminate\Console\Command;

/** White Label funnel (MASTER-ONLY): drafts nobody came back to expire after their saved lifetime. Never touches anything paid or already issued. */
class WlFunnelExpireCommand extends Command
{
    protected $signature = 'wl-funnel:expire';

    protected $description = 'Expire White Label funnel instances whose draft lifetime has passed.';

    public function handle(WlFunnel $funnel): int
    {
        $n = 0;
        WlFunnelInstance::whereNotNull('expires_at')->where('expires_at', '<', now())
            ->whereIn('status', [WlFunnelStates::LANDING, WlFunnelStates::REGISTERING, WlFunnelStates::REGISTERED, WlFunnelStates::PREVIEW_ACTIVE, WlFunnelStates::PREVIEW_EXPIRED, WlFunnelStates::WIZARD, WlFunnelStates::DRAFT_SAVED, WlFunnelStates::ABANDONED])
            ->orderBy('id')->each(function ($i) use ($funnel, &$n) {
                $funnel->transition($i, WlFunnelStates::EXPIRED, 'system', ['reason' => 'draft_lifetime']);
                $n++;
            });
        $this->info("Expired {$n} funnel instance(s).");

        return self::SUCCESS;
    }
}
