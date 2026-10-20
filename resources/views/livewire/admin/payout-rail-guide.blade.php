{{-- nx:converted (skin tokens; legacy palette classes removed 2026-10-05) --}}
<div class="mt-6 space-y-4">
    @php
        $card = 'rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-5 shadow-sm';
        $inp = 'rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm text-[rgb(var(--nx-text))]';
    @endphp

    {{-- Matrix editor --}}
    <div class="{{ $card }}">
        <h2 class="text-sm font-semibold text-[rgb(var(--nx-text))]">Country x rail matrix</h2>
        <p class="text-xs text-[rgb(var(--nx-text-2))]">What the user guide promises. "Available" needs the provider to cover the country AND an enabled corridor (or a force-on). Overrides survive re-seeding. Every edit is audited.</p>
        <select wire:model.live="country" class="{{ $inp }} mt-3" aria-label="Country">
            @foreach ($countries as $c) <option value="{{ $c }}">{{ \App\Services\Payouts\Rail\PayoutRailRegistry::countryName($c, 'en') }} ({{ $c }})</option> @endforeach
        </select>

        <table class="mt-3 w-full text-sm">
            <thead><tr class="text-left text-xs uppercase tracking-wide text-[rgb(var(--nx-text-2))]"><th class="py-2">Rail</th><th class="py-2">Shown as</th><th class="py-2">Override</th><th class="py-2">Verified</th></tr></thead>
            <tbody>
                @foreach ($rows as $r)
                    <tr class="border-t border-[rgb(var(--nx-line))]" wire:key="guide-{{ $country }}-{{ $r['rail'] }}">
                        <td class="py-2 font-medium capitalize text-[rgb(var(--nx-text))]">{{ str_replace('_', ' ', $r['rail']) }}</td>
                        <td class="py-2 text-[rgb(var(--nx-text-2))]">{{ str_replace('_', ' ', $r['state']) }}</td>
                        <td class="py-2">
                            <select wire:change="setOverride('{{ $r['rail'] }}', $event.target.value)" class="{{ $inp }} !py-1" aria-label="Override for {{ $r['rail'] }}">
                                @foreach (['none' => 'No override', 'force_on' => 'Force on', 'force_off' => 'Force off'] as $v => $l) <option value="{{ $v }}" @selected($r['override'] === $v)>{{ $l }}</option> @endforeach
                            </select>
                        </td>
                        <td class="py-2 text-xs {{ $r['stale'] ? 'text-[rgb(var(--nx-warn))]' : 'text-[rgb(var(--nx-text-2))]' }}">{{ $r['verified_at']?->diffForHumans() ?? 'never' }}{{ $r['stale'] ? ' · needs re-verification' : '' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="mt-4 flex flex-wrap items-end gap-2">
            <div><label class="block text-xs text-[rgb(var(--nx-text-2))]">ETA min (hours)</label><input type="number" wire:model="etaMin" class="{{ $inp }} w-28"></div>
            <div><label class="block text-xs text-[rgb(var(--nx-text-2))]">ETA max (hours)</label><input type="number" wire:model="etaMax" class="{{ $inp }} w-28"></div>
            @foreach (['paystack', 'flutterwave', 'stripe_connect', 'global'] as $r)
                <button type="button" wire:click="saveEta('{{ $r }}')" wire:loading.attr="disabled" class="rounded-lg border border-[rgb(var(--nx-line))] px-3 py-2 text-xs font-semibold text-[rgb(var(--nx-text-2))] disabled:opacity-60">Set ETA: {{ str_replace('_', ' ', $r) }}</button>
            @endforeach
        </div>
        @error('etaMin') <span class="text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</span> @enderror @error('etaMax') <span class="text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</span> @enderror
    </div>

    {{-- Settings + audit --}}
    <div class="{{ $card }}">
        <h2 class="text-sm font-semibold text-[rgb(var(--nx-text))]">Guide settings</h2>
        <label class="mt-3 flex items-center gap-2 text-sm text-[rgb(var(--nx-text))]"><input type="checkbox" wire:model="allowGlobal"> Allow Global Payout while a faster rail is available (default off)</label>
        <div class="mt-3 grid gap-3 sm:grid-cols-2">
            <div><label class="block text-xs text-[rgb(var(--nx-text-2))]">Global payout ETA (days)</label><input type="number" wire:model="globalDays" class="{{ $inp }} w-full"></div>
            <div><label class="block text-xs text-[rgb(var(--nx-text-2))]">Guide version (bump to re-require the acknowledgement)</label><input type="number" wire:model="guideVersion" class="{{ $inp }} w-full"></div>
            @foreach (['paystack', 'flutterwave', 'stripe_connect', 'global'] as $r)
                <div><label class="block text-xs text-[rgb(var(--nx-text-2))]">ETA text: {{ str_replace('_', ' ', $r) }} (blank = default)</label><input type="text" wire:model="etaText.{{ $r }}" maxlength="120" class="{{ $inp }} w-full"></div>
            @endforeach
        </div>
        @error('globalDays') <span class="text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</span> @enderror
        <div class="mt-4 flex flex-wrap gap-2">
            <button type="button" wire:click="saveSettings" wire:loading.attr="disabled" wire:target="saveSettings" class="rounded-lg bg-[rgb(var(--nx-cta-b))] px-4 py-2 text-sm font-semibold text-white hover:bg-[rgb(var(--nx-cta-a))] disabled:opacity-60">Save settings</button>
            <button type="button" wire:click="runAudit" wire:loading.attr="disabled" wire:target="runAudit" class="rounded-lg border border-[rgb(var(--nx-line))] px-4 py-2 text-sm font-semibold text-[rgb(var(--nx-text-2))] disabled:opacity-60">Run audit now</button>
            <button type="button" wire:click="reseed" wire:loading.attr="disabled" wire:target="reseed" wire:confirm="Re-seed from providers? Overrides are never overwritten." class="rounded-lg border border-[rgb(var(--nx-line))] px-4 py-2 text-sm font-semibold text-[rgb(var(--nx-text-2))] disabled:opacity-60">Re-seed from providers</button>
        </div>
        @if ($auditResult) <p class="mt-2 text-xs text-[rgb(var(--nx-text-2))]">{{ $auditResult }}</p> @endif
    </div>

    {{-- Preview --}}
    <div class="{{ $card }}">
        <h2 class="text-sm font-semibold text-[rgb(var(--nx-text))]">Preview the guide</h2>
        <p class="text-xs text-[rgb(var(--nx-text-2))]">Simulation only: nothing is written.</p>
        <div class="mt-3 flex flex-wrap gap-2">
            <select wire:model="previewCountry" class="{{ $inp }}" aria-label="Preview country">@foreach ($countries as $c) <option value="{{ $c }}">{{ $c }}</option> @endforeach</select>
            <select wire:model="previewHistoryRail" class="{{ $inp }}" aria-label="Simulated history"><option value="">No history</option>@foreach (['paystack', 'flutterwave', 'stripe_connect'] as $r) <option value="{{ $r }}">Paid via {{ $r }}</option> @endforeach</select>
            <button type="button" wire:click="runPreview" class="rounded-lg border border-[rgb(var(--nx-line))] px-4 py-2 text-sm font-semibold text-[rgb(var(--nx-text-2))]">Preview</button>
        </div>
        @if ($preview)
            <p class="mt-3 text-sm font-medium text-[rgb(var(--nx-text))]">Verdict: {{ str_replace('_', ' ', $preview['verdict']) }}</p>
            <ul class="mt-1 text-xs text-[rgb(var(--nx-text-2))]">@foreach ($preview['options'] as $o) <li>{{ $o }}</li> @endforeach</ul>
        @endif
    </div>

    {{-- Funnel --}}
    <div class="{{ $card }}">
        <h2 class="text-sm font-semibold text-[rgb(var(--nx-text))]">Guide funnel (last {{ $report['days'] }} days)</h2>
        <div class="mt-3 grid gap-3 sm:grid-cols-4 text-sm">
            <div><div class="text-xs text-[rgb(var(--nx-text-2))]">Viewers</div><div class="text-xl font-bold text-[rgb(var(--nx-text))]">{{ $report['viewers'] }}</div></div>
            <div><div class="text-xs text-[rgb(var(--nx-text-2))]">Followed the recommendation</div><div class="text-xl font-bold text-[rgb(var(--nx-text))]">{{ $report['followed_pct'] === null ? '—' : $report['followed_pct'].'%' }}</div></div>
            <div><div class="text-xs text-[rgb(var(--nx-text-2))]">Global acknowledgements</div><div class="text-xl font-bold text-[rgb(var(--nx-text))]">{{ $report['global_acks'] }}</div></div>
            <div><div class="text-xs text-[rgb(var(--nx-text-2))]">Guide to verified account</div><div class="text-xl font-bold text-[rgb(var(--nx-text))]">{{ $report['conversion_pct'] === null ? '—' : $report['conversion_pct'].'%' }}</div></div>
        </div>
        <div class="mt-3 grid gap-4 sm:grid-cols-3 text-xs text-[rgb(var(--nx-text-2))]">
            <div><div class="font-semibold">Selections by rail</div>@forelse ($report['selections_by_rail'] as $r => $n) <div>{{ $r }}: {{ $n }}</div> @empty <div class="text-[rgb(var(--nx-text-2))]">None</div> @endforelse</div>
            <div><div class="font-semibold">Selections by country</div>@forelse ($report['selections_by_country'] as $r => $n) <div>{{ $r }}: {{ $n }}</div> @empty <div class="text-[rgb(var(--nx-text-2))]">None</div> @endforelse</div>
            <div><div class="font-semibold">Asked to be notified</div>@forelse ($report['notify_by_country'] as $r => $n) <div>{{ $r }}: {{ $n }}</div> @empty <div class="text-[rgb(var(--nx-text-2))]">None</div> @endforelse</div>
        </div>
    </div>
</div>
