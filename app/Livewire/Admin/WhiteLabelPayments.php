<?php

namespace App\Livewire\Admin;

use App\Models\ManualPaymentAccount;
use App\Models\ManualPaymentRate;
use App\Models\Setting;
use App\Services\WhiteLabel\Payments\GreyRateSource;
use App\Services\WhiteLabel\Payments\ManualPaymentAccounts;
use App\Services\WhiteLabel\Payments\ManualPaymentException;
use App\Services\WhiteLabel\Payments\WlRateQuoter;
use App\Support\Auditor;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Admin -> White Label -> Payments. Where the owner edits the receiving accounts shown to White Label buyers (bank or
 * crypto) and manages the rates used to turn a USD price into the amount a payer sends. Every account change needs the
 * admin's password and a reason, and is logged. Live rates come from the Grey Business API.
 */
#[Layout('components.layouts.admin')]
class WhiteLabelPayments extends Component
{
    public string $tab = 'accounts';

    // Authorisation for any account change.
    public string $password = '';

    public string $reason = '';

    // Account form.
    public ?int $editingId = null;

    public bool $formOpen = false;

    public string $currency = 'USD';

    public string $kind = 'bank';

    public string $network = 'TRC20';

    public string $label = '';

    public string $payeeNote = '';

    public int $sort = 100;

    /** @var list<array{label: string, value: string}> */
    public array $rows = [];

    // Rates.
    public bool $useLive = true;

    public int $maxAgeHours = 24;

    public int $lockFiat = 60;

    public int $lockStable = 30;

    public string $manualCurrency = 'GBP';

    public string $manualRate = '';

    /** @var array<string, array{rate: float}|null> */
    public array $liveCheck = [];

    public ?string $message = null;

    public ?string $error = null;

    public function mount(): void
    {
        $this->useLive = WlRateQuoter::useLive();
        $this->maxAgeHours = (int) Setting::getValue(WlRateQuoter::S_MAX_AGE_HOURS, 24);
        $this->lockFiat = (int) Setting::getValue(WlRateQuoter::S_LOCK_FIAT_MIN, 60);
        $this->lockStable = (int) Setting::getValue(WlRateQuoter::S_LOCK_STABLE_MIN, 30);
    }

    private function guard(): void
    {
        abort_unless(Auth::user()?->hasAnyRole(['super_admin', 'admin']), 403);
        $this->message = $this->error = null;
    }

    private function authorizeView(): void
    {
        abort_unless(Auth::user()?->hasAnyRole(['super_admin', 'admin']), 403);
    }

    // ------------------------------------------------------------------ accounts

    public function newAccount(): void
    {
        $this->guard();
        $this->resetForm();
        $this->formOpen = true;
        $this->rows = $this->blankRows();
    }

    public function edit(int $id): void
    {
        $this->guard();
        $a = ManualPaymentAccount::findOrFail($id);
        $this->editingId = $a->id;
        $this->currency = $a->currency;
        $this->kind = $a->kind;
        $this->network = $a->network ?: 'TRC20';
        $this->label = $a->label;
        $this->payeeNote = (string) $a->payee_note;
        $this->sort = (int) $a->sort;
        $this->rows = array_values(array_map(fn ($r) => ['label' => (string) $r['label'], 'value' => (string) $r['value']], (array) $a->fields));
        $this->formOpen = true;
    }

    public function addRow(): void
    {
        $this->rows[] = ['label' => '', 'value' => ''];
    }

    public function removeRow(int $i): void
    {
        unset($this->rows[$i]);
        $this->rows = array_values($this->rows);
    }

    public function updatedKind(): void
    {
        if ($this->editingId === null) {
            $this->rows = $this->blankRows();
        }
    }

    public function updatedCurrency(): void
    {
        if ($this->editingId === null) {
            $this->rows = $this->blankRows();
        }
    }

    public function save(ManualPaymentAccounts $accounts): void
    {
        $this->guard();
        try {
            $existing = $this->editingId ? ManualPaymentAccount::findOrFail($this->editingId) : null;
            $account = $accounts->save($existing, [
                'currency' => $this->currency, 'kind' => $this->kind, 'network' => $this->kind === 'crypto' ? $this->network : null,
                'label' => $this->label, 'payee_note' => $this->payeeNote, 'fields' => $this->rows, 'sort' => $this->sort,
            ], Auth::user(), $this->password, $this->reason);
        } catch (ManualPaymentException $e) {
            $this->error = $e->getMessage();

            return;
        }
        $this->message = $account->isCrypto() && ! $account->enabled
            ? 'Saved. This crypto address is OFF until you send a test transaction and confirm it.'
            : 'Saved.';
        $this->resetForm();
    }

    public function toggle(int $id, ManualPaymentAccounts $accounts): void
    {
        $this->guard();
        $a = ManualPaymentAccount::findOrFail($id);
        try {
            $accounts->setEnabled($a, ! $a->enabled, Auth::user(), $this->password, $this->reason);
        } catch (ManualPaymentException $e) {
            $this->error = $e->getMessage();

            return;
        }
        $this->message = $a->fresh()->enabled ? 'Account is now shown to buyers.' : 'Account is hidden from buyers.';
    }

    public function confirmTest(int $id, ManualPaymentAccounts $accounts): void
    {
        $this->guard();
        try {
            $accounts->confirmTest(ManualPaymentAccount::findOrFail($id), Auth::user(), $this->password, $this->reason);
        } catch (ManualPaymentException $e) {
            $this->error = $e->getMessage();

            return;
        }
        $this->message = 'Test confirmed. You can now turn this account on.';
    }

    public function cancel(): void
    {
        $this->resetForm();
    }

    private function resetForm(): void
    {
        $this->reset('editingId', 'formOpen', 'label', 'payeeNote', 'rows');
        $this->currency = 'USD';
        $this->kind = 'bank';
        $this->network = 'TRC20';
        $this->sort = 100;
        $this->password = '';
        $this->reason = '';
    }

    /** @return list<array{label: string, value: string}> */
    private function blankRows(): array
    {
        return array_map(fn ($l) => ['label' => $l, 'value' => ''], ManualPaymentAccounts::template($this->currency, $this->kind));
    }

    // ------------------------------------------------------------------ rates

    public function saveRateSettings(): void
    {
        $this->guard();
        Setting::setValue(WlRateQuoter::S_USE_LIVE, $this->useLive, 'white_label');
        Setting::setValue(WlRateQuoter::S_MAX_AGE_HOURS, max(1, min(168, $this->maxAgeHours)), 'white_label');
        Setting::setValue(WlRateQuoter::S_LOCK_FIAT_MIN, max(5, min(240, $this->lockFiat)), 'white_label');
        Setting::setValue(WlRateQuoter::S_LOCK_STABLE_MIN, max(5, min(240, $this->lockStable)), 'white_label');
        Auditor::log('wl.rates.settings_saved');
        $this->message = 'Rate settings saved.';
    }

    public function checkLive(GreyRateSource $grey): void
    {
        $this->guard();
        $this->liveCheck = [];
        if (! $grey->configured()) {
            $this->error = 'Add the Grey API key first (Admin, Provider Keys).';

            return;
        }
        foreach (['GBP', 'EUR', 'USDT', 'USDC'] as $c) {
            $q = $grey->quote($c, 'USD', 1000.0);
            $this->liveCheck[$c] = $q ? ['rate' => round($q['rate'], 6)] : null;
        }
    }

    public function saveManualRate(): void
    {
        $this->guard();
        $rate = (float) $this->manualRate;
        $cur = strtoupper(trim($this->manualCurrency));
        if (! preg_match('/^[A-Z]{3,5}$/', $cur) || $cur === 'USD' || $rate <= 0 || $rate > 1000000) {
            $this->error = 'Enter a currency code and a rate above zero (value of 1 unit in USD).';

            return;
        }
        ManualPaymentRate::create(['from_currency' => $cur, 'to_currency' => 'USD', 'rate' => $rate, 'source' => ManualPaymentRate::SOURCE_MANUAL, 'as_of' => now(), 'updated_by' => Auth::id(), 'note' => 'Entered by admin']);
        Auditor::log('wl.rates.manual_saved', null, null, ['currency' => $cur]);
        $this->manualRate = '';
        $this->message = "{$cur} rate saved.";
    }

    public function render()
    {
        $this->authorizeView();

        return view('livewire.admin.white-label-payments', [
            'accounts' => ManualPaymentAccount::query()->orderBy('sort')->orderBy('id')->get(),
            'networks' => ManualPaymentAccounts::CRYPTO_NETWORKS,
            'manualRates' => ManualPaymentRate::query()->where('source', ManualPaymentRate::SOURCE_MANUAL)->orderByDesc('as_of')->limit(8)->get(),
            'greyConfigured' => app(GreyRateSource::class)->configured(),
            'changes' => \App\Models\ManualPaymentAccountChange::with('user:id,name')->latest('id')->limit(10)->get(),
        ]);
    }
}
