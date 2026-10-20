{{-- nx:converted (skin tokens; legacy palette classes removed 2026-10-05) --}}
<div class="mx-auto max-w-4xl">
    <a href="{{ route('admin.nci.registry') }}" wire:navigate class="mb-4 inline-flex items-center gap-1 text-xs font-medium text-[rgb(var(--nx-text-2))] hover:text-[rgb(var(--nx-teal-ink))]">
        <x-icon name="chevron-right" class="h-3.5 w-3.5 rotate-180" /> Provider Registry
    </a>

    {{-- Header: real name + the primary "Open Provider Dashboard" action --}}
    <div class="mb-6 flex flex-wrap items-start justify-between gap-4 rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-5">
        <div>
            <div class="flex flex-wrap items-center gap-2">
                <h1 class="text-2xl font-bold capitalize text-[rgb(var(--nx-text))]">{{ $row->provider_key }}</h1>
                @if ($row->paused_at)
                    <span class="inline-flex items-center gap-1 rounded-full bg-[rgb(var(--nx-surface-3))] px-2 py-0.5 text-[11px] font-semibold text-[rgb(var(--nx-text-2))]">
                        <x-icon name="moon" class="h-3 w-3" /> Paused {{ $row->paused_at->diffForHumans() }}
                    </span>
                @endif
            </div>
            <div class="mt-1 flex flex-wrap items-center gap-2 text-xs text-[rgb(var(--nx-text-2))]">
                <span class="uppercase">{{ $row->stack }}</span> ·
                <span>{{ $tierLabel }}</span>
                @foreach ($families as $fam)<span class="rounded-full bg-[rgb(var(--nx-cta-b)/0.1)] px-2 py-0.5 font-medium text-[rgb(var(--nx-teal-ink))]">{{ $fam }}</span>@endforeach
            </div>
            <div class="mt-2 flex flex-wrap items-center gap-3 text-xs">
                @if ($row->docs_url)<a href="{{ $row->docs_url }}" target="_blank" rel="noopener" class="text-[rgb(var(--nx-teal-ink))] hover:underline">API docs ↗</a>@endif
                @if ($row->contact_email)<span class="text-[rgb(var(--nx-text-2))]">{{ $row->contact_email }}</span>@endif
            </div>
        </div>
        <div class="flex flex-col items-end gap-2">
            @if ($row->dashboard_login_url)
                <a href="{{ $row->dashboard_login_url }}" target="_blank" rel="noopener"
                   class="inline-flex items-center gap-2 rounded-xl bg-[rgb(var(--nx-cta-b))] px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-[rgb(var(--nx-cta-a))]">
                    <x-icon name="link" class="h-4 w-4" /> Open Provider Dashboard ↗
                </a>
            @else
                <span class="text-xs text-[rgb(var(--nx-text-2))]">No dashboard URL — add one in the registry seed.</span>
            @endif

            @if ($canOverride)
                @if ($row->paused_at)
                    <button type="button" wire:click="resume" wire:confirm="Resume {{ $row->provider_key }}? It rejoins routing immediately."
                            class="inline-flex items-center gap-2 rounded-xl border border-[rgb(var(--nx-ok))] px-4 py-2 text-sm font-semibold text-[rgb(var(--nx-ok))] transition hover:bg-[rgb(var(--nx-ok)/0.12)]">
                        <x-icon name="check" class="h-4 w-4" /> Resume provider
                    </button>
                @else
                    <div class="flex items-center gap-2">
                        <input type="text" wire:model="pauseReason" placeholder="Reason (optional)"
                               class="w-40 rounded-lg border border-[rgb(var(--nx-line))] px-2 py-1.5 text-xs">
                        <button type="button" wire:click="pause" wire:confirm="Pause {{ $row->provider_key }}? It is excluded from routing until you resume it — no auto-recovery."
                                class="inline-flex items-center gap-2 rounded-xl border border-[rgb(var(--nx-line-strong))] px-4 py-2 text-sm font-semibold text-[rgb(var(--nx-text-2))] transition hover:bg-[rgb(var(--nx-surface-2))]">
                            <x-icon name="moon" class="h-4 w-4" /> Pause provider
                        </button>
                    </div>
                @endif
            @endif
        </div>
    </div>

    {{-- Tabs --}}
    <div class="mb-4 flex flex-wrap gap-1 border-b border-[rgb(var(--nx-line))]">
        @foreach (['overview' => 'Overview', 'circuit' => 'Circuit & Outcomes', 'nci' => 'NCI Intelligence', 'config' => 'Config'] as $key => $label)
            <button type="button" wire:click="$set('tab', '{{ $key }}')"
                    class="-mb-px border-b-2 px-3 py-2 text-sm font-medium transition {{ $tab === $key ? 'border-[rgb(var(--nx-teal))] text-[rgb(var(--nx-teal-ink))]' : 'border-transparent text-[rgb(var(--nx-text-2))] hover:text-[rgb(var(--nx-text))]' }}">
                {{ $label }}
            </button>
        @endforeach
    </div>

    {{-- OVERVIEW --}}
    @if ($tab === 'overview')
        <div class="grid gap-3 sm:grid-cols-4">
            <div class="rounded-xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-3">
                <p class="text-[11px] uppercase tracking-wide text-[rgb(var(--nx-text-2))]">Status</p><div class="mt-1"><x-nci.pill kind="status" :value="$row->status" /></div>
            </div>
            <div class="rounded-xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-3">
                <p class="text-[11px] uppercase tracking-wide text-[rgb(var(--nx-text-2))]">Balance</p><p class="mt-1 text-sm font-semibold text-[rgb(var(--nx-text))]">{{ is_null($row->balance) ? '—' : '$'.number_format((float) $row->balance, 2) }}</p>
            </div>
            <div class="rounded-xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-3">
                <p class="text-[11px] uppercase tracking-wide text-[rgb(var(--nx-text-2))]">Latency</p><p class="mt-1 text-sm font-semibold text-[rgb(var(--nx-text))]">{{ is_null($row->latency_ms) ? '—' : $row->latency_ms.'ms' }}</p>
            </div>
            <div class="rounded-xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-3">
                <p class="text-[11px] uppercase tracking-wide text-[rgb(var(--nx-text-2))]">Last checked</p><p class="mt-1 text-sm font-semibold text-[rgb(var(--nx-text))]">{{ $row->last_checked_at?->diffForHumans() ?? '—' }}</p>
            </div>
        </div>

        {{-- Credentials — masked; reveal requires the admin's password --}}
        <div class="mt-4 rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-5">
            <div class="flex items-center justify-between">
                <h2 class="text-sm font-semibold text-[rgb(var(--nx-text))]">Credentials</h2>
                <div class="flex items-center gap-3">
                    @if ($revealed)
                        <button type="button" wire:click="hideSecrets" class="text-xs font-medium text-[rgb(var(--nx-text-2))] underline">Hide</button>
                    @endif
                    @if (Auth::user()?->hasRole('super_admin') && collect($credentials)->contains('has', true))
                        <button type="button" wire:click="clearKeys"
                                wire:confirm="Clear every stored API key for {{ $row->provider_key }}? It reverts to Coming Soon until new keys are saved."
                                class="text-xs font-medium text-[rgb(var(--nx-bad))] underline hover:text-[rgb(var(--nx-bad))]">
                            Clear API keys
                        </button>
                    @endif
                </div>
            </div>
            @forelse ($credentials as $c)
                <div class="mt-3 flex items-center justify-between gap-3 border-t border-[rgb(var(--nx-line))] pt-3">
                    <div class="min-w-0">
                        <p class="truncate text-sm text-[rgb(var(--nx-text))]">{{ $c['label'] }}</p>
                        <p class="font-mono text-xs text-[rgb(var(--nx-text-2))]">
                            @if (! $c['has']) <span class="text-[rgb(var(--nx-text-3))]">not set</span>
                            @elseif ($revealed && $c['secret']) {{ $c['raw'] }}
                            @else {{ $c['preview'] }} @endif
                        </p>
                    </div>
                    @if ($c['secret'] && $c['has'])<span class="shrink-0 text-[10px] uppercase text-[rgb(var(--nx-warn))]">secret</span>@endif
                </div>
            @empty
                <p class="mt-2 text-xs text-[rgb(var(--nx-text-2))]">No credential fields registered for this provider.</p>
            @endforelse

            @unless ($revealed)
                <form wire:submit="reveal" class="mt-4 flex items-end gap-2">
                    <div class="flex-1">
                        <label class="mb-1 block text-[11px] font-medium uppercase tracking-wide text-[rgb(var(--nx-text-2))]">Re-enter your password to reveal secrets</label>
                        <input type="password" wire:model="revealPassword" autocomplete="current-password"
                               class="w-full max-w-xs rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm">
                        @error('revealPassword') <p class="mt-1 text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p> @enderror
                    </div>
                    <button type="submit" class="rounded-lg bg-black/80 px-3 py-2 text-sm font-semibold text-white hover:bg-black/80">Reveal</button>
                </form>
            @endunless
        </div>
    @endif

    {{-- CIRCUIT & OUTCOMES --}}
    @if ($tab === 'circuit')
        <div class="rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-5">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-2">
                    <span class="text-sm font-semibold text-[rgb(var(--nx-text))]">Circuit</span>
                    <x-nci.pill kind="circuit" :value="$row->circuit_breaker_state" />
                    <span class="inline-flex items-center gap-1 text-xs text-[rgb(var(--nx-text-2))]">24h:
                        <x-icon name="check" class="h-3 w-3 text-[rgb(var(--nx-ok))]" /> {{ $row->success_count_24h }}
                        <span class="text-[rgb(var(--nx-text-3))]">/</span>
                        <x-icon name="x" class="h-3 w-3 text-[rgb(var(--nx-bad))]" /> {{ $row->failure_count_24h }}
                    </span>
                </div>
                @if ($canOverride)
                    <div class="flex items-center gap-2">
                        <button type="button" wire:click="openCircuit" wire:confirm="Force this provider's circuit OPEN? Live routing will skip it until reset."
                                class="rounded-lg border border-[rgb(var(--nx-bad))] px-3 py-1.5 text-xs font-semibold text-[rgb(var(--nx-bad))] hover:bg-[rgb(var(--nx-bad)/0.1)]">Open circuit</button>
                        <button type="button" wire:click="closeCircuit"
                                class="rounded-lg border border-[rgb(var(--nx-ok))] px-3 py-1.5 text-xs font-semibold text-[rgb(var(--nx-ok))] hover:bg-[rgb(var(--nx-ok)/0.12)]">Reset to closed</button>
                    </div>
                @endif
            </div>
        </div>
        <div class="mt-4 overflow-hidden rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))]">
            <div class="border-b border-[rgb(var(--nx-line))] p-4"><h2 class="text-sm font-semibold text-[rgb(var(--nx-text))]">Recent outcomes</h2></div>
            <div class="divide-y divide-[rgb(var(--nx-line))]">
                @forelse ($outcomes as $o)
                    <div class="flex items-center justify-between px-4 py-2 text-sm">
                        <span class="flex items-center gap-2">
                            <span class="h-2 w-2 rounded-full {{ $o->outcome === 'success' ? 'bg-[rgb(var(--nx-ok))]' : 'bg-[rgb(var(--nx-bad))]' }}"></span>
                            <span class="capitalize text-[rgb(var(--nx-text))]">{{ $o->outcome }}</span>
                            @if ($o->error_code)<span class="font-mono text-xs text-[rgb(var(--nx-text-2))]">{{ $o->error_code }}</span>@endif
                        </span>
                        <span class="text-xs text-[rgb(var(--nx-text-2))]">{{ $o->occurred_at?->diffForHumans() }}</span>
                    </div>
                @empty
                    <p class="p-4 text-xs text-[rgb(var(--nx-text-2))]">No recorded attempts yet.</p>
                @endforelse
            </div>
        </div>
    @endif

    {{-- NCI INTELLIGENCE (read-only) --}}
    @if ($tab === 'nci')
        <div class="rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-5">
            <p class="mb-3 text-xs text-[rgb(var(--nx-text-2))]">NCI's output is read-only here — the learning engine owns these; admin only views them.</p>
            <div class="grid gap-3 sm:grid-cols-4">
                <div><p class="text-[11px] uppercase tracking-wide text-[rgb(var(--nx-text-2))]">Score</p><p class="mt-1 text-lg font-bold text-[rgb(var(--nx-text))]">{{ is_null($row->nci_score) ? '—' : round($row->nci_score * 100) }}</p></div>
                <div><p class="text-[11px] uppercase tracking-wide text-[rgb(var(--nx-text-2))]">Confidence</p><p class="mt-1 text-lg font-bold text-[rgb(var(--nx-text))]">{{ is_null($row->nci_confidence) ? '—' : round($row->nci_confidence * 100).'%' }}</p></div>
                <div><p class="text-[11px] uppercase tracking-wide text-[rgb(var(--nx-text-2))]">Risk</p><div class="mt-1"><x-nci.pill kind="risk" :value="$row->nci_risk_rating" /></div></div>
                <div><p class="text-[11px] uppercase tracking-wide text-[rgb(var(--nx-text-2))]">Samples</p><p class="mt-1 text-lg font-bold text-[rgb(var(--nx-text))]">{{ $row->nci_sample_size }}</p></div>
            </div>
            <p class="mt-3 text-xs text-[rgb(var(--nx-text-2))]">Last computed: {{ $row->nci_computed_at?->diffForHumans() ?? 'never' }}</p>
        </div>
    @endif

    {{-- CONFIG (links to the existing settings — never duplicated) --}}
    @if ($tab === 'config')
        <div class="space-y-2 rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-5 text-sm">
            <p class="text-[rgb(var(--nx-text-2))]">Per-provider configuration lives in its existing admin home — this tab links there rather than duplicating it:</p>
            <a href="{{ route('admin.integrations') }}" wire:navigate class="block text-[rgb(var(--nx-teal-ink))] hover:underline">→ API keys & credentials (Integrations)</a>
            <a href="{{ route('admin.pricing') }}" wire:navigate class="block text-[rgb(var(--nx-teal-ink))] hover:underline">→ Pricing & margin rules</a>
        </div>
    @endif
</div>
