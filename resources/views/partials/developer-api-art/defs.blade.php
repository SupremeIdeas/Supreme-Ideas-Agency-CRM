  <style>
    .{{ $u }} { --c-bg1: rgb(var(--nx-surface-2, 244 249 249)); --c-bg2: rgb(var(--nx-surface, 255 255 255)); --c-card: rgb(var(--nx-surface, 255 255 255)); --c-line: rgb(var(--nx-line-strong, 190 208 210));
          --c-ink: rgb(var(--nx-text, 13 27 42)); --c-ink2: rgb(var(--nx-text-2, 84 104 112)); --c-teal: rgb(var(--nx-teal, 10 155 148)); --c-tealink: rgb(var(--nx-teal-ink, 0 103 98)); --c-gold: rgb(var(--nx-gold, 212 160 23));
          --c-goldink: rgb(var(--nx-gold-ink, 138 98 0)); --c-code: rgb(var(--nx-surface-3, 232 240 241));
          font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; }
    .{{ $u }} .panel { fill: url(#{{ $u }}-bg); stroke: var(--c-line); stroke-opacity: .55; }
    .{{ $u }} .grid { stroke: var(--c-line); stroke-opacity: .2; }
    .{{ $u }} .card { fill: var(--c-card); stroke: var(--c-line); stroke-opacity: .7; }
    .{{ $u }} .code { fill: var(--c-code); }
    .{{ $u }} .t { fill: var(--c-ink); font-weight: 700; } .{{ $u }} .s { fill: var(--c-ink2); font-weight: 500; }
    .{{ $u }} .mono { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-weight: 600; }
    .{{ $u }} .k { fill: var(--c-tealink); } .{{ $u }} .g { fill: var(--c-goldink); } .{{ $u }} .m { fill: var(--c-ink2); } .{{ $u }} .v { fill: var(--c-ink); }
    .{{ $u }} .chip { fill: var(--c-card); stroke: var(--c-teal); stroke-opacity: .45; }
    .{{ $u }} .ico { fill: none; stroke: var(--c-tealink); stroke-width: 1.9; stroke-linecap: round; stroke-linejoin: round; }
    .{{ $u }} .flow { fill: none; stroke: url(#{{ $u }}-flow); stroke-width: 2.4; stroke-linecap: round; stroke-dasharray: 5 8; animation: {{ $u }}-dash 1.5s linear infinite; }
    .{{ $u }} .back { animation-direction: reverse; }
    .{{ $u }} .halo { fill: var(--c-teal); fill-opacity: .13; transform-box: fill-box; transform-origin: center; animation: {{ $u }}-pulse 3.4s ease-in-out infinite; }
    .{{ $u }} .ring { fill: none; stroke: var(--c-teal); stroke-opacity: .4; stroke-dasharray: 2 7; stroke-linecap: round; transform-box: fill-box; transform-origin: center; animation: {{ $u }}-spin 36s linear infinite; }
    .{{ $u }} .caret { animation: {{ $u }}-blink 1.1s steps(1) infinite; }
    .{{ $u }} .bar { transform-box: fill-box; transform-origin: left; animation: {{ $u }}-fill 5s ease-in-out infinite alternate; }
    @keyframes {{ $u }}-dash { to { stroke-dashoffset: -26; } }
    @keyframes {{ $u }}-spin { to { transform: rotate(360deg); } }
    @keyframes {{ $u }}-pulse { 0%, 100% { transform: scale(1); opacity: .95; } 50% { transform: scale(1.14); opacity: .35; } }
    @keyframes {{ $u }}-blink { 50% { opacity: 0; } }
    @keyframes {{ $u }}-fill { from { transform: scaleX(.62); } to { transform: scaleX(.78); } }
    @media (prefers-reduced-motion: reduce) { .{{ $u }} .flow, .{{ $u }} .halo, .{{ $u }} .ring, .{{ $u }} .caret, .{{ $u }} .bar { animation: none; } }
  </style>
  <defs>
    <linearGradient id="{{ $u }}-bg" x1="0" y1="0" x2="1" y2="1"><stop offset="0" style="stop-color:var(--c-bg1)"/><stop offset="1" style="stop-color:var(--c-bg2)"/></linearGradient>
    <linearGradient id="{{ $u }}-flow" x1="0" y1="0" x2="1" y2="0"><stop offset="0" stop-color="#0A9B94"/><stop offset="1" stop-color="#D4A017"/></linearGradient>
    <linearGradient id="{{ $u }}-core" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#0B7F7A"/><stop offset="1" stop-color="#0D1B2A"/></linearGradient>
    <linearGradient id="{{ $u }}-mark" x1="0" y1="1" x2="1" y2="0"><stop offset="0" stop-color="#3DDBD0"/><stop offset=".6" stop-color="#F2B93B"/><stop offset="1" stop-color="#FF8A3D"/></linearGradient>
    <filter id="{{ $u }}-sh" x="-20%" y="-20%" width="140%" height="150%"><feDropShadow dx="0" dy="9" stdDeviation="9" flood-color="#0D1B2A" flood-opacity=".16"/></filter>
    <symbol id="{{ $u }}-i-list" viewBox="0 0 24 24"><path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/></symbol>
    <symbol id="{{ $u }}-i-quote" viewBox="0 0 24 24"><path d="M12 2v20M17 6.5C17 4.5 14.8 3 12 3S7 4.5 7 6.5 9 9.7 12 10.5s5 2 5 4-2.200 3.500-5 3.500-5-1.500-5-3.500"/></symbol>
    <symbol id="{{ $u }}-i-bag" viewBox="0 0 24 24"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4zM3 6h18M16 10a4 4 0 0 1-8 0"/></symbol>
    <symbol id="{{ $u }}-i-pulse" viewBox="0 0 24 24"><path d="M3 12h4l3-8 4 16 3-8h4"/></symbol>
    <symbol id="{{ $u }}-i-shield" viewBox="0 0 24 24"><path d="M12 3l8 3v6c0 5-3.500 8-8 9-4.500-1-8-4-8-9V6z"/><path d="m9 12 2 2 4-4"/></symbol>
    <symbol id="{{ $u }}-i-key" viewBox="0 0 24 24"><circle cx="8" cy="15" r="4.500"/><path d="m11.400 11.600 8.100-8.100M16 7l3 3M14 9l2.500 2.500"/></symbol>
  </defs>
