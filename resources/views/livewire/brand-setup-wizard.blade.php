{{-- nx:converted (skin tokens only). Brand listing setup wizard: a resumable pop-up, one short step at a time, launched from the brand dashboard. --}}
<div data-brand-setup-wizard>
@if ($open && $current)
    @php($field = 'w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm text-[rgb(var(--nx-text))]')
    <x-nx.sheet title="Set up your listing" :subtitle="$percent.'% ready'" icon="sparkles" :nia="true" closeAction="close" backLabel="Close" closeLabel="Close">
        <div class="mb-4">
            <div class="h-2 overflow-hidden rounded-full bg-[rgb(var(--nx-surface-3))]" role="progressbar" aria-valuenow="{{ $percent }}" aria-valuemin="0" aria-valuemax="100"><div class="h-full rounded-full bg-[rgb(var(--nx-teal))] transition-all" style="width: {{ $percent }}%"></div></div>
            <div class="mt-3 flex gap-2 overflow-x-auto pb-1">
                @foreach ($steps as $i => $s)
                    <button type="button" wire:click="goTo('{{ $s['key'] }}')" data-brand-step="{{ $s['key'] }}" aria-current="{{ $step === $s['key'] ? 'step' : 'false' }}"
                            class="flex shrink-0 items-center gap-1.5 rounded-full border px-3 py-1.5 text-xs font-semibold {{ $step === $s['key'] ? 'border-[rgb(var(--nx-teal))] bg-[rgb(var(--nx-teal)/0.14)] text-[rgb(var(--nx-teal-ink))]' : 'border-[rgb(var(--nx-line))] text-[rgb(var(--nx-text-2))]' }}">
                        <span class="h-2 w-2 rounded-full {{ $s['done'] ? 'bg-[rgb(var(--nx-ok))]' : 'bg-[rgb(var(--nx-text-2)/0.5)]' }}"></span>{{ $i + 1 }}. {{ $s['title'] }}
                    </button>
                @endforeach
            </div>
        </div>

        @if ($ok)<p role="status" class="mb-3 rounded-lg bg-[rgb(var(--nx-ok)/0.12)] p-3 text-sm text-[rgb(var(--nx-ok))]">{{ $ok }}</p>@endif
        @if ($error)<p role="alert" class="mb-3 rounded-lg bg-[rgb(var(--nx-bad)/0.12)] p-3 text-sm text-[rgb(var(--nx-bad))]">{{ $error }}</p>@endif

        <div class="space-y-4" wire:key="brand-step-{{ $current['key'] }}">
            <img src="{{ asset('images/illustrations/'.$current['art'].'.svg') }}" alt="" width="240" height="180" class="mx-auto h-36 w-auto sm:h-40" data-wizard-art>
            <div>
                <h3 class="text-base font-bold text-[rgb(var(--nx-text))]">{{ $current['title'] }}@if ($current['optional']) <span class="text-xs font-normal text-[rgb(var(--nx-text-2))]">(optional)</span>@endif</h3>
                <p class="mt-1 text-sm text-[rgb(var(--nx-text-2))]"><b>Why it matters:</b> {{ $current['enables'] }}</p>
            </div>

            @if ($step === 'identity')
                <form wire:submit="saveIdentity" class="space-y-3">
                    <div><label class="mb-1 block text-xs font-semibold text-[rgb(var(--nx-text-2))]" for="bw-name">Brand name</label>
                        <input id="bw-name" type="text" wire:model="brandName" maxlength="120" class="{{ $field }}">@error('brandName')<p class="mt-1 text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p>@enderror</div>
                    <div><label class="mb-1 block text-xs font-semibold text-[rgb(var(--nx-text-2))]" for="bw-cat">Category</label>
                        <select id="bw-cat" wire:model="category" class="{{ $field }}"><option value="">Choose a category</option>@foreach ($categories as $c)<option value="{{ $c }}">{{ $c }}</option>@endforeach</select></div>
                    <div><label class="mb-1 block text-xs font-semibold text-[rgb(var(--nx-text-2))]" for="bw-desc">One-line description</label>
                        <textarea id="bw-desc" wire:model="description" rows="3" maxlength="500" placeholder="What you do, and who it is for" class="{{ $field }}"></textarea>@error('description')<p class="mt-1 text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p>@enderror</div>
                    <button type="submit" wire:loading.attr="disabled" wire:target="saveIdentity" class="ns-cta !w-auto px-6"><span wire:loading.remove wire:target="saveIdentity">Save and continue</span><span wire:loading wire:target="saveIdentity">Saving...</span></button>
                </form>

            @elseif ($step === 'look')
                <form wire:submit="saveLook" class="space-y-3">
                    <div class="flex items-center gap-3">
                        @if ($brand->logo_path)<img src="{{ $brand->logo_path }}" alt="" class="h-12 w-12 rounded-full object-cover">@endif
                        <div class="min-w-0 flex-1"><label class="mb-1 block text-xs font-semibold text-[rgb(var(--nx-text-2))]" for="bw-logo">Logo or profile photo (square, up to 1 MB)</label>
                            <input id="bw-logo" type="file" wire:model="logoImage" accept="image/*" class="block w-full text-xs text-[rgb(var(--nx-text-2))]">@error('logoImage')<p class="mt-1 text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p>@enderror</div>
                    </div>
                    @if ($brand->hero_image_path)<img src="{{ $brand->hero_image_path }}" alt="" class="h-24 w-full rounded-xl object-cover">@endif
                    <div><label class="mb-1 block text-xs font-semibold text-[rgb(var(--nx-text-2))]" for="bw-hero">Cover photo (1280 by 720, up to 2 MB)</label>
                        <input id="bw-hero" type="file" wire:model="heroImage" accept="image/*" class="block w-full text-xs text-[rgb(var(--nx-text-2))]">@error('heroImage')<p class="mt-1 text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p>@enderror</div>
                    <div class="flex items-center gap-3"><label class="text-xs font-semibold text-[rgb(var(--nx-text-2))]" for="bw-color">Card colour</label><input id="bw-color" type="color" wire:model="color" class="h-9 w-14 rounded-lg border border-[rgb(var(--nx-line-strong))] bg-transparent"></div>
                    <div wire:loading wire:target="logoImage,heroImage" class="text-xs text-[rgb(var(--nx-text-2))]">Uploading...</div>
                    <button type="submit" wire:loading.attr="disabled" wire:target="saveLook,logoImage,heroImage" class="ns-cta !w-auto px-6"><span wire:loading.remove wire:target="saveLook">Save and continue</span><span wire:loading wire:target="saveLook">Saving...</span></button>
                </form>

            @elseif ($step === 'handles')
                <p class="text-xs text-[rgb(var(--nx-text-2))]">{{ $brand->handles->count() }} of {{ $brand->plan?->handles_included ?? 1 }} used on your plan. Two are needed to publish.</p>
                @foreach ($brand->handles as $h)
                    <div class="flex items-center gap-2 rounded-lg border border-[rgb(var(--nx-line))] p-2 text-sm" wire:key="bwh-{{ $h->id }}">
                        <span class="min-w-0 flex-1 truncate text-[rgb(var(--nx-text))]">{{ $h->handle_label }} <span class="text-xs text-[rgb(var(--nx-text-2))]">{{ $h->handle_url }}</span></span>
                        <button type="button" wire:click="removeHandle({{ $h->id }})" aria-label="Remove {{ $h->handle_label }}" class="rounded-lg px-2 py-1 text-xs font-semibold text-[rgb(var(--nx-bad))]">Remove</button>
                    </div>
                @endforeach
                <form wire:submit="addHandle" class="space-y-2">
                    <div class="grid gap-2 sm:grid-cols-2">
                        <select wire:model="newHandle.platform" aria-label="Platform" class="{{ $field }}">@foreach ($platforms as $slug => $label)<option value="{{ $slug }}">{{ $label }}</option>@endforeach</select>
                        <input type="text" wire:model="newHandle.handle_label" placeholder="Label, for example @yourbrand" aria-label="Label" class="{{ $field }}">
                    </div>
                    <input type="url" wire:model="newHandle.handle_url" placeholder="Profile link" aria-label="Profile link" class="{{ $field }}">
                    @error('newHandle.handle_label')<p class="text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p>@enderror
                    @error('newHandle.handle_url')<p class="text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p>@enderror
                    <button type="submit" wire:loading.attr="disabled" wire:target="addHandle" class="ns-cta !w-auto px-6"><span wire:loading.remove wire:target="addHandle">Add handle</span><span wire:loading wire:target="addHandle">Adding...</span></button>
                </form>

            @elseif ($step === 'links')
                @foreach ($links as $l)
                    <div class="flex items-center gap-2 rounded-lg border border-[rgb(var(--nx-line))] p-2 text-sm" wire:key="bwl-{{ $l->id }}">
                        <span class="min-w-0 flex-1 truncate text-[rgb(var(--nx-text))]">{{ $l->label }} <span class="text-xs text-[rgb(var(--nx-text-2))]">{{ $l->url }}</span></span>
                        <span class="rounded-full px-2 py-0.5 text-[11px] font-bold {{ $l->status === 'approved' ? 'bg-[rgb(var(--nx-ok)/0.14)] text-[rgb(var(--nx-ok))]' : 'bg-[rgb(var(--nx-warn)/0.16)] text-[rgb(var(--nx-warn))]' }}">{{ $l->status === 'approved' ? 'Live' : ucfirst($l->status) }}</span>
                        <button type="button" wire:click="removeLink({{ $l->id }})" aria-label="Remove {{ $l->label }}" class="rounded-lg px-2 py-1 text-xs font-semibold text-[rgb(var(--nx-bad))]">Remove</button>
                    </div>
                @endforeach
                <form wire:submit="addLink" class="grid gap-2 sm:grid-cols-[9rem_1fr_auto]">
                    <input type="text" wire:model="linkLabel" maxlength="40" placeholder="Label, e.g. Website" aria-label="Label" class="{{ $field }}">
                    <input type="text" wire:model="linkUrl" placeholder="https://yourbrand.com" aria-label="Link" class="{{ $field }}">
                    <button type="submit" wire:loading.attr="disabled" wire:target="addLink" class="ns-cta !w-auto px-5"><span wire:loading.remove wire:target="addLink">Check and add</span><span wire:loading wire:target="addLink">Checking...</span></button>
                </form>
                @error('linkLabel')<p class="text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p>@enderror
                @error('linkUrl')<p class="text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p>@enderror
                @foreach ($linkProblems as $pr)<p role="alert" class="rounded-lg bg-[rgb(var(--nx-bad)/0.12)] p-2 text-xs text-[rgb(var(--nx-bad))]">{{ $pr }}</p>@endforeach

            @elseif ($step === 'chat')
                <a href="{{ route('n2n.brand.manage') }}" wire:navigate class="ns-cta !w-auto px-6">Open my Naara chat profile</a>

            @elseif ($step === 'connect')
                <a href="{{ route('brand.connections') }}" wire:navigate class="ns-cta !w-auto px-6">Open Connections</a>

            @elseif ($step === 'plan' && $planInfo)
                @php($pi = $planInfo)
                <div data-brand-plan class="space-y-3">
                    @if ($pi['trialing'])
                        <div class="rounded-xl border border-[rgb(var(--nx-teal)/0.4)] bg-[rgb(var(--nx-teal)/0.08)] p-3 text-sm" data-plan-trial-running>
                            <p class="font-semibold text-[rgb(var(--nx-text))]">Free trial running: {{ $pi['sub']->trial_ends_at->diffForHumans(null, true) }} left</p>
                            <p class="mt-1 text-xs text-[rgb(var(--nx-text-2))]">When it ends, <b>${{ number_format((float) $pi['sub']->locked_price_usd, 2) }}</b> for <b>{{ $pi['current']?->name }}</b> is taken from your wallet @if (! $pi['sub']->auto_convert), but auto-start is off, so the trial will simply end with no charge @endif. Your wallet holds ${{ number_format($pi['balance'], 2) }}.</p>
                            @if ($pi['balance'] < (float) $pi['sub']->locked_price_usd && $pi['sub']->auto_convert)<p class="mt-1 text-xs font-semibold text-[rgb(var(--nx-warn))]">Your wallet is short for that. <a href="{{ route('wallet') }}" wire:click="close" class="underline">Add funds</a> before the trial ends so nothing pauses.</p>@endif
                            <button type="button" wire:click="trialAuto({{ $pi['sub']->auto_convert ? 'false' : 'true' }})" class="mt-2 rounded-lg bg-[rgb(var(--nx-surface-3))] px-3 py-1.5 text-xs font-semibold text-[rgb(var(--nx-text))]">{{ $pi['sub']->auto_convert ? 'Turn off auto-start' : 'Turn on auto-start' }}</button>
                        </div>
                        <p class="text-xs font-semibold text-[rgb(var(--nx-text))]">Change the plan your wallet is charged for when the trial ends</p>
                    @elseif ($pi['active'])
                        <p class="rounded-xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface-2))] p-3 text-sm text-[rgb(var(--nx-text-2))]"><b class="text-[rgb(var(--nx-text))]">{{ $pi['current']?->name }}</b> is active @if ($pi['sub']->next_billing_at), next billing {{ $pi['sub']->next_billing_at->format('d M Y') }}@endif. To move plans, use Plan on your dashboard.</p>
                    @else
                        <p class="text-xs text-[rgb(var(--nx-text-2))]">Naara Pro is every Pro capability on Naara in one plan. Pick the one that fits.@if ($pi['trial'] && $pi['trial']['available']) The free trial is {{ $pi['trial']['days'] }} days and charges nothing today.@endif</p>
                    @endif

                    @unless ($pi['active'])
                        <div class="grid gap-2" role="radiogroup" aria-label="Naara Pro plans">
                            @foreach ($pi['plans'] as $pl)
                                @php($on = $this->pickPlanId === $pl->id || (! $this->pickPlanId && $pi['picked'] && $pi['picked']->id === $pl->id))
                                <button type="button" role="radio" aria-checked="{{ $on ? 'true' : 'false' }}" wire:click="pickPlan({{ $pl->id }})" wire:key="bwp-{{ $pl->id }}" data-plan-option="{{ $pl->id }}"
                                        class="flex items-start justify-between gap-3 rounded-xl border p-3 text-left {{ $on ? 'border-[rgb(var(--nx-teal))] bg-[rgb(var(--nx-teal)/0.1)]' : 'border-[rgb(var(--nx-line))]' }}">
                                    <span class="min-w-0">
                                        <b class="block text-sm text-[rgb(var(--nx-text))]">{{ $pl->name }}@if ($pi['trialing'] && $pi['current'] && $pi['current']->id === $pl->id) <span class="text-[11px] font-semibold text-[rgb(var(--nx-teal-ink))]">· trial plan</span>@endif</b>
                                        <span class="mt-0.5 block text-xs text-[rgb(var(--nx-text-2))]">{{ $pl->handles_included }} {{ \Illuminate\Support\Str::plural('handle', $pl->handles_included) }}, {{ $pl->guaranteed_followers_per_handle_per_month }} guaranteed follows per handle @if ($pl->video_previews_allowed), {{ $pl->video_previews_allowed }} {{ \Illuminate\Support\Str::plural('video', $pl->video_previews_allowed) }}@endif</span>
                                    </span>
                                    <span class="shrink-0 text-sm font-bold text-[rgb(var(--nx-text))]">${{ number_format((float) $pl->price_usd_per_month, 0) }}<small class="text-xs font-medium text-[rgb(var(--nx-text-2))]">/mo</small></span>
                                </button>
                            @endforeach
                        </div>

                        @if ($pi['picked'])
                            <div class="rounded-xl border border-[rgb(var(--nx-line))] p-3" data-plan-unlocks>
                                <p class="text-xs font-semibold text-[rgb(var(--nx-text))]">{{ $pi['picked']->name }} unlocks</p>
                                <ul class="mt-1.5 grid gap-1 text-xs text-[rgb(var(--nx-text-2))] sm:grid-cols-2">@foreach ($pi['delta'] as $line)<li class="flex gap-1.5"><span class="mt-1 h-1.5 w-1.5 shrink-0 rounded-full bg-[rgb(var(--nx-ok))]"></span>{{ $line }}</li>@endforeach</ul>
                            </div>

                            @if ($pi['trialing'])
                                <button type="button" wire:click="changeTrialPlan" wire:loading.attr="disabled" wire:target="changeTrialPlan" class="ns-cta !w-auto px-6"><span wire:loading.remove wire:target="changeTrialPlan">Charge {{ $pi['picked']->name }} when the trial ends</span><span wire:loading wire:target="changeTrialPlan">Saving...</span></button>
                            @else
                                @if ($pi['trial'] && $pi['trial']['available'])
                                    <div class="rounded-xl border border-[rgb(var(--nx-gold)/0.5)] bg-[rgb(var(--nx-gold)/0.08)] p-3 text-sm" data-plan-trial-offer>
                                        <p class="font-semibold text-[rgb(var(--nx-text))]">Try {{ $pi['picked']->name }} free for {{ $pi['trial']['days'] }} days</p>
                                        <p class="mt-1 text-xs text-[rgb(var(--nx-text-2))]">You pay nothing today. When the {{ $pi['trial']['days'] }} days end, <b>${{ number_format((float) $pi['picked']->price_usd_per_month, 2) }}</b> is taken from your wallet automatically and {{ $pi['picked']->name }} continues each month. Cancel any time before then and you are never charged.@if ($pi['short']) <b class="text-[rgb(var(--nx-warn))]">Your wallet holds ${{ number_format($pi['balance'], 2) }}: add funds before the trial ends so your listing does not pause.</b>@endif</p>
                                        <label class="mt-2 flex items-start gap-2 text-xs text-[rgb(var(--nx-text))]"><input type="checkbox" wire:model.live="trialAccept" class="mt-0.5"><span>I understand my wallet will be charged ${{ number_format((float) $pi['picked']->price_usd_per_month, 2) }} when the trial ends unless I cancel.</span></label>
                                        <button type="button" wire:click="startTrial" wire:loading.attr="disabled" wire:target="startTrial" @disabled(! $this->trialAccept) class="ns-cta mt-3 !w-auto px-6"><span wire:loading.remove wire:target="startTrial">Start free trial</span><span wire:loading wire:target="startTrial">Starting...</span></button>
                                    </div>
                                @elseif ($pi['trial'] && $pi['trial']['why'])
                                    <p class="text-xs text-[rgb(var(--nx-text-2))]">{{ $pi['trial']['why'] }}</p>
                                @endif
                                <button type="button" wire:click="subscribeNow" wire:loading.attr="disabled" wire:target="subscribeNow" class="{{ $pi['trial'] && $pi['trial']['available'] ? 'rounded-lg bg-[rgb(var(--nx-surface-3))] px-4 py-2 text-sm font-semibold text-[rgb(var(--nx-text))]' : 'ns-cta !w-auto px-6' }}"><span wire:loading.remove wire:target="subscribeNow">Subscribe now: ${{ number_format((float) $pi['picked']->price_usd_per_month, 2) }} from your wallet</span><span wire:loading wire:target="subscribeNow">Subscribing...</span></button>
                                @if ($pi['short'] && ! ($pi['trial'] && $pi['trial']['available']))<p class="text-xs text-[rgb(var(--nx-warn))]">Your wallet holds ${{ number_format($pi['balance'], 2) }}. <a href="{{ route('wallet') }}" wire:click="close" class="underline">Add funds</a> first.</p>@endif
                            @endif
                        @endif
                    @endunless

                    <details class="rounded-xl border border-[rgb(var(--nx-line))] p-3" data-pro-compare-wrap>
                        <summary class="cursor-pointer text-xs font-semibold text-[rgb(var(--nx-text))]">Compare everything Naara Pro includes</summary>
                        <div class="mt-2"><x-naara-pro.compare :plans="$pi['plans']" :current="$pi['current']?->id" compact /></div>
                    </details>
                </div>

            @elseif ($step === 'publish')
                <ul class="space-y-1.5" data-brand-checklist>
                    @foreach ($requirements as $r)
                        <li class="flex items-start gap-2 text-sm"><span class="mt-1 h-2.5 w-2.5 shrink-0 rounded-full {{ $r['met'] ? 'bg-[rgb(var(--nx-ok))]' : ($r['required'] ? 'bg-[rgb(var(--nx-warn))]' : 'bg-[rgb(var(--nx-text-2)/0.4)]') }}"></span>
                            <span class="text-[rgb(var(--nx-text))]">{{ $r['label'] }} <span class="text-xs text-[rgb(var(--nx-text-2))]">{{ $r['required'] ? 'required' : 'recommended' }}{{ $r['met'] ? '' : ': '.$r['hint'] }}</span></span></li>
                    @endforeach
                </ul>
                @if ($brand->published_at)
                    <p class="rounded-lg bg-[rgb(var(--nx-ok)/0.12)] p-3 text-sm text-[rgb(var(--nx-ok))]">You are published.</p>
                    @if ($share)
                        <div class="rounded-xl border border-[rgb(var(--nx-line))] p-3 text-sm" data-brand-share x-data="{ done: false }">
                            <p class="font-semibold text-[rgb(var(--nx-text))]">Share your listing</p>
                            <p class="mt-1 break-all text-xs text-[rgb(var(--nx-text-2))]">{{ $share['url'] }}</p>
                            <button type="button" @click="navigator.clipboard.writeText(@js($share['url'])).then(() => { done = true; setTimeout(() => done = false, 1800) })" class="mt-2 rounded-lg bg-[rgb(var(--nx-surface-3))] px-3 py-1.5 text-xs font-semibold text-[rgb(var(--nx-text))]"><span x-show="!done">Copy link</span><span x-show="done" x-cloak>Copied</span></button>
                        </div>
                    @endif
                @else
                    <button type="button" wire:click="publish" wire:loading.attr="disabled" wire:target="publish" class="ns-cta !w-auto px-6"><span wire:loading.remove wire:target="publish">Publish my listing</span><span wire:loading wire:target="publish">Publishing...</span></button>
                @endif
                <a href="{{ route('rewards.hunt.profile', ['brandPartner' => $brand->id, 'preview' => 1]) }}" target="_blank" rel="noopener" class="inline-block text-sm font-semibold text-[rgb(var(--nx-teal-ink))] underline">Preview as a visitor</a>
            @endif
        </div>

        <x-slot:foot>
            <div class="flex items-center justify-between gap-2">
                <button type="button" wire:click="back" class="rounded-lg px-3 py-2 text-sm font-semibold text-[rgb(var(--nx-text-2))] hover:bg-[rgb(var(--nx-surface-3))]">Back</button>
                @if ($step !== 'publish')
                    <div class="flex items-center gap-2">
                        @if ($current['optional'])<button type="button" wire:click="next" class="rounded-lg px-3 py-2 text-sm font-semibold text-[rgb(var(--nx-text-2))] hover:bg-[rgb(var(--nx-surface-3))]">Skip</button>@endif
                        @if (! in_array($step, ['identity', 'look', 'card'], true))<button type="button" wire:click="next" class="ns-cta !w-auto px-6">Next</button>@endif
                    </div>
                @else
                    <button type="button" wire:click="close" class="ns-cta !w-auto px-6">Done</button>
                @endif
            </div>
        </x-slot:foot>
    </x-nx.sheet>
@endif
</div>
