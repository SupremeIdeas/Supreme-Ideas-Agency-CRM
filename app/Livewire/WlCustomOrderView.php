<?php

namespace App\Livewire;

use App\Exceptions\InsufficientBalanceException;
use App\Models\WlCustomOrder;
use App\Services\Wallet\WalletService;
use App\Services\WhiteLabel\Funnel\WlCustomOrderException;
use App\Services\WhiteLabel\Funnel\WlCustomOrders;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

/** One custom order for its buyer: the request, the quote, accept or decline, pay from the wallet, and the conversation (Funnel Phase 8, MASTER-ONLY). */
#[Layout('components.layouts.customer')]
class WlCustomOrderView extends Component
{
    public string $reference = '';

    public string $message = '';

    public string $reason = '';

    public ?string $error = null;

    public ?string $notice = null;

    public function mount(string $reference)
    {
        abort_unless(WlCustomOrders::enabled(), 404);
        $this->reference = $reference;
        $this->order();   // 404 for anyone else's reference
    }

    private function order(): WlCustomOrder
    {
        return WlCustomOrder::query()->where('reference', $this->reference)->where('user_id', Auth::id())->firstOrFail();
    }

    private function run(callable $fn, string $ok): void
    {
        $this->reset('error', 'notice');
        try {
            $fn();
            $this->notice = $ok;
        } catch (WlCustomOrderException $e) {
            $this->error = $e->getMessage();
        } catch (InsufficientBalanceException) {
            $this->error = 'Your wallet balance is not enough. Top it up and try again.';
        } catch (ValidationException $e) {
            $this->error = (string) collect($e->errors())->flatten()->first();
        } catch (\Throwable $e) {
            report($e);
            $this->error = 'Something went wrong. You were not charged.';
        }
    }

    public function accept(WlCustomOrders $orders): void
    {
        $this->run(fn () => $orders->accept($this->order(), Auth::user()), 'Quote accepted. You can pay it now.');
    }

    public function decline(WlCustomOrders $orders): void
    {
        $this->run(fn () => $orders->decline($this->order(), Auth::user(), $this->reason ?: null), 'This request was cancelled.');
    }

    public function pay(WlCustomOrders $orders): void
    {
        $this->run(fn () => $orders->payFromWallet($this->order(), Auth::user()), 'Paid from your wallet. We are starting your custom build.');
    }

    public function send(WlCustomOrders $orders): void
    {
        $this->run(function () use ($orders) {
            $orders->post($this->order(), Auth::user(), $this->message, false);
            $this->message = '';
        }, 'Message sent.');
    }

    public function render(WalletService $wallet)
    {
        $o = $this->order();
        $total = (float) ($o->quote_total_usd ?? 0);

        return view('livewire.wl-custom-order-view', [
            'o' => $o, 'messages' => $o->messages()->with('author')->get(), 'moduleOptions' => WlCustomOrders::MODULES,
            'spendable' => $total > 0 ? $wallet->spendableUsd(Auth::user()) : 0.0, 'expired' => $o->quoteExpired(),
        ]);
    }
}
