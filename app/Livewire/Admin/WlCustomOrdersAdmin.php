<?php

namespace App\Livewire\Admin;

use App\Models\WlCustomOrder;
use App\Services\WhiteLabel\Funnel\WlCustomOrderException;
use App\Services\WhiteLabel\Funnel\WlCustomOrders;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/** Admin > White Label > Custom orders (Funnel Phase 8, MASTER-ONLY): the queue, the quote form, rejecting, marking a transfer paid, and the thread with the buyer. */
#[Layout('components.layouts.admin')]
class WlCustomOrdersAdmin extends Component
{
    #[Url(as: 'status')]
    public string $filter = 'open';

    #[Url(as: 'q')]
    public string $search = '';

    #[Url(as: 'order')]
    public ?int $selectedId = null;

    /** @var list<array{label:string, amount:string}> */
    public array $items = [['label' => '', 'amount' => '']];

    public int $validDays = 14;

    public string $note = '';

    public string $rejectReason = '';

    public string $paidReference = '';

    public string $reply = '';

    public ?string $error = null;

    public ?string $notice = null;

    public function mount(): void
    {
        abort_unless(Auth::user()->hasAnyRole(['super_admin', 'admin']), 404);
        if ($this->selectedId) {
            $this->open($this->selectedId);
        }
    }

    public function open(int $id): void
    {
        $o = WlCustomOrder::query()->findOrFail($id);
        $this->selectedId = $o->id;
        $this->reset('error', 'notice', 'rejectReason', 'paidReference', 'reply');
        $this->items = collect((array) $o->quote_items)->map(fn ($i) => ['label' => (string) $i['label'], 'amount' => (string) $i['amount']])->all() ?: [['label' => '', 'amount' => '']];
        $this->validDays = 14;
        $this->note = (string) $o->quote_note;
    }

    public function addLine(): void
    {
        if (count($this->items) < 12) {
            $this->items[] = ['label' => '', 'amount' => ''];
        }
    }

    public function removeLine(int $i): void
    {
        unset($this->items[$i]);
        $this->items = array_values($this->items) ?: [['label' => '', 'amount' => '']];
    }

    private function run(callable $fn, string $ok): void
    {
        $this->reset('error', 'notice');
        try {
            $fn();
            $this->notice = $ok;
        } catch (WlCustomOrderException $e) {
            $this->error = $e->getMessage();
        } catch (ValidationException $e) {
            $this->error = (string) collect($e->errors())->flatten()->first();
        }
    }

    private function selected(): WlCustomOrder
    {
        return WlCustomOrder::query()->findOrFail($this->selectedId);
    }

    public function sendQuote(WlCustomOrders $orders): void
    {
        $this->run(fn () => $orders->quote($this->selected(), Auth::user(), $this->items, $this->note ?: null, $this->validDays), 'Quote sent to the buyer.');
    }

    public function reject(WlCustomOrders $orders): void
    {
        $this->run(function () use ($orders) {
            if (trim($this->rejectReason) === '') {
                throw new WlCustomOrderException('Tell the buyer why, in a sentence.');
            }
            $orders->reject($this->selected(), $this->rejectReason);
        }, 'Request declined and the buyer told.');
    }

    public function markPaid(WlCustomOrders $orders): void
    {
        $this->run(fn () => $orders->markPaidOffline($this->selected(), Auth::user(), $this->paidReference), 'Marked paid and recorded as platform revenue.');
    }

    public function send(WlCustomOrders $orders): void
    {
        $this->run(function () use ($orders) {
            $orders->post($this->selected(), Auth::user(), $this->reply, true);
            $this->reply = '';
        }, 'Reply sent.');
    }

    public function render()
    {
        $q = WlCustomOrder::query()->with('user:id,name,email')->latest('id');
        $this->filter === 'open' ? $q->whereIn('status', WlCustomOrder::OPEN) : ($this->filter === 'all' ? null : $q->where('status', $this->filter));
        if (trim($this->search) !== '') {
            $s = '%'.trim($this->search).'%';
            $q->where(fn ($w) => $w->where('reference', 'like', $s)->orWhere('brand_name', 'like', $s)->orWhere('contact_email', 'like', $s));
        }
        $sel = $this->selectedId ? WlCustomOrder::query()->with(['user:id,name,email', 'messages.author:id,name'])->find($this->selectedId) : null;

        return view('livewire.admin.wl-custom-orders-admin', ['orders' => $q->limit(100)->get(), 'sel' => $sel, 'moduleOptions' => WlCustomOrders::MODULES, 'counts' => WlCustomOrder::query()->selectRaw('status, count(*) c')->groupBy('status')->pluck('c', 'status')]);
    }
}
