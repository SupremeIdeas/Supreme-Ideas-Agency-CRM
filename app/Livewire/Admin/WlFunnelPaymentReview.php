<?php

namespace App\Livewire\Admin;

use App\Models\WlFunnelManualPayment as P;
use App\Services\WhiteLabel\Funnel\WlFunnelManualPayments as Svc;
use App\Services\WhiteLabel\Payments\ManualPaymentException;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/** Admin > White Label > Payment review (Funnel Phase 5.5, MASTER-ONLY): proof next to what was expected, automatic checks, and Approve / Underpaid / Reject. Only an approval issues a licence. */
#[Layout('components.layouts.admin')]
class WlFunnelPaymentReview extends Component
{
    public ?int $open = null;

    public string $received = '';

    public string $reason = '';

    public ?string $error = null;

    public function mount(): void
    {
        abort_unless(Auth::user()->hasAnyRole(['super_admin', 'admin']), 404);
    }

    public function view(int $id): void
    {
        $this->reset('error', 'reason');
        $p = P::query()->findOrFail($id);
        $this->open = $p->id;
        $this->received = (string) $p->expected_amount;
    }

    public function close(): void
    {
        $this->reset('open', 'error', 'reason', 'received');
    }

    private function act(callable $fn, string $ok): void
    {
        $this->error = null;
        try {
            $fn(P::query()->findOrFail($this->open));
            $this->dispatch('nx-toast', type: 'success', message: $ok);
            $this->close();
        } catch (ManualPaymentException $e) {
            $this->error = $e->getMessage();
        }
    }

    public function approve(Svc $svc): void
    {
        $this->validate(['received' => ['required', 'numeric', 'min:0.01']]);
        $this->act(fn (P $p) => $svc->approve($p, Auth::user(), (float) $this->received), 'Approved. The licence has been issued.');
    }

    public function underpaid(Svc $svc): void
    {
        $this->validate(['received' => ['required', 'numeric', 'min:0']]);
        $this->act(fn (P $p) => $svc->underpaid($p, Auth::user(), (float) $this->received), 'Marked underpaid. The buyer was told the shortfall.');
    }

    public function reject(Svc $svc): void
    {
        $this->act(fn (P $p) => $svc->reject($p, Auth::user(), $this->reason), 'Rejected. The buyer can send a new proof.');
    }

    public function render()
    {
        $detail = $this->open ? P::query()->with(['instance.user', 'account'])->find($this->open) : null;
        $checks = [];
        if ($detail) {
            $recv = (float) $this->received;
            $checks = [
                ['label' => 'Amount within tolerance', 'ok' => $recv + 0.00001 >= (float) $detail->expected_amount * Svc::TOLERANCE],
                ['label' => 'Quote still locked when proof was sent', 'ok' => ! in_array('quote_expired_when_submitted', (array) $detail->flags, true)],
                ['label' => 'Proof image not used on another payment', 'ok' => ! in_array('duplicate_image', (array) $detail->flags, true)],
                ['label' => 'Reference code on file', 'ok' => $detail->reference_code !== ''],
            ];
        }

        return view('livewire.admin.wl-funnel-payment-review', [
            'queue' => P::query()->with('instance')->whereIn('status', [P::REVIEW, P::UNDERPAID])->orderBy('updated_at')->limit(100)->get(),
            'recent' => P::query()->with('instance')->whereIn('status', [P::APPROVED])->latest('id')->limit(10)->get(),
            'detail' => $detail, 'checks' => $checks,
        ]);
    }
}
