{{-- Style + gradients of the White Label engine illustration (MASTER-ONLY). Included once per layout, so each <svg> carries its own ids. --}}
  <style>
    .{{ $u }} { --c-bg1: #F1F9F9; --c-bg2: #FFFFFF; --c-card: #FFFFFF; --c-line: #B9CFD2; --c-ink: #0D1B2A; --c-ink2: #46606A; --c-teal: #0A9B94; --c-tealink: #006762; --c-goldink: #6B4D00; --c-gold: #D4A017; --c-chip: #EEF6F6;
           font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; }
    .dark .{{ $u }} { --c-bg1: #11263B; --c-bg2: #0D1B2A; --c-card: #16263F; --c-line: #3B556C; --c-ink: #F1F5F9; --c-ink2: #AFC0CE; --c-tealink: #5EEAD4; --c-goldink: #F2B93B; --c-chip: #1B2F4A; }
    .{{ $u }} .panel { fill: url(#{{ $u }}-bg); stroke: var(--c-line); stroke-opacity: .6; }
    .{{ $u }} .grid { stroke: var(--c-line); stroke-opacity: .2; }
    .{{ $u }} .card { fill: var(--c-card); stroke: var(--c-line); stroke-opacity: .75; }
    .{{ $u }} .chip { fill: var(--c-chip); stroke: var(--c-line); stroke-opacity: .6; }
    .{{ $u }} .t { fill: var(--c-ink); font-weight: 700; } .{{ $u }} .s { fill: var(--c-ink2); font-weight: 500; }
    .{{ $u }} .tag { fill: var(--c-goldink); font-weight: 700; letter-spacing: .14em; text-transform: uppercase; }
    .{{ $u }} .ico { fill: none; stroke: var(--c-tealink); stroke-width: 1.9; stroke-linecap: round; stroke-linejoin: round; }
    .{{ $u }} .ico-w { fill: none; stroke: #fff; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }
    .{{ $u }} .flow { fill: none; stroke: url(#{{ $u }}-flow); stroke-width: 2.6; stroke-linecap: round; stroke-dasharray: 5 8; animation: {{ $u }}-dash 1.5s linear infinite; }
    .{{ $u }} .orbit { fill: none; stroke: var(--c-teal); stroke-opacity: .4; stroke-dasharray: 2 7; stroke-linecap: round; animation: {{ $u }}-spin 38s linear infinite; }
    .{{ $u }} .orbit.r { stroke: var(--c-gold); stroke-opacity: .5; animation-duration: 56s; animation-direction: reverse; }
    .{{ $u }} .halo { fill: var(--c-teal); fill-opacity: .13; animation: {{ $u }}-pulse 3.6s ease-in-out infinite; }
    .{{ $u }} .float { animation: {{ $u }}-float 6s ease-in-out infinite; }
    .{{ $u }} .float.b { animation-delay: -2s; } .{{ $u }} .float.c { animation-delay: -4s; }
    .{{ $u }} .orbit, .{{ $u }} .halo { transform-box: fill-box; transform-origin: center; }
    @keyframes {{ $u }}-dash { to { stroke-dashoffset: -26; } }
    @keyframes {{ $u }}-spin { to { transform: rotate(360deg); } }
    @keyframes {{ $u }}-pulse { 0%, 100% { transform: scale(1); opacity: .95; } 50% { transform: scale(1.14); opacity: .35; } }
    @keyframes {{ $u }}-float { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-7px); } }
    @media (prefers-reduced-motion: reduce) { .{{ $u }} .flow, .{{ $u }} .orbit, .{{ $u }} .halo, .{{ $u }} .float { animation: none; } }
  </style>
  <defs>
    <linearGradient id="{{ $u }}-bg" x1="0" y1="0" x2="1" y2="1"><stop offset="0" style="stop-color:var(--c-bg1)"/><stop offset="1" style="stop-color:var(--c-bg2)"/></linearGradient>
    <linearGradient id="{{ $u }}-flow" x1="0" y1="0" x2="1" y2="0"><stop offset="0" stop-color="#0A9B94"/><stop offset="1" stop-color="#D4A017"/></linearGradient>
    <linearGradient id="{{ $u }}-core" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#0B7F7A"/><stop offset="1" stop-color="#0D1B2A"/></linearGradient>
    <linearGradient id="{{ $u }}-mark" x1="0" y1="1" x2="1" y2="0"><stop offset="0" stop-color="#3DDBD0"/><stop offset=".6" stop-color="#F2B93B"/><stop offset="1" stop-color="#FF8A3D"/></linearGradient>
    <linearGradient id="{{ $u }}-pa" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#8B5CF6"/><stop offset="1" stop-color="#4C1D95"/></linearGradient>
    <linearGradient id="{{ $u }}-pb" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#14B8A6"/><stop offset="1" stop-color="#0F5F63"/></linearGradient>
    <linearGradient id="{{ $u }}-pc" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#FB7185"/><stop offset="1" stop-color="#9F1239"/></linearGradient>
    <linearGradient id="{{ $u }}-area" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#D4A017" stop-opacity=".38"/><stop offset="1" stop-color="#D4A017" stop-opacity="0"/></linearGradient>
    <radialGradient id="{{ $u }}-glow1" cx=".5" cy=".5" r=".5"><stop offset="0" stop-color="#0A9B94" stop-opacity=".28"/><stop offset="1" stop-color="#0A9B94" stop-opacity="0"/></radialGradient>
    <radialGradient id="{{ $u }}-glow2" cx=".5" cy=".5" r=".5"><stop offset="0" stop-color="#D4A017" stop-opacity=".26"/><stop offset="1" stop-color="#D4A017" stop-opacity="0"/></radialGradient>
    <filter id="{{ $u }}-sh" x="-20%" y="-20%" width="140%" height="150%"><feDropShadow dx="0" dy="10" stdDeviation="10" flood-color="#0D1B2A" flood-opacity=".18"/></filter>
    <symbol id="{{ $u }}-i-signal" viewBox="0 0 24 24"><path d="M2 8.8a15 15 0 0 1 20 0M5 12.5a10 10 0 0 1 14 0M8.5 16a5 5 0 0 1 7 0M12 19.5h.01"/></symbol>
    <symbol id="{{ $u }}-i-hash" viewBox="0 0 24 24"><path d="M4 9h16M4 15h16M10 3 8 21M16 3l-2 18"/></symbol>
    <symbol id="{{ $u }}-i-card" viewBox="0 0 24 24"><rect x="2" y="5" width="20" height="14" rx="3"/><path d="M2 10h20"/></symbol>
    <symbol id="{{ $u }}-i-headset" viewBox="0 0 24 24"><path d="M3 14v-2a9 9 0 0 1 18 0v2M3 14a2 2 0 0 1 2-2h1v6H5a2 2 0 0 1-2-2zM21 14a2 2 0 0 0-2-2h-1v6h1a2 2 0 0 0 2-2z"/></symbol>
    <symbol id="{{ $u }}-i-shield" viewBox="0 0 24 24"><path d="M12 3l8 3v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V6z"/><path d="m9 12 2 2 4-4"/></symbol>
    <symbol id="{{ $u }}-i-refresh" viewBox="0 0 24 24"><path d="M21 12a9 9 0 0 1-15.5 6.2M3 12A9 9 0 0 1 18.5 5.8M18 2v4h-4M6 22v-4h4"/></symbol>
    <symbol id="{{ $u }}-i-lock" viewBox="0 0 24 24"><rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></symbol>
    <symbol id="{{ $u }}-i-key" viewBox="0 0 24 24"><circle cx="8" cy="15" r="4.5"/><path d="m11.4 11.6 8.1-8.1M16 7l3 3M14 9l2.5 2.5"/></symbol>
  </defs>
