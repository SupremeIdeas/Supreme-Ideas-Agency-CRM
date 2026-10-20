{{-- Account -> Sounds (Batch 2). Skin-aware: only ns-* hooks and tokens. Foreground sound + vibration; honest about closed tabs and iPhone. --}}
<div>
<x-nx.page class="ns-sounds" x-data="{ ios: /iPad|iPhone|iPod/.test(navigator.userAgent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1), canVibrate: 'vibrate' in navigator }">
    <a href="{{ route('account') }}" wire:navigate class="ns-back"><x-nx.icon name="chevron-left" />{{ __('sounds.back') }}</a>
    <h1 class="ns-h1" style="margin-top:6px">{{ __('sounds.title') }}</h1>
    <p class="ns-sub">{{ __('sounds.subtitle') }}</p>

    <x-nx.note icon="info">{{ __('sounds.foreground_note') }}</x-nx.note>

    <div class="ns-list" style="margin-top:14px">
        <label class="ns-list__row ns-sounds__switch">
            <span class="ns-tile"><x-nx.icon name="volume" /></span>
            <span>{{ __('sounds.sound_on') }}<small class="ns-small">{{ __('sounds.sound_on_hint') }}</small></span>
            <input type="checkbox" wire:model.live="sound" class="ns-sounds__check" aria-label="{{ __('sounds.sound_on') }}">
            <i class="ns-sounds__track" aria-hidden="true"><b></b></i>
        </label>
        <label class="ns-list__row ns-sounds__switch">
            <span class="ns-tile"><x-nx.icon name="vibrate" /></span>
            <span>{{ __('sounds.vibrate_on') }}<small class="ns-small">{{ __('sounds.vibrate_hint') }}</small></span>
            <input type="checkbox" wire:model.live="vibrate" class="ns-sounds__check" aria-label="{{ __('sounds.vibrate_on') }}">
            <i class="ns-sounds__track" aria-hidden="true"><b></b></i>
        </label>
    </div>
    <p class="ns-sub" style="font-size:13px" x-show="ios" x-cloak>{{ __('sounds.ios_note') }}</p>
    <p class="ns-sub" style="font-size:13px" x-show="!ios && !canVibrate" x-cloak>{{ __('sounds.no_vibrate_note') }}</p>

    @foreach ($categories as $cat => $def)
        <div class="ns-section">
            <span class="ns-lbl">{{ __('sounds.cat.'.$cat) }}</span>
            <p class="ns-sub" style="font-size:13px;margin-top:2px">{{ __('sounds.cat_hint.'.$cat) }}</p>
            @foreach ($def['slots'] as $slot => $slotName)
                @php($key = $cat.'.'.$slot)
                @php($pk = $cat.'__'.$slot)
                @php($options = $library[$key] ?? [])
                @php($default = collect($options)->firstWhere('default', true))
                <span class="ns-group">{{ __('sounds.slot.'.$cat.'.'.$slot) }}</span>
                @if ($options === [])
                    <p class="ns-sub" style="font-size:13px">{{ __('sounds.none_yet') }}</p>
                @else
                    <div class="ns-list ns-sounds__list" role="radiogroup" aria-label="{{ __('sounds.slot.'.$cat.'.'.$slot) }}" wire:key="snd-{{ $key }}">
                        <button type="button" role="radio" aria-checked="{{ ($picks[$pk] ?? '') === '' ? 'true' : 'false' }}" wire:click="$set('picks.{{ $pk }}', '')"
                                @class(['ns-list__row', 'is-on' => ($picks[$pk] ?? '') === ''])>
                            <span class="ns-tile"><x-nx.icon name="star" /></span>
                            <span>{{ __('sounds.platform_default') }}<small class="ns-small">{{ $default['label'] ?? '' }}@if (! empty($default['credit'])) · {{ $default['credit'] }}@endif</small></span>
                            @if ($default)
                                <span class="ns-sounds__play" role="button" tabindex="0" aria-label="{{ __('sounds.play', ['name' => $default['label']]) }}" x-data="{ on: false }" :class="on ? 'is-playing' : ''" :aria-pressed="on ? 'true' : 'false'"
                                      x-on:nx-sound-preview.window="if ($event.detail.url === '{{ route('sounds.tone', $default['id']) }}') on = $event.detail.playing"
                                      x-on:click.stop="window.NaaraSound && window.NaaraSound.preview('{{ route('sounds.tone', $default['id']) }}')"
                                      x-on:keydown.enter.stop.prevent="window.NaaraSound && window.NaaraSound.preview('{{ route('sounds.tone', $default['id']) }}')"><span class="ns-sounds__ico" x-show="!on"><x-nx.icon name="play" /></span><span class="ns-sounds__bars" x-show="on" x-cloak aria-hidden="true"><i></i><i></i><i></i><i></i></span></span>
                            @endif
                            <span class="ns-sounds__tickslot">@if (($picks[$pk] ?? '') === '')<x-nx.icon name="check" class="ns-sounds__tick" />@endif</span>
                        </button>
                        @foreach ($options as $o)
                            <button type="button" role="radio" aria-checked="{{ (string) ($picks[$pk] ?? '') === (string) $o['id'] ? 'true' : 'false' }}" wire:click="$set('picks.{{ $pk }}', '{{ $o['id'] }}')"
                                    wire:key="snd-{{ $key }}-{{ $o['id'] }}" @class(['ns-list__row', 'is-on' => (string) ($picks[$pk] ?? '') === (string) $o['id']])>
                                <span class="ns-tile"><x-nx.icon name="volume" /></span>
                                <span>{{ $o['label'] }}@if (! empty($o['credit']))<small class="ns-small">{{ $o['credit'] }}</small>@endif</span>
                                <span class="ns-sounds__play" role="button" tabindex="0" aria-label="{{ __('sounds.play', ['name' => $o['label']]) }}" x-data="{ on: false }" :class="on ? 'is-playing' : ''" :aria-pressed="on ? 'true' : 'false'"
                                      x-on:nx-sound-preview.window="if ($event.detail.url === '{{ route('sounds.tone', $o['id']) }}') on = $event.detail.playing"
                                      x-on:click.stop="window.NaaraSound && window.NaaraSound.preview('{{ route('sounds.tone', $o['id']) }}')"
                                      x-on:keydown.enter.stop.prevent="window.NaaraSound && window.NaaraSound.preview('{{ route('sounds.tone', $o['id']) }}')"><span class="ns-sounds__ico" x-show="!on"><x-nx.icon name="play" /></span><span class="ns-sounds__bars" x-show="on" x-cloak aria-hidden="true"><i></i><i></i><i></i><i></i></span></span>
                                <span class="ns-sounds__tickslot">@if ((string) ($picks[$pk] ?? '') === (string) $o['id'])<x-nx.icon name="check" class="ns-sounds__tick" />@endif</span>
                            </button>
                        @endforeach
                    </div>
                @endif
            @endforeach
        </div>
    @endforeach

    <x-nx.cta type="button" wire:click="resetAll" wire:loading.attr="disabled" wire:target="resetAll" style="margin-top:18px;height:46px;font-size:15px">{{ __('sounds.reset') }}</x-nx.cta>
</x-nx.page>
</div>
