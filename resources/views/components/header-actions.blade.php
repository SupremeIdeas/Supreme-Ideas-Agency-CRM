{{-- The dashboard header action row (owner request, 2026-10-11): up to three shortcuts the member picked in Account > Header (language, dark/light, NaaraBrain,
     Naara chat, Business Suite), the merchant panel button for approved merchants on a phone, then the notification bell and the menu button which are always
     there. Kept compact so it fits a 360 px phone. One component for both header slots; `variant` keeps each Livewire bell instance's id unique. --}}
@props(['variant' => 'mobile'])
@php
    $hu = auth()->user();
    $hdrChosen = \App\Support\Header\HeaderItems::chosen($hu);
    $hdrMerchant = $variant === 'mobile' && \App\Support\Header\HeaderItems::showMerchantButton($hu);
@endphp
<div class="nx-hdr" data-header-actions="{{ $variant }}">
    @foreach ($hdrChosen as $hdrKey)
        @switch($hdrKey)
            @case('language')
                <x-lang-switcher class="nx-hdr__lang" />
                @break
            @case('theme')
                <x-theme-toggle />
                @break
            @case('brain')
                <button type="button" x-data @click="$dispatch('open-brain-modal')" aria-haspopup="dialog" aria-label="NaaraBrain" title="NaaraBrain" class="nx-hdr__btn nx-hdr__brain">
                    <img src="{{ \App\Support\Brain\BrainSettings::logo('mark', true) }}" alt="" width="22" height="22" decoding="async" draggable="false">
                </button>
                @break
            @case('chat')
                <a href="{{ route('n2n.chat') }}" wire:navigate aria-label="Naara chat" title="Naara chat" class="nx-hdr__btn"><x-icon name="message-circle" class="h-5 w-5" /></a>
                @break
            @case('business')
                <a href="{{ route(\App\Support\Business\BusinessNav::entryRoute(auth()->user())) }}" wire:navigate aria-label="Business Suite" title="Business Suite" class="nx-hdr__btn"><x-icon name="briefcase" class="h-5 w-5" /></a>
                @break
        @endswitch
    @endforeach

    @if ($hdrMerchant)
        <button type="button" x-data @click="$dispatch('open-merchant-pane')" aria-haspopup="dialog" aria-label="{{ __('header.merchant_panel') }}" title="{{ __('header.merchant_panel') }}" class="nx-hdr__btn nx-hdr__merchant">
            <x-icon name="package" class="h-5 w-5" />
        </button>
    @endif

    <livewire:notification-center :key="'nc-'.$variant" />
    <x-global-sidebar />
</div>
