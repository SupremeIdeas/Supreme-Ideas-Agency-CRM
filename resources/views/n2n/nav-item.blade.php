{{-- One tab of the N2N bottom bar. The active tab takes the skin's own primary-button look (nx-tab.is-on + ns-cta). --}}
@php($on = \App\Support\N2N\N2NNav::active($item))
<a href="{{ route($item['route']) }}" wire:navigate @if ($on) aria-current="page" @endif class="nx-tab nn__tab relative {{ $on ? 'is-on ns-cta' : '' }}">
    <span class="relative"><x-nx.icon :name="$item['icon']" />@if ($item['badge'] > 0)<b class="nn__badge nn__badge--dot">{{ $item['badge'] > 9 ? '9+' : $item['badge'] }}</b>@endif</span>
    <span>{{ $item['label'] }}</span>
</a>
