{{-- nx:converted --}}
{{-- "Naara alerts" (Journey Engine J2): per-channel opt-in for protective alerts. Skin tokens only; both switches default to OFF. --}}
<div>
<x-nx.page class="ns-pg ns-pg--mid">
    <h1 class="ns-h1" style="margin-top:6px">{{ __('journey_engine.delivery.title') }}</h1>
    <p class="ns-sub">{{ __('journey_engine.delivery.sub') }}</p>

    @if ($notice)<x-nx.note icon="check" role="status" style="margin-top:14px">{{ $notice }}</x-nx.note>@endif

    <section class="ns-pg__card ns-ring" style="margin-top:16px">
        @foreach (['push' => ['bell', $push], 'email' => ['mail', $email]] as $channel => [$icon, $on])
            <div class="ns-note" style="align-items:center;margin:0 0 10px" wire:key="alert-{{ $channel }}">
                <x-nx.icon :name="$icon" />
                <span style="flex:1;min-width:0">
                    <b style="display:block;color:rgb(var(--nx-text));font-weight:600">{{ __("journey_engine.delivery.channel_{$channel}") }}</b>
                    <small class="ns-small">{{ __("journey_engine.delivery.channel_{$channel}_hint") }}</small>
                </span>
                <button type="button" role="switch" aria-checked="{{ $on ? 'true' : 'false' }}" aria-label="{{ __("journey_engine.delivery.channel_{$channel}") }}"
                        wire:click="toggle('{{ $channel }}')" wire:loading.attr="disabled" wire:target="toggle('{{ $channel }}')"
                        class="ns-toggle {{ $on ? 'is-on' : '' }}"><i></i></button>
            </div>
        @endforeach
    </section>

    <section class="ns-pg__card ns-ring" style="margin-top:16px">
        <h2 class="ns-pg__h2" style="margin-bottom:8px">{{ __('journey_engine.delivery.what_title') }}</h2>
        <ul class="ns-small" style="margin:0;padding-left:18px;display:grid;gap:6px">
            <li>{{ __('journey_engine.delivery.what_1') }}</li>
            <li>{{ __('journey_engine.delivery.what_2') }}</li>
            <li>{{ __('journey_engine.delivery.what_3') }}</li>
            <li>{{ __('journey_engine.delivery.what_4') }}</li>
        </ul>
    </section>
</x-nx.page>
</div>
