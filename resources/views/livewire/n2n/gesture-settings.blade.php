{{-- nx:converted --}}
{{-- Chat gestures and controls (N2N, MASTER-ONLY). Skin tokens only. Every gesture has a button alternative: "Buttons only" turns the gestures off and a tap opens the action menu. --}}
<div>
@vite('resources/js/n2n/gesture-practice.js')
<script type="application/json" id="ng-config">{!! json_encode(array_merge($engine, ['stat_url' => '']), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
<x-nx.page class="ns-pg ns-pg--mid">
    <div class="ns-pg__head">
        <div>
            <a href="{{ route('n2n.identity') }}" wire:navigate class="ns-pg__back"><x-nx.icon name="left" /> Naara ID and chat</a>
            <h1 class="ns-h1" style="margin-top:6px">Chat gestures and controls</h1>
            <p class="ns-sub" style="margin:4px 0 0">Swipes and taps are shortcuts. Everything they do is also in the menu you get by pressing and holding a message, so nothing depends on a gesture.</p>
        </div>
        <img src="{{ asset('images/illustrations/nc-gestures.svg') }}" alt="" width="240" height="180" class="hidden h-28 w-auto sm:block" data-n2n-art>
    </div>
    <x-n2n.section-tabs />

    @if ($message)<div class="ns-pg__card ns-ring" role="status" style="margin-top:12px">{{ $message }}</div>@endif

    <form wire:submit="save" class="ns-pg__card ns-ring" style="display:grid;gap:14px" data-tour="chat.gestures">
        <label style="display:flex;gap:10px;align-items:center"><input type="checkbox" wire:model.live="buttonsOnly"> <span><b>Buttons only</b><small class="ns-sub" style="display:block">Turn every gesture off. Tap a message to open its action menu.</small></span></label>
        <fieldset @disabled($buttonsOnly) style="border:0;padding:0;margin:0;display:grid;gap:14px">
            <label style="display:flex;gap:10px;align-items:center"><input type="checkbox" wire:model="swipeReply"> <span><b>Swipe to reply</b><small class="ns-sub" style="display:block">Swipe a message toward the end of the screen.</small></span></label>
            <label style="display:flex;gap:10px;align-items:center" data-list-swipe-setting><input type="checkbox" wire:model="listSwipe"> <span><b>Swipe chats in your list</b><small class="ns-sub" style="display:block">Swipe a chat toward the end to pin it, toward the start to archive it. You can always undo.</small></span></label>
            <label style="display:grid;gap:4px"><b>Swipe toward the start does</b>
                <select wire:model="swipeStart" class="ns-input"><option value="info">Message info</option><option value="forward">Forward</option><option value="star">Star</option><option value="none">Nothing</option></select></label>
            <label style="display:flex;gap:10px;align-items:center"><input type="checkbox" wire:model="doubleTapReact"> <span><b>Double-tap to react</b><small class="ns-sub" style="display:block">Double-tap again to take your reaction back.</small></span></label>
            <label style="display:grid;gap:4px"><b>Double-tap reaction</b>
                <select wire:model="doubleTapCode" class="ns-input">@foreach ($reactions as $code => $glyph)<option value="{{ $code }}">{{ str_replace('_', ' ', $code) }}</option>@endforeach</select></label>
            <label style="display:flex;gap:10px;align-items:center"><input type="checkbox" wire:model="haptics"> <span><b>Vibration feedback</b><small class="ns-sub" style="display:block">A small tick when a gesture is ready. Phones and browsers that cannot vibrate ignore it.</small></span></label>
            <label style="display:grid;gap:4px"><b>Press and hold delay: {{ $longPressMs }} ms</b><input type="range" min="300" max="1000" step="50" wire:model.live="longPressMs"><small class="ns-sub">Longer is easier on shaky hands.</small></label>
            <label style="display:grid;gap:4px"><b>Swipe distance: {{ $swipeCommitPx }} px</b><input type="range" min="40" max="120" step="4" wire:model.live="swipeCommitPx"><small class="ns-sub">How far to drag before it counts.</small></label>
        </fieldset>
        <div style="display:flex;gap:8px;flex-wrap:wrap">
            <button type="submit" wire:loading.attr="disabled" wire:target="save" class="ns-cta ns-cta--pill"><span wire:loading.remove wire:target="save">Save</span><span wire:loading wire:target="save">Saving...</span></button>
            <button type="button" wire:click="resetToDefaults" wire:loading.attr="disabled" class="ns-cta ns-cta--pill ns-cta--ghost">Back to defaults</button>
        </div>
    </form>

    <div class="ns-pg__card ns-ring" aria-labelledby="ng-try">
        <h2 id="ng-try" class="ns-pg__h2">Try it</h2>
        <p class="ns-sub" style="margin:4px 0 0">Practise on this sample message with the settings you have saved. Nothing is sent.</p>
        <div class="nc ng-practice">
            <div class="ncb ncb--in rounded-2xl px-3 py-2 text-sm" tabindex="0" data-ctx='{"k":"practice"}'>Swipe me either way, double-tap me, or press and hold.</div>
        </div>
        <p id="ng-practice-log" class="ng-practice__log ns-sub" role="status" aria-live="polite">Nothing yet.</p>
    </div>
</x-nx.page>
</div>
