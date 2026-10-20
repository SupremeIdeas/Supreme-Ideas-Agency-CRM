<?php

namespace App\Services\WhiteLabel\Payments;

use App\Livewire\Wallet;
use App\Models\User;
use App\Services\Wallet\WalletService;
use App\Support\AppPlatform;
use App\Support\GatewayCurrencyMatrix;
use App\Support\ProviderStatus;

/**
 * Top up the wallet from inside a White Label purchase (MASTER-ONLY), so the buyer never has to leave to the Wallet page: the exact shortfall is quoted, the same gateway initialisation as the Wallet screen is used, and the gateway
 * sends them back to the purchase page. The wallet is still credited only by the verified webhook (never by the redirect), exactly as everywhere else; nothing here touches a balance.
 */
class WlWalletTopUp
{
    public function __construct(private readonly WalletService $wallet) {}

    /** The amount still missing, rounded UP to the cent (so the top-up always covers it), or 0 when the wallet already covers it. */
    public function shortfall(User $user, float $needUsd): float
    {
        $missing = $needUsd - $this->wallet->spendableUsd($user);

        return $missing <= 0.00001 ? 0.0 : round(ceil(($missing - 1e-9) * 100) / 100, 2);
    }

    /** @return array<string,string> slug => label for the active gateways that take USD */
    public function gateways(): array
    {
        if (AppPlatform::isIosBuild()) {
            return [];
        }
        $out = [];
        foreach (Wallet::GATEWAYS as $slug => [$label]) {
            if (ProviderStatus::isActive($slug) && GatewayCurrencyMatrix::supports($slug, 'USD')) {
                $out[$slug] = $label;
            }
        }

        return $out;
    }

    /**
     * Start the gateway checkout for the shortfall and return where to send the buyer.
     *
     * @throws ManualPaymentException
     */
    public function start(User $user, string $gateway, float $needUsd, string $returnUrl): string
    {
        if (! array_key_exists($gateway, $this->gateways())) {
            throw new ManualPaymentException('That payment method is not available right now.');
        }
        $amount = max(1.0, $this->shortfall($user, $needUsd));
        try {
            $result = app("pay.{$gateway}")->initialize($user, $amount, 'USD', [
                'return_url' => $returnUrl, 'success_url' => $returnUrl, 'cancel_url' => $returnUrl, 'redirect_url' => $returnUrl, 'callback_url' => $returnUrl,
            ]);
        } catch (\Throwable $e) {
            report($e);
            throw new ManualPaymentException('We could not start the payment. You were not charged. Please try again.');
        }
        if (! empty($result['reference'])) {
            session(['pending_topup' => ['gateway' => $gateway, 'reference' => $result['reference'], 'amount' => $amount, 'currency' => 'USD', 'started_at' => time()]]);
        }

        return (string) $result['redirect_url'];
    }
}
