{{-- nx:converted (skin tokens; legacy palette classes removed 2026-10-05) --}}
{{-- One region as a horizontal list row: a small square map thumbnail, name + teaser/count, chevron. Expects: $t (grid region row), $fmt. --}}
@php($teaser = $t['from_usd'] !== null ? $fmt((float) $t['from_usd']) : null)
<button type="button" wire:click="openRegion('{{ $t['slug'] }}')" class="ns-row ns-ring ns-place">
    <span class="ns-place__thumb">
        @if (! empty($t['icon']))
            <img src="{{ $t['icon'] }}" alt="{{ $t['label'] }}" loading="lazy">
        @else
            <x-nx.icon name="globe" style="font-size:26px;color:rgb(var(--nx-teal-ink))" />
        @endif
    </span>
    <span class="ns-row__label" style="min-width:0">
        <b style="display:block;font-size:17px;font-weight:600;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ $t['label'] }}</b>
        <small class="ns-small" style="display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">@if ($teaser)from {{ $teaser['usd'] }} · @endif{{ $t['count'] }} {{ \Illuminate\Support\Str::plural('plan', $t['count']) }}</small>
    </span>
    <x-nx.icon name="chevron-right" class="ns-chevron" />
</button>
