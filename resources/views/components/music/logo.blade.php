{{-- nx:converted --}}
{{-- A music platform or distributor mark on a clean chip (N2N, MASTER-ONLY). Bundled files only; a missing mark shows the initial, never an invented logo. --}}
@props(['kind' => 'platform', 'slug', 'size' => 'md'])
@php
    $logo = $kind === 'distributor' ? \App\Support\N2N\MusicLogos::distributor($slug) : \App\Support\N2N\MusicLogos::platform($slug);
@endphp
@if ($logo)
    <span {{ $attributes->class(['mlogo', 'mlogo--'.$size, 'mlogo--dark' => $logo['dark'], 'mlogo--wide' => $kind === 'distributor']) }} title="{{ $logo['label'] }}">
        @if ($logo['url'])<img src="{{ $logo['url'] }}" alt="{{ $logo['label'] }}" loading="lazy" decoding="async">@elseif ($kind === 'distributor')<span class="mlogo__t">{{ $logo['label'] }}</span>@else<span class="mlogo__i" aria-label="{{ $logo['label'] }}">{{ $logo['initial'] }}</span>@endif
    </span>
@endif
