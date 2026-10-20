<?php

namespace App\Livewire;

use App\Models\WlFunnelManualPayment as P;
use App\Services\WhiteLabel\Funnel\WlFunnel;
use App\Services\WhiteLabel\Funnel\WlFunnelManualPayments as Svc;
use App\Services\WhiteLabel\Payments\ManualPaymentException;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\SvgWriter;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

/** Pay for a White Label by bank transfer or stablecoin and send proof (Funnel Phases 4 and 5.4, MASTER-ONLY). The server decides every number and every account shown. */
#[Layout('components.layouts.customer')]
class WlFunnelPay extends Component
{
    use WithFileUploads;
    use \App\Services\WhiteLabel\Payments\WatchesWlTopUp;

    public string $currency = 'USD';

    public ?string $network = null;

    public array $files = [];

    public string $payer_name = '';

    public string $payer_bank = '';

    public ?string $paid_at = null;

    public string $tx_hash = '';

    public ?string $error = null;

    public ?string $notice = null;

    public function mount(WlFunnel $funnel, Svc $svc)
    {
        abort_unless(WlFunnel::enabled(), 404);
        $inst = $funnel->forUser(Auth::user());
        if (! $inst) {
            return $this->redirectRoute('white-label.landing');
        }
        if ($funnel->missing($inst) !== []) {
            return $this->redirectRoute('wl-funnel.purchase');
        }
        $this->payer_name = (string) ((array) $inst->draft)['billing_name'];
        $this->loadPendingTopUp();
        if ($pay = $svc->current($inst)) {
            $this->currency = $pay->currency;
            $this->network = $pay->network;
        }
    }

    private function inst()
    {
        return app(WlFunnel::class)->forUser(Auth::user()) ?? abort(404);
    }

    public function choose(string $currency, ?string $network = null, ?Svc $svc = null): void
    {
        $svc ??= app(Svc::class);
        $this->reset('error', 'notice');
        $this->currency = strtoupper($currency);
        $this->network = in_array($this->currency, ['USDT', 'USDC'], true) ? ($network ?: ($svc->networksFor($this->currency)[0] ?? null)) : null;
        try {
            $svc->begin($this->inst(), $this->currency, $this->network);
        } catch (ManualPaymentException $e) {
            $this->error = $e->getMessage();
        }
    }

    public function payFromWallet(Svc $svc): void
    {
        $this->reset('error', 'notice');
        try {
            $svc->payWithWallet($this->inst(), Auth::user());
            $this->notice = 'Paid from your wallet. Your licence is being set up.';
        } catch (\App\Exceptions\InsufficientBalanceException) {
            $this->error = 'Your wallet balance is not enough. Top it up and try again.';
        } catch (ManualPaymentException $e) {
            $this->error = $e->getMessage();
        } catch (\Throwable $e) {
            report($e);
            $this->error = 'We could not complete the payment. You were not charged.';
        }
    }

    /** Top up the exact shortfall by card or gateway without visiting the Wallet page; the gateway returns here and the wallet is credited by its verified webhook. */
    public function topUp(string $gateway, \App\Services\WhiteLabel\Payments\WlWalletTopUp $top)
    {
        $this->reset('error', 'notice');
        $plan = $this->inst()->plan();
        try {
            return $this->redirect($top->start(Auth::user(), $gateway, (float) ($plan?->price_usd ?? 0), route('wl-funnel.pay')));
        } catch (ManualPaymentException $e) {
            $this->error = $e->getMessage();
        }

        return null;
    }

    public function requote(): void
    {
        $this->choose($this->currency, $this->network);
        if ($this->error === null) {
            $this->notice = 'Quote refreshed. The amount below is current.';
        }
    }

    public function submit(Svc $svc): void
    {
        $this->reset('error', 'notice');
        $this->validate(['files.*' => ['file', 'max:8192'], 'payer_name' => ['required', 'max:120'], 'payer_bank' => ['nullable', 'max:120'], 'paid_at' => ['nullable', 'date'], 'tx_hash' => ['nullable', 'max:120']]);
        $pay = $svc->current($this->inst());
        if (! $pay) {
            $this->error = 'Choose how you will pay first.';

            return;
        }
        try {
            $svc->submitProof($pay, Auth::user(), array_values($this->files), $this->payer_name, $this->payer_bank ?: null, $this->paid_at, $this->tx_hash ?: null);
            $this->notice = 'Proof received. We will review it and tell you the moment it is approved.';
        } catch (ManualPaymentException $e) {
            $this->error = $e->getMessage();
        } finally {
            foreach ($this->files as $f) {
                try {
                    $f->delete();   // the temporary upload never lingers on the server's disk
                } catch (\Throwable) {
                }
            }
            $this->reset('files');
        }
    }

    public function render(Svc $svc)
    {
        $inst = $this->inst();
        $pay = $svc->current($inst) ?? P::query()->where('funnel_instance_id', $inst->id)->where('status', P::APPROVED)->latest('id')->first();
        $rows = $pay ? $svc->instructions($pay) : [];
        $qr = null;
        if ($pay && in_array($pay->currency, ['USDT', 'USDC'], true) && $rows !== []) {
            $addr = collect($rows)->first(fn ($r) => stripos($r['label'], 'address') !== false)['value'] ?? $rows[0]['value'];
            $qr = (new SvgWriter)->write(new QrCode(data: $addr, errorCorrectionLevel: ErrorCorrectionLevel::Medium, size: 180, margin: 8))->getString();
        }

        return view('livewire.wl-funnel-pay', [
            'inst' => $inst, 'pay' => $pay, 'rows' => $rows, 'qr' => $qr, 'available' => $svc->availableCurrencies(),
            'networks' => in_array($this->currency, ['USDT', 'USDC'], true) ? $svc->networksFor($this->currency) : [],
            'balance' => $svc->balanceDue($inst) ?: $svc->currentBalance($inst), 'topGateways' => ($tu = app(\App\Services\WhiteLabel\Payments\WlWalletTopUp::class))->gateways(), 'short' => ($pl = $inst->plan()) ? $tu->shortfall(Auth::user(), (float) $pl->price_usd) : 0.0, 'plan' => $inst->plan(), 'manualOn' => Svc::enabled(), 'walletOk' => ($plan = $inst->plan()) ? app(\App\Services\Wallet\WalletService::class)->spendableUsd(Auth::user()) + 0.00001 >= (float) $plan->price_usd : false, 'docs' => \App\Models\WlInvoice::query()->where('funnel_instance_id', $inst->id)->orderBy('id')->get(),
        ]);
    }
}
