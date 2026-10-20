{{-- nx:converted --}}
{{-- Journey Engine "Today" stack: ONE primary card + at most two secondary ones, each with its evidence, plus the first-run intent chooser. Skin tokens only. --}}
<div>
    @if ($off)
        {{-- switched off by the admin (journey.enabled): render nothing --}}
    @elseif ($chooser)
        {{-- Rendered by the always-present modal below; nothing inline, so the dashboard is not pushed down while it is open. --}}
    @elseif ($primary)
        <section aria-label="{{ __('journey_engine.card.today') }}" style="margin-bottom:14px">
            @if ($attention > 0)
                <x-nx.note icon="alert-triangle" variant="warn" style="margin-bottom:10px">{{ trans_choice('journey_engine.card.guardian_attention', $attention, ['count' => $attention]) }}</x-nx.note>
            @endif

            <div class="ns-card ns-card--primary ns-full" wire:key="card-{{ $primary['id'] }}">
                <i class="ns-deco" aria-hidden="true"></i>
                <div class="relative flex flex-col gap-3 sm:flex-row sm:items-center sm:gap-5">
                @if (! empty($primary['art']))
                    <img src="{{ $primary['art'] }}" alt="" width="480" height="320" loading="lazy" decoding="async" class="mx-auto h-36 w-auto shrink-0 drop-shadow-lg sm:order-2 sm:mx-0 sm:h-40" data-journey-art>
                @endif
                <div class="min-w-0 flex-1">
                <h3>{{ $primary['title'] }}</h3>
                <p>{{ $primary['body'] }}</p>
                <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-top:12px">
                    <x-nx.cta wire:click="open({{ $primary['id'] }})" loading="open" icon="arrow-right">{{ $primary['cta'] }}</x-nx.cta>
                    @if ($primary['priority'] === 'critical')
                        <button type="button" wire:click="snooze({{ $primary['id'] }})" class="ns-small" style="text-decoration:underline">{{ __('journey_engine.card.snooze') }}</button>
                    @else
                        <button type="button" wire:click="dismiss({{ $primary['id'] }})" class="ns-small" style="text-decoration:underline">{{ __('journey_engine.card.dismiss') }}</button>
                    @endif
                </div>
                <details class="ns-small" style="margin-top:10px">
                    <summary style="cursor:pointer">{{ __('journey_engine.card.why') }}</summary>
                    <p style="margin-top:6px">{{ $primary['why'] }}</p>
                </details>
                </div>
                </div>
            </div>

            @if ($secondary)
                <x-nx.list style="margin-top:10px">
                    @foreach ($secondary as $c)
                        <x-nx.list-row icon="bell" :title="$c['title']" :text="$c['body']" wire:click="open({{ $c['id'] }})" wire:key="card-{{ $c['id'] }}" />
                    @endforeach
                </x-nx.list>
            @endif
            <p class="ns-small" style="margin-top:8px"><a href="{{ route('account.alerts') }}" wire:navigate style="text-decoration:underline">{{ __('journey_engine.delivery.title') }}</a></p>
        </section>
    @endif

    {{-- "What brings you to Naara?": its own pop-up (the platform modal engine: focus trap, Esc, backdrop). Closing it any other way than Continue counts as "Skip for now". --}}
    @unless ($off)
        <x-ui.modal wire="chooser" :title="__('journey_engine.intent.title')" max-width="xl">
            <div class="ns-intent-wrap">
                <img src="{{ asset('images/illustrations/intent.svg') }}" alt="" width="480" height="240" loading="lazy" decoding="async" class="mx-auto mb-1 h-28 w-auto max-w-full sm:h-32" data-intent-art>
                <p class="ns-intent-sub">{{ __('journey_engine.intent.sub') }}</p>

                <div class="ns-intent-grid" role="group" aria-label="{{ __('journey_engine.intent.title') }}">
                    @foreach ($catalog as $key => $i)
                        @php($on = in_array($key, $selected, true))
                        <button type="button" role="checkbox" aria-checked="{{ $on ? 'true' : 'false' }}" wire:click="toggle('{{ $key }}')" wire:key="intent-{{ $key }}" @class(['ns-intent', 'is-on' => $on])>
                            <span class="ns-intent__tile"><x-nx.icon :name="$i['icon']" /></span>
                            <span class="ns-intent__txt"><b>{{ $i['label'] }}</b><small>{{ $i['hint'] }}</small></span>
                            <span class="ns-intent__box" aria-hidden="true"><x-nx.icon name="check" /></span>
                        </button>
                    @endforeach
                </div>

                <form wire:submit="understand" class="ns-intent-text">
                    <label for="je-text">{{ __('journey_engine.intent.text_label') }}</label>
                    <span class="ns-intent-text__row">
                        <input id="je-text" type="text" wire:model="text" maxlength="300" placeholder="{{ __('journey_engine.intent.text_placeholder') }}" class="ns-input">
                        <button type="submit" class="ns-btn" wire:loading.attr="disabled" wire:target="understand">{{ __('journey_engine.intent.understand') }}</button>
                    </span>
                    @error('text')<span class="ns-small">{{ $message }}</span>@enderror
                </form>
                @if ($notice)<x-nx.note icon="info" style="margin-top:10px">{{ $notice }}</x-nx.note>@endif

                <div class="ns-intent-foot">
                    <span class="ns-intent-count">{{ trans_choice('journey_engine.intent.selected', count($selected), ['count' => count($selected)]) }}</span>
                    <button type="button" wire:click="skip" class="ns-intent-skip">{{ __('journey_engine.intent.skip') }}</button>
                    <x-nx.cta wire:click="save" loading="save" icon="check">{{ __('journey_engine.intent.save') }}</x-nx.cta>
                </div>
            </div>
        </x-ui.modal>
    @endunless
</div>
