{{-- One slide's headline + call to action in the eSIM hero (own copy per slide). Photo slides: "Check compatibility". The master-only handbook slide: a real link to the store. --}}
@php
    $h1 = ['sm' => 'text-base', 'md' => 'text-base sm:text-2xl', 'lg' => 'text-3xl'][$size] ?? 'text-base';
    $p = ['sm' => 'text-xs', 'md' => 'text-xs sm:text-sm', 'lg' => 'text-base'][$size] ?? 'text-xs';
    $btn = ['sm' => 'px-4 py-2 text-xs', 'md' => 'px-4 py-2 text-xs sm:text-sm', 'lg' => 'px-4 py-2 text-sm'][$size] ?? 'px-4 py-2 text-xs';
@endphp
@if ($s['kind'] === 'handbook' && $hb)
    <div class="min-w-0">
        <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-[#F2C94C] sm:text-[11px]">{{ $hb['eyebrow'] }}</p>
        <h2 class="mt-0.5 line-clamp-2 {{ $h1 }} font-bold leading-tight text-white">{{ $hb['title'] }}</h2>
        <p class="mt-1 line-clamp-1 {{ $p }} text-white/80">{{ $hb['description'] }}</p>
    </div>
    <a href="{{ $hb['href'] }}" wire:navigate data-esim-hero-handbook-cta aria-label="{{ $hb['cta'] }}"
       class="pointer-events-auto inline-flex shrink-0 items-center gap-1.5 rounded-full bg-white {{ $btn }} font-semibold text-[#0D1B2A] shadow transition hover:bg-[#F2C94C]">
        {{ $hb['cta'] }} <x-icon name="arrow-right" class="h-4 w-4" />
    </a>
@else
    <div class="min-w-0">
        <h1 class="line-clamp-2 {{ $h1 }} font-bold leading-tight text-white">{{ $hero['title'] }}</h1>
        <p class="mt-1 line-clamp-1 {{ $p }} text-white/80">{{ $hero['description'] }}</p>
    </div>
    <button type="button" @click="$dispatch('open-compatibility')"
            class="pointer-events-auto inline-flex shrink-0 items-center gap-1.5 rounded-full bg-white/95 {{ $btn }} font-semibold text-slate-900 shadow transition hover:bg-white">
        <x-icon name="signal" class="h-4 w-4 text-primary" /> Check compatibility
    </button>
@endif
