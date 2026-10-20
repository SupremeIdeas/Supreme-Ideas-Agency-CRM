<?php

namespace App\Services\WhiteLabel\Payments;

use App\Models\WalletTransaction;
use Illuminate\Support\Facades\Auth;

/**
 * Shared by the White Label purchase pages (MASTER-ONLY). After the buyer returns from the card gateway the wallet is credited by the verified webhook, which can
 * lag the redirect. While a top-up is in flight the page shows a "confirming your payment" note, polls until the credit lands (then toasts and re-quotes), and
 * hides the top-up buttons so the buyer can never pay the same shortfall twice. The marker lives in the session for PENDING_TOPUP_SECONDS.
 */
trait WatchesWlTopUp
{
    public ?array $pendingTopUp = null;

    public const PENDING_TOPUP_SECONDS = 600;

    protected function loadPendingTopUp(): void
    {
        $p = session('pending_topup');
        if (! is_array($p) || ! isset($p['gateway'], $p['reference'], $p['started_at'])) {
            return;
        }
        $this->pendingTopUp = $p;
        $this->checkPendingTopUp();
    }

    /** Polled from the banner: clears itself on credit or on timeout. */
    public function checkPendingTopUp(): void
    {
        $p = $this->pendingTopUp;
        if (! $p) {
            return;
        }
        if (WalletTransaction::where('user_id', Auth::id())->where('reference', "topup:{$p['gateway']}:{$p['reference']}")->exists()) {
            $this->forgetPendingTopUp();
            $this->dispatch('nx-toast', variant: 'hero', type: 'success', title: 'Payment confirmed', message: 'Your wallet has been credited. You can finish the purchase now.', sound: 'credit_alert.default');

            return;
        }
        if (time() - (int) $p['started_at'] >= self::PENDING_TOPUP_SECONDS) {
            $this->forgetPendingTopUp();
            $this->notice = null;
            $this->error = 'We have not received confirmation from your bank yet. If you were charged, the credit will appear in your wallet shortly; you can top up again if it does not.';
        }
    }

    /** "I did not pay" - lets the buyer start over without waiting out the window. */
    public function cancelPendingTopUp(): void
    {
        $this->forgetPendingTopUp();
    }

    private function forgetPendingTopUp(): void
    {
        session()->forget('pending_topup');
        $this->pendingTopUp = null;
    }
}
