<?php

namespace App\Console\Commands;

use App\Models\BrandPartner;
use App\Models\User;
use App\Services\Business\BusinessAccess;
use App\Services\Credits\CreditService;
use App\Support\Business\BusinessSettings;
use App\Support\CreditSettings;
use Illuminate\Console\Command;

/**
 * Pays the monthly NaaraCredit refill that a Naara Pro plan includes (MASTER-ONLY). Safe to run every day: each owner is paid at most once per calendar
 * month (the ledger reference is idempotent), a lapsed plan is never paid, and nothing is paid while the admin's "Pay the monthly NaaraCredit refill" switch or
 * the NaaraCredits programme itself is off. Whatever was missed while a switch was off is NOT back-paid for a past month: it is simply not issued.
 */
class BusinessRefillCredits extends Command
{
    protected $signature = 'business:refill-credits';

    protected $description = 'Pay the monthly NaaraCredit refill included in Naara Pro plans (idempotent per owner per month)';

    public function handle(BusinessAccess $access, CreditService $credits): int
    {
        if (! BusinessSettings::creditRefillOn() || ! CreditSettings::enabled()) {
            $this->info('Credit refill is switched off; nothing to do.');

            return self::SUCCESS;
        }

        $month = now()->format('Y-m');
        $paid = 0;
        BrandPartner::query()->whereNotNull('owner_user_id')->where('is_active', true)->with(['subscription.plan', 'plan'])->chunkById(200, function ($brands) use ($access, $credits, $month, &$paid) {
            foreach ($brands as $brand) {
                $owner = User::find($brand->owner_user_id);
                $plan = $owner ? $access->planFor($owner) : null;
                $amount = $plan?->monthlyCredits() ?? 0;
                if ($owner === null || $amount <= 0) {
                    continue;
                }
                try {
                    $credits->earn($owner, (float) $amount, 'naara_pro_refill', "pro-refill:{$brand->id}:{$month}", "Naara Pro monthly refill ({$plan->name})");
                    $paid++;
                } catch (\Throwable $e) {
                    report($e);   // one owner's problem never stops the rest
                }
            }
        });

        $this->info("Checked this month's refills; {$paid} owner(s) on a plan with a refill.");

        return self::SUCCESS;
    }
}
