<?php

namespace App\Livewire\Admin;

use App\Services\WhiteLabel\Funnel\WlInvoiceNumbering;
use App\Services\WhiteLabel\Funnel\WlInvoiceSettings as S;
use App\Support\Auditor;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/** Admin > White Label > Invoice settings (Funnel Phase 6B.5, MASTER-ONLY): who the seller is, terms, tax and number formats. Nothing is hard-coded; leave a field empty to leave it off the documents. */
#[Layout('components.layouts.admin')]
class WlInvoiceSettingsPage extends Component
{
    public string $sellerName = '';

    public string $sellerAddress = '';

    public string $sellerRegistration = '';

    public string $sellerTaxId = '';

    public string $sellerEmail = '';

    public string $terms = '';

    public string $notes = '';

    public int $termsDays = 14;

    public string $taxLabel = 'Tax';

    public float $taxRate = 0;

    public bool $autoSend = true;

    /** @var array<string,string> */
    public array $formats = [];

    public function mount(): void
    {
        abort_unless(Auth::user()->hasAnyRole(['super_admin', 'admin']), 404);
        $this->sellerName = S::sellerName();
        $this->sellerAddress = (string) S::get('seller_address');
        $this->sellerRegistration = (string) S::get('seller_registration');
        $this->sellerTaxId = (string) S::get('seller_tax_id');
        $this->sellerEmail = (string) S::get('seller_email');
        $this->terms = (string) S::get('terms');
        $this->notes = (string) S::get('notes');
        $this->termsDays = S::paymentTermsDays();
        $this->taxLabel = S::tax()['label'];
        $this->taxRate = S::tax()['rate'];
        $this->autoSend = S::autoSend();
        foreach (array_keys(S::DEFAULT_FORMATS) as $series) {
            $this->formats[$series] = S::format($series);
        }
    }

    public function save(): void
    {
        abort_unless(Auth::user()->hasAnyRole(['super_admin', 'admin']), 403);
        $this->validate([
            'sellerName' => ['nullable', 'max:160'], 'sellerAddress' => ['nullable', 'max:500'], 'sellerRegistration' => ['nullable', 'max:80'], 'sellerTaxId' => ['nullable', 'max:80'], 'sellerEmail' => ['nullable', 'email', 'max:160'],
            'terms' => ['nullable', 'max:2000'], 'notes' => ['nullable', 'max:1000'], 'termsDays' => ['required', 'integer', 'min:1', 'max:120'], 'taxLabel' => ['required', 'max:30'], 'taxRate' => ['required', 'numeric', 'min:0', 'max:100'],
            'formats.*' => ['required', 'max:60', 'regex:/\{0+\}/'],
        ], ['formats.*.regex' => 'A number format needs a counter such as {000000}.']);
        foreach (['seller_name' => $this->sellerName, 'seller_address' => $this->sellerAddress, 'seller_registration' => $this->sellerRegistration, 'seller_tax_id' => $this->sellerTaxId, 'seller_email' => $this->sellerEmail, 'terms' => $this->terms, 'notes' => $this->notes,
            'payment_terms_days' => $this->termsDays, 'tax_label' => $this->taxLabel, 'tax_rate' => $this->taxRate, 'auto_send' => $this->autoSend] as $k => $v) {
            S::put($k, is_string($v) ? trim(strip_tags($v)) : $v);
        }
        foreach ($this->formats as $series => $f) {
            S::put('format_'.$series, trim($f));
        }
        Auditor::log('wl.invoice_settings_saved', null, null, ['seller' => $this->sellerName]);
        $this->dispatch('nx-toast', type: 'success', message: 'Saved. New documents use these settings; issued ones never change.');
    }

    public function render()
    {
        return view('livewire.admin.wl-invoice-settings-page', ['example' => WlInvoiceNumbering::render($this->formats['INV'] ?? S::format('INV'), (int) now()->format('Y'), 1)]);
    }
}
