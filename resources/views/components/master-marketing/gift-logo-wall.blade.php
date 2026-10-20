{{-- MASTER-ONLY (lives under master-marketing so MasterOnlyModules keeps it out of fork packages; the shared hero includes it only when this class exists).
     A live wall of the most bought gift-card logos: 4 columns on desktop, 3 on phones, drifting up / down / up / down. Pure CSS, decorative (aria-hidden),
     paused for reduced-motion users. --}}
@php($cols = \App\Support\MasterMarketing\GiftLogoWall::columns(4))
<div class="gw" aria-hidden="true">
    @foreach ($cols as $i => $tiles)
        @continue($tiles === [])
        <div class="gw__col gw__col--{{ $i % 2 === 0 ? 'up' : 'down' }} @if ($i === 3) gw__col--wide @endif" style="--gw-speed: {{ 38 + ($i * 7) }}s">
            <div class="gw__track">
                @foreach ([0, 1] as $copy)
                    @foreach ($tiles as $t)
                        <span class="gw__tile"><img src="{{ $t['logo'] }}" alt="" width="96" height="96" loading="{{ $copy === 0 ? 'eager' : 'lazy' }}" decoding="async"></span>
                    @endforeach
                @endforeach
            </div>
        </div>
    @endforeach
</div>
@once
<style>
    .gw { position: absolute; inset-block: -0.5rem; right: 0; width: min(46%, 25rem); display: flex; gap: .55rem; justify-content: flex-end; overflow: hidden; pointer-events: none;
          -webkit-mask-image: linear-gradient(to bottom, transparent 0, #000 16%, #000 84%, transparent 100%), linear-gradient(to right, transparent 0, #000 26%);
          -webkit-mask-composite: source-in; mask-image: linear-gradient(to bottom, transparent 0, #000 16%, #000 84%, transparent 100%), linear-gradient(to right, transparent 0, #000 26%); mask-composite: intersect; }
    .gw__col { flex: 1 1 0; min-width: 0; max-width: 5.6rem; }
    .gw__col--wide { display: none; }
    .gw__col:nth-child(even) { margin-top: 1.4rem; }
    .gw__track { display: flex; flex-direction: column; gap: .55rem; will-change: transform; animation: gw-up var(--gw-speed, 40s) linear infinite; }
    .gw__col--down .gw__track { animation-name: gw-down; }
    .gw__tile { display: block; aspect-ratio: 1; border-radius: .9rem; overflow: hidden; background: rgb(var(--nx-surface)); box-shadow: 0 0 0 1px rgb(var(--nx-line)), 0 6px 16px rgb(0 0 0 / .18); }
    .gw__tile img { display: block; width: 100%; height: 100%; object-fit: cover; }
    @keyframes gw-up { from { transform: translateY(0); } to { transform: translateY(-50%); } }
    @keyframes gw-down { from { transform: translateY(-50%); } to { transform: translateY(0); } }
    @media (min-width: 640px) { .gw { width: min(48%, 29rem); gap: .7rem; } .gw__col--wide { display: block; } .gw__col { max-width: 6.2rem; } .gw__track { gap: .7rem; } }
    @media (max-width: 639px) { .gw { width: 50%; right: -.5rem; gap: .4rem; } .gw__track { gap: .4rem; } .gw__tile { border-radius: .7rem; } }
    @media (prefers-reduced-motion: reduce) { .gw__track { animation: none; } }
</style>
@endonce
