{{-- nx:converted (skin tokens only; see docs/appearance/SKIN-CONTRACT.md) --}}
<div class="mx-auto max-w-4xl">
    <h1 class="text-2xl font-bold text-[rgb(var(--nx-text))]">Payouts</h1>
    <p class="mt-1 text-sm text-[rgb(var(--nx-text-2))]">
        Send earned money out to bank accounts. Off by default. In manual mode you
        approve each request before it's sent; declining returns the held funds.
    </p>

    @if ($saved)
        <div class="mt-4 flex items-center gap-2 rounded-lg bg-[rgb(var(--nx-ok)/0.12)] p-3 text-sm text-[rgb(var(--nx-ok))]">
            <x-icon name="check" class="h-4 w-4" /> {{ $saved }}
        </div>
    @endif

    {{-- Settings --}}
    <div class="mt-5 rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-5 shadow-sm">
        <label class="flex items-center justify-between gap-4">
            <span>
                <span class="block text-sm font-semibold text-[rgb(var(--nx-text))]">Enable payouts</span>
                <span class="block text-xs text-[rgb(var(--nx-text-2))]">When off, no withdrawals can be requested or sent.</span>
            </span>
            <input type="checkbox" wire:model="enabled" class="h-5 w-9 cursor-pointer rounded-full">
        </label>

        <div class="mt-5 grid gap-4 sm:grid-cols-2">
            <div>
                <label class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">Settlement mode</label>
                <select wire:model="mode"
                        class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm text-[rgb(var(--nx-text))]">
                    <option value="manual">Manual — approve each request</option>
                    <option value="auto">Auto — the Guardian may approve (needs its own switches ON)</option>
                </select>
                @error('mode') <span class="text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</span> @enderror
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">Minimum withdrawal (USD)</label>
                <input type="number" step="0.5" min="0" wire:model="minWithdrawal"
                       class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm text-[rgb(var(--nx-text))]">
                @error('minWithdrawal') <span class="text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</span> @enderror
            </div>
        </div>

        <button type="button" wire:click="save" wire:loading.attr="disabled" wire:target="save"
                class="mt-5 flex items-center gap-2 rounded-lg bg-[rgb(var(--nx-cta-b))] px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-[rgb(var(--nx-cta-a))] disabled:opacity-60">
            <x-icon name="check" wire:loading.remove wire:target="save" class="h-4 w-4" />
            <x-ui.spinner wire:loading wire:target="save" class="h-4 w-4" />
            Save settings
        </button>
    </div>


    <div class="mt-3 flex flex-wrap gap-3 text-sm">
        @if ($isSuper)<a href="{{ route('admin.payout-settings', ['adminGateway' => request()->route('adminGateway')]) }}" wire:navigate class="font-semibold text-[rgb(var(--nx-teal-ink))] hover:underline">All payout settings</a>@endif
        <a href="{{ route('admin.payout-health', ['adminGateway' => request()->route('adminGateway')]) }}" wire:navigate class="font-semibold text-[rgb(var(--nx-teal-ink))] hover:underline">Payout health &amp; operations</a>
    </div>

    {{-- Tabs --}}
    <div class="mt-5 flex flex-wrap gap-1 rounded-xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-1 text-sm" role="tablist">
        @foreach (['queue' => 'Review queue', 'log' => 'Decision log', 'float' => 'Float', 'guardian' => 'Guardian', 'trust' => 'Trust', 'guide' => 'Rail guide'] as $key => $label)
            <button type="button" wire:click="$set('tab', '{{ $key }}')" role="tab" aria-selected="{{ $tab === $key ? 'true' : 'false' }}"
                    class="rounded-lg px-3 py-1.5 font-medium transition {{ $tab === $key ? 'bg-[rgb(var(--nx-cta-b))] text-white' : 'text-[rgb(var(--nx-text-2))] hover:bg-[rgb(var(--nx-surface-3))]' }}">
                {{ $label }}
                @if ($key === 'queue' && $pending->isNotEmpty())
                    <span class="ml-1 rounded-full bg-white/20 px-1.5 text-[11px]">{{ $pending->count() }}</span>
                @endif
            </button>
        @endforeach
    </div>

    @if ($tab === 'queue')
    {{-- Pending approvals (manual mode) --}}
    <div class="mt-6 flex items-center justify-between">
        <h2 class="text-sm font-semibold text-[rgb(var(--nx-text))]">Pending approval</h2>
        <div class="text-xs text-[rgb(var(--nx-text-2))]">${{ number_format($pendingTotal, 2) }} awaiting</div>
    </div>

    <div class="mt-3 space-y-3">
        @forelse ($pending as $req)
            @php($op = $opinions[$req->id] ?? null)
            <div class="rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-4 shadow-sm" wire:key="pending-{{ $req->id }}">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0">
                        <div class="text-sm font-semibold text-[rgb(var(--nx-text))]">{{ $req->user?->email ?? '—' }}</div>
                        <div class="mt-0.5 text-xs text-[rgb(var(--nx-text-2))]">
                            {{ $req->account?->account_name ?? '—' }}
                            <span class="text-[rgb(var(--nx-text-2))]">· {{ $req->account?->bank_name }} {{ $req->account?->masked_number }}</span>
                            · {{ str_replace('_', ' ', $req->source_bucket) }}
                            @if ($req->usd_amount) · ${{ number_format((float) $req->usd_amount, 2) }} USD @endif
                        </div>
                    </div>
                    <div class="text-right">
                        <div class="text-base font-semibold text-[rgb(var(--nx-text))]">{{ number_format((float) $req->amount, 2) }} {{ $req->currency }}</div>
                        <div class="mt-1 flex flex-wrap justify-end gap-1">
                            <span class="rounded-full bg-[rgb(var(--nx-surface-3))] px-2 py-0.5 text-[11px] font-semibold text-[rgb(var(--nx-text-2))]">{{ str_replace('_', ' ', $req->review_state) }}</span>
                            @if ($req->hold_reason)
                                <span class="rounded-full bg-[rgb(var(--nx-warn)/0.16)] px-2 py-0.5 text-[11px] font-semibold text-[rgb(var(--nx-warn))]">{{ str_replace('_', ' ', $req->hold_reason) }}</span>
                            @endif
                            @if ($req->risk_score !== null)
                                <span class="rounded-full bg-[rgb(var(--nx-surface-3))] px-2 py-0.5 text-[11px] font-semibold text-[rgb(var(--nx-text-2))]">risk {{ $req->risk_score }}</span>
                            @endif
                        </div>
                    </div>
                </div>

                @if ($op)
                    <details class="mt-3 rounded-lg bg-[rgb(var(--nx-surface-2))] p-3 text-xs">
                        <summary class="cursor-pointer font-medium text-[rgb(var(--nx-text))]">
                            Guardian opinion: <span class="uppercase">{{ $op->decision }}</span>
                            @if ($op->reason) <span class="text-[rgb(var(--nx-text-2))]">({{ str_replace('_', ' ', $op->reason) }})</span> @endif
                            @if ($op->shadow) <span class="text-[rgb(var(--nx-text-2))]">· advisory</span> @endif
                        </summary>
                        <ul class="mt-2 space-y-1">
                            @foreach ($op->rules as $rule)
                                <li class="flex items-start gap-2">
                                    <span class="mt-0.5 inline-block h-2 w-2 shrink-0 rounded-full {{ ['pass' => 'bg-[rgb(var(--nx-ok))]', 'warn' => 'bg-[rgb(var(--nx-warn))]', 'fail' => 'bg-[rgb(var(--nx-bad))]'][$rule['result'] ?? 'pass'] ?? 'bg-[rgb(var(--nx-line-strong))]' }}"></span>
                                    <span class="text-[rgb(var(--nx-text-2))]">
                                        <span class="font-medium">{{ $rule['id'] }}</span>
                                        @if (! empty($rule['reason'])) — {{ str_replace('_', ' ', $rule['reason']) }} @endif
                                        @if (! empty($rule['evidence'])) <code class="ml-1 break-all text-[11px] text-[rgb(var(--nx-text-2))]">{{ json_encode($rule['evidence']) }}</code> @endif
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    </details>
                @endif

                <div class="mt-3 flex flex-wrap items-start gap-2">
                    <div class="min-w-[12rem] flex-1">
                        <input type="text" wire:model="notes.{{ $req->id }}" placeholder="Note (required for any decision)"
                               class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-1.5 text-xs text-[rgb(var(--nx-text))]">
                        @error('notes.'.$req->id) <span class="text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</span> @enderror
                    </div>
                    <button type="button" wire:click="approve({{ $req->id }})" wire:loading.attr="disabled" wire:target="approve({{ $req->id }})"
                            wire:confirm="Approve and send this payout?"
                            class="rounded-lg bg-[rgb(var(--nx-cta-b))] px-3 py-1.5 text-xs font-semibold text-white hover:bg-[rgb(var(--nx-cta-a))] disabled:opacity-60">Approve</button>
                    <button type="button" wire:click="requestKyc({{ $req->id }})" wire:loading.attr="disabled" wire:target="requestKyc({{ $req->id }})"
                            class="rounded-lg border border-[rgb(var(--nx-line))] px-3 py-1.5 text-xs font-semibold text-[rgb(var(--nx-text-2))] hover:border-[rgb(var(--nx-warn))] hover:text-[rgb(var(--nx-warn))] disabled:opacity-60">Request KYC</button>
                    <button type="button" wire:click="reject({{ $req->id }})" wire:loading.attr="disabled" wire:target="reject({{ $req->id }})"
                            wire:confirm="Decline this payout and return the funds?"
                            class="rounded-lg border border-[rgb(var(--nx-line))] px-3 py-1.5 text-xs font-semibold text-[rgb(var(--nx-text-2))] hover:border-[rgb(var(--nx-bad))] hover:text-[rgb(var(--nx-bad))] disabled:opacity-60">Decline</button>
                </div>
            </div>
        @empty
            <div class="rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] px-4 py-8 text-center text-sm text-[rgb(var(--nx-text-2))]">Nothing awaiting approval.</div>
        @endforelse
    </div>

    {{-- Recent activity --}}
    <h2 class="mt-6 text-sm font-semibold text-[rgb(var(--nx-text))]">Recent payouts</h2>
    <div class="mt-3 overflow-x-auto rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] shadow-sm">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-[rgb(var(--nx-line))] text-left text-xs uppercase tracking-wide text-[rgb(var(--nx-text-2))]">
                    <th class="px-4 py-3">Payee</th>
                    <th class="px-4 py-3 text-right">Amount</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3">When</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($recent as $req)
                    @php($badge = match ($req->status) {
                        'paid' => 'bg-[rgb(var(--nx-ok)/0.16)] text-[rgb(var(--nx-ok))]',
                        'processing' => 'bg-[rgb(var(--nx-warn)/0.16)] text-[rgb(var(--nx-warn))]',
                        default => 'bg-[rgb(var(--nx-bad)/0.14)] text-[rgb(var(--nx-bad))]',
                    })
                    <tr class="border-b border-[rgb(var(--nx-line))]" wire:key="recent-{{ $req->id }}">
                        <td class="px-4 py-3 text-[rgb(var(--nx-text-2))]">{{ $req->user?->email ?? '—' }}</td>
                        <td class="px-4 py-3 text-right font-semibold text-[rgb(var(--nx-text))]">{{ number_format((float) $req->amount, 2) }} {{ $req->currency }}</td>
                        <td class="px-4 py-3"><span class="rounded-full px-2 py-0.5 text-[11px] font-semibold capitalize{{ $badge }}">{{ $req->status }}</span></td>
                        <td class="px-4 py-3 text-xs text-[rgb(var(--nx-text-2))]">{{ $req->updated_at?->diffForHumans() }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-4 py-8 text-center text-sm text-[rgb(var(--nx-text-2))]">No payouts yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @endif

    @if ($tab === 'log')
    <div class="mt-6 grid gap-3 sm:grid-cols-4">
        @foreach ([['Acted (24h)', array_sum($metrics['acted_24h'])], ['Advisory (24h)', array_sum($metrics['advisory_24h'])], ['In review', $metrics['manual_review']], ['Deferred', $metrics['deferred']]] as [$label, $val])
            <div class="rounded-xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-3">
                <div class="text-xs text-[rgb(var(--nx-text-2))]">{{ $label }}</div>
                <div class="text-xl font-bold text-[rgb(var(--nx-text))]">{{ $val }}</div>
            </div>
        @endforeach
    </div>
    @if ($metrics['breaker_active'])
        <div class="mt-3 rounded-lg bg-[rgb(var(--nx-bad)/0.1)] p-3 text-sm text-[rgb(var(--nx-bad))]">A Guardian circuit breaker is active — auto-approvals are paused for review.</div>
    @endif

    <div class="mt-4 flex flex-wrap items-center justify-between gap-2">
        <select wire:model.live="decisionFilter" class="rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-1.5 text-sm">
            <option value="">All decisions</option>
            @foreach (['approve', 'defer', 'hold', 'reject'] as $d) <option value="{{ $d }}">{{ ucfirst($d) }}</option> @endforeach
        </select>
        <button type="button" wire:click="exportLog" wire:loading.attr="disabled" wire:target="exportLog"
                class="rounded-lg border border-[rgb(var(--nx-line))] px-3 py-1.5 text-xs font-semibold text-[rgb(var(--nx-text-2))]">Export CSV</button>
    </div>
    <div class="mt-3 overflow-x-auto rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] shadow-sm">
        <table class="w-full text-sm">
            <thead><tr class="border-b border-[rgb(var(--nx-line))] text-left text-xs uppercase tracking-wide text-[rgb(var(--nx-text-2))]">
                <th class="px-4 py-3">When</th><th class="px-4 py-3">Payout</th><th class="px-4 py-3">Decision</th><th class="px-4 py-3">Reason</th><th class="px-4 py-3">Score</th><th class="px-4 py-3">By</th>
            </tr></thead>
            <tbody>
                @forelse ($log as $d)
                    <tr class="border-b border-[rgb(var(--nx-line))]" wire:key="log-{{ $d->id }}">
                        <td class="px-4 py-2 text-xs text-[rgb(var(--nx-text-2))]">{{ $d->decided_at?->diffForHumans() }}</td>
                        <td class="px-4 py-2 text-[rgb(var(--nx-text-2))]">#{{ $d->payout_request_id }}</td>
                        <td class="px-4 py-2 font-semibold uppercase text-[rgb(var(--nx-text))]">{{ $d->decision }} @if ($d->shadow)<span class="text-[10px] font-normal normal-case text-[rgb(var(--nx-text-2))]">advisory</span>@endif @if ($d->qa_sampled)<span class="text-[10px] font-normal normal-case text-[rgb(var(--nx-warn))]">QA</span>@endif</td>
                        <td class="px-4 py-2 text-xs text-[rgb(var(--nx-text-2))]">{{ str_replace('_', ' ', (string) $d->reason) }}</td>
                        <td class="px-4 py-2 text-[rgb(var(--nx-text-2))]">{{ $d->score }}</td>
                        <td class="px-4 py-2 text-xs text-[rgb(var(--nx-text-2))]">{{ $d->decided_by }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-8 text-center text-sm text-[rgb(var(--nx-text-2))]">No decisions yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($qa->isNotEmpty())
        <h3 class="mt-6 text-sm font-semibold text-[rgb(var(--nx-text))]">Auto-approved, flagged for QA review (14 days)</h3>
        <ul class="mt-2 space-y-1 text-xs text-[rgb(var(--nx-text-2))]">
            @foreach ($qa as $d) <li>#{{ $d->payout_request_id }} · score {{ $d->score }} · {{ $d->decided_at?->diffForHumans() }}</li> @endforeach
        </ul>
    @endif
    @endif

    @if ($tab === 'float')
    <div class="mt-6 rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-5 shadow-sm">
        <h2 class="text-sm font-semibold text-[rgb(var(--nx-text))]">Provider float</h2>
        <p class="text-xs text-[rgb(var(--nx-text-2))]">The money you have pre-funded at each provider. A tracked rail never sends past its float: payouts wait in "awaiting funds" (the user's money stays held) and resume, oldest first, when you record a top-up.</p>

        <div class="mt-4 overflow-x-auto">
            <table class="w-full text-sm">
                <thead><tr class="border-b border-[rgb(var(--nx-line))] text-left text-xs uppercase tracking-wide text-[rgb(var(--nx-text-2))]">
                    <th class="py-2 pr-4">Rail</th><th class="py-2 pr-4 text-right">Balance</th><th class="py-2 pr-4 text-right">Alert below</th><th class="py-2">Synced</th>
                </tr></thead>
                <tbody>
                    @forelse ($floats as $f)
                        <tr class="border-b border-[rgb(var(--nx-line))]" wire:key="float-{{ $f->id }}">
                            <td class="py-2 pr-4 font-medium capitalize text-[rgb(var(--nx-text))]">{{ $f->provider }} <span class="text-[rgb(var(--nx-text-2))]">{{ $f->currency }}</span></td>
                            <td class="py-2 pr-4 text-right tabular-nums {{ (float) $f->balance < (float) $f->low_threshold ? 'text-[rgb(var(--nx-bad))]' : 'text-[rgb(var(--nx-text))]' }}">{{ number_format((float) $f->balance, 2) }}</td>
                            <td class="py-2 pr-4 text-right tabular-nums text-[rgb(var(--nx-text-2))]">{{ number_format((float) $f->low_threshold, 2) }}</td>
                            <td class="py-2 text-xs text-[rgb(var(--nx-text-2))]">{{ $f->last_synced_at?->diffForHumans() ?? ($f->auto_sync ? 'pending' : 'manual') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="py-6 text-center text-sm text-[rgb(var(--nx-text-2))]">No rail is tracked yet — payouts are not gated by float.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-5 grid gap-3 sm:grid-cols-5">
            <select wire:model="floatProvider" class="rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm capitalize">
                @foreach (\App\Livewire\Admin\Payouts::PROVIDERS as $p) <option value="{{ $p }}">{{ $p }}</option> @endforeach
            </select>
            <input type="text" wire:model="floatCurrency" maxlength="3" placeholder="NGN" class="rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm uppercase">
            <input type="number" step="any" wire:model="floatAmount" placeholder="Amount" class="rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm">
            <input type="number" step="any" wire:model="floatThreshold" placeholder="Alert below" class="rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm">
            <input type="text" wire:model="floatNote" placeholder="Note / bank reference" class="rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm">
        </div>
        @error('floatAmount') <span class="text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</span> @enderror
        @error('floatNote') <span class="text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</span> @enderror
        <div class="mt-3 flex flex-wrap gap-2">
            <button type="button" wire:click="topUp" wire:loading.attr="disabled" wire:target="topUp" class="rounded-lg bg-[rgb(var(--nx-cta-b))] px-4 py-2 text-sm font-semibold text-white hover:bg-[rgb(var(--nx-cta-a))] disabled:opacity-60">Record top-up</button>
            <button type="button" wire:click="trackRail" wire:loading.attr="disabled" wire:target="trackRail" class="rounded-lg border border-[rgb(var(--nx-line))] px-4 py-2 text-sm font-semibold text-[rgb(var(--nx-text-2))] disabled:opacity-60">Track this rail</button>
            @if ($isSuper)
                <button type="button" wire:click="adjustFloat" wire:loading.attr="disabled" wire:target="adjustFloat" wire:confirm="Post a signed correction to the float ledger?" class="rounded-lg border border-[rgb(var(--nx-line))] px-4 py-2 text-sm font-semibold text-[rgb(var(--nx-text-2))] disabled:opacity-60">Adjust (signed)</button>
            @endif
        </div>

        @if ($waiting->isNotEmpty())
            <h3 class="mt-6 text-sm font-semibold text-[rgb(var(--nx-text))]">Awaiting funds ({{ $waiting->count() }})</h3>
            <ul class="mt-2 space-y-1 text-xs text-[rgb(var(--nx-text-2))]">
                @foreach ($waiting as $w) <li wire:key="wait-{{ $w->id }}">#{{ $w->id }} · {{ $w->user?->email }} · {{ number_format((float) $w->amount, 2) }} {{ $w->currency }} · {{ $w->provider }}</li> @endforeach
            </ul>
        @endif

        @if ($movements->isNotEmpty())
            <h3 class="mt-6 text-sm font-semibold text-[rgb(var(--nx-text))]">Recent movements</h3>
            <ul class="mt-2 space-y-1 text-xs text-[rgb(var(--nx-text-2))]">
                @foreach ($movements as $m)
                    <li wire:key="mv-{{ $m->id }}" class="flex justify-between gap-3">
                        <span>{{ $m->created_at?->diffForHumans() }} · {{ $m->provider }} {{ $m->currency }} · {{ str_replace('_', ' ', $m->type) }} @if ($m->note) · {{ $m->note }} @endif</span>
                        <span class="tabular-nums {{ (float) $m->amount < 0 ? 'text-[rgb(var(--nx-bad))]' : 'text-[rgb(var(--nx-ok))]' }}">{{ number_format((float) $m->amount, 2) }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
    @endif

    @if ($tab === 'guardian')
    <div class="mt-6 rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-5 shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-sm font-semibold text-[rgb(var(--nx-text))]">Payout Guardian</h2>
                <p class="text-xs text-[rgb(var(--nx-text-2))]">Starts advisory: it logs what it WOULD do and every request still comes to you. It only approves when auto-approval is on, shadow mode is off, settlement mode is Auto and the rail's own switch is on.</p>
            </div>
            <button type="button" wire:click="pauseAutoApprovals" wire:loading.attr="disabled" wire:target="pauseAutoApprovals" wire:confirm="Pause ALL auto-approvals now?"
                    class="rounded-lg bg-[rgb(var(--nx-bad))] px-4 py-2 text-sm font-semibold text-white hover:opacity-90 disabled:opacity-60">Pause all auto-approvals</button>
        </div>

        @unless ($isSuper)
            <p class="mt-3 rounded-lg bg-[rgb(var(--nx-warn)/0.12)] p-3 text-xs text-[rgb(var(--nx-warn))]">Only a super admin can change these settings. You can still pause auto-approvals.</p>
        @endunless

        <div class="mt-4 grid gap-3 sm:grid-cols-2">
            <label class="flex items-center gap-2 text-sm text-[rgb(var(--nx-text))]"><input type="checkbox" wire:model="autoApproval" @disabled(! $isSuper)> Auto-approval enabled</label>
            <label class="flex items-center gap-2 text-sm text-[rgb(var(--nx-text))]"><input type="checkbox" wire:model="shadow" @disabled(! $isSuper)> Shadow (advisory) mode</label>
        </div>

        <div class="mt-4">
            <div class="text-xs font-medium text-[rgb(var(--nx-text-2))]">Allow auto-approval per rail</div>
            <div class="mt-1 flex flex-wrap gap-4">
                @foreach (\App\Livewire\Admin\Payouts::PROVIDERS as $p)
                    <label class="flex items-center gap-2 text-sm capitalize text-[rgb(var(--nx-text))]"><input type="checkbox" wire:model="providerAuto.{{ $p }}" @disabled(! $isSuper)> {{ $p }}</label>
                @endforeach
            </div>
        </div>

        <div class="mt-4 grid gap-3 sm:grid-cols-3">
            @foreach ([['tierNew', 'New-tier limit (USD)'], ['tierTrusted', 'Trusted limit (USD)'], ['tierVip', 'VIP limit (USD)'], ['dailyCap', 'Daily auto-approval cap (USD)'], ['qaPct', 'QA sample %'], ['coolingOff', 'Cooling-off (hours)'], ['fxTolerance', 'FX tolerance %'], ['maxOpen', 'Max open requests / user']] as [$field, $label])
                <div>
                    <label class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">{{ $label }}</label>
                    <input type="number" step="any" min="0" wire:model="{{ $field }}" @disabled(! $isSuper)
                           class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm text-[rgb(var(--nx-text))] disabled:opacity-60">
                    @error($field) <span class="text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</span> @enderror
                </div>
            @endforeach
        </div>
        <div class="mt-3">
            <label class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">Denied countries (ISO codes, comma-separated)</label>
            <input type="text" wire:model="deniedCountries" @disabled(! $isSuper)
                   class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm text-[rgb(var(--nx-text))] disabled:opacity-60">
        </div>

        @if ($isSuper)
            <button type="button" wire:click="saveGuardian" wire:loading.attr="disabled" wire:target="saveGuardian"
                    class="mt-5 flex items-center gap-2 rounded-lg bg-[rgb(var(--nx-cta-b))] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[rgb(var(--nx-cta-a))] disabled:opacity-60">
                <x-ui.spinner wire:loading wire:target="saveGuardian" class="h-4 w-4" /> Save Guardian settings
            </button>
        @endif
    </div>
    @endif

    @if ($tab === 'guide')
        <livewire:admin.payout-rail-guide />
    @endif

    @if ($tab === 'trust')
    <div class="mt-6 rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-5 shadow-sm">
        <h2 class="text-sm font-semibold text-[rgb(var(--nx-text))]">Trust tier override</h2>
        <p class="text-xs text-[rgb(var(--nx-text-2))]">New users start at the lowest limit; the daily recompute promotes clean payees. An override here is never changed by the recompute. Super admin only.</p>
        <div class="mt-4 grid gap-3 sm:grid-cols-3">
            <input type="email" wire:model="trustEmail" placeholder="User email" @disabled(! $isSuper)
                   class="rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm">
            <select wire:model="trustTier" @disabled(! $isSuper) class="rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm">
                <option value="new">New</option><option value="trusted">Trusted</option><option value="vip">VIP</option>
            </select>
            <input type="text" wire:model="trustReason" placeholder="Reason (required)" @disabled(! $isSuper)
                   class="rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm">
        </div>
        @error('trustEmail') <span class="text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</span> @enderror
        @error('trustReason') <span class="text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</span> @enderror
        @if ($isSuper)
            <button type="button" wire:click="setTrust" wire:loading.attr="disabled" wire:target="setTrust"
                    class="mt-4 rounded-lg bg-[rgb(var(--nx-cta-b))] px-4 py-2 text-sm font-semibold text-white hover:bg-[rgb(var(--nx-cta-a))] disabled:opacity-60">Set tier</button>
        @endif
    </div>
    @endif
</div>
