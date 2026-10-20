{{-- nx:converted (skin tokens; legacy palette classes removed 2026-10-05) --}}
{{-- One follow-to-earn handle card (BUILD-6 §C), on the skin system. Open the handle, then confirm to claim. Verified vs self-confirmed is tagged
     honestly; a claimed card shows "Followed" and is re-checked server-side ($claimed). --}}
@php($verified = ($handle->verification ?? 'self') === 'api')
{{-- Return gate (master only): pressing Follow starts a server clock; the claim opens after the delay AND once the person is back on this page. --}}
@php($gate = class_exists(\App\Services\Social\FollowRewardGate::class) && \App\Services\Social\FollowRewardGate::enabled())
@php($gateKind = $action === 'followBrandHandle' ? 'brand' : 'platform')
@php($gateSecs = $gate ? \App\Services\Social\FollowRewardGate::minSeconds() : 0)
<div x-data="{ opened: false, left: 0, timer: null, back: false, k: 'nx_fs_{{ $gateKind }}_{{ $handle->id }}',
        {{-- The Follow link opens the platform (its app when it is installed). The phone may pause or even restart this page while the other app is in front, so the pressed-Follow state is kept
             for half an hour and restored on return; the server clock (followStarted) stays the authority. --}}
        init() {
            try { const t = +localStorage.getItem(this.k); if (t && Date.now() - t < 1800000) { this.opened = true; this.left = Math.max(0, {{ $gateSecs }} - Math.floor((Date.now() - t) / 1000)); this.tick(); } else { localStorage.removeItem(this.k); } } catch (e) {}
            document.addEventListener('visibilitychange', () => { if (document.visibilityState === 'visible' && this.opened) { this.back = true; setTimeout(() => this.back = false, 6000); } });
        },
        start() { this.opened = true; try { localStorage.setItem(this.k, Date.now()); } catch (e) {} @if ($gate) $wire.followStarted('{{ $gateKind }}', {{ $handle->id }}).catch(() => {}); @endif this.left = {{ $gateSecs }}; this.tick(); },
        tick() { clearInterval(this.timer); this.timer = setInterval(() => { if (document.visibilityState === 'visible' && this.left > 0) this.left--; if (this.left <= 0) clearInterval(this.timer); }, 1000); } }"
     @follow-reset.window="if ($event.detail.kind === '{{ $gateKind }}' && $event.detail.id === {{ $handle->id }}) { opened = false; left = 0; try { localStorage.removeItem(k); } catch (e) {} }"
     class="ns-bh__handle ns-ring">
    <span class="ns-bh__icon"><x-service-icon :slug="$handle->platform" class="h-5 w-5" /></span>
    <div style="min-width:0;flex:1">
        <b>{{ $handle->handle_label }}</b>
        <small class="{{ $verified ? 'is-ver' : '' }}">{{ $verified ? 'Verified automatically' : 'Self-confirmed' }}</small>
    </div>

    @if ($claimed)
        <button type="button" disabled aria-disabled="true" title="Reward already claimed" class="ns-st ns-st--ok" style="flex:none;pointer-events:none;cursor:default;opacity:.9"><x-nx.icon name="check" style="font-size:13px;margin-inline-end:4px" /> Followed</button>
    @else
        {{-- Step 1: open the handle. Step 2: confirm the follow → server grant. --}}
        <a x-show="!opened" href="{{ $handle->handle_url }}" target="_blank" rel="noopener" @click="start()" class="ns-cta ns-cta--pill ns-cta--sm" style="flex:none">Follow</a>
        <button x-show="opened" x-cloak type="button" wire:click="{{ $action }}({{ $handle->id }})" wire:loading.attr="disabled" wire:target="{{ $action }}({{ $handle->id }})" :disabled="left > 0" :class="back && left <= 0 && 'animate-pulse'" class="ns-cta ns-cta--pill ns-cta--sm ns-cta--gold" style="flex:none"><span x-show="left <= 0"><span x-show="back">Welcome back. </span>I followed — claim</span><span x-show="left > 0" x-cloak>Claim in <span x-text="left"></span>s</span></button>
    @endif
</div>
