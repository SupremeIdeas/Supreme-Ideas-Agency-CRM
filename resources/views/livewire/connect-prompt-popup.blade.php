{{-- nx:converted --}}
{{-- Dashboard popup for a brand the person came from (Brand-Growth Phase 6, MASTER-ONLY). Skin sheet, one plain root. --}}
<div data-connect-popup x-data="{ open: false, timer: null,
        // Wait while any other dialog (the welcome chooser, a tour, a share sheet) is open, then show; never stack two overlays.
        busy() { return [...document.querySelectorAll('[role=dialog]')].some(d => ! d.closest('[data-connect-popup]') && d.getClientRects().length > 0 && getComputedStyle(d).visibility !== 'hidden'); },
        init() { @if ($intentId) let tries = 0; this.timer = setInterval(() => { tries++; if (! this.busy()) { clearInterval(this.timer); this.open = true; $wire.shown(); } else if (tries > 120) { clearInterval(this.timer); } }, 1000); @endif } }">
    @if ($intentId)
        <x-nx.alpine-sheet show="open" :title="'Connect with '.$brandName" icon="users">
            <div style="display:flex;gap:12px;align-items:center">
                @if ($logo)<img src="{{ $logo }}" alt="" width="56" height="56" style="width:56px;height:56px;border-radius:50%;object-fit:cover">@endif
                <p class="ns-sub" style="margin:0">You came from {{ $brandName }}. Connect to see their updates and offers in Naara. It is your choice, and you can undo it any time.</p>
            </div>
            <x-slot:foot>
                <div style="display:flex;gap:8px;flex-wrap:wrap">
                    <a href="{{ route('rewards.hunt.profile', $brandId) }}" wire:navigate class="ns-cta ns-cta--pill" data-connect-continue>Continue</a>
                    <button type="button" wire:click="notNow" wire:loading.attr="disabled" wire:target="notNow" @click="open = false" class="ns-cta ns-cta--pill ns-cta--ghost">Not now</button>
                </div>
            </x-slot:foot>
        </x-nx.alpine-sheet>
    @endif
</div>
