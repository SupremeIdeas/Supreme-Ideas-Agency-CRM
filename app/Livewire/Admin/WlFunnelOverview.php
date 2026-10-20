<?php

namespace App\Livewire\Admin;

use App\Models\WhiteLabelInstance;
use App\Models\WlFunnelInstance;
use App\Models\WlFunnelManualPayment;
use App\Services\WhiteLabel\Funnel\WlFunnel;
use App\Services\WhiteLabel\Funnel\WlFunnelStates;
use App\Support\Auditor;
use App\Support\Notifications\Notify;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Admin > White Label > Funnel (Funnel Phase 6.2 and 6.3, MASTER-ONLY): every funnel purchase with its status, payments, timeline and setup details. Secrets (licence key, own-server login) are never in the
 * page until an admin re-enters their password and gives a reason; each reveal is audited and shown for this request only.
 */
#[Layout('components.layouts.admin')]
class WlFunnelOverview extends Component
{
    use WithPagination;

    public const SEGMENTS = ['all' => 'All', 'review' => 'Needs review', 'owing' => 'Owing', 'overdue' => 'Overdue', 'needs_details' => 'Needs setup details', 'setup' => 'In setup', 'live' => 'Live', 'drafts' => 'Not paid yet', 'abandoned' => 'Lost'];

    public const SORTS = ['activity' => 'Recently active', 'newest' => 'Newest first', 'oldest' => 'Oldest first', 'owed' => 'Most owed', 'due' => 'Due soonest'];

    #[Url(as: 'seg', except: 'all')]
    public string $filter = 'all';

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(as: 'sort', except: 'activity')]
    public string $sort = 'activity';

    #[Url(as: 'plan', except: '')]
    public string $planFilter = '';

    public string $noteBody = '';

    public ?int $open = null;

    public string $password = '';

    public string $reason = '';

    public ?string $revealed = null;

    public ?string $revealedLabel = null;

    public ?string $error = null;

    public ?int $voiding = null;

    public string $voidReason = '';

    public function mount(): void
    {
        abort_unless(Auth::user()->hasAnyRole(['super_admin', 'admin']), 404);
    }

    public function view(int $id): void
    {
        $this->reset('error', 'revealed', 'revealedLabel', 'password', 'reason');
        $this->open = WlFunnelInstance::query()->findOrFail($id)->id;
    }

    public function close(): void
    {
        $this->reset('open', 'error', 'revealed', 'revealedLabel', 'password', 'reason');
    }

    /** Reveal one secret for this request only: needs the admin's password and a reason, and writes an audit entry. */
    public function reveal(string $what): void
    {
        $this->error = $this->revealed = $this->revealedLabel = null;
        $admin = Auth::user();
        if (! Hash::check($this->password, (string) $admin->password)) {
            $this->error = 'Your password is not correct.';
            $this->password = '';

            return;
        }
        if (trim($this->reason) === '') {
            $this->error = 'Say why you need to see this.';

            return;
        }
        $license = $this->license(WlFunnelInstance::query()->findOrFail($this->open));
        $intake = $license?->intake;
        $value = match ($what) {
            'license_key' => $license?->license_key,
            'server' => $intake && $intake->hosting_host ? "Host: {$intake->hosting_host}\nUser: {$intake->hosting_username}\nPassword: {$intake->hosting_password}\nNotes: {$intake->hosting_notes}" : null,
            default => null,
        };
        if ($value === null || $value === '') {
            $this->error = 'There is nothing stored to reveal.';

            return;
        }
        Auditor::log('wl.funnel_secret_revealed', WlFunnelInstance::class, $this->open, ['what' => $what, 'reason' => mb_substr(trim($this->reason), 0, 200)]);
        $this->revealed = $value;
        $this->revealedLabel = $what === 'license_key' ? 'Licence key' : 'Server details';
        $this->reset('password', 'reason');
    }

    public function requestSetupDetails(): void
    {
        $inst = WlFunnelInstance::query()->with('user')->findOrFail($this->open);
        if ($inst->user) {
            Notify::event($inst->user, 'wl.setup_details_requested', ['brand' => (string) (((array) $inst->draft)['brand_name'] ?? ''), 'reference' => $inst->public_code], 'wl-setup-req-'.$inst->id.'-'.now()->format('Ymd'));
        }
        Auditor::log('wl.funnel_setup_details_requested', WlFunnelInstance::class, $inst->id, []);
        $this->dispatch('nx-toast', type: 'success', message: 'The buyer was asked for their setup details.');
    }

    public function markComplete(WlFunnel $funnel): void
    {
        $inst = WlFunnelInstance::query()->with('user')->findOrFail($this->open);
        if (! WlFunnelStates::can($inst->status, WlFunnelStates::LIVE)) {
            $this->error = 'Only a purchase in setup can be marked complete.';

            return;
        }
        $funnel->transition($inst, WlFunnelStates::LIVE, 'admin', ['by' => Auth::id()]);
        $intake = $this->license($inst)?->intake;
        $intake?->forceFill(['deploy_completed_at' => $intake->deploy_completed_at ?? now(), 'status' => 'completed'])->save();
        if ($inst->user) {
            Notify::event($inst->user, 'wl.setup_complete', ['brand' => (string) (((array) $inst->draft)['brand_name'] ?? ''), 'reference' => $inst->public_code], 'wl-setup-done-'.$inst->id);
        }
        Auditor::log('wl.funnel_setup_complete', WlFunnelInstance::class, $inst->id, []);
        $this->dispatch('nx-toast', type: 'success', message: 'Marked complete. The buyer was told.');
    }

    /** Cancel an invoice by issuing a credit note. Money and the licence are untouched: refunds stay a deliberate action. */
    public function voidInvoice(\App\Services\WhiteLabel\Funnel\WlInvoiceVoider $voider): void
    {
        $this->error = null;
        try {
            $inv = \App\Models\WlInvoice::query()->where('funnel_instance_id', $this->open)->findOrFail($this->voiding);
            $cn = $voider->creditNote($inv, $this->voidReason, Auth::id());
        } catch (\InvalidArgumentException $e) {
            $this->error = $e->getMessage();

            return;
        }
        $this->reset('voiding', 'voidReason');
        $this->dispatch('nx-toast', type: 'success', message: 'Credit note '.$cn->number.' issued. No money was moved.');
    }

    private function license(WlFunnelInstance $inst): ?WhiteLabelInstance
    {
        $id = WlFunnelManualPayment::query()->where('funnel_instance_id', $inst->id)->whereNotNull('white_label_instance_id')->latest('id')->value('white_label_instance_id');

        return $id ? WhiteLabelInstance::query()->with('intake')->find($id) : null;
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function setFilter(string $key): void
    {
        $this->filter = array_key_exists($key, self::SEGMENTS) ? $key : 'all';
        $this->resetPage();
    }

    public function updatedSort(): void
    {
        $this->resetPage();
    }

    public function updatedPlanFilter(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset('search', 'filter', 'sort', 'planFilter');
        $this->resetPage();
    }

    /** A private note on this purchase, for the team only. */
    public function addNote(): void
    {
        $body = trim(strip_tags($this->noteBody));
        if ($this->open === null || $body === '') {
            return;
        }
        WlFunnelInstance::query()->findOrFail($this->open)->notes()->create(['admin_id' => Auth::id(), 'body' => mb_substr($body, 0, 500)]);
        Auditor::log('wl.funnel_note_added', WlFunnelInstance::class, $this->open, []);
        $this->reset('noteBody');
    }

    /** Send the buyer a fresh link to an invoice or receipt (the old public link stops working). */
    public function resendDocument(int $docId, \App\Services\WhiteLabel\Funnel\WlInvoiceIssuer $issuer): void
    {
        $doc = \App\Models\WlInvoice::query()->where('funnel_instance_id', $this->open)->with('user')->findOrFail($docId);
        if ($doc->doc_type === \App\Models\WlInvoice::CREDIT_NOTE || ! $doc->user) {
            return;
        }
        $token = $issuer->newPublicToken($doc);
        Notify::event($doc->user, $doc->doc_type === \App\Models\WlInvoice::RECEIPT ? 'wl.receipt_issued' : 'wl.invoice_issued', ['number' => $doc->number, 'url' => route('wl-invoice.show', $token), 'due' => $doc->due_at?->format('Y-m-d') ?? '', 'balance' => number_format((float) $doc->balance_due, 2)], 'wl-resend-'.$doc->id.'-'.now()->format('YmdHi'));
        Auditor::log('wl.invoice_resent', \App\Models\WlInvoice::class, $doc->id, ['by' => Auth::id()]);
        $this->dispatch('nx-toast', type: 'success', message: 'A fresh link to '.$doc->number.' was sent to the buyer.');
    }

    /** The list query with its money columns, so a row never needs a second query. */
    private function base()
    {
        $paidStates = [WlFunnelStates::PAID, WlFunnelStates::LICENSE_ISSUED, WlFunnelStates::SETUP, WlFunnelStates::LIVE];
        $q = WlFunnelInstance::query()->with('user')
            ->withSum(['invoices as paid_usd' => fn ($i) => $i->where('doc_type', 'invoice')->where('status', 'paid')], 'total')
            ->withSum(['invoices as owed_usd' => fn ($i) => $i->where('doc_type', 'invoice')->where('status', 'issued')->where('balance_due', '>', 0)], 'balance_due')
            ->withMin(['invoices as next_due_at' => fn ($i) => $i->where('doc_type', 'invoice')->where('status', 'issued')->where('balance_due', '>', 0)], 'due_at')
            ->withCount(['payments as review_count' => fn ($p) => $p->where('status', 'under_review')]);

        $term = trim($this->search);
        if ($term !== '') {
            $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $term).'%';
            $q->where(fn ($w) => $w->where('public_code', 'like', $like)->orWhere('draft', 'like', $like)
                ->orWhereHas('user', fn ($u) => $u->where('email', 'like', $like)->orWhere('name', 'like', $like)));
        }
        if ($this->planFilter !== '') {
            $q->where(fn ($w) => $w->where('selected_plan_key', $this->planFilter)->orWhere(fn ($x) => $x->whereNull('selected_plan_key')->where('plan_key_at_landing', $this->planFilter)));
        }
        $this->applySegment($q, $this->filter, $paidStates);

        match ($this->sort) {
            'newest' => $q->orderByDesc('id'),
            'oldest' => $q->orderBy('id'),
            'owed' => $q->orderByDesc('owed_usd')->orderByDesc('id'),
            'due' => $q->orderByRaw('next_due_at IS NULL')->orderBy('next_due_at')->orderByDesc('id'),
            default => $q->orderByDesc('updated_at')->orderByDesc('id'),
        };

        return $q;
    }

    private function applySegment($q, string $seg, array $paidStates): void
    {
        $unpaid = fn ($i) => $i->where('doc_type', 'invoice')->where('status', 'issued')->where('balance_due', '>', 0);
        match ($seg) {
            'review' => $q->where(fn ($w) => $w->where('status', WlFunnelStates::PAYMENT_REVIEW)->orWhereHas('payments', fn ($p) => $p->where('status', 'under_review'))),
            'owing' => $q->whereHas('invoices', $unpaid),
            'overdue' => $q->whereHas('invoices', fn ($i) => $unpaid($i)->whereNotNull('due_at')->where('due_at', '<', now())),
            'needs_details' => $q->where('status', WlFunnelStates::LICENSE_ISSUED),
            'setup' => $q->where('status', WlFunnelStates::SETUP),
            'live' => $q->where('status', WlFunnelStates::LIVE),
            'drafts' => $q->whereNotIn('status', array_merge($paidStates, [WlFunnelStates::ABANDONED, WlFunnelStates::EXPIRED, WlFunnelStates::REJECTED, WlFunnelStates::REFUNDED])),
            'abandoned' => $q->whereIn('status', [WlFunnelStates::ABANDONED, WlFunnelStates::EXPIRED, WlFunnelStates::REJECTED]),
            default => null,
        };
    }

    /** @return array<string,int> a count for every segment chip (same rules as the list) */
    private function segmentCounts(): array
    {
        $paidStates = [WlFunnelStates::PAID, WlFunnelStates::LICENSE_ISSUED, WlFunnelStates::SETUP, WlFunnelStates::LIVE];
        $out = [];
        foreach (array_keys(self::SEGMENTS) as $seg) {
            $q = WlFunnelInstance::query();
            $this->applySegment($q, $seg, $paidStates);
            $out[$seg] = $q->count();
        }

        return $out;
    }

    public function render()
    {
        $detail = $this->open ? WlFunnelInstance::query()->with(['user', 'events'])->find($this->open) : null;
        $license = $detail ? $this->license($detail) : null;

        return view('livewire.admin.wl-funnel-overview', [
            'stats' => \App\Services\WhiteLabel\Funnel\WlFunnelStats::summary(), 'rows' => $this->base()->paginate(20), 'counts' => $this->segmentCounts(),
            'plans' => \App\Models\WhiteLabelLicensePlan::query()->orderBy('sort_order')->pluck('name', 'key'),
            'detail' => $detail, 'license' => $license, 'intake' => $license?->intake,
            'payments' => $detail ? WlFunnelManualPayment::query()->where('funnel_instance_id', $detail->id)->latest('id')->get() : collect(),
            'docs' => $detail ? \App\Models\WlInvoice::query()->where('funnel_instance_id', $detail->id)->orderBy('id')->get() : collect(),
            'notes' => $detail ? $detail->notes()->with('admin')->latest('id')->limit(30)->get() : collect(),
        ]);
    }
}
