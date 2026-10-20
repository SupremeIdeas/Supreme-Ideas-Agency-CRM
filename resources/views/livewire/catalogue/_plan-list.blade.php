{{-- A paginated list of plan ROWS: one column on mobile, two on wide screens (a tidy list, not a busy grid). Expects: $plans, $fmt. --}}
<div wire:loading.flex wire:target="search,gotoPage,nextPage,previousPage,setView,openCountry,openRegion,openGlobal,setTab" class="ns-cards-grid ns-cards-grid--rows" style="display:none" aria-hidden="true">
    @for ($i = 0; $i < 4; $i++)<div class="ns-skel" style="height:64px;border-radius:calc(var(--nx-r-row) * var(--nx-rs))"></div>@endfor
</div>

<div wire:loading.remove wire:target="search,gotoPage,nextPage,previousPage,setView,openCountry,openRegion,openGlobal,setTab" class="ns-cards-grid ns-cards-grid--rows">
    @forelse ($plans as $plan)
        @include('livewire.catalogue._plan-row', ['plan' => $plan, 'fmt' => $fmt])
    @empty
        <x-nx.empty style="grid-column:1/-1" text="No plans here yet. Try another tab or search." />
    @endforelse
</div>

@if ($plans->hasPages())
    <div style="margin-top:22px">{{ $plans->links() }}</div>
@endif
