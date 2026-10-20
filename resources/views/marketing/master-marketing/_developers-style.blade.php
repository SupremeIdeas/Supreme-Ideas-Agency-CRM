{{-- Styles for the developer pages (MASTER-ONLY). Brand colours: Midnight Navy #0D1B2A, Deep Teal #0A6E6E, Warm Gold #D4A017; the lighter teal/gold tints are for text on navy, where the brand shades alone would be too dim. --}}
<style>
    .dv-code { --dv-navy: #0D1B2A; --dv-navy-2: #12263A; --dv-teal: #0A6E6E; --dv-teal-lt: #4FD1C5; --dv-teal-pale: #A8EDE6; --dv-gold: #D4A017; --dv-gold-lt: #F2CB5C; margin: 0; border-radius: 16px; overflow: hidden; background: var(--dv-navy); border: 1px solid rgb(10 110 110 / .6); box-shadow: 0 18px 40px -22px rgb(13 27 42 / .7); }
    .dv-code__bar { display: flex; align-items: center; gap: 10px; padding: 8px 10px 0 14px; background: var(--dv-navy-2); border-bottom: 2px solid var(--dv-teal); flex-wrap: wrap; }
    .dv-code__title { font-size: 12px; font-weight: 700; letter-spacing: .04em; color: #E4ECF3; margin-inline-end: auto; padding: 6px 0; }
    .dv-code__tabs { display: flex; gap: 2px; overflow-x: auto; }
    .dv-code__tabs button { border: 0; background: transparent; color: #9FB3C6; font-size: 12px; font-weight: 700; padding: 8px 10px; cursor: pointer; border-bottom: 2px solid transparent; margin-bottom: -2px; white-space: nowrap; }
    .dv-code__tabs button:hover { color: #fff; }
    .dv-code__tabs button.is-on { color: var(--dv-gold-lt); border-bottom-color: var(--dv-gold); }
    .dv-code__lang { font-size: 12px; font-weight: 700; color: var(--dv-gold-lt); padding: 8px 0; }
    .dv-code__copy { display: inline-flex; align-items: center; gap: 6px; border: 1px solid rgb(255 255 255 / .16); background: rgb(255 255 255 / .05); color: #E4ECF3; font-size: 12px; font-weight: 600; padding: 4px 10px; border-radius: 999px; cursor: pointer; margin-bottom: 6px; }
    .dv-code__copy:hover { background: rgb(212 160 23 / .18); border-color: var(--dv-gold); }
    .dv-code__pre { margin: 0; padding: 16px 18px; overflow-x: auto; font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size: 12.8px; line-height: 1.7; color: #E4ECF3; tab-size: 2; }
    .dv-code__pre:focus-visible { outline: 2px solid var(--dv-gold); outline-offset: -2px; }
    .dv-t-com { color: #6F879D; font-style: italic; }
    .dv-t-str { color: var(--dv-teal-lt); }
    .dv-t-key { color: var(--dv-teal-pale); }
    .dv-t-num { color: var(--dv-gold-lt); }
    .dv-t-kw { color: var(--dv-gold); font-weight: 600; }
    .dv-t-fn { color: #FFFFFF; font-weight: 600; }
    .dv-t-var { color: var(--dv-teal-pale); }
    .dv-t-flag { color: var(--dv-gold-lt); }
    .dv-t-url { color: var(--dv-teal-lt); }
    .dv-prose { font-size: 15.5px; line-height: 1.75; color: rgb(51 65 85); }
    .dark .dv-prose { color: rgb(203 213 225); }
    .dv-prose code, .dv-inline { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size: .86em; padding: .12em .42em; border-radius: 6px; background: rgb(10 110 110 / .1); color: #075a5a; }
    .dark .dv-prose code, .dark .dv-inline { background: rgb(79 209 197 / .12); color: #A8EDE6; }
    .dv-prose strong { color: rgb(15 23 42); }
    .dark .dv-prose strong { color: #fff; }
    [x-cloak] { display: none !important; }
    @media (prefers-reduced-motion: reduce) { .dv-anim { animation: none !important; } }
    @keyframes dv-blink { 50% { opacity: 0; } }
    .dv-cursor { display: inline-block; width: 8px; height: 1.05em; background: var(--dv-gold, #D4A017); vertical-align: -2px; animation: dv-blink 1s steps(1) infinite; }
    @keyframes dv-float { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-8px); } }
    .dv-float { animation: dv-float 6s ease-in-out infinite; }
</style>
