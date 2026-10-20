<?php

namespace App\Services\WhiteLabel\Payments;

use App\Models\ManualPaymentAccount;
use App\Models\ManualPaymentAccountChange;
use App\Models\User;
use App\Support\Auditor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Admin-editable receiving accounts for White Label manual payments. Money rules (Funnel blueprint 2.3, 5.6, 5.7):
 *  - every change needs the admin's password and a reason, and is written to an append-only change log (fingerprints
 *    only, never the details);
 *  - changing the payable details clears the "test transfer confirmed" mark; a CRYPTO account is switched off by such
 *    a change and cannot be enabled again until a test transaction is confirmed (anti address-swap);
 *  - crypto addresses must pass the network's format/checksum before they can be saved;
 *  - a bank account may be enabled without a test (owner's call), but it shows as "not test-confirmed" until one is.
 * No account is deleted (the change log would lose its subject); an account is disabled instead.
 */
class ManualPaymentAccounts
{
    public const CRYPTO_NETWORKS = ['TRC20', 'BEP20', 'ERC20', 'Solana'];

    private const MAX_ROWS = 20;

    /** Field labels offered when adding an account. Labels only: details are typed in by the owner, never shipped in code. */
    public static function template(string $currency, string $kind): array
    {
        if ($kind === 'crypto') {
            return ['Address'];
        }

        return match (strtoupper($currency)) {
            'USD' => ['Account holder', 'Account number', 'Bank name', 'ACH routing number', 'Wire routing number', 'Account type', 'Bank address', 'Payment reference'],
            default => ['Account holder', 'Account number', 'Bank name', 'Sort code', 'IBAN', 'SWIFT/BIC', 'Bank address', 'Payment reference'],
        };
    }

    /**
     * @param  array{currency: string, kind: string, network?: ?string, label: string, fields: list<array{label?: string, value?: string}>, sort?: int}  $data
     *
     * @throws ManualPaymentException
     */
    public function save(?ManualPaymentAccount $account, array $data, User $admin, string $password, string $reason): ManualPaymentAccount
    {
        $this->authorise($admin, $password, $reason);

        $kind = $data['kind'] === 'crypto' ? 'crypto' : 'bank';
        $currency = strtoupper(trim((string) $data['currency']));
        if (! preg_match('/^[A-Z]{3,5}$/', $currency)) {
            throw new ManualPaymentException('Enter the currency code, for example USD, GBP, EUR, USDT.');
        }
        $label = trim((string) $data['label']);
        if ($label === '' || mb_strlen($label) > 120) {
            throw new ManualPaymentException('Give the account a short name (up to 120 characters).');
        }
        $fields = $this->cleanFields((array) ($data['fields'] ?? []));
        $network = null;
        if ($kind === 'crypto') {
            $network = (string) ($data['network'] ?? '');
            if (! in_array($network, self::CRYPTO_NETWORKS, true)) {
                throw new ManualPaymentException('Choose the network this address is on.');
            }
            if (count($fields) !== 1) {
                throw new ManualPaymentException('A crypto account has exactly one address.');
            }
            $check = AddressIntegrity::check($network, (string) $fields[0]['value']);
            if (! $check['ok']) {
                throw new ManualPaymentException((string) $check['reason']);
            }
        }

        return DB::transaction(function () use ($account, $kind, $currency, $label, $fields, $network, $data, $admin, $reason) {
            $isNew = $account === null;
            $before = $isNew ? null : $account->fingerprint();
            $after = (new ManualPaymentAccount)->fingerprint($fields);
            $changedDetails = $isNew || $before !== $after;

            $account ??= new ManualPaymentAccount(['enabled' => false]);
            $account->fill([
                'currency' => $currency, 'kind' => $kind, 'network' => $network, 'label' => $label, 'fields' => $fields,
                'sort' => (int) ($data['sort'] ?? $account->sort ?? 100), 'updated_by' => $admin->id,
                'payee_note' => ($n = trim(strip_tags((string) ($data['payee_note'] ?? '')))) !== '' ? mb_substr($n, 0, 300) : null,
            ]);
            if ($changedDetails) {
                $account->test_confirmed_at = null;
                $account->test_note = null;
                if ($kind === 'crypto') {
                    $account->enabled = false; // never keep a changed crypto address live
                }
            }
            $account->save();

            if ($isNew || $changedDetails) {
                $this->log($account, $isNew ? 'created' : 'fields_changed', $admin, $reason, $before, $after);
            }

            return $account;
        });
    }

    /** @throws ManualPaymentException */
    public function setEnabled(ManualPaymentAccount $account, bool $enabled, User $admin, string $password, string $reason): ManualPaymentAccount
    {
        $this->authorise($admin, $password, $reason);
        if ($enabled) {
            if (empty($account->fields)) {
                throw new ManualPaymentException('Add the account details first.');
            }
            if ($account->isCrypto() && $account->test_confirmed_at === null) {
                throw new ManualPaymentException('Send a small test transaction to this address and confirm it before turning it on.');
            }
        }
        if ($account->enabled !== $enabled) {
            $account->forceFill(['enabled' => $enabled, 'updated_by' => $admin->id])->save();
            $this->log($account, $enabled ? 'enabled' : 'disabled', $admin, $reason, $account->fingerprint(), $account->fingerprint());
        }

        return $account;
    }

    /** The owner sent a test transfer/transaction and saw it arrive. */
    public function confirmTest(ManualPaymentAccount $account, User $admin, string $password, string $note): ManualPaymentAccount
    {
        $this->authorise($admin, $password, $note);
        $account->forceFill(['test_confirmed_at' => now(), 'test_note' => mb_substr($note, 0, 250), 'updated_by' => $admin->id])->save();
        $this->log($account, 'test_confirmed', $admin, $note, $account->fingerprint(), $account->fingerprint());

        return $account;
    }

    /** @throws ManualPaymentException */
    private function authorise(User $admin, string $password, string $reason): void
    {
        if (! $admin->hasAnyRole(['super_admin', 'admin'])) {
            throw new ManualPaymentException('Only an admin can change receiving accounts.');
        }
        if ($password === '' || ! Hash::check($password, (string) $admin->password)) {
            throw new ManualPaymentException('Your password is not correct.');
        }
        if (mb_strlen(trim($reason)) < 5) {
            throw new ManualPaymentException('Write a short reason for this change (at least 5 characters).');
        }
    }

    /**
     * @param  list<array{label?: string, value?: string}>  $rows
     * @return list<array{label: string, value: string}>
     *
     * @throws ManualPaymentException
     */
    private function cleanFields(array $rows): array
    {
        $out = [];
        foreach (array_slice($rows, 0, self::MAX_ROWS) as $r) {
            $label = trim((string) ($r['label'] ?? ''));
            $value = trim((string) ($r['value'] ?? ''));
            if ($label === '' && $value === '') {
                continue;
            }
            if ($label === '' || $value === '' || mb_strlen($label) > 60 || mb_strlen($value) > 300 || preg_match('/[\x00-\x08\x0b-\x1f]/', $label.$value)) {
                throw new ManualPaymentException('Every detail needs a name and a value (no control characters).');
            }
            $out[] = ['label' => $label, 'value' => $value];
        }
        if ($out === []) {
            throw new ManualPaymentException('Add at least one account detail.');
        }

        return $out;
    }

    private function log(ManualPaymentAccount $a, string $action, User $admin, string $reason, ?string $before, ?string $after): void
    {
        ManualPaymentAccountChange::create(['account_id' => $a->id, 'user_id' => $admin->id, 'action' => $action, 'reason' => mb_substr($reason, 0, 250), 'before_hash' => $before, 'after_hash' => $after]);
        Auditor::log('wl.manual_account.'.$action, 'ManualPaymentAccount', $a->id, ['currency' => $a->currency, 'kind' => $a->kind]);
    }
}
