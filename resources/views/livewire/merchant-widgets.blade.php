{{-- nx:converted --}}
{{-- Naara Widgets (merchant dashboard tab): keys, snippet, WordPress plugin, funnel and earnings. MASTER-ONLY. --}}
<div>
<x-nx.page class="ns-pg ns-pg--wide">
    <div class="ns-pg__head">
        <div>
            <a href="{{ route('merchant.dashboard') }}" wire:navigate class="ns-pg__back"><x-nx.icon name="left" /> My Storefront</a>
            <h1 class="ns-h1" style="margin-top:6px">Widgets</h1>
            <p class="ns-sub" style="margin:4px 0 0">Sell Naara eSIMs and gift cards on your own site. You earn your margin on every sale; Naara handles payment, delivery and support.</p>
        </div>
    </div>

    {{-- How it fits together: your site, the widget, the orchestration and the resources behind it. --}}
    <x-merchant-widget-illustration class="ns-pg__card ns-ring" style="margin-top:12px;padding:0;overflow:hidden" />

    @if ($notice)<div class="ns-pg__card ns-ring" role="status" style="margin-top:12px">{{ $notice }}</div>@endif
    @if ($error)<div class="ns-pg__card ns-ring" role="alert" style="margin-top:12px"><span class="ns-pg__fielderr">{{ $error }}</span></div>@endif

    @unless ($hasAccess)
        <div class="ns-pg__card ns-ring mw-pay" style="margin-top:12px" data-mw-paywall>
            <h2 class="ns-pg__h2">Unlock Naara Widgets</h2>
            <div class="mw-pay__price">${{ number_format($price, 2) }} <small>one-time, yours for good</small></div>
            <p class="ns-sub" style="margin:0">Pay once from your wallet to create widget keys, copy the embed snippet and download the WordPress plugin. Shoppers on your site never pay anything extra, and you keep your margin on every sale.</p>
            <ul class="ns-sub" style="margin:0;padding-inline-start:18px;display:grid;gap:3px">
                <li>Up to the platform key limit, each locked to your own domains</li>
                <li>Live analytics for views, checkouts and orders</li>
                <li>One charge: pressing the button twice never bills you twice</li>
            </ul>
            @if ($spendable >= $price)
                <button type="button" wire:click="buyAccess" wire:confirm="Pay ${{ number_format($price, 2) }} from your wallet to unlock Widgets?" wire:loading.attr="disabled" wire:target="buyAccess" class="ns-cta ns-cta--pill" style="justify-self:start"><span wire:loading.remove wire:target="buyAccess">Unlock for ${{ number_format($price, 2) }}</span><span wire:loading wire:target="buyAccess">Unlocking...</span></button>
            @else
                <p class="ns-sub" style="margin:0">Your wallet needs ${{ number_format($price - $spendable, 2) }} more. <a href="{{ route('wallet') }}" wire:navigate>Top up your wallet</a>, then come back.</p>
            @endif
        </div>
    @endunless

    <div class="ns-pg__card ns-ring" style="margin-top:12px">
        <h2 class="ns-pg__h2" style="margin-bottom:8px">Get selling in three steps</h2>
        <ol class="ns-sub" style="margin:0;padding-inline-start:1.2em;display:grid;gap:4px">
            <li><b>Create a key</b> and list the exact website address it may run on.</li>
            <li><b>Paste the snippet</b> into your page, or install the WordPress plugin.</li>
            <li><b>Done.</b> Your shoppers pick a plan, pay on a secure page and get it by email. You earn your margin on each sale.</li>
        </ol>
    </div>

    <div class="ns-pg__split">
        <div class="ns-pg__stack">
            {{-- Keys --}}
            <div class="ns-pg__card ns-ring" style="margin-top:0">
                <h2 class="ns-pg__h2" style="margin-bottom:10px">Your widget keys</h2>
                @forelse ($keys as $k)
                    <div wire:key="wk-{{ $k->id }}" class="ns-pg__lrow" style="padding-inline:0;gap:8px;flex-wrap:wrap">
                        <button type="button" wire:click="select({{ $k->id }})" class="ns-pg__act ns-pg__act--link" aria-pressed="{{ $selected?->id === $k->id ? 'true' : 'false' }}" style="text-align:start">
                            <b>{{ $k->label }}</b> <small class="ns-sub">{{ implode(', ', $k->allowedDomains()) }}</small>
                        </button>
                        <span class="ns-st {{ $k->status === 'active' ? 'ns-st--ok' : '' }}">{{ $k->status === 'active' ? 'Active' : 'Revoked' }}</span>
                        @if ($k->status === 'active')
                            <button type="button" wire:click="rotate({{ $k->id }})" wire:loading.attr="disabled" class="ns-pg__act ns-pg__act--link">Rotate</button>
                            <button type="button" wire:click="revoke({{ $k->id }})" wire:confirm="Revoke this key? Widgets using it stop working immediately." wire:loading.attr="disabled" class="ns-pg__act ns-pg__act--link">Revoke</button>
                        @endif
                    </div>
                @empty
                    <p class="ns-sub" style="margin:0">No widget keys yet. Create one to get your embed snippet.</p>
                @endforelse
            </div>

            {{-- Create --}}
            <form wire:submit="create" class="ns-pg__card ns-ring">
                <h2 class="ns-pg__h2" style="margin-bottom:10px">New widget key</h2>
                <div class="ns-pg__form">
                    <div><label class="ns-pg__lbl" for="wk-label">Label</label><input id="wk-label" type="text" wire:model="label" class="ns-input" placeholder="My website"></div>
                    <div><label class="ns-pg__lbl" for="wk-domains">Allowed domains</label>
                        <textarea id="wk-domains" wire:model="domains" rows="2" class="ns-input" placeholder="mystore.com, www.mystore.com"></textarea>
                        <small class="ns-sub">The widget only works on these exact addresses (no wildcards). Add www and non-www separately.</small></div>
                    <fieldset style="border:0;padding:0;margin:0"><legend class="ns-pg__lbl">What can it sell?</legend>
                        <label style="margin-inline-end:16px"><input type="checkbox" wire:model="scopes" value="esim"> eSIM data plans</label>
                        <label style="margin-inline-end:16px"><input type="checkbox" wire:model="scopes" value="number"> Verification numbers</label>
                        <label><input type="checkbox" wire:model="scopes" value="giftcard"> Gift cards</label>
                        @unless ($numbersOn)<small class="ns-sub" style="display:block;margin-top:4px">Verification numbers are not switched on for widgets on this platform yet. Your choice is saved and they appear as soon as they are.</small>@endunless</fieldset>
                </div>
                <button type="submit" wire:loading.attr="disabled" wire:target="create" @disabled(! $hasAccess) class="ns-cta ns-cta--pill" style="margin-top:12px"><x-nx.icon name="plus" /> Create key</button>
            </form>
        </div>

        <div class="ns-pg__stack">
            @if ($selected)
                {{-- Snippet --}}
                <div class="ns-pg__card ns-ring" style="margin-top:0">
                    <h2 class="ns-pg__h2" style="margin-bottom:10px">Embed on your site</h2>
                    @if ($snippet)
                        <div style="display:flex;gap:12px;flex-wrap:wrap;margin-bottom:10px">
                            <label>Show <select wire:model.live="snippetKind" class="ns-input" style="width:auto"><option value="">Everything this key allows</option><option value="esim">eSIM data plans</option><option value="number">Verification numbers</option><option value="giftcard">Gift cards</option></select></label>
                            <label>Checkout <select wire:model.live="snippetMode" class="ns-input" style="width:auto"><option value="modal">Pop-up</option><option value="redirect">Full page</option></select></label>
                        </div>
                        <div x-data="{ copied: false }">
                            <pre class="ns-input" style="white-space:pre-wrap;word-break:break-all;margin:0;font-size:12.5px" id="wk-snippet">{{ $snippet }}</pre>
                            <button type="button" class="ns-cta ns-cta--pill ns-cta--sm" style="margin-top:8px" x-on:click="navigator.clipboard.writeText(document.getElementById('wk-snippet').innerText).then(() => { copied = true; setTimeout(() => copied = false, 1800) })">
                                <span x-show="!copied">Copy snippet</span><span x-show="copied" x-cloak>Copied</span>
                            </button>
                        </div>
                        <p class="ns-sub" style="margin:10px 0 0">Only your public key is in this snippet. It is safe to publish: Naara checks the website address on every request.</p>
                    @elseif (! $hasAccess)
                        <p class="ns-sub" style="margin:0">Unlock Widgets above to see the embed snippet for this key.</p>
                    @else
                        <p class="ns-sub" style="margin:0">This key is revoked.</p>
                    @endif
                </div>

                {{-- What shoppers get --}}
                <div class="ns-pg__card ns-ring">
                    <h2 class="ns-pg__h2" style="margin-bottom:6px">What your customers get</h2>
                    <ul class="ns-sub" style="margin:0;padding-inline-start:18px;display:grid;gap:4px">
                        <li><b>Clear tabs.</b> eSIM data, verification numbers and gift cards each say in plain words what they are, so nobody is unsure what they are buying.</li>
                        <li><b>Live number codes.</b> A verification number shows its SMS code on your page the moment it arrives, and is refunded automatically if none comes.</li>
                        @if ($accountOn)
                            <li><b>A "My orders" area.</b> Customers sign in with an emailed code (no password) to see their eSIMs and numbers, copy codes, download their data or ask to delete it. It only ever shows orders placed on your site, so they come back to you.</li>
                            <li><b>Your own page.</b> On a plain website use <code>data-start="account"</code> on the snippet for a dedicated My orders page. On WordPress use the shortcode <code>[naara_account]</code>.</li>
                        @endif
                        <li><b>Safe browser features.</b> Copy and share buttons, one-tap eSIM install on phones, and an optional "tell me when my code arrives" notification the customer chooses to allow. Nothing is stored until they sign in, and every call is checked against your allowed domains.</li>
                    </ul>
                </div>

                {{-- WordPress --}}
                @if ($selected->status === 'active' && $hasAccess)
                    <div class="ns-pg__card ns-ring">
                        <h2 class="ns-pg__h2" style="margin-bottom:6px">WordPress</h2>
                        <p class="ns-sub" style="margin:0 0 10px">Prefer WordPress? Upload the plugin under Plugins, Add New, Upload. It comes pre-filled with this key, and gives you a block and shortcodes, including [naara_account] for a My orders page. Version {{ $pluginVersion }}.</p>
                        <a href="{{ $pluginUrl ?: route('merchant.widgets.plugin', ['key' => $selected->id]) }}" class="ns-cta ns-cta--pill ns-cta--sm"><x-nx.icon name="download" /> Download plugin</a>
                    </div>
                @endif

            @endif
        </div>
    </div>
    {{-- Analytics: full width, below the keys --}}
    @if ($selected && $analytics)
        @php
            $a = $analytics; $t = $a['totals']; $ser = $a['series']; $n = max(1, count($ser));
            $W = 600; $H = 170; $padB = 18; $plotH = $H - $padB - 8;
            $maxV = max(1, max(array_column($ser, 'views')), max(array_column($ser, 'checkouts')));
            $maxO = max(1, max(array_column($ser, 'orders')));
            $step = $W / $n;
            $pts = [];
            foreach ($ser as $i => $d) {
                $x = round($i * $step + $step / 2, 1);
                $pts[] = [$x, round(8 + $plotH - $d['views'] / $maxV * $plotH, 1), round(8 + $plotH - $d['checkouts'] / $maxV * $plotH, 1)];
            }
            $first = $pts[0] ?? [0, 0, 0]; $last = end($pts) ?: [0, 0, 0];
            $area = 'M'.$first[0].','.($H - $padB).' '.implode(' ', array_map(fn ($p) => 'L'.$p[0].','.$p[1], $pts)).' L'.$last[0].','.($H - $padB).' Z';
            $line = implode(' ', array_map(fn ($p) => $p[0].','.$p[2], $pts));
            $delta = function ($v) {
                if ($v === null) return ['flat', 'No earlier data'];
                if ($v > 0) return ['up', '+'.$v.'% vs previous'];
                if ($v < 0) return ['down', $v.'% vs previous'];
                return ['flat', 'Same as previous'];
            };
            $statusLabel = ['completed' => 'Completed', 'pending_payment' => 'Awaiting payment', 'fulfilling' => 'Being delivered', 'failed' => 'Failed', 'refunded' => 'Refunded', 'held' => 'Held for review'];
            $kindLabel = ['esim' => 'eSIM data', 'number' => 'Verification numbers', 'giftcard' => 'Gift cards'];
        @endphp
        <div class="ns-pg__card ns-ring" style="margin-top:12px" data-mw-analytics>
            <div class="mw-an__bar">
                <div>
                    <h2 class="ns-pg__h2" style="margin:0">Performance: {{ $selected->label }}</h2>
                    <p class="ns-sub" style="margin:2px 0 0">{{ \Illuminate\Support\Carbon::parse($a['from'])->format('j M') }} to {{ \Illuminate\Support\Carbon::parse($a['to'])->format('j M Y') }}</p>
                </div>
                <div class="mw-an__seg" role="group" aria-label="Time range">
                    @foreach ([7 => '7 days', 30 => '30 days', 90 => '90 days'] as $d => $lbl)
                        <button type="button" wire:click="setRange({{ $d }})" aria-pressed="{{ $range === $d ? 'true' : 'false' }}">{{ $lbl }}</button>
                    @endforeach
                </div>
            </div>

            <div class="mw-an__kpis">
                @foreach ([
                    ['Views', number_format($t['views']), $a['delta']['views']],
                    ['Orders', number_format($t['orders']), $a['delta']['orders']],
                    ['Sales value', '$'.number_format($t['revenue'], 2), $a['delta']['revenue']],
                    ['Conversion', $t['conversion'] !== null ? $t['conversion'].'%' : '-', null],
                ] as [$lbl, $val, $dl])
                    @php [$tone, $txt] = $delta($dl); @endphp
                    <div class="mw-an__kpi"><small>{{ $lbl }}</small><b>{{ $val }}</b>
                        @if ($lbl !== 'Conversion')<span class="mw-an__delta mw-an__delta--{{ $tone }}">{{ $txt }}</span>@else<span class="mw-an__delta mw-an__delta--flat">Orders per checkout opened</span>@endif
                    </div>
                @endforeach
            </div>

            @if (! $a['has_data'])
                <div class="mw-an__empty">
                    <b style="color:rgb(var(--nx-text))">Nothing to chart yet</b>
                    <p class="ns-sub" style="margin:4px 0 0">As soon as your widget is live and someone looks at it, views, checkouts and orders appear here.</p>
                </div>
            @else
                <svg class="mw-an__chart" viewBox="0 0 {{ $W }} {{ $H }}" preserveAspectRatio="none" role="img" aria-label="Daily views, checkouts and orders over the last {{ $a['days'] }} days">
                    <title>Daily views, checkouts and orders</title>
                    @foreach ([0.25, 0.5, 0.75] as $g)
                        <line x1="0" x2="{{ $W }}" y1="{{ round(8 + $plotH * $g, 1) }}" y2="{{ round(8 + $plotH * $g, 1) }}" stroke="rgb(var(--nx-line))" stroke-width="1" vector-effect="non-scaling-stroke" />
                    @endforeach
                    <path d="{{ $area }}" fill="rgb(var(--nx-teal) / .16)" />
                    @foreach ($ser as $i => $d)
                        @if ($d['orders'] > 0)
                            @php $bh = round($d['orders'] / $maxO * $plotH * .8, 1); @endphp
                            <rect x="{{ round($i * $step + $step * .2, 1) }}" y="{{ round($H - $padB - $bh, 1) }}" width="{{ round($step * .6, 1) }}" height="{{ $bh }}" rx="2" fill="rgb(var(--nx-teal))"><title>{{ $d['date'] }}: {{ $d['orders'] }} order(s), ${{ number_format($d['revenue'], 2) }}</title></rect>
                        @endif
                    @endforeach
                    <polyline points="{{ $line }}" fill="none" stroke="rgb(var(--nx-gold))" stroke-width="2" stroke-linejoin="round" vector-effect="non-scaling-stroke" />
                </svg>
                <div class="mw-an__legend"><span><i style="background:rgb(var(--nx-teal) / .35)"></i>Views</span><span><i style="background:rgb(var(--nx-gold))"></i>Checkouts opened</span><span><i style="background:rgb(var(--nx-teal))"></i>Orders completed</span></div>

                <h3 class="ns-pg__h2" style="margin:18px 0 0;font-size:15px">Where shoppers drop off</h3>
                <div class="mw-an__fun" data-mw-funnel>
                    @foreach ($a['funnel'] as $f)
                        <div class="mw-an__fun-row"><span>{{ $f['label'] }}</span><div class="mw-an__track"><div class="mw-an__fill" style="width:{{ max(1, $f['pct']) }}%"></div></div><span style="text-align:end;font-variant-numeric:tabular-nums"><b>{{ number_format($f['value']) }}</b> <small class="ns-sub">{{ $f['pct'] }}%</small></span></div>
                    @endforeach
                </div>

                <div class="mw-an__cols">
                    <div class="ns-pg__stat ns-ring">
                        <small>How orders ended</small>
                        <ul class="mw-an__list">
                            @forelse ($a['by_status'] as $st => $c)
                                <li><span>{{ $statusLabel[$st] ?? ucfirst(str_replace('_', ' ', $st)) }}</span><span>{{ $c }}</span></li>
                            @empty
                                <li><span>No orders in this period</span><span>0</span></li>
                            @endforelse
                        </ul>
                    </div>
                    <div class="ns-pg__stat ns-ring">
                        <small>What sold</small>
                        <ul class="mw-an__list">
                            @forelse ($a['by_kind'] as $k => $row)
                                <li><span>{{ $kindLabel[$k] ?? ucfirst($k) }}</span><span>{{ $row['orders'] }} sold · ${{ number_format($row['revenue'], 2) }}</span></li>
                            @empty
                                <li><span>No completed sales yet</span><span>-</span></li>
                            @endforelse
                            @if ($t['aov'] !== null)<li><span>Average order</span><span>${{ number_format($t['aov'], 2) }}</span></li>@endif
                        </ul>
                    </div>
                    <div class="ns-pg__stat ns-ring">
                        <small>Top sites</small>
                        <ul class="mw-an__list">
                            @forelse ($a['origins'] as $host => $c)
                                <li><span>{{ $host }}</span><span>{{ $c }} orders</span></li>
                            @empty
                                <li><span>No orders yet</span><span>-</span></li>
                            @endforelse
                        </ul>
                    </div>
                    <div class="ns-pg__stat ns-ring">
                        <small>Your margin, last 30 days</small>
                        <ul class="mw-an__list">
                            @foreach (['widget:esim' => 'eSIM data', 'widget:number' => 'Verification numbers', 'widget:giftcard' => 'Gift cards'] as $src => $lbl)
                                <li><span>{{ $lbl }}</span><span>${{ number_format((float) ($earned[$src] ?? 0), 2) }}</span></li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            @endif

            <h3 class="ns-pg__h2" style="margin:18px 0 0;font-size:15px">Latest orders</h3>
            @if ($a['recent'])
                <div class="mw-an__scroll" data-mw-orders>
                    <table class="mw-an__tbl">
                        <thead><tr><th>When</th><th>What</th><th>Shopper</th><th>Value</th><th>Status</th></tr></thead>
                        <tbody>
                            @foreach ($a['recent'] as $o)
                                <tr wire:key="mwo-{{ $o['id'] }}">
                                    <td>{{ $o['at']->diffForHumans() }}</td>
                                    <td>{{ $kindLabel[$o['kind']] ?? ucfirst($o['kind']) }}</td>
                                    <td>{{ $o['buyer'] }}</td>
                                    <td>${{ number_format($o['value'], 2) }}</td>
                                    <td><span class="ns-st {{ $o['status'] === 'completed' ? 'ns-st--ok' : '' }}">{{ $statusLabel[$o['status']] ?? $o['status'] }}</span>@if ($o['note'])<small class="ns-sub" style="display:block">{{ $o['note'] }}</small>@endif</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <p class="ns-sub" style="margin:6px 0 0">No orders on this key yet.</p>
            @endif
            <p class="ns-sub" style="margin:12px 0 0">Earnings land in your normal merchant balance and follow the usual payout rules. Shopper emails are partly hidden.</p>
        </div>
    @endif
</x-nx.page>
</div>
