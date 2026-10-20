{{-- Brand dashboard on the skin system (S3 Batch 6): plan/status header, delivery vs guarantee, profile, handles, videos, plan + cancel. --}}
<div>
<x-nx.page>
    {{-- Dashboard header: plan, status and next billing at a glance. A deliberate dark brand panel in every skin and mode. --}}
    @php($statusTone = match ($brand->listing_status) { 'active' => 'is-ok', 'paused_billing' => 'is-warn', default => '' })
    <div class="ns-pg__hero ns-pg__hero--left">
        <div style="max-width:42rem;margin-inline:auto">
            <span class="ns-pg__kicker">Brand dashboard</span>
            <h1 style="display:flex;flex-wrap:wrap;align-items:center;gap:8px">
                {{ $brand->brand_name ?: 'My brand listing' }}
                @if ($brand->isNaaraPro())<x-naara-pro-badge size="lg" label tone="light" />@endif
            </h1>
            <div class="ns-pg__herometa">
                <span class="ns-st ns-st--ondark {{ $statusTone }}">{{ ucwords(str_replace('_', ' ', $brand->listing_status)) }}</span>
                <span>{{ $brand->plan?->name ?? 'No plan' }}@if ($brand->hasProGrant() && ! $subscription) · Complimentary Naara Pro @endif</span>
                @if ($subscription)<span>· Next billing {{ $subscription->next_billing_at?->format('d M Y') }}</span>@endif
            </div>
        </div>
    </div>

    <div class="ns-pg__sheet">
    <div class="ns-pg ns-pg--mid" style="padding:0">

        @if ($flash)<div class="ns-pg__callout ns-pg__callout--ok" role="status"><x-nx.icon name="check" /><div><p style="margin:0;color:rgb(var(--nx-text))">{{ $flash }}</p></div></div>@endif
        @if ($error)<div class="ns-pg__err" role="alert" style="margin-top:0">{{ $error }}</div>@endif

        @if ($trial)
            <div class="ns-pg__callout {{ $trial['unfunded'] ? '' : 'ns-pg__callout--ok' }}" role="status" style="margin-top:16px" data-tour="brand.trial">
                <x-nx.icon name="{{ $trial['unfunded'] ? 'alert-triangle' : 'sparkles' }}" />
                <div style="flex:1">
                    @if ($trial['unfunded'])
                        <b style="color:rgb(var(--nx-text))">Your wallet could not cover {{ $trial['plan'] }} (${{ number_format($trial['price'], 2) }})</b>
                        <p style="margin:2px 0 0">Your listing is paused and nothing has been charged. Add funds and press Reactivate{{ $trial['sub']->next_retry_at ? ', or we will try again on '.$trial['sub']->next_retry_at->format('d M Y') : '' }}.</p>
                    @else
                        <b style="color:rgb(var(--nx-text))">Free trial of {{ $trial['plan'] }}: ends {{ $trial['sub']->trial_ends_at->format('d M Y, H:i') }}</b>
                        <p style="margin:2px 0 0">@if ($trial['sub']->auto_convert) Then ${{ number_format($trial['price'], 2) }} is taken from your wallet and the plan continues monthly.@else Auto-start is off, so the trial will simply end with no charge.@endif @if ($trial['short'] && $trial['sub']->auto_convert) <b>Your wallet is below that amount; top up before the trial ends.</b>@endif</p>
                    @endif
                    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:10px">
                        @if ($trial['unfunded'])
                            <a href="{{ route('wallet') }}" wire:navigate class="ns-cta ns-cta--pill">Fund wallet</a>
                            <button type="button" wire:click="trialReactivate" wire:loading.attr="disabled" wire:target="trialReactivate" class="ns-cta ns-cta--pill ns-cta--ghost"><span wire:loading.remove wire:target="trialReactivate">Reactivate</span><span wire:loading wire:target="trialReactivate"><x-ui.spinner class="h-4 w-4" /></span></button>
                        @else
                            <button type="button" wire:click="trialConvertNow" wire:loading.attr="disabled" wire:target="trialConvertNow" wire:confirm="Start {{ $trial['plan'] }} now and charge ${{ number_format($trial['price'], 2) }}?" class="ns-cta ns-cta--pill"><span wire:loading.remove wire:target="trialConvertNow">Start plan now</span><span wire:loading wire:target="trialConvertNow"><x-ui.spinner class="h-4 w-4" /></span></button>
                            <button type="button" wire:click="trialAuto({{ $trial['sub']->auto_convert ? 'false' : 'true' }})" wire:loading.attr="disabled" class="ns-cta ns-cta--pill ns-cta--ghost">{{ $trial['sub']->auto_convert ? 'Turn off auto-start' : 'Turn on auto-start' }}</button>
                        @endif
                        <button type="button" wire:click="trialCancel" wire:loading.attr="disabled" wire:target="trialCancel" wire:confirm="Cancel the trial? Your listing will switch off. You will not be charged." class="ns-cta ns-cta--pill ns-cta--ghost">Cancel trial</button>
                    </div>
                </div>
            </div>
        @endif

        @if (class_exists(\App\Services\Brands\BrandConnectService::class) && \App\Services\Brands\BrandConnectService::enabled())
            <a href="{{ route('brand.connections') }}" wire:navigate class="ns-pg__card ns-ring" style="margin-top:16px;display:flex;align-items:center;justify-content:space-between;gap:12px;text-decoration:none" data-brand-connections-link><span><b style="color:rgb(var(--nx-text))">Connections</b><span class="ns-pg__hint" style="display:block;margin:2px 0 0">See who connected, accept or decline, and set your welcome message.</span></span><x-nx.icon name="right" /></a>
        @endif

        @if ($card)
            <div class="ns-pg__card ns-ring" style="margin-top:16px" data-tour="brand.card">
                <div style="display:flex;gap:16px;align-items:center;flex-wrap:wrap">
                    @if ($card['qr'])
                        <div class="[&>svg]:h-full [&>svg]:w-full" style="width:112px;height:112px;flex:none;background:white;border-radius:12px;padding:4px" role="img" aria-label="QR code for your Business Card">{!! $card['qr'] !!}</div>
                    @endif
                    <div style="flex:1;min-width:12rem">
                        <b style="color:rgb(var(--nx-text))">Your Business Card</b>
                        @if ($card['live'])
                            <p class="ns-pg__hint" style="margin:2px 0 0;word-break:break-all">{{ $card['url'] }}</p>
                            <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:10px">
                                <a href="{{ $card['url'] }}" target="_blank" rel="noopener" class="ns-cta ns-cta--pill ns-cta--ghost">View</a>
                                <button type="button" x-data="{ done: false }" @click="navigator.clipboard.writeText(@js($card['url'])).then(() => { done = true; setTimeout(() => done = false, 1800) })" class="ns-cta ns-cta--pill ns-cta--ghost"><span x-show="!done">Copy link</span><span x-show="done" x-cloak>Copied</span></button>
                                <a href="{{ route('business-card.qr', [$card['code'], 'png']) }}" class="ns-cta ns-cta--pill ns-cta--ghost">QR (PNG)</a>
                                <a href="{{ route('business-card.qr', [$card['code'], 'svg']) }}" class="ns-cta ns-cta--pill ns-cta--ghost">QR (SVG)</a>
                                <a href="{{ route('business-card.pdf', $card['code']) }}" class="ns-cta ns-cta--pill ns-cta--ghost" data-card-pdf>Print card (PDF)</a>
                            </div>
                            <div style="margin-top:14px" data-card-address>
                                <label class="ns-pg__hint" for="card-slug" style="display:block;margin:0 0 4px">Readable address (optional)</label>
                                <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
                                    <span class="ns-pg__hint" style="margin:0">{{ rtrim(preg_replace('#/[^/]*$#', '/', $card['idUrl']), '/') }}/</span>
                                    <input id="card-slug" type="text" wire:model="cardSlug" maxlength="30" autocomplete="off" placeholder="yourbrand" class="ns-input" style="flex:1;min-width:9rem">
                                    <button type="button" wire:click="saveCardSlug" wire:loading.attr="disabled" wire:target="saveCardSlug" class="ns-cta ns-cta--pill ns-cta--ghost">Save</button>
                                </div>
                                <p class="ns-pg__hint" style="margin:6px 0 0">Your Naara ID address <span style="word-break:break-all">{{ $card['idUrl'] }}</span> and your short link <b style="color:rgb(var(--nx-text))" data-card-short>{{ $card['short'] }}</b> always work. Changing the readable name stops the old readable name working.</p>
                            </div>
                            @if ($card['snippets'])
                                <details style="margin-top:12px" data-card-snippets>
                                    <summary class="ns-pg__hint" style="cursor:pointer;margin:0"><b style="color:rgb(var(--nx-text))">Bio lines to paste</b> (each fits its platform)</summary>
                                    <ul style="list-style:none;margin:8px 0 0;padding:0;display:grid;gap:8px">
                                        @foreach ($card['snippets'] as $sn)
                                            <li class="ns-pg__hint" style="margin:0;display:flex;gap:8px;align-items:flex-start;justify-content:space-between" x-data="{ done: false }">
                                                <span style="min-width:0"><b style="color:rgb(var(--nx-text))">{{ $sn['label'] }}</b> <span>({{ mb_strlen($sn['text']) }}/{{ $sn['limit'] }})</span><br><span style="word-break:break-word">{{ $sn['text'] }}</span></span>
                                                <button type="button" @click="navigator.clipboard.writeText(@js($sn['text'])).then(() => { done = true; setTimeout(() => done = false, 1800) })" class="ns-cta ns-cta--pill ns-cta--ghost" style="flex:none"><span x-show="!done">Copy</span><span x-show="done" x-cloak>Copied</span></button>
                                            </li>
                                        @endforeach
                                    </ul>
                                </details>
                            @endif
                            @if ($card['stats'])
                                <p class="ns-pg__hint" style="margin:10px 0 0" data-card-stats>Last 30 days: <b style="color:rgb(var(--nx-text))">{{ number_format($card['stats']['view']) }}</b> views, <b style="color:rgb(var(--nx-text))">{{ number_format($card['stats']['qr_scan']) }}</b> QR scans, <b style="color:rgb(var(--nx-text))">{{ number_format($card['stats']['vcard']) }}</b> contact saves. Counts only; we never see who visited.</p>
                            @endif
                        @else
                            <p class="ns-pg__hint" style="margin:2px 0 0">Your card and QR code go live the moment your listing does. It will be at {{ $card['url'] }} and that address never changes, so printing it is safe.</p>
                        @endif
                    </div>
                </div>
            </div>
        @endif

        @if (class_exists(\App\Livewire\BrandSetupWizard::class))
            @if ($visibility['state'] !== 'published')
                <div class="ns-pg__card ns-ring" style="margin-top:16px;display:flex;align-items:center;gap:14px;flex-wrap:wrap" data-brand-wizard-launch>
                    <img src="{{ asset('images/illustrations/br-identity.svg') }}" alt="" width="240" height="180" style="width:96px;height:auto;flex:none">
                    <div style="flex:1;min-width:12rem"><b style="color:rgb(var(--nx-text))">Set up your listing step by step</b><p class="ns-pg__hint" style="margin:2px 0 0">Brand, logo and cover, social handles, then publish. It saves as you go and picks up where you left off.</p></div>
                    <button type="button" onclick="Livewire.dispatch('brand-setup-open')" class="ns-cta ns-cta--pill">{{ $visibility['progress']['done'] > 0 ? 'Continue setup' : 'Start setup' }}</button>
                </div>
            @endif
            <livewire:brand-setup-wizard />
        @endif

        @php($pct = $visibility['progress']['total'] > 0 ? (int) round($visibility['progress']['done'] / $visibility['progress']['total'] * 100) : 100)
        <div class="ns-pg__card ns-ring" style="margin-top:16px" data-tour="brand.completeness">
            <div style="display:flex;align-items:center;gap:14px;flex-wrap:wrap">
                <svg viewBox="0 0 36 36" width="54" height="54" role="img" aria-label="{{ $pct }} percent complete" style="flex:none">
                    <circle cx="18" cy="18" r="15.5" fill="none" stroke="rgb(var(--nx-line-strong))" stroke-width="3"/>
                    <circle cx="18" cy="18" r="15.5" fill="none" stroke="rgb(var(--nx-teal))" stroke-width="3" stroke-linecap="round" stroke-dasharray="{{ round(97.4 * $pct / 100, 1) }} 97.4" transform="rotate(-90 18 18)"/>
                    <text x="18" y="21.5" text-anchor="middle" font-size="9" font-weight="700" fill="rgb(var(--nx-text))">{{ $pct }}%</text>
                </svg>
                <div style="flex:1;min-width:12rem">
                    <b style="color:rgb(var(--nx-text))">{{ $visibility['grandfathered'] ? 'Complete your profile' : ($visibility['state'] === 'published' ? 'Your profile' : 'Publish your profile') }}</b>
                    <p class="ns-pg__hint" style="margin:2px 0 0">{{ $visibility['message'] }}</p>
                </div>
                @if ($visibility['canPublish'])
                    <button type="button" wire:click="publish" wire:loading.attr="disabled" wire:target="publish" class="ns-cta ns-cta--pill">Publish</button>
                @endif
                <a href="{{ route('rewards.hunt.profile', ['brandPartner' => $brand->id, 'preview' => 1]) }}" target="_blank" rel="noopener" class="ns-cta ns-cta--pill ns-cta--ghost ns-cta--sm">Preview as visitor</a>
            </div>
            <ul style="list-style:none;margin:12px 0 0;padding:0;display:grid;gap:6px">
                @foreach ($visibility['requirements'] as $r)
                    <li style="display:flex;align-items:center;gap:8px;color:rgb(var(--nx-text-2));font-size:14px" @if (! $r['met']) title="{{ $r['hint'] }}" @endif>
                        <x-nx.icon :name="$r['met'] ? 'check' : 'info'" style="color:{{ $r['met'] ? 'rgb(var(--nx-ok))' : 'rgb(var(--nx-text-3))' }}" />
                        <span style="{{ $r['met'] ? '' : 'color:rgb(var(--nx-text))' }}">{{ $r['label'] }}</span>
                        <small class="ns-small">{{ $r['required'] ? 'required' : 'recommended' }}{{ $r['met'] ? '' : ': '.$r['hint'] }}</small>
                    </li>
                @endforeach
            </ul>
        </div>

        {{-- Profile visitors + chat switch (master only: the visit service does not exist in a fork) --}}
        @if ($visitStats)
            <div class="ns-pg__card ns-ring" data-brand-visitors>
                <h2 class="ns-bh__h"><x-nx.icon name="users" /> Profile visitors</h2>
                <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:10px;text-align:center">
                    @foreach ([['Last 24 hours', $visitStats['last24h']], ['Today', $visitStats['today']], ['Last 7 days', $visitStats['week']]] as [$l, $n])
                        <div><b style="display:block;font-size:24px;color:rgb(var(--nx-text))">{{ number_format($n) }}</b><small class="ns-small">{{ $l }}</small></div>
                    @endforeach
                </div>
                <p class="ns-pg__hint">{{ $visitStats['members24h'] }} Naara {{ \Illuminate\Support\Str::plural('member', $visitStats['members24h']) }} and {{ $visitStats['guests24h'] }} signed-out {{ \Illuminate\Support\Str::plural('visitor', $visitStats['guests24h']) }} in the last 24 hours. Each person counts once a day; your own views are not counted.</p>
                @if (count($visitors))
                    <ul style="list-style:none;margin:8px 0 0;padding:0;display:grid;gap:6px">
                        @foreach ($visitors as $v)
                            <li class="ns-pg__metarow"><span>@if ($v['handle'])<a href="{{ route('n2n.profile', ['handle' => $v['handle']]) }}" wire:navigate>{{ $v['name'] }}</a>@else{{ $v['name'] }}@endif</span><span>{{ $v['at']->diffForHumans() }}</span></li>
                        @endforeach
                    </ul>
                @endif
            </div>
            <div class="ns-pg__card ns-ring" data-brand-chat-mode>
                <h2 class="ns-bh__h"><x-nx.icon name="chat" /> Who can chat with you</h2>
                <div class="ns-pg__form">
                    <select wire:model="chatMode" aria-label="Who can chat with this brand" class="ns-input">
                        <option value="open">Anyone on Naara</option>
                        <option value="connections">Only people we are connected with</option>
                    </select>
                    <button type="button" wire:click="saveChatMode" wire:loading.attr="disabled" wire:target="saveChatMode" class="ns-cta ns-cta--pill ns-cta--sm">Save</button>
                </div>
                <p class="ns-pg__hint">Connected means you follow each other: a person connects with your brand and you follow them back. Contacts and chats you already have are never cut off.</p>
            </div>
        @endif

        {{-- Delivery vs guarantee (transparency) --}}
        @if ($delivery->isNotEmpty())
            <div class="ns-pg__card ns-ring">
                <h2 class="ns-bh__h"><x-nx.icon name="bars" /> Follows delivered this cycle</h2>
                @foreach ($delivery as $row)
                    @php($pct = $row['guaranteed'] > 0 ? min(100, (int) round($row['actual'] / $row['guaranteed'] * 100)) : 0)
                    <div style="margin-bottom:12px">
                        <div class="ns-pg__metarow"><span>{{ $row['handle']->handle_label }}</span><span>{{ $row['actual'] }} / {{ $row['guaranteed'] }} guaranteed</span></div>
                        <div class="ns-pg__meter"><i style="width: {{ $pct }}%;{{ $pct >= 100 ? 'background:rgb(var(--nx-ok))' : '' }}"></i></div>
                    </div>
                @endforeach
                <p class="ns-pg__hint">Under-delivered handles boost your placement next cycle automatically.</p>
            </div>
        @endif

        {{-- Profile --}}
        <div class="ns-pg__card ns-ring">
            <h2 class="ns-bh__h"><x-nx.icon name="cog" /> Profile</h2>
            <div class="ns-pg__form">
                <input wire:model="profile.brand_name" placeholder="Brand name" aria-label="Brand name" class="ns-input">
                @error('profile.brand_name')<span class="ns-pg__fielderr">{{ $message }}</span>@enderror
                <select wire:model="profile.category" aria-label="Category" class="ns-input" style="margin-top:8px">
                    <option value="">Choose a category…</option>
                    @foreach ($categories as $c)<option value="{{ $c }}">{{ $c }}</option>@endforeach
                </select>
                @error('profile.category')<span class="ns-pg__fielderr">{{ $message }}</span>@enderror
                <textarea wire:model="profile.short_description" rows="2" placeholder="Short description" aria-label="Short description" class="ns-input" style="margin-top:8px"></textarea>
                <div class="ns-pg__row" style="margin-top:10px;align-items:center">
                    <label class="ns-pg__lbl" style="margin:0" for="bm-color">Card colour</label>
                    <input id="bm-color" type="color" wire:model="profile.background_color" style="width:56px;height:38px;border-radius:8px;border:1px solid rgb(var(--nx-line-strong));background:transparent">
                    <label class="ns-pg__lbl" style="margin:0" for="bm-hero">Cover photo (1280×720)</label>
                    <input id="bm-hero" type="file" wire:model="heroImage" accept="image/*" class="ns-ct__file">
                </div>
                <div class="ns-pg__row" style="margin-top:10px;align-items:center">
                    <label class="ns-pg__lbl" style="margin:0" for="bm-logo">Profile photo (logo)</label>
                    <input id="bm-logo" type="file" wire:model="logoImage" accept="image/*" class="ns-ct__file">
                    @if ($brand->logo_path)<img src="{{ $brand->logo_path }}" alt="" style="width:44px;height:44px;border-radius:50%;object-fit:cover">@endif
                </div>
                @error('logoImage')<span class="ns-pg__fielderr">{{ $message }}</span>@enderror
                @error('heroImage')<span class="ns-pg__fielderr">{{ $message }}</span>@enderror
                @if ($brand->hero_image_path)<img src="{{ $brand->hero_image_path }}" alt="" class="ns-pg__figure" style="height:112px">@endif
                <button type="button" wire:click="saveProfile" wire:loading.attr="disabled" wire:target="saveProfile" class="ns-cta ns-cta--pill" style="align-self:flex-start;margin-top:12px">Save profile</button>
            </div>
        </div>

        {{-- Handles --}}
        <div class="ns-pg__card ns-ring">
            <h2 class="ns-bh__h" style="margin-bottom:4px"><x-nx.icon name="hash" /> Social handles</h2>
            <p class="ns-pg__hint" style="margin:0 0 12px">{{ $brand->handles->count() }} / {{ $brand->plan?->handles_included ?? 1 }} used</p>
            <div class="ns-pg__stack ns-pg__stack--sm">
                @foreach ($brand->handles as $h)
                    <div class="ns-pg__kv" style="gap:10px">
                        <span class="ns-bh__icon" style="width:32px;height:32px;border-radius:9px"><x-service-icon :slug="$h->platform" class="h-4 w-4" /></span>
                        <span style="flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:rgb(var(--nx-text))">{{ $h->handle_label }} <span class="ns-small">· {{ $h->handle_url }}</span></span>
                        <button type="button" wire:click="removeHandle({{ $h->id }})" aria-label="Remove {{ $h->handle_label }}" class="ns-ct__act ns-ct__act--spam" style="width:32px;height:32px"><x-nx.icon name="x" /></button>
                    </div>
                @endforeach
            </div>
            <div x-data="{ p: @entangle('newHandle.platform') }" class="ns-pg__form" style="margin-top:12px">
                <div class="ns-pg__two">
                    <select wire:model="newHandle.platform" x-model="p" aria-label="Platform" class="ns-input">@foreach ($platforms as $slug => $label)<option value="{{ $slug }}">{{ $label }}</option>@endforeach</select>
                    <input wire:model="newHandle.handle_label" placeholder="Label" aria-label="Handle label" class="ns-input">
                </div>
                <input wire:model="newHandle.handle_url" placeholder="Profile URL" aria-label="Profile URL" class="ns-input" style="margin-top:8px">
                @error('newHandle.handle_url')<span class="ns-pg__fielderr">{{ $message }}</span>@enderror
                <button type="button" wire:click="addHandle" class="ns-cta ns-cta--pill ns-cta--ghost ns-cta--sm" style="align-self:flex-start;margin-top:10px">Add handle</button>
            </div>
        </div>

        @if (class_exists(\App\Livewire\BrandLinksManager::class))
            <livewire:brand-links-manager />
        @endif

        {{-- Videos (only if the plan allows) --}}
        @if (($brand->plan?->video_previews_allowed ?? 0) > 0)
            <div class="ns-pg__card ns-ring">
                <h2 class="ns-bh__h" style="margin-bottom:4px"><x-nx.icon name="play" /> Video previews</h2>
                <p class="ns-pg__hint" style="margin:0 0 12px">{{ $brand->videos->count() }} / {{ $brand->plan->video_previews_allowed }} used</p>
                <div class="ns-pg__stack ns-pg__stack--sm">
                    @foreach ($brand->videos as $v)
                        <div class="ns-pg__kv" style="gap:10px">
                            <x-nx.icon name="play" />
                            <span style="flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:rgb(var(--nx-text))">{{ $v->platform }} · {{ $v->video_url }}</span>
                            <button type="button" wire:click="removeVideo({{ $v->id }})" aria-label="Remove video" class="ns-ct__act ns-ct__act--spam" style="width:32px;height:32px"><x-nx.icon name="x" /></button>
                        </div>
                    @endforeach
                </div>
                <div class="ns-pg__form" style="margin-top:12px">
                    <div class="ns-pg__two">
                        <select wire:model="newVideo.platform" aria-label="Video platform" class="ns-input"><option value="youtube">YouTube</option><option value="vimeo">Vimeo</option></select>
                        <input wire:model="newVideo.video_url" placeholder="Video URL" aria-label="Video URL" class="ns-input">
                    </div>
                    <button type="button" wire:click="addVideo" class="ns-cta ns-cta--pill ns-cta--ghost ns-cta--sm" style="align-self:flex-start;margin-top:10px">Add video</button>
                </div>
            </div>
        @endif

        {{-- Plan + cancel --}}
        <div class="ns-pg__card ns-ring" id="plan">
            <h2 class="ns-bh__h"><x-nx.icon name="cards" /> Plan</h2>
            @if (class_exists(\App\Support\Business\NaaraProCatalogue::class))
                @php($ent = \App\Support\Business\NaaraProCatalogue::entitlements($brand->plan, $plans))
                <div style="margin:10px 0 14px" data-pro-entitlements>
                    <p class="ns-pg__hint" style="margin:0 0 6px"><b style="color:rgb(var(--nx-text))">Your plan unlocks</b></p>
                    <ul style="list-style:none;margin:0;padding:0;display:grid;gap:5px">
                        @foreach ($ent['on'] as $e)<li style="display:flex;gap:8px;align-items:flex-start;font-size:14px;color:rgb(var(--nx-text))"><x-nx.icon name="check" style="color:rgb(var(--nx-ok));margin-top:3px" /><span>{{ $e['cap']['label'] }}@if ($e['text']) <small class="ns-small">{{ $e['text'] }}</small>@endif</span></li>@endforeach
                    </ul>
                    @if ($ent['locked'])
                        <p class="ns-pg__hint" style="margin:12px 0 6px"><b style="color:rgb(var(--nx-text))">Not on this plan</b></p>
                        <ul style="list-style:none;margin:0;padding:0;display:grid;gap:5px">
                            @foreach ($ent['locked'] as $l)<li style="display:flex;gap:8px;align-items:flex-start;font-size:14px;color:rgb(var(--nx-text-2))"><x-nx.icon name="lock" style="margin-top:3px" /><span>{{ $l['cap']['label'] }}@if ($l['plan']) <small class="ns-small">comes with {{ $l['plan'] }}</small>@endif</span></li>@endforeach
                        </ul>
                    @endif
                </div>
                <details style="margin-bottom:14px" data-pro-compare-wrap><summary class="ns-pg__hint" style="cursor:pointer;margin:0"><b style="color:rgb(var(--nx-text))">Compare all plans</b></summary><div style="margin-top:10px"><x-naara-pro.compare :plans="$plans" :current="$brand->current_plan_id" compact /></div></details>
            @endif
            <div class="ns-pg__chips">
                @foreach ($plans as $plan)
                    <button type="button" wire:click="changePlan({{ $plan->id }})" class="ns-pg__chip {{ $brand->current_plan_id === $plan->id ? 'is-on' : '' }}" aria-pressed="{{ $brand->current_plan_id === $plan->id ? 'true' : 'false' }}">{{ $plan->name }} · ${{ number_format($plan->price_usd_per_month, 0) }}/mo</button>
                @endforeach
            </div>
            <button type="button" wire:click="cancel" wire:confirm="Cancel your listing? It will be removed from the directory. You can resubscribe later." class="ns-pg__act ns-pg__act--bad" style="margin-top:16px">Cancel listing</button>
        </div>
    </div>
    </div>
</x-nx.page>
</div>
