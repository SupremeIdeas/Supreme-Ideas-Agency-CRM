<?php

namespace App\Jobs;

use App\Exceptions\InsufficientBalanceException;
use App\Exceptions\LicenseActivationException;
use App\Models\WhiteLabelInstance;
use App\Services\Updater\WhiteLabelLicenseService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Oversight/Withdrawal blueprint Phase E3 — the admin's approval decision
 * (still made synchronously, still the only gate that matters) is unchanged;
 * only the actual wallet charge + license issuance move off the admin's
 * request cycle (money-safety rule 8 — no external/payment work inline).
 *
 * WhiteLabelLicenseService::approveAndActivate() already handles an
 * insufficient-balance outcome correctly on its own (parks the instance at
 * APPROVED_AWAITING_FUNDS, notifies the merchant + admins, never charges
 * without delivering) — this job simply calls it from the queue instead of
 * from the Livewire action. `tries = 1`: a money action never blind-retries;
 * a genuine failure surfaces via the SAME notifications approveAndActivate()
 * already sends, not a job retry.
 */
class ProcessWhiteLabelApprovalJob implements ShouldQueue, ShouldBeUnique
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public int $instanceId, public ?int $reviewerId) {}

    public function uniqueId(): string
    {
        return (string) $this->instanceId;
    }

    public function handle(WhiteLabelLicenseService $licenses): void
    {
        $instance = WhiteLabelInstance::find($this->instanceId);
        if ($instance === null) {
            return;
        }

        try {
            $licenses->approveAndActivate($instance, $this->reviewerId);
        } catch (InsufficientBalanceException) {
            // Already parked at APPROVED_AWAITING_FUNDS and notified by the
            // service itself — nothing further to do here.
        } catch (LicenseActivationException $e) {
            // Not priced / already licensed / no payer — a precondition the
            // admin action already checks before dispatching, but a race
            // (another admin acted on the same instance in the meantime) is
            // still possible. Log rather than silently drop.
            Log::warning('white_label.approval_job_skipped', [
                'instance_id' => $this->instanceId,
                'reason' => $e->getMessage(),
            ]);
        }
    }
}
