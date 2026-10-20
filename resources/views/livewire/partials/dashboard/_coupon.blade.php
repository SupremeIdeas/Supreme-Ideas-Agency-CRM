{{-- nx:converted (skin tokens; legacy palette classes removed 2026-10-05) --}}
{{-- Coupon marketing nudge (Theme Batch 2 §2), on the skin system. --}}
@if ($couponNudge)
    <div class="ns-banner ns-ring" style="margin-top:20px;flex-wrap:wrap">
        <span class="ns-tile" style="--nx-tone:var(--nx-gold)"><x-nx.icon name="gift" /></span>
        <div class="ns-text" style="flex:1;min-width:10rem">
            <b>{{ $couponNudge['title'] }}</b><small>{{ $couponNudge['message'] }}</small>
        </div>
        <span style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
            <span class="ns-code">{{ $couponNudge['code'] }}</span>
            <a href="{{ route('catalogue') }}" wire:navigate class="ns-btn ns-btn--solid">{{ $couponNudge['cta'] }} <x-nx.icon name="chevron-right" /></a>
        </span>
    </div>
@endif
