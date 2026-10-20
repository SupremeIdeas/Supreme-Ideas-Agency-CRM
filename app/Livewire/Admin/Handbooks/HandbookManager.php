<?php

namespace App\Livewire\Admin\Handbooks;

use App\Models\Handbook;
use App\Models\HandbookAudioEntitlement;
use App\Models\HandbookCategory;
use App\Models\HandbookEntitlement;
use App\Models\HandbookSeries;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;

/** Admin > Handbooks (MASTER-ONLY): the catalogue list with filters, status, price, last-verified date and per-title sales read from the entitlement ledger. */
#[Layout('components.layouts.admin')]
class HandbookManager extends Component
{
    public string $q = '';

    public string $country = '';

    public string $status = '';

    public string $series = '';

    public string $category = '';

    public function mount(): void
    {
        abort_unless(Auth::user()->hasAnyRole(['super_admin', 'admin']), 404);
    }

    public ?string $flash = null;

    /** Flip one of a title's switches from the list. */
    public function toggle(int $id, string $field): void
    {
        abort_unless(Auth::user()->hasAnyRole(['super_admin', 'admin']), 403);
        abort_unless(in_array($field, ['is_featured', 'unlock_via_esim', 'unlock_via_pro', 'allow_purchase'], true), 422);
        $b = Handbook::query()->findOrFail($id);
        $b->forceFill([$field => ! $b->{$field}])->save();
        \App\Support\Auditor::log('handbook.switch', Handbook::class, $b->id, ['field' => $field, 'value' => $b->{$field}, 'by' => Auth::id()]);
    }

    /** Publish (the checklist still applies) or take a live title out of the store. */
    public function togglePublish(int $id, \App\Services\Handbooks\HandbookCatalogueService $svc): void
    {
        abort_unless(Auth::user()->hasAnyRole(['super_admin', 'admin']), 403);
        $b = Handbook::query()->findOrFail($id);
        if ($b->status === Handbook::PUBLISHED) {
            $svc->archive($b, Auth::user());
            $this->flash = '"'.$b->title.'" was taken out of the store. Owners keep access.';

            return;
        }
        $r = $svc->publish($b, Auth::user());
        $this->flash = $r['ok'] ? '"'.$b->title.'" is live in the store.' : '"'.$b->title.'" cannot go live yet: '.implode(' ', $r['problems']);
    }

    public function render()
    {
        $q = Handbook::query()->with(['series', 'categories']);
        if (trim($this->q) !== '') {
            $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], trim($this->q)).'%';
            $q->where(fn ($w) => $w->where('title', 'like', $like)->orWhere('catalogue_id', 'like', $like));
        }
        if ($this->country !== '') {
            $q->where('country_code', strtoupper($this->country));
        }
        if (array_key_exists($this->status, Handbook::STATUSES)) {
            $q->where('status', $this->status);
        }
        if ($this->series !== '') {
            $q->where('series_id', (int) $this->series);
        }
        if ($this->category !== '') {
            $q->whereHas('categories', fn ($c) => $c->whereKey((int) $this->category));
        }
        $books = $q->orderBy('country_code')->orderBy('series_slot')->orderBy('title')->limit(300)->get();
        // Sales are read from the ledger rows themselves (price paid on each entitlement), never from a second counter.
        $sales = HandbookEntitlement::query()->whereIn('handbook_id', $books->pluck('id'))->selectRaw("handbook_id, sum(case when source = 'purchase' then 1 else 0 end) purchases, sum(case when source = 'upgrade' then 1 else 0 end) upgrades, sum(case when source in ('esim_order','pro_slot') then 1 else 0 end) unlocks, sum(price_paid_usd) revenue")->groupBy('handbook_id')->get()->keyBy('handbook_id');
        $totals = HandbookEntitlement::query()->selectRaw("sum(case when source = 'purchase' then 1 else 0 end) purchases, sum(case when source = 'upgrade' then 1 else 0 end) upgrades, sum(case when source in ('esim_order','pro_slot') then 1 else 0 end) unlocks, coalesce(sum(price_paid_usd),0) revenue")->first();
        $audioRevenue = (float) HandbookAudioEntitlement::query()->sum('price_paid_usd');

        return view('livewire.admin.handbooks.manager', [
            'books' => $books, 'sales' => $sales, 'totals' => $totals, 'audioRevenue' => $audioRevenue, 'pendingRefunds' => \App\Models\HandbookRefundRequest::query()->where('status', 'pending')->count(),
            'countries' => Handbook::query()->whereNotNull('country_code')->distinct()->orderBy('country_code')->pluck('country_code'),
            'seriesList' => HandbookSeries::query()->orderBy('name')->get(), 'categories' => HandbookCategory::query()->orderBy('name')->get(),
        ]);
    }
}
