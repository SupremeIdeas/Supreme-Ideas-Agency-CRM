{{-- nx:converted (skin tokens only; see docs/appearance/SKIN-CONTRACT.md) --}}
@php
    $in = 'w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm text-[rgb(var(--nx-text))]';
    $lb = 'mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]';
    $card = 'rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-4 sm:p-5';
@endphp
<div class="mx-auto max-w-3xl space-y-6">
    <div>
        <h1 class="text-2xl font-bold text-[rgb(var(--nx-text))]">Business links</h1>
        <p class="mt-1 text-sm text-[rgb(var(--nx-text-2))]">The rules for the custom links brands add to their profile. {{ $live }} link(s) live. Links are checked when added and again every week; one that starts failing is paused and the brand is told.</p>
    </div>

    <form wire:submit="test" class="{{ $card }} space-y-3">
        <h2 class="text-base font-semibold text-[rgb(var(--nx-text))]">Test a link</h2>
        <p class="text-xs text-[rgb(var(--nx-text-2))]">Runs every check exactly as a brand owner's link would be checked, including the live look at the website. Nothing is saved.</p>
        <div class="grid gap-3 sm:grid-cols-3">
            <div><label class="{{ $lb }}">Brand name</label><input type="text" wire:model="tBrand" class="{{ $in }}">@error('tBrand')<p class="mt-1 text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p>@enderror</div>
            <div><label class="{{ $lb }}">Link name</label><input type="text" wire:model="tLabel" class="{{ $in }}"></div>
            <div><label class="{{ $lb }}">Address</label><input type="text" wire:model="tUrl" placeholder="https://" class="{{ $in }}">@error('tUrl')<p class="mt-1 text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p>@enderror</div>
        </div>
        <button type="submit" wire:loading.attr="disabled" wire:target="test" class="rounded-lg bg-[rgb(var(--nx-cta-b))] px-4 py-2 text-sm font-semibold text-white disabled:opacity-60"><span wire:loading.remove wire:target="test">Check link</span><span wire:loading wire:target="test">Checking...</span></button>
        @if ($result)
            <div class="rounded-xl border border-[rgb(var(--nx-line))] p-3 text-sm">
                <p class="font-semibold {{ $result['status'] === 'approved' ? 'text-[rgb(var(--nx-ok))]' : ($result['status'] === 'pending' ? 'text-[rgb(var(--nx-text))]' : 'text-[rgb(var(--nx-bad))]') }}">Verdict: {{ strtoupper($result['status']) }} · resemblance {{ $result['score'] }}%</p>
                <p class="mt-1 text-xs text-[rgb(var(--nx-text-2))]">{{ $result['host'] ?: 'n/a' }} @if ($result['registrable']) (domain {{ $result['registrable'] }}) @endif @if ($result['final']) · final host {{ $result['final'] }} @endif @if ($result['http']) · HTTP {{ $result['http'] }} @endif · Safe Browsing: {{ $result['sb'] ?? 'skipped' }}</p>
                <ul class="mt-2 space-y-1">@foreach ($result['checks'] as $c)<li class="flex gap-2 {{ $c['ok'] ? 'text-[rgb(var(--nx-text-2))]' : 'text-[rgb(var(--nx-bad))]' }}"><span class="font-mono text-xs">{{ $c['ok'] ? 'PASS' : 'FAIL' }}</span><span class="text-xs"><b>{{ $c['key'] }}</b>: {{ $c['message'] }}</span></li>@endforeach</ul>
            </div>
        @endif
    </form>

    <div class="{{ $card }}">
        <h2 class="text-base font-semibold text-[rgb(var(--nx-text))]">Review queue</h2>
        <div class="mt-3 space-y-3">
            @forelse ($queue as $l)
                <div wire:key="q-{{ $l->id }}" class="rounded-xl border border-[rgb(var(--nx-line))] p-3">
                    <p class="text-sm font-medium text-[rgb(var(--nx-text))]">{{ $l->brand?->brand_name }} · {{ $l->label }} <span class="text-xs text-[rgb(var(--nx-text-2))]">{{ $l->host }} · {{ $l->resemblance_score }}% · {{ $l->status }}@if ($l->review_requested) · owner asked for a review @endif</span></p>
                    @if ($l->reject_reason)<p class="text-xs text-[rgb(var(--nx-text-2))]">{{ $l->reject_reason }}</p>@endif
                    <div class="mt-2 flex flex-wrap items-center gap-2">
                        <button type="button" wire:click="approve({{ $l->id }})" class="rounded-lg bg-[rgb(var(--nx-cta-b))] px-3 py-1.5 text-xs font-semibold text-white">Approve</button>
                        <input type="text" wire:model="rejectReason.{{ $l->id }}" placeholder="Reason to show the brand" class="{{ $in }} !w-56 !py-1.5 text-xs">
                        <button type="button" wire:click="reject({{ $l->id }})" class="rounded-lg border border-[rgb(var(--nx-line))] px-3 py-1.5 text-xs">Reject</button>
                        <button type="button" wire:click="recheck({{ $l->id }})" wire:loading.attr="disabled" class="rounded-lg border border-[rgb(var(--nx-line))] px-3 py-1.5 text-xs">Check now</button>
                    </div>
                </div>
            @empty
                <p class="text-sm text-[rgb(var(--nx-text-2))]">Nothing is waiting.</p>
            @endforelse
        </div>
    </div>

    <form wire:submit="saveSettings" class="{{ $card }} space-y-4">
        <h2 class="text-base font-semibold text-[rgb(var(--nx-text))]">Policy</h2>
        <div class="grid gap-3 sm:grid-cols-4">
            <div><label class="{{ $lb }}">Reject below (%)</label><input type="number" wire:model="threshold" class="{{ $in }}"></div>
            <div><label class="{{ $lb }}">Auto-approve from (%)</label><input type="number" wire:model="reviewUpper" class="{{ $in }}"></div>
            <div><label class="{{ $lb }}">Links per brand</label><input type="number" wire:model="perBrand" class="{{ $in }}"></div>
            <div><label class="{{ $lb }}">Re-check every (days)</label><input type="number" wire:model="recheckDays" class="{{ $in }}"></div>
        </div>
        <p class="text-xs text-[rgb(var(--nx-text-2))]">Between "reject below" and "auto-approve from", a link waits for a person. An initialism such as sia.com for "Supreme Ideas Agency" always lands there.</p>
        <div class="grid gap-3 sm:grid-cols-2">
            @foreach (['tld_allow' => 'Recommended endings (never denied)', 'tld_deny' => 'Denied endings', 'shorteners' => 'Link shorteners', 'aggregators' => 'Link-in-bio sites', 'trust_bait' => 'Words refused in a subdomain', 'protected_brands' => 'Protected well-known names', 'stopwords' => 'Words ignored in a brand name'] as $k => $t)
                <div><label class="{{ $lb }}">{{ $t }} (one per line)</label><textarea wire:model="lists.{{ $k }}" rows="5" class="{{ $in }} font-mono text-xs"></textarea></div>
            @endforeach
        </div>
        <div class="flex justify-end"><button type="submit" wire:loading.attr="disabled" class="rounded-lg bg-[rgb(var(--nx-cta-b))] px-5 py-2 text-sm font-semibold text-white">Save policy</button></div>
    </form>

    <div class="{{ $card }}">
        <h2 class="text-base font-semibold text-[rgb(var(--nx-text))]">Data</h2>
        <p class="mt-1 text-sm text-[rgb(var(--nx-text-2))]">Public Suffix List version {{ $pslVersion }} (checked into the code {{ $verifiedOn }}). It decides what counts as a domain ending. It is bundled, never fetched while someone adds a link.</p>
        <button type="button" wire:click="refreshData" wire:loading.attr="disabled" wire:target="refreshData" class="mt-3 rounded-lg border border-[rgb(var(--nx-line))] px-3 py-1.5 text-sm">Refresh from source</button>
        @if ($dataMessage)<p class="mt-2 text-sm text-[rgb(var(--nx-text))]">{{ $dataMessage }}</p>@endif
    </div>
</div>
