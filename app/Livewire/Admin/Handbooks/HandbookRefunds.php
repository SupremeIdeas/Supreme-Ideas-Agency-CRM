<?php

namespace App\Livewire\Admin\Handbooks;

use App\Models\HandbookRefundRequest;
use App\Services\Handbooks\HandbookAnomalies;
use App\Services\Handbooks\HandbookException;
use App\Services\Handbooks\HandbookRefundService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/** Admin > Handbooks > Refund requests and download watch (MASTER-ONLY): a person approves or declines each request; approving moves the money. */
#[Layout('components.layouts.admin')]
class HandbookRefunds extends Component
{
    /** @var array<int, string> */
    public array $notes = [];

    public ?string $flash = null;

    public ?string $error = null;

    public function mount(): void
    {
        abort_unless(Auth::user()->hasAnyRole(['super_admin', 'admin']), 404);
    }

    public function approve(int $id, HandbookRefundService $svc): void
    {
        $this->decide($id, fn ($r) => $svc->approve($r, Auth::user(), $this->notes[$id] ?? null), 'Refund approved and sent to the wallet.');
    }

    public function decline(int $id, HandbookRefundService $svc): void
    {
        $this->decide($id, fn ($r) => $svc->decline($r, Auth::user(), $this->notes[$id] ?? null), 'Request declined.');
    }

    private function decide(int $id, callable $do, string $ok): void
    {
        $this->reset('flash', 'error');
        abort_unless(Auth::user()->hasAnyRole(['super_admin', 'admin']), 404);
        try {
            $do(HandbookRefundRequest::query()->findOrFail($id));
            $this->flash = $ok;
        } catch (HandbookException $e) {
            $this->error = $e->getMessage();
        }
    }

    public function render(HandbookAnomalies $watch)
    {
        return view('livewire.admin.handbooks.refunds', [
            'pending' => HandbookRefundRequest::query()->where('status', HandbookRefundRequest::PENDING)->with(['user:id,name,email', 'handbook:id,title,catalogue_id'])->oldest()->get(),
            'decided' => HandbookRefundRequest::query()->where('status', '!=', HandbookRefundRequest::PENDING)->with(['user:id,email', 'handbook:id,title'])->latest('decided_at')->limit(20)->get(),
            'flags' => $watch->scan(),
        ]);
    }
}
