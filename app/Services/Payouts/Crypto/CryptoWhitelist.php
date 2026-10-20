<?php

namespace App\Services\Payouts\Crypto;

use App\Models\PayoutAccount;
use App\Models\User;
use App\Support\Auditor;
use App\Support\CryptoPayoutNetworks;
use Illuminate\Support\Collection;

/**
 * The admin side of the wallet-address whitelist. NOWPayments only pays wallet addresses that have been added to
 * the dashboard whitelist (Settings -> Whitelist). This service produces the list to add and tracks the hand-off:
 *
 *   pending  -> a user saved the address; it is not on the provider whitelist yet
 *   exported -> an admin downloaded it in a CSV (so they know it was handed over)
 *   whitelisted -> an admin confirmed it is live in the dashboard; only now can a payout be sent to it
 */
class CryptoWhitelist
{
    public const CSV_HEADER = ['account_id', 'network', 'currency', 'address', 'memo', 'label', 'added_at'];

    /** @return Collection<int, PayoutAccount> */
    public function queue(string $status = PayoutAccount::WL_PENDING): Collection
    {
        return PayoutAccount::query()->where('provider', 'nowpayments')->where('whitelist_status', $status)->orderBy('id')->get();
    }

    /** @return array<string, int> */
    public function counts(): array
    {
        $rows = PayoutAccount::query()->where('provider', 'nowpayments')->whereNotNull('whitelist_status')
            ->selectRaw('whitelist_status, count(*) c')->groupBy('whitelist_status')->pluck('c', 'whitelist_status');

        return [
            PayoutAccount::WL_PENDING => (int) ($rows[PayoutAccount::WL_PENDING] ?? 0),
            PayoutAccount::WL_EXPORTED => (int) ($rows[PayoutAccount::WL_EXPORTED] ?? 0),
            PayoutAccount::WHITELISTED => (int) ($rows[PayoutAccount::WHITELISTED] ?? 0),
        ];
    }

    /**
     * CSV of the addresses to add to the dashboard whitelist. Only `pending` addresses are exported by default
     * (the NEW ones); pass `$includeExported` to re-download ones already handed over. Exported rows move to
     * `exported` so the next export contains only what is new.
     */
    public function exportCsv(User $admin, bool $includeExported = false): string
    {
        $rows = $this->queue(PayoutAccount::WL_PENDING);
        if ($includeExported) {
            $rows = $rows->concat($this->queue(PayoutAccount::WL_EXPORTED))->sortBy('id')->values();
        }

        $h = fopen('php://temp', 'r+');
        fputcsv($h, self::CSV_HEADER);
        foreach ($rows as $a) {
            $net = CryptoPayoutNetworks::get((string) $a->bank_code);
            fputcsv($h, [
                $a->id,
                $net['label'] ?? $a->bank_code,
                $net['currency'] ?? $a->bank_code,
                $this->safe((string) $a->account_number),
                $this->safe((string) data_get($a->details, 'memo', '')),
                'naara-user-'.$a->user_id,
                optional($a->created_at)->toIso8601String(),
            ]);
        }
        rewind($h);
        $csv = (string) stream_get_contents($h);
        fclose($h);

        $ids = $rows->where('whitelist_status', PayoutAccount::WL_PENDING)->pluck('id');
        if ($ids->isNotEmpty()) {
            PayoutAccount::query()->whereIn('id', $ids)->update(['whitelist_status' => PayoutAccount::WL_EXPORTED, 'whitelist_exported_at' => now()]);
        }
        Auditor::log('payout.crypto_whitelist_exported', 'PayoutAccount', null, ['admin_id' => $admin->id, 'rows' => $rows->count()]);

        return $csv;
    }

    /**
     * Mark addresses as live on the provider whitelist. Returns how many changed. Only addresses that were
     * pending/exported can be marked; the held payouts are re-evaluated by the Guardian sweeper.
     *
     * @param  list<int>  $ids
     */
    public function markWhitelisted(User $admin, array $ids): int
    {
        $n = PayoutAccount::query()->whereIn('id', $ids)->where('provider', 'nowpayments')
            ->whereIn('whitelist_status', [PayoutAccount::WL_PENDING, PayoutAccount::WL_EXPORTED])
            ->update(['whitelist_status' => PayoutAccount::WHITELISTED, 'whitelisted_at' => now()]);
        Auditor::log('payout.crypto_whitelisted', 'PayoutAccount', null, ['admin_id' => $admin->id, 'ids' => $ids, 'changed' => $n]);

        return $n;
    }

    /** Spreadsheet formula injection guard: a cell starting with = + - @ is read as a formula by Excel. */
    private function safe(string $v): string
    {
        return preg_match('/^[=+\-@\t\r]/', $v) ? "'".$v : $v;
    }
}
