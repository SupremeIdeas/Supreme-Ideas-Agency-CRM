{{-- Self-contained Clay skin for the hosted widget pages. These pages live inside a third party's iframe, so they must NOT depend on the app bundle.
     Expects $brand (hex) and $theme ('auto'|'light'|'dark'). Light is the default; dark follows the shopper's device unless the widget forced a theme. --}}
@php $ink = \App\Support\MerchantWidgetBrand::ink($brand); @endphp
<style>
:root{--brand:{{ $brand }};--on-brand:{{ $ink }};--bg:#e8eef3;--fg:#0d1b2a;--muted:#43566a;--line:rgba(13,27,42,.12);--field:#eef3f7;--ok:#067647;--bad:#b42318;
 --card:linear-gradient(145deg,#fff,#eef3f7);--raise:10px 12px 24px -10px rgba(10,36,49,.28),-8px -8px 18px #fff,inset 2px 2px 4px #fff,inset -3px -3px 6px rgba(10,36,49,.06);
 --press:inset 3px 4px 8px rgba(10,36,49,.14),inset -3px -3px 7px #fff;--soft:6px 8px 16px -8px rgba(10,36,49,.22),-5px -5px 12px #fff}
</style>
<style>
@if ($theme === 'dark')
:root{--bg:#0b1624;--fg:#f0f4f8;--muted:#a9bdd1;--line:rgba(255,255,255,.14);--field:#0c1a29;--ok:#4ade9a;--bad:#ff9a8f;
 --card:linear-gradient(145deg,#173048,#0f2133);--raise:10px 12px 26px -8px rgba(0,0,0,.55),-6px -6px 16px rgba(255,255,255,.05),inset 2px 2px 4px rgba(255,255,255,.07),inset -3px -3px 7px rgba(0,0,0,.25);
 --press:inset 3px 4px 8px rgba(0,0,0,.4),inset -3px -3px 7px rgba(255,255,255,.05);--soft:6px 8px 16px -8px rgba(0,0,0,.5),-4px -4px 10px rgba(255,255,255,.04)}
@elseif ($theme === 'auto')
@media (prefers-color-scheme:dark){:root{--bg:#0b1624;--fg:#f0f4f8;--muted:#a9bdd1;--line:rgba(255,255,255,.14);--field:#0c1a29;--ok:#4ade9a;--bad:#ff9a8f;
 --card:linear-gradient(145deg,#173048,#0f2133);--raise:10px 12px 26px -8px rgba(0,0,0,.55),-6px -6px 16px rgba(255,255,255,.05),inset 2px 2px 4px rgba(255,255,255,.07),inset -3px -3px 7px rgba(0,0,0,.25);
 --press:inset 3px 4px 8px rgba(0,0,0,.4),inset -3px -3px 7px rgba(255,255,255,.05);--soft:6px 8px 16px -8px rgba(0,0,0,.5),-4px -4px 10px rgba(255,255,255,.04)}}
@endif
*{box-sizing:border-box}html{-webkit-text-size-adjust:100%}
body{margin:0;font:16px/1.5 system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;background:var(--bg);color:var(--fg)}
main{max-width:30rem;margin:0 auto;padding:1.1rem 1rem 2rem}
.top{display:flex;align-items:center;justify-content:space-between;gap:.75rem;margin-bottom:1rem}
.brand{display:flex;align-items:center;gap:.6rem;min-width:0}.brand img{height:2rem;width:auto;max-width:7rem;object-fit:contain}.brand b{font-size:1.05rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.lock{display:inline-flex;align-items:center;gap:.35rem;font-size:.8rem;color:var(--muted);padding:.3rem .65rem;border-radius:999px;background:var(--field);box-shadow:var(--press);white-space:nowrap}
svg.i{width:1.1em;height:1.1em;flex:none;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
.steps{display:flex;align-items:center;gap:.4rem;margin:0 0 1rem;padding:0;list-style:none;font-size:.8rem;color:var(--muted)}
.steps li{display:flex;align-items:center;gap:.4rem;flex:1 1 0;min-width:0}.steps li:last-child{flex:0 0 auto}.steps li+li:before{content:"";flex:1;height:2px;border-radius:2px;background:var(--line);min-width:.6rem}
.steps .n{display:grid;place-items:center;width:1.6rem;height:1.6rem;border-radius:50%;font-weight:700;font-size:.78rem;background:var(--field);box-shadow:var(--press);flex:none}
.steps .on{color:var(--fg);font-weight:600}.steps .on .n{background:var(--brand);color:var(--on-brand);box-shadow:var(--soft)}.steps .done .n{background:var(--ok);color:#fff;box-shadow:var(--soft)}
.steps .t{white-space:nowrap}@media(max-width:380px){.steps .t{display:none}.steps .on .t{display:inline}}
.card{background:var(--card);border-radius:28px;padding:1.1rem;margin-bottom:1rem;box-shadow:var(--raise)}
h1{font-size:1.3rem;line-height:1.25;margin:0 0 .35rem}h2{font-size:.95rem;margin:0 0 .15rem}.sub{color:var(--muted);font-size:.88rem;margin:0}
.sum{display:flex;gap:.9rem;align-items:flex-start;justify-content:space-between}.sum .name{font-weight:700;font-size:1.1rem;line-height:1.3}
.price{font-size:1.65rem;font-weight:800;white-space:nowrap;line-height:1.1}.price small{display:block;font-size:.72rem;font-weight:500;color:var(--muted);text-align:right}
.chips{display:flex;flex-wrap:wrap;gap:.4rem;margin-top:.6rem}.chip{display:inline-flex;align-items:center;gap:.35rem;font-size:.82rem;padding:.25rem .65rem;border-radius:999px;background:var(--field);box-shadow:var(--press)}
.sec{margin-top:1.1rem}.sec:first-child{margin-top:0}
label.l,legend.l{display:block;font-weight:700;font-size:.95rem;margin:0 0 .5rem;padding:0;width:100%}fieldset{min-width:0}
input[type=email]{width:100%;padding:.85rem 1rem;border-radius:18px;border:0;background:var(--field);box-shadow:var(--press);color:var(--fg);font:inherit;outline:0}
input[type=email]::placeholder{color:var(--muted);opacity:.8}
:focus-visible{outline:3px solid var(--brand);outline-offset:2px}input[type=email]:focus-visible{outline-offset:0}
.hint{font-size:.82rem;color:var(--muted);margin:.4rem .1rem 0}.hint button{all:unset;cursor:pointer;color:var(--brand);font-weight:700;text-decoration:underline}
.opts{display:grid;gap:.6rem;border:0;margin:0;padding:0}.opt{position:relative}.opt input{position:absolute;opacity:0;inset:0;width:100%;height:100%;margin:0;cursor:pointer}
.opt span.b{display:flex;align-items:center;justify-content:space-between;gap:.75rem;padding:.8rem 1rem;border-radius:20px;background:var(--card);box-shadow:var(--soft);border:2px solid transparent;min-height:3.2rem}
.opt input:checked+span.b{box-shadow:var(--press);border-color:var(--brand);background:var(--field)}.opt input:focus-visible+span.b{outline:3px solid var(--brand);outline-offset:2px}
.opt b{font-size:.95rem}.opt small{display:block;color:var(--muted);font-size:.8rem;font-weight:400}.opt .tick{width:1.4rem;height:1.4rem;border-radius:50%;display:grid;place-items:center;background:var(--field);box-shadow:var(--press);color:transparent;flex:none}
.opt input:checked+span.b .tick{background:var(--brand);color:var(--on-brand);box-shadow:none}
.pills{display:flex;flex-wrap:wrap;gap:.5rem}.pills .opt span.b{padding:.5rem .95rem;min-height:0;border-radius:999px;font-weight:600;font-size:.88rem}
.pay{width:100%;margin-top:1.25rem;display:flex;align-items:center;justify-content:center;gap:.5rem;padding:1rem;border:0;border-radius:999px;font:inherit;font-weight:800;font-size:1.05rem;cursor:pointer;color:var(--on-brand);
 background:var(--brand);background:linear-gradient(180deg,color-mix(in srgb,var(--brand) 82%,#fff),var(--brand) 55%,color-mix(in srgb,var(--brand) 88%,#000));
 box-shadow:inset 0 4px 8px rgba(255,255,255,.3),inset 0 -5px 10px rgba(0,0,0,.22),0 12px 22px -8px color-mix(in srgb,var(--brand) 70%,transparent);transition:transform .12s}
.pay:active{transform:translateY(1px) scale(.995)}.pay[disabled]{opacity:.7;cursor:wait}
.spin{width:1.1em;height:1.1em;border:2.5px solid currentColor;border-right-color:transparent;border-radius:50%;animation:sp .8s linear infinite;display:none}.pay[disabled] .spin,.busy .spin{display:inline-block}
@keyframes sp{to{transform:rotate(360deg)}}@media(prefers-reduced-motion:reduce){.spin{animation-duration:2.4s}.pay{transition:none}}
.err{color:var(--bad);font-size:.9rem;margin-top:.7rem;min-height:1.3rem;font-weight:600}
.trust{list-style:none;margin:1rem 0 0;padding:0;display:grid;gap:.45rem;font-size:.85rem;color:var(--muted)}.trust li{display:flex;gap:.5rem;align-items:flex-start}.trust svg{color:var(--ok);margin-top:.15rem}
.next{margin:0;padding:0;list-style:none;display:grid;gap:.7rem;counter-reset:s}.next li{display:flex;gap:.7rem;align-items:flex-start;font-size:.9rem}
.next li:before{counter-increment:s;content:counter(s);display:grid;place-items:center;width:1.7rem;height:1.7rem;flex:none;border-radius:50%;background:var(--field);box-shadow:var(--press);font-weight:700;font-size:.8rem}
.by{text-align:center;color:var(--muted);font-size:.8rem;margin-top:1.1rem}
a.btn{display:inline-flex;align-items:center;justify-content:center;gap:.5rem;margin-top:1rem;padding:.85rem 1.4rem;border-radius:999px;background:var(--brand);color:var(--on-brand);text-decoration:none;font-weight:800;
 box-shadow:inset 0 4px 8px rgba(255,255,255,.28),inset 0 -5px 10px rgba(0,0,0,.2),0 10px 20px -8px color-mix(in srgb,var(--brand) 70%,transparent)}
.badge{display:grid;place-items:center;width:4rem;height:4rem;border-radius:50%;margin:0 auto .8rem;background:var(--field);box-shadow:var(--press);color:var(--brand)}.badge svg{width:1.9rem;height:1.9rem}
.badge.ok{color:var(--ok)}.badge.bad{color:var(--bad)}.center{text-align:center}
.ref{font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:.8rem;color:var(--muted);margin-top:.9rem}
</style>
