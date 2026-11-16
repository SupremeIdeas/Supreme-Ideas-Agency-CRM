{{-- nx:converted (skin tokens; legacy palette classes removed 2026-10-05) --}}
{{-- Slim connectivity summary (Theme Batch 2 §2). Only for an account that already has lines. --}}
{{-- DISCONNECTED (CRM Phase 1 fork-and-strip): numbers.lines is unrouted on
     this fork, so guard on it existing rather than just $hasAny. --}}
@if ($hasAny && Illuminate\Support\Facades\Route::has('numbers.lines'))
    <a href="{{ route('numbers.lines') }}" wire:navigate class="ns-card ns-ring ns-full" style="margin-top:20px;padding:16px;text-decoration:none">
        <span class="ns-card__row" style="justify-content:space-between">
            <b style="display:flex;align-items:center;gap:8px;font-size:15px"><x-nx.icon name="bars" style="color:rgb(var(--nx-teal-ink))" /> My Lines</b>
            <span  class="ns-linkink" style="display:inline-flex;align-items:center;gap:4px;font-size:14px;font-weight:600">Manage all <x-nx.icon name="chevron-right" /></span>
        </span>
        <span class="ns-stats" style="grid-template-columns:repeat(3,minmax(0,1fr));margin-top:12px">
            <span class="ns-stat-cell"><b>{{ $esimActiveCount }}</b><small>Active eSIMs</small></span>
            <span class="ns-stat-cell"><b>{{ $numberActiveCount }}</b><small>Active numbers</small></span>
            <span class="ns-stat-cell ns-stat-cell--muted"><b>{{ $archivedCount }}</b><small>Archived</small></span>
        </span>
    </a>
@endif
