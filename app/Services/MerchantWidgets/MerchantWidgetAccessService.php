<?php

namespace App\Services\MerchantWidgets;

use App\Exceptions\InsufficientBalanceException;
use App\Jobs\AlertAdminJob;
use App\Models\Merchant;
use App\Models\MerchantWidgetAccessPurchase as P;
use App\Models\User;
use App\Services\Platform\PlatformEarningsService;
use App\Services\Wallet\WalletService;
use App\Support\Auditor;
use App\Support\MerchantWidgetSettings;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Paid access to Naara Widgets (MASTER-ONLY). The admin sets a price or switches it to free at any time. Free = everyone has access.
 * Paid = a merchant needs one purchase row; once bought it is kept for good, so flipping the platform back to free and then to paid
 * again never takes access away from someone who already paid. Live keys keep serving shoppers whatever the access state is: this
 * gate only decides who may create keys, see the snippet and download the plugin.
 */
class MerchantWidgetAccessService
{
    public function __construct(private readonly WalletService $wallet, private readonly PlatformEarningsService $earnings) {}

    public function hasAccess(Merchant $merchant): bool
    {
        return ! MerchantWidgetSettings::isPaid() || $this->purchase($merchant) !== null;
    }

    public function purchase(Merchant $merchant): ?P
    {
        return P::query()->where('merchant_id', $merchant->id)->first();
    }

    /**
     * Buy access from the owner's wallet. Atomic debit idempotent on this merchant, then the row is written; if that fails the debit is
     * refunded (never charge without delivering) and the admin is alerted.
     *
     * @throws InsufficientBalanceException @throws RuntimeException
     */
    public function buy(Merchant $merchant, User $buyer): P
    {
        if ($existing = $this->purchase($merchant)) {
            return $existing;   // already owns it: a double click never charges twice
        }
        if (! MerchantWidgetSettings::isPaid()) {
            throw new RuntimeException('Widgets are free right now, so there is nothing to buy.');
        }
        $price = MerchantWidgetSettings::accessPriceUsd();
        $ref = 'mwa:'.$merchant->id;
        $this->wallet->debit($buyer, $price, 'USD', ['reference' => $ref, 'description' => 'Naara Widgets access']);
        try {
            $row = P::query()->create([
                'merchant_id' => $merchant->id, 'user_id' => $buyer->id, 'amount_usd' => $price,
                'source' => P::WALLET, 'reference' => $ref, 'granted_at' => now(),
            ]);
        } catch (QueryException $e) {
            // Two clicks raced and the other one won: hand this debit back and report the winner.
            $this->wallet->refund($buyer, $price, 'USD', ['reference' => 'refund-dup:'.$ref.':'.Str::random(6), 'description' => 'Duplicate Naara Widgets purchase', 'notify' => false]);

            return $this->purchase($merchant) ?? throw new RuntimeException('We could not record your purchase, so you were not charged.');
        } catch (Throwable $e) {
            $this->wallet->refund($buyer, $price, 'USD', ['reference' => 'refund:'.$ref, 'description' => 'Naara Widgets purchase could not be recorded', 'notify' => false]);
            Log::warning('Widget access purchase failed after debit: '.$e->getMessage());
            AlertAdminJob::dispatch(code: 'merchant_widget_access_orphan_charge', message: "Widget access charge {$ref} was refunded because it could not be recorded.", context: ['merchant_id' => $merchant->id]);
            throw new RuntimeException('We could not record your purchase, so you were not charged.');
        }
        $this->earnings->accrue($price, 'merchant_widget_access', 'earn:'.$ref, 'Naara Widgets access: '.$merchant->business_name);
        Auditor::log('merchant_widget.access_purchased', P::class, $row->id, ['merchant_id' => $merchant->id, 'amount' => $price]);

        return $row;
    }

    /** Admin gives a merchant access without charging (support, partners, refunds). Idempotent. */
    public function grant(Merchant $merchant, User $admin): P
    {
        return DB::transaction(function () use ($merchant, $admin) {
            $row = P::query()->firstOrCreate(
                ['merchant_id' => $merchant->id],
                ['user_id' => null, 'amount_usd' => 0, 'source' => P::GRANT, 'reference' => 'mwa-grant:'.$merchant->id, 'granted_at' => now()],
            );
            if ($row->wasRecentlyCreated) {
                Auditor::log('merchant_widget.access_granted', P::class, $row->id, ['merchant_id' => $merchant->id, 'by' => $admin->id]);
            }

            return $row;
        });
    }
}
