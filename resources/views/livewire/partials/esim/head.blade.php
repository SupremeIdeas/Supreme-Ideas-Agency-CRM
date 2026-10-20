{{-- nx:converted (skin tokens; legacy palette classes removed 2026-10-05) --}}
{{-- eSIM storefront CONTROL BLOCK on the skin system (S3 Batch 2). Search + "Browse by country" · the Data Only / Data + Calls line toggle ·
     the Trending / Countries / Regions / Global chip row. Shared by both eSIM theme variants — only its position relative to the hero
     changes per theme (variant-a: hero then this; variant-b: this then hero). All Livewire state ($tab, $view, $search) lives on the
     Catalogue component; this partial only drives it. --}}

{{-- Search — top of the front, with a Browse-by-country action beside it. --}}
<div style="display:flex;gap:10px;align-items:center;margin-bottom:6px">
    <label class="ns-search" style="flex:1;min-width:0;margin-top:0">
        <x-nx.icon name="search" />
        <input type="text" wire:model.live.debounce.400ms="search" placeholder="Where are you travelling to?" aria-label="Where are you travelling to?" autocomplete="off">
        <span wire:loading wire:target="search" style="color:rgb(var(--nx-teal-ink))"><x-ui.spinner class="h-4 w-4" /></span>
    </label>
    <button type="button" class="ns-btn" style="margin:0;height:54px;width:54px;padding:0;justify-content:center;flex:none" wire:click="browseCountries" aria-label="Browse by country">
        <x-nx.icon name="globe" style="font-size:22px" />
    </button>
</div>

{{-- Data Only / Data + Calls — the two hard-separated lines (§3.0). "Naara Connect" (the full line's product name) stays in its own
     coming-soon state below. Batch 8: on a white-label fork whose license locks the full voice line the toggle is hidden entirely — the
     store sells data-only eSIM. Inert on the master (voiceLocked is always false there). --}}
@unless ($this->voiceLocked)
    <div class="ns-seg" style="margin-top:14px" role="group" aria-label="eSIM line">
        <button type="button" wire:click="setTab('data')" class="{{ $tab === 'data' ? 'is-on' : '' }}" aria-pressed="{{ $tab === 'data' ? 'true' : 'false' }}"><x-nx.icon name="sim" /> Data Only</button>
        <button type="button" wire:click="setTab('full')" class="{{ $tab === 'full' ? 'is-on' : '' }}" aria-pressed="{{ $tab === 'full' ? 'true' : 'false' }}"><x-nx.icon name="phone" /> Data + Calls</button>
    </div>
@endunless

{{-- Category chips: Trending / Countries / Regions / Global. Horizontal-scroll on small screens, active state filled. These are the app's
     REAL navigation modes (no fabricated continent grouping) — Trending is the popular-destinations view, and every country card drills
     into the same tab-scoped country page. --}}
@php($chips = [
    ['popular', 'Trending', 'zap'],
    ['local', 'Countries', 'map-pin'],
    ['regional', 'Regions', 'grid'],
    ['global', 'Global', 'globe'],
])
<div class="ns-chips-filter" style="margin-top:14px" role="group" aria-label="Browse">
    @foreach ($chips as [$key, $label, $icon])
        <button type="button" wire:click="setView('{{ $key }}')" class="{{ $view === $key ? 'is-on' : '' }}" aria-pressed="{{ $view === $key ? 'true' : 'false' }}">
            <x-nx.icon :name="$icon" /> {{ $label }}
        </button>
    @endforeach
</div>
