{{-- nx:converted (skin tokens; legacy palette classes removed 2026-10-05) --}}
{{-- One country as a horizontal list row: EXPLORE label + name + teaser on the left, the scaled 3D model (or flag fallback) on the right,
     then a chevron. Expects: $t (grid row), $fmt. --}}
@php($teaser = $t['from_usd'] !== null ? $fmt((float) $t['from_usd']) : null)
<button type="button" wire:click="openCountry('{{ $t['code'] }}')" class="ns-row ns-ring ns-place">
    <span class="ns-row__label" style="min-width:0">
        <small class="ns-eyebrow">Explore</small>
        <b style="display:block;font-size:17px;font-weight:600;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ $t['name'] }}</b>
        <small class="ns-small" style="display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">@if ($teaser)from {{ $teaser['usd'] }} · @endif{{ $t['count'] }} {{ \Illuminate\Support\Str::plural('plan', $t['count']) }}</small>
    </span>
    <span class="ns-place__art">
        @if (! empty($t['icon']))
            <img src="{{ $t['icon'] }}" alt="{{ $t['name'] }}" loading="lazy">
        @else
            <x-country-flag :country="$t['code']" class="h-10 w-14 rounded shadow-sm" />
        @endif
    </span>
    <x-nx.icon name="chevron-right" class="ns-chevron" />
</button>
