{{-- Master-only sign-in styling: the illustration, the frosted-glass card and the calm aurora, all driven by data attributes the admin sets in
     Admin > Auth & Footer > Sign-in illustration. Plain CSS (no Tailwind classes), included once by the auth layout through @includeIf. --}}
@once
<style>
    .auth-art { position: relative; z-index: 0; width: 100%; margin-inline: auto; pointer-events: none; user-select: none; }
    .auth-art img { display: block; width: 100%; height: auto; margin-inline: auto; }
    .auth-art[data-art-size=small] { max-width: 18rem; }
    .auth-art[data-art-size=medium] { max-width: 24rem; }
    .auth-art[data-art-size=large] { max-width: 30rem; }
    .auth-art[data-art-overlap=none] { margin-top: .75rem; }
    .auth-art[data-art-overlap=light] { margin-top: -1.5rem; }
    .auth-art[data-art-overlap=medium] { margin-top: -3rem; }
    @media (min-width: 1024px) {
        .auth-art[data-art-size=small] { max-width: 24rem; }
        .auth-art[data-art-size=medium] { max-width: 32rem; }
        .auth-art[data-art-size=large] { max-width: 38rem; }
        .auth-art[data-art-overlap=light] { margin-top: -2rem; }
        .auth-art[data-art-overlap=medium] { margin-top: -4rem; }
    }
    /* Frosted glass card. The bottom padding keeps every line of text clear of the part of the artwork the card overlaps. */
    .auth-glass { position: relative; z-index: 10; border: 1px solid rgb(255 255 255 / .6); box-shadow: 0 10px 40px -12px rgb(var(--brand-primary) / .35); }
    .auth-glass[data-art-overlap=none] { padding-bottom: 1.5rem; }
    .auth-glass[data-art-overlap=light] { padding-bottom: 2.25rem; }
    .auth-glass[data-art-overlap=medium] { padding-bottom: 3.75rem; }
    .auth-glass[data-art-glass=light] { background: rgb(255 255 255 / .30); -webkit-backdrop-filter: blur(8px) saturate(1.4); backdrop-filter: blur(8px) saturate(1.4); }
    .auth-glass[data-art-glass=medium] { background: rgb(255 255 255 / .48); -webkit-backdrop-filter: blur(14px) saturate(1.5); backdrop-filter: blur(14px) saturate(1.5); }
    .auth-glass[data-art-glass=strong] { background: rgb(255 255 255 / .66); -webkit-backdrop-filter: blur(22px) saturate(1.6); backdrop-filter: blur(22px) saturate(1.6); }
    .dark .auth-glass { border-color: rgb(255 255 255 / .14); }
    .dark .auth-glass[data-art-glass=light] { background: rgb(26 40 64 / .30); }
    .dark .auth-glass[data-art-glass=medium] { background: rgb(26 40 64 / .46); }
    .dark .auth-glass[data-art-glass=strong] { background: rgb(26 40 64 / .64); }
    /* Calm aurora behind the form column: three slow, soft pools of the brand colours. */
    .auth-aurora { position: absolute; inset: 0; z-index: 0; overflow: hidden; pointer-events: none; }
    .auth-aurora i { position: absolute; border-radius: 50%; filter: blur(48px); will-change: transform; animation: auth-aurora-drift 22s ease-in-out infinite alternate; }
    .auth-aurora i:nth-child(1) { width: 70%; height: 38%; left: -15%; top: -6%; background: rgb(var(--brand-primary)); }
    .auth-aurora i:nth-child(2) { width: 60%; height: 34%; right: -18%; top: 22%; background: rgb(var(--nx-teal, var(--brand-accent))); animation-duration: 28s; animation-direction: alternate-reverse; }
    .auth-aurora i:nth-child(3) { width: 55%; height: 30%; left: 12%; bottom: 8%; background: rgb(var(--brand-accent)); animation-duration: 34s; }
    .auth-aurora[data-aurora=calm] { opacity: .26; }
    .auth-aurora[data-aurora=soft] { opacity: .42; }
    .dark .auth-aurora[data-aurora=calm] { opacity: .30; }
    .dark .auth-aurora[data-aurora=soft] { opacity: .48; }
    /* Sign-in footer (master only): one colour shared by the footer, the feathered foot of the illustration and the browser chrome (AuthFooterTone). */
    :root { --auth-foot: {{ \App\Support\MasterMarketing\AuthFooterTone::hex() }}; }
    body:has(.auth-art) footer { position: relative; z-index: 1; background: var(--auth-foot) !important; border-top: 0 !important; box-shadow: none !important; }
    body:has(.auth-art) footer, body:has(.auth-art) footer a { color: rgb(226 240 242 / .78); }
    body:has(.auth-art) footer a:hover { color: #fff; }
    /* The foot of the illustration melts into the footer colour: no hard line where the hills end. */
    .auth-art::after { content: ""; position: absolute; inset-inline: -2px; bottom: -1px; height: 38%; min-height: 36px; pointer-events: none; background: linear-gradient(to bottom, transparent 0%, var(--auth-foot) 100%); }
    @keyframes auth-aurora-drift { from { transform: translate3d(-4%, -2%, 0) scale(1); } to { transform: translate3d(5%, 4%, 0) scale(1.08); } }
    @media (prefers-reduced-motion: reduce) { .auth-aurora i { animation: none; } }
</style>
@endonce
