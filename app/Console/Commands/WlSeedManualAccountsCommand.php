<?php

namespace App\Console\Commands;

use App\Models\ManualPaymentAccount;
use App\Models\ManualPaymentAccountChange;
use App\Services\WhiteLabel\Payments\AddressIntegrity;
use Illuminate\Console\Command;

/**
 * Load White Label receiving accounts from a seed file you keep OUTSIDE the repository (MASTER-ONLY, operator-run). Nothing is read from code: the file path is an argument,
 * the data lands encrypted, every row can be edited or replaced later in Admin > White Label payments, and crypto rows always start OFF until their test transaction is confirmed.
 * Existing accounts are left alone unless --update is passed (so an edit made in the admin is never overwritten by accident).
 */
class WlSeedManualAccountsCommand extends Command
{
    protected $signature = 'wl:seed-manual-accounts {--file= : path to the seed JSON, kept outside the repository} {--update : overwrite accounts that already exist} {--dry-run : show what would happen and write nothing}';

    protected $description = 'Load White Label manual payment accounts from a private seed file';

    private const LABELS = [
        'account_holder' => 'Account holder', 'account_number' => 'Account number', 'bank_name' => 'Bank name', 'ach_routing' => 'ACH routing number', 'wire_routing' => 'Wire routing number',
        'account_type' => 'Account type', 'bank_address' => 'Bank address', 'sort_code' => 'Sort code', 'swift_code' => 'SWIFT / BIC', 'iban' => 'IBAN', 'address' => 'Address',
    ];

    public function handle(): int
    {
        $path = (string) $this->option('file');
        if ($path === '' || ! is_file($path) || ! is_readable($path)) {
            $this->error('Pass --file=/path/to/seed.json (a readable file outside the repository).');

            return self::FAILURE;
        }
        if (str_starts_with(realpath($path) ?: '', base_path().DIRECTORY_SEPARATOR) && ! str_starts_with(realpath($path), storage_path())) {
            $this->warn('That file is inside the repository folder. Keep real bank details outside it and never commit them.');
        }
        $data = json_decode((string) file_get_contents($path), true);
        if (! is_array($data) || ! is_array($data['accounts'] ?? null)) {
            $this->error('The file is not a valid seed (no "accounts" list).');

            return self::FAILURE;
        }
        $payee = trim((string) ($data['payee_label'] ?? ''));
        $note = $payee !== '' ? 'You are paying '.$payee.' directly. Send so the full amount arrives.' : null;
        $dry = (bool) $this->option('dry-run');
        $made = $skipped = $failed = 0;

        foreach ($data['accounts'] as $row) {
            $currency = strtoupper(trim((string) ($row['currency'] ?? '')));
            $kind = ($row['kind'] ?? 'bank') === 'crypto' ? 'crypto' : 'bank';
            $network = $kind === 'crypto' ? (string) ($row['network'] ?? '') : null;
            $label = trim((string) ($row['label'] ?? ''));
            $fields = [];
            foreach ((array) ($row['fields'] ?? []) as $k => $v) {
                if (trim((string) $v) !== '') {
                    $fields[] = ['label' => self::LABELS[$k] ?? ucfirst(str_replace('_', ' ', (string) $k)), 'value' => trim((string) $v)];
                }
            }
            if ($currency === '' || $label === '' || $fields === []) {
                $this->warn("Skipped a row without currency, label or details ({$label}).");
                $failed++;

                continue;
            }
            if ($kind === 'crypto') {
                $addr = (string) ($fields[0]['value'] ?? '');
                $check = AddressIntegrity::check((string) $network, $addr);
                if (! $check['ok']) {
                    $this->error("{$label}: address check failed ({$check['reason']}). Not loaded.");
                    $failed++;

                    continue;
                }
            }
            $existing = ManualPaymentAccount::query()->where(['currency' => $currency, 'kind' => $kind, 'network' => $network, 'label' => $label])->first();
            if ($existing && ! $this->option('update')) {
                $this->line("Kept existing: {$label}");
                $skipped++;

                continue;
            }
            if ($dry) {
                $this->line(($existing ? 'Would update: ' : 'Would create: ').$label);
                $made++;

                continue;
            }
            $account = $existing ?? new ManualPaymentAccount;
            $before = $existing?->fingerprint();
            $account->fill([
                'currency' => $currency, 'kind' => $kind, 'network' => $network, 'label' => $label, 'payee_note' => $note, 'fields' => $fields, 'sort' => 100 + $made,
                'enabled' => $kind === 'bank' ? (bool) ($row['enabled'] ?? false) : false,   // crypto is always OFF until its test transaction is confirmed
            ]);
            if ($existing && $before !== $account->fingerprint($fields)) {
                $account->test_confirmed_at = null;
            }
            $account->save();
            ManualPaymentAccountChange::query()->create(['account_id' => $account->id, 'user_id' => null, 'action' => $existing ? 'fields_changed' : 'created', 'reason' => 'Seeded by wl:seed-manual-accounts', 'before_hash' => $before, 'after_hash' => $account->fingerprint()]);
            $this->info(($existing ? 'Updated: ' : 'Created: ').$label.($kind === 'crypto' ? ' (OFF until test transaction)' : ''));
            $made++;
        }
        $this->line(($dry ? 'Dry run: ' : '')."{$made} written, {$skipped} kept, {$failed} problem(s). Edit or replace any of them in Admin > White Label payments.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
