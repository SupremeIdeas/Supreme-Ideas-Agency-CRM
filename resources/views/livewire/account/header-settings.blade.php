{{-- Account -> Header. Skin-aware: ns-* hooks and tokens only. Pick up to three shortcuts for the dashboard header. --}}
<div>
<x-nx.page class="ns-sounds">
    <a href="{{ route('account') }}" wire:navigate class="ns-back"><x-nx.icon name="chevron-left" />{{ __('header.back') }}</a>
    <h1 class="ns-h1" style="margin-top:6px">{{ __('header.title') }}</h1>
    <p class="ns-sub">{{ __('header.subtitle', ['max' => $max]) }}</p>

    <x-nx.note icon="info">{{ __('header.always_there') }}</x-nx.note>

    <div class="ns-section">
        <span class="ns-lbl">{{ __('header.shortcuts') }} <b>{{ count($picked) }} / {{ $max }}</b></span>
        @if ($notice)
            <x-nx.note icon="info" style="margin-top:8px">{{ $notice }}</x-nx.note>
        @endif
        <div class="ns-list" style="margin-top:10px">
            @foreach ($available as $key => $item)
                @php($on = in_array($key, $picked, true))
                @php($full = ! $on && count($picked) >= $max)
                <label class="ns-list__row ns-sounds__switch" wire:key="hdr-{{ $key }}" @style(['opacity:.55' => $full])>
                    <span class="ns-tile"><x-nx.icon :name="$item['icon']" /></span>
                    <span>{{ $item['label'] }}<small class="ns-small">{{ $item['hint'] }}</small></span>
                    <input type="checkbox" class="ns-sounds__check" aria-label="{{ $item['label'] }}" @checked($on) @disabled($full) wire:click="toggle('{{ $key }}')">
                    <i class="ns-sounds__track" aria-hidden="true"><b></b></i>
                </label>
            @endforeach
        </div>
        <button type="button" class="ns-btn" style="margin-top:12px" wire:click="resetToDefault" wire:loading.attr="disabled" wire:target="resetToDefault">{{ __('header.reset') }}</button>
    </div>

    @if ($isMerchant)
        <div class="ns-section">
            <span class="ns-lbl">{{ __('header.merchant_section') }}</span>
            <div class="ns-list" style="margin-top:10px">
                <label class="ns-list__row ns-sounds__switch">
                    <span class="ns-tile"><x-nx.icon name="package" /></span>
                    <span>{{ __('header.merchant_panel') }}<small class="ns-small">{{ __('header.merchant_hint') }}</small></span>
                    <input type="checkbox" wire:model.live="merchantButton" class="ns-sounds__check" aria-label="{{ __('header.merchant_panel') }}">
                    <i class="ns-sounds__track" aria-hidden="true"><b></b></i>
                </label>
            </div>
        </div>
    @endif
</x-nx.page>
</div>
