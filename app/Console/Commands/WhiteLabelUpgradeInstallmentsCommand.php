<?php

namespace App\Console\Commands;

use App\Exceptions\InsufficientBalanceException;
use App\Models\WhiteLabelInstance;
use App\Notifications\WhiteLabelUpgradeDueSoonNotification;
use App\Services\Updater\WhiteLabelLicenseService;
use App\Support\Mailer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Content/billing/hot-menu blueprint Phase F2 — the Basic-to-Extended
 * installment path's own scheduled sweep, mirroring RenewVirtualNumbersCommand/
 * BrandSubscriptionsBillCommand exactly: advance notice, attempt the charge
 * once due, handle failure. Run daily by the scheduler.
 *
 *   - Advance notice: once per upgrade window, every instance whose admin-
 *     confirmed deadline falls within the notice window gets a heads-up with
 *     the exact amount and date.
 *   - On the deadline (or the first run on/after it, if the scheduler was
 *     ever missed), attempts the balance charge. On success, the instance
 *     upgrades to Extended. On insufficient funds — owner decision — the
 *     upgrade proposal is cleared and the instance simply stays on standard
 *     Basic terms; no suspension, no auto-extension.
 *
 * Money-safety: every charge is idempotent (keyed by instance + deadline
 * date) and runs through WalletService via the service's own
 * attemptScheduledUpgradeCharge(), never inline here.
 */
class WhiteLabelUpgradeInstallmentsCommand extends Command
{
    protected $signature = 'white-label:upgrade-installments {--notice-days=3 : Advance-notice window in days}';

    protected $description = 'Send advance notices and attempt due Basic-to-Extended installment charges.';

    public function handle(WhiteLabelLicenseService $licenses): int
    {
        $noticeDays = max(1, (int) $this->option('notice-days'));
        $notified = 0;
        $upgraded = 0;
        $reverted = 0;

        // 1) Advance notice — once per window, for anything due within it.
        WhiteLabelInstance::whereNotNull('upgrade_deadline_at')
            ->whereNull('upgrade_notice_sent_at')
            ->whereBetween('upgrade_deadline_at', [now(), now()->addDays($noticeDays)])
            ->with('owner')
            ->chunkById(100, function ($instances) use (&$notified) {
                foreach ($instances as $instance) {
                    if (! $instance->owner) {
                        continue;
                    }
                    Mailer::notify($instance->owner, new WhiteLabelUpgradeDueSoonNotification(
                        $instance->brand_name, (float) $instance->upgrade_balance_due, $instance->upgrade_deadline_at,
                    ));
                    $instance->forceFill(['upgrade_notice_sent_at' => now()])->save();
                    $notified++;
                }
            });

        // 2) Deadlines due today or passed — attempt the charge exactly once.
        WhiteLabelInstance::whereNotNull('upgrade_deadline_at')
            ->where('upgrade_deadline_at', '<=', now())
            ->chunkById(100, function ($instances) use ($licenses, &$upgraded, &$reverted) {
                foreach ($instances as $instance) {
                    try {
                        $licenses->attemptScheduledUpgradeCharge($instance);
                        $upgraded++;
                    } catch (InsufficientBalanceException) {
                        $reverted++;
                    } catch (\Throwable $e) {
                        Log::warning("white-label:upgrade-installments failed for instance {$instance->id}: ".$e->getMessage());
                    }
                }
            });

        $this->info("Notified {$notified}, upgraded {$upgraded}, reverted {$reverted}.");

        return self::SUCCESS;
    }
}
