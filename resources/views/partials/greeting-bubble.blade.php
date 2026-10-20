{{-- The assistant's welcome on the dashboard, as a designed card (skin tokens only, so every skin, accent and mode restyles it): an avatar with a live ring,
     the assistant's name and role, a two-tone greeting, and a calm "Did you know?" insight strip. Shared by the inline card and the popup.
     Expects: $gAvatar, $gName, $greeting, $greetingAsk, $factOfTheDay, and optionally $onDismiss (an @click expression). --}}
<div class="ns-hello ns-ring">
    <span class="ns-hello__glow" aria-hidden="true"></span>
    @isset($onDismiss)
        <button type="button" {!! $onDismiss !!} aria-label="{{ __('Dismiss') }}" class="ns-icon-btn ns-hello__x"><x-nx.icon name="x" /></button>
    @endisset
    <div class="ns-hello__head">
        <span class="ns-hello__avatar">
            <x-nx.avatar :name="$gName" :src="$gAvatar" :size="46" />
            <i class="ns-hello__live" aria-hidden="true"></i>
        </span>
        <span class="ns-hello__who">
            <b>{{ $gName }}</b>
            <small>{{ __('Your assistant') }}</small>
        </span>
    </div>
    <p class="ns-hello__line"><span class="ns-hello__greet">{{ $greeting }}</span> <span class="ns-hello__ask">{{ $greetingAsk }}</span></p>
    <div class="ns-hello__tip">
        <b class="ns-hello__eyebrow">{{ __('Did you know?') }}</b>
        <span>{{ $factOfTheDay }}</span>
    </div>
</div>
