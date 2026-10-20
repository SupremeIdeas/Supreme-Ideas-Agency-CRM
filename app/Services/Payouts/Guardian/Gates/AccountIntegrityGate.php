<?php

namespace App\Services\Payouts\Guardian\Gates;

use App\Services\Payouts\Guardian\GateResult;
use App\Services\Payouts\Guardian\GuardianContext;

/**
 * G2 — the destination is the requester's own and verified. The global-rail extras
 * (active enrollment, saved guide acknowledgement) belong to Addendum A/B; until
 * those exist, a rail that needs them can never be auto-approved.
 */
class AccountIntegrityGate implements Gate
{
    /** Rails that require an enrollment + guide acknowledgement we cannot yet verify. */
    private const NEEDS_ENROLLMENT = ['payoneer', 'grey'];

    public function id(): string
    {
        return 'G2_account_integrity';
    }

    public function check(GuardianContext $ctx): GateResult
    {
        $account = $ctx->account;
        if ($account === null) {
            return GateResult::fail($this->id(), GateResult::HOLD, 'account_missing');
        }
        if ($account->user_id !== $ctx->request->user_id) {
            return GateResult::fail($this->id(), GateResult::HOLD, 'account_not_owned', ['account_user' => $account->user_id]);
        }
        if (! $account->is_verified) {
            return GateResult::fail($this->id(), GateResult::HOLD, 'account_unverified');
        }
        if (in_array($account->provider, self::NEEDS_ENROLLMENT, true)) {
            return GateResult::fail($this->id(), GateResult::HOLD, 'enrollment_unverifiable', ['provider' => $account->provider]);
        }

        // Whitelist-gated destinations (crypto wallets) cannot be paid until an admin has put the address on the
        // provider whitelist. Wait and re-check; the request is not refused and not refunded.
        if ($account->whitelist_status !== null && $account->whitelist_status !== \App\Models\PayoutAccount::WHITELISTED) {
            return GateResult::fail($this->id(), GateResult::DEFER, 'crypto_awaiting_whitelist', ['whitelist_status' => $account->whitelist_status], now()->addMinutes(30));
        }

        return GateResult::pass($this->id(), ['has_recipient_ref' => filled($account->provider_recipient_ref)]);
    }
}
