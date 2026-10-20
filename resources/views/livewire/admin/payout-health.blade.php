{{-- nx:converted (skin tokens only; see docs/appearance/SKIN-CONTRACT.md) --}}
@php
    $card = 'rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-5 shadow-sm';
    $inp = 'w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm text-[rgb(var(--nx-text))]';
    $btn = 'rounded-lg bg-[rgb(var(--nx-cta-b))] px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-[rgb(var(--nx-cta-a))] disabled:opacity-50';
    $btn2 = 'rounded-lg border border-[rgb(var(--nx-line-strong))] px-3 py-1.5 text-xs font-semibold text-[rgb(var(--nx-text))] transition hover:bg-[rgb(var(--nx-surface-2))] disabled:opacity-50';
@endphp
<div class="mx-auto max-w-4xl space-y-6 pb-12">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-[rgb(var(--nx-text))]">Payout health &amp; operations</h1>
            <p class="mt-1 text-sm text-[rgb(var(--nx-text-2))]">Proof the books are right, and the few things that need a human decision. Every action needs a note and is logged.</p>
        </div>
        <span class="flex items-center gap-4">
            @if (\Illuminate\Support\Facades\Route::has('admin.crypto-whitelist'))
                <a href="{{ route('admin.crypto-whitelist', ['adminGateway' => request()->route('adminGateway')]) }}" wire:navigate class="text-sm font-semibold text-[rgb(var(--nx-teal-ink))] hover:underline">Crypto wallet whitelist</a>
            @endif
            <a href="{{ route('admin.payouts', ['adminGateway' => request()->route('adminGateway')]) }}" wire:navigate class="text-sm font-semibold text-[rgb(var(--nx-teal-ink))] hover:underline">Back to Payouts</a>
        </span>
    </div>

    @if ($message)<div class="flex items-center gap-2 rounded-lg bg-[rgb(var(--nx-ok)/0.12)] p-3 text-sm text-[rgb(var(--nx-ok))]" role="status"><x-icon name="check" class="h-4 w-4" /> {{ $message }}</div>@endif
    @if ($error)<div class="rounded-lg bg-[rgb(var(--nx-bad)/0.1)] p-3 text-sm text-[rgb(var(--nx-bad))]" role="alert">{{ $error }}</div>@endif

    {{-- Gateway status and probes: every payout gateway (core and updater-delivered) with its real state and the proof it works --}}
    <section class="{{ $card }}" aria-labelledby="h-gw">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 id="h-gw" class="text-base font-bold text-[rgb(var(--nx-text))]">Payout gateways</h2>
                <p class="text-xs text-[rgb(var(--nx-text-2))]">The real state of each rail and the result of the last test. A probe only reads: no money moves.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <button type="button" wire:click="probeAll('auth')" wire:loading.attr="disabled" wire:target="probeAll" class="{{ $btn }}"><span wire:loading.remove wire:target="probeAll">Run all probes</span><span wire:loading wire:target="probeAll">Running…</span></button>
                <button type="button" wire:click="probeAll('capability')" wire:loading.attr="disabled" wire:target="probeAll" class="{{ $btn2 }}">Check corridors</button>
            </div>
        </div>
        <ul class="mt-3 divide-y divide-[rgb(var(--nx-line))]">
            @foreach ($gateways as $g)
                @php $s = $g['status']; @endphp
                <li class="py-3" wire:key="gw-{{ $s->provider }}">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-[rgb(var(--nx-text))]">{{ $s->label }}
                                <span class="ml-1 rounded-full px-2 py-0.5 text-xs font-semibold {{ $s->usable() ? 'bg-[rgb(var(--nx-ok)/0.14)] text-[rgb(var(--nx-ok))]' : ($s->state === 'failing' || $s->state === 'blocked' ? 'bg-[rgb(var(--nx-bad)/0.12)] text-[rgb(var(--nx-bad))]' : 'bg-[rgb(var(--nx-surface-2))] text-[rgb(var(--nx-text-2))]') }}">{{ $s->usable() ? 'Ready' : str_replace('_', ' ', $s->state) }}</span>
                            </p>
                            <p class="mt-1 text-xs text-[rgb(var(--nx-text-2))]">{{ $s->adminMessage }}</p>
                            @if ($s->fix)<p class="mt-1 text-xs text-[rgb(var(--nx-warn))]">Fix: {{ $s->fix }}</p>@endif
                            @foreach (['auth' => 'Credentials test', 'capability' => 'Corridor check'] as $tier => $tierLabel)
                                @if ($g[$tier])
                                    <p class="mt-1 text-xs {{ $g[$tier]->status === 'ok' ? 'text-[rgb(var(--nx-ok))]' : ($g[$tier]->status === 'fail' ? 'text-[rgb(var(--nx-bad))]' : 'text-[rgb(var(--nx-text-2))]') }}">{{ $tierLabel }}: {{ $g[$tier]->status }} · {{ $g[$tier]->created_at->diffForHumans() }}{{ $g[$tier]->latency_ms ? ' · '.$g[$tier]->latency_ms.' ms' : '' }} · {{ $g[$tier]->summary }}</p>
                                    @foreach ((array) $g[$tier]->mismatches as $mm)<p class="ml-3 text-xs text-[rgb(var(--nx-bad))]">• {{ $mm }}</p>@endforeach
                                @endif
                            @endforeach
                        </div>
                        @if ($s->installed || $s->core)
                            <div class="flex gap-2">
                                <button type="button" wire:click="probe('{{ $s->provider }}', 'auth')" wire:loading.attr="disabled" wire:target="probe" class="{{ $btn2 }}">Test</button>
                                <button type="button" wire:click="probe('{{ $s->provider }}', 'capability')" wire:loading.attr="disabled" wire:target="probe" class="{{ $btn2 }}">Check corridors</button>
                            </div>
                        @endif
                    </div>
                </li>
            @endforeach
        </ul>
    </section>

    <section class="{{ $card }}" aria-labelledby="h-sim">
        <h2 id="h-sim" class="text-base font-bold text-[rgb(var(--nx-text))]">Simulate a corridor</h2>
        <p class="text-xs text-[rgb(var(--nx-text-2))]">Pick a country, currency and amount (USD) to see which gateway would pay it, or exactly why none can.</p>
        <div class="mt-3 grid gap-3 sm:grid-cols-[1fr_1fr_1fr_auto] sm:items-end">
            <label class="block text-xs text-[rgb(var(--nx-text-2))]">Country<input type="text" wire:model="simCountry" maxlength="2" class="{{ $inp }} mt-1 uppercase"></label>
            <label class="block text-xs text-[rgb(var(--nx-text-2))]">Currency<input type="text" wire:model="simCurrency" maxlength="3" class="{{ $inp }} mt-1 uppercase"></label>
            <label class="block text-xs text-[rgb(var(--nx-text-2))]">Amount (USD)<input type="text" inputmode="decimal" wire:model="simAmount" class="{{ $inp }} mt-1"></label>
            <button type="button" wire:click="simulate" wire:loading.attr="disabled" wire:target="simulate" class="{{ $btn }}">Simulate</button>
        </div>
        @if ($simLines)
            <ul class="mt-3 space-y-1 text-sm text-[rgb(var(--nx-text))]">@foreach ($simLines as $l)<li>{{ $l }}</li>@endforeach</ul>
        @endif
    </section>

    {{-- Automation status --}}
    <section class="{{ $card }}" aria-labelledby="h-auto">
        <div class="flex items-start gap-3">
            <x-icon :name="$automation['automatic'] ? 'check' : 'alert-triangle'" class="mt-0.5 h-5 w-5 shrink-0 {{ $automation['automatic'] ? 'text-[rgb(var(--nx-ok))]' : 'text-[rgb(var(--nx-warn))]' }}" />
            <div class="min-w-0">
                <h2 id="h-auto" class="text-base font-bold text-[rgb(var(--nx-text))]">
                    {{ $automation['automatic'] ? 'Payouts are automatic' : 'Payouts are NOT fully automatic yet' }}
                </h2>
                <p class="text-xs text-[rgb(var(--nx-text-2))]">
                    @if ($automation['automatic']) Low-risk requests on {{ implode(', ', array_map('ucfirst', $automation['rails'])) }} are approved and sent by the system. Unusual or large ones still wait for a person.
                    @else Fix the red items below and supported payouts will run by themselves. @endif
                </p>
                <ul class="mt-2 space-y-1 text-sm">
                    @foreach ($automation['checks'] as $c)
                        <li class="flex items-start gap-2">
                            <x-icon :name="$c['ok'] ? 'check' : 'x'" class="mt-0.5 h-4 w-4 shrink-0 {{ $c['ok'] ? 'text-[rgb(var(--nx-ok))]' : 'text-[rgb(var(--nx-bad))]' }}" />
                            <span class="text-[rgb(var(--nx-text))]">{{ $c['label'] }}@if ($c['fix']) <span class="block text-xs text-[rgb(var(--nx-bad))]">{{ $c['fix'] }}</span>@endif</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    </section>

    {{-- Rails delivered later by the Platform Updater --}}
    <section class="{{ $card }}" aria-labelledby="h-rails">
        <h2 id="h-rails" class="text-base font-bold text-[rgb(var(--nx-text))]">Rails coming through the Platform Updater</h2>
        <p class="text-xs text-[rgb(var(--nx-text-2))]">These need a provider account first. Until their package is installed they show as "coming soon" to users and nothing else is affected: Paystack, Flutterwave and Stripe keep paying out as normal.</p>
        <ul class="mt-3 divide-y divide-[rgb(var(--nx-line))] text-sm">
            @foreach ($rails as $r)
                <li class="flex flex-wrap items-start justify-between gap-2 py-2">
                    <div class="min-w-0">
                        <span class="font-semibold text-[rgb(var(--nx-text))]">{{ $r['label'] }}</span>
                        @if ($r['version'])<span class="ml-1 text-xs text-[rgb(var(--nx-text-2))]">v{{ $r['version'] }}</span>@endif
                        @if ($r['note'])<span class="block text-xs text-[rgb(var(--nx-text-2))]">{{ $r['note'] }}</span>@endif
                    </div>
                    @php
                        $badge = match ($r['status']) {
                            'installed' => 'bg-[rgb(var(--nx-ok)/0.12)] text-[rgb(var(--nx-ok))]',
                            'problem' => 'bg-[rgb(var(--nx-bad)/0.1)] text-[rgb(var(--nx-bad))]',
                            'disabled' => 'bg-[rgb(var(--nx-surface-3))] text-[rgb(var(--nx-text-2))]',
                            default => 'bg-[rgb(var(--nx-warn)/0.12)] text-[rgb(var(--nx-warn))]',
                        };
                    @endphp
                    <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold{{ $badge }}">{{ ['installed' => 'Installed', 'problem' => 'Needs attention', 'disabled' => 'Switched off', 'coming_soon' => 'Coming soon'][$r['status']] ?? $r['status'] }}</span>
                </li>
            @endforeach
        </ul>
        @if ($railProblems !== [])
            <p class="mt-2 text-xs text-[rgb(var(--nx-bad))]">Skipped extensions: {{ collect($railProblems)->map(fn ($p) => $p['slug'].' ('.$p['message'].')')->implode('; ') }}</p>
        @endif
    </section>

    {{-- Money invariants --}}
    <section class="{{ $card }}" aria-labelledby="h-inv">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <div>
                <h2 id="h-inv" class="text-base font-bold text-[rgb(var(--nx-text))]">Money checks</h2>
                <p class="text-xs text-[rgb(var(--nx-text-2))]">Runs every night. Read-only: it proves the ledgers agree with every payout, and never changes anything.</p>
            </div>
            @if ($canReview)<button type="button" wire:click="runInvariants" wire:loading.attr="disabled" wire:target="runInvariants" class="{{ $btn }}">Run now</button>@endif
        </div>
        @if ($run)
            <p class="mt-3 text-sm {{ $run->status === 'ok' ? 'text-[rgb(var(--nx-ok))]' : 'text-[rgb(var(--nx-bad))]' }}">
                {{ $run->status === 'ok' ? 'All checks passed' : $run->violation_count.' problem(s) found' }} · {{ $run->finished_at?->diffForHumans() ?? 'running' }} · {{ $run->triggered_by }}
            </p>
            <ul class="mt-2 space-y-1 text-sm">
                @foreach ($run->results ?? [] as $r)
                    <li class="flex items-start gap-2">
                        <x-icon :name="$r['ok'] ? 'check' : 'alert-triangle'" class="mt-0.5 h-4 w-4 shrink-0 {{ $r['ok'] ? 'text-[rgb(var(--nx-ok))]' : 'text-[rgb(var(--nx-bad))]' }}" />
                        <div class="min-w-0">
                            <span class="text-[rgb(var(--nx-text))]">{{ $r['name'] }}</span>
                            @unless ($r['ok'])
                                <span class="text-xs text-[rgb(var(--nx-bad))]">({{ $r['count'] }})</span>
                                <ul class="mt-1 space-y-0.5 text-xs text-[rgb(var(--nx-text-2))]">
                                    @foreach (array_slice($r['offenders'], 0, 8) as $o)<li class="font-mono">{{ json_encode($o) }}</li>@endforeach
                                </ul>
                            @endunless
                        </div>
                    </li>
                @endforeach
            </ul>
        @else
            <p class="mt-3 text-sm text-[rgb(var(--nx-text-2))]">No run yet. It runs nightly, or press Run now.</p>
        @endif
    </section>

    {{-- Needs a decision --}}
    @if ($canReview)
        <section class="{{ $card }}" aria-labelledby="h-act">
            <h2 id="h-act" class="text-base font-bold text-[rgb(var(--nx-text))]">Needs your decision</h2>
            @if ($held->isEmpty() && $unknown->isEmpty() && $manual->isEmpty())
                <p class="mt-2 text-sm text-[rgb(var(--nx-text-2))]">Nothing waiting.</p>
            @endif

            @foreach ($held as $r)
                <div class="mt-4 rounded-xl border border-[rgb(var(--nx-warn))] p-3" wire:key="held-{{ $r->id }}">
                    <p class="text-sm font-semibold text-[rgb(var(--nx-text))]">#{{ $r->id }} · {{ number_format((float) $r->amount, 2) }} {{ $r->currency }} · {{ $r->user?->email }}</p>
                    <p class="text-xs text-[rgb(var(--nx-warn))]">Not sent: the payout account was {{ $r->hold_reason === 'account_deleted_after_request' ? 'removed' : 'changed' }} after the request was made. Check with the user first.</p>
                    <div class="mt-2 flex flex-wrap items-center gap-2">
                        <input type="text" wire:model="notes.{{ $r->id }}" placeholder="Note (required)" class="{{ $inp }} sm:max-w-xs" aria-label="Note for request {{ $r->id }}">
                        @if ($r->hold_reason === 'account_changed_after_request')<button type="button" wire:click="acceptDestination({{ $r->id }})" wire:loading.attr="disabled" class="{{ $btn }}">It's genuine — accept new account</button>@endif
                        <button type="button" wire:click="rejectHeld({{ $r->id }})" wire:confirm="Decline this payout and return the funds?" wire:loading.attr="disabled" class="{{ $btn2 }}">Decline &amp; return funds</button>
                    </div>
                </div>
            @endforeach

            @foreach ($unknown as $r)
                <div class="mt-4 rounded-xl border border-[rgb(var(--nx-bad))] p-3" wire:key="unk-{{ $r->id }}">
                    <p class="text-sm font-semibold text-[rgb(var(--nx-text))]">#{{ $r->id }} · {{ number_format((float) $r->amount, 2) }} {{ $r->currency }} · {{ ucfirst($r->provider) }}</p>
                    <p class="text-xs text-[rgb(var(--nx-bad))]">We sent this but never heard the result and the provider can't confirm it. Check the provider's own dashboard, then say what happened. Do not refund blindly.</p>
                    <div class="mt-2 flex flex-wrap items-center gap-2">
                        <input type="text" wire:model="notes.{{ $r->id }}" placeholder="Evidence (required)" class="{{ $inp }} sm:max-w-xs" aria-label="Evidence for request {{ $r->id }}">
                        <button type="button" wire:click="resolveUnknown({{ $r->id }}, 'paid')" wire:confirm="Provider shows it was PAID?" class="{{ $btn }}">It was paid</button>
                        <button type="button" wire:click="resolveUnknown({{ $r->id }}, 'failed')" wire:confirm="Provider shows it did NOT go out? The funds will be returned." class="{{ $btn2 }}">It did not go out</button>
                    </div>
                </div>
            @endforeach

            @foreach ($manual as $r)
                <div class="mt-4 rounded-xl border border-[rgb(var(--nx-line-strong))] p-3" wire:key="man-{{ $r->id }}">
                    <p class="text-sm font-semibold text-[rgb(var(--nx-text))]">Pay by hand · #{{ $r->id }} · {{ number_format((float) $r->amount, 2) }} {{ $r->currency }} · {{ $r->user?->email }}</p>
                    <p class="text-xs text-[rgb(var(--nx-teal-ink))]">Pay this person from your own bank/app, then record the proof so the books stay right.</p>
                    <div class="mt-2 grid gap-2 sm:grid-cols-[1fr_1fr_auto]">
                        <input type="text" wire:model="proofs.{{ $r->id }}" placeholder="Proof reference (transfer / receipt no.)" class="{{ $inp }}" aria-label="Proof reference for request {{ $r->id }}">
                        <input type="text" wire:model="notes.{{ $r->id }}" placeholder="Note (required)" class="{{ $inp }}" aria-label="Note for request {{ $r->id }}">
                        <button type="button" wire:click="recordManual({{ $r->id }})" wire:loading.attr="disabled" class="{{ $btn }}">Record payment</button>
                    </div>
                </div>
            @endforeach
        </section>

        <section class="{{ $card }}" aria-labelledby="h-ret">
            <h2 id="h-ret" class="text-base font-bold text-[rgb(var(--nx-text))]">A delivered payout came back</h2>
            <p class="text-xs text-[rgb(var(--nx-text-2))]">If the bank or provider returned money after we marked it delivered: enter the payout number and the evidence. The money goes back to the user's balance once, and their payout account is flagged.</p>
            <div class="mt-3 grid gap-2 sm:grid-cols-[120px_1fr_auto]">
                <input type="number" wire:model="returnedId" placeholder="Payout #" class="{{ $inp }}" aria-label="Payout number">
                <input type="text" wire:model="returnedEvidence" placeholder="Evidence (bank notice, provider message…)" class="{{ $inp }}" aria-label="Evidence">
                <button type="button" wire:click="markReturned" wire:confirm="Mark this payout as returned and re-credit the user?" wire:loading.attr="disabled" class="{{ $btn }}">Mark returned</button>
            </div>
        </section>
    @endif

    {{-- Frozen payees --}}
    <section class="{{ $card }}" aria-labelledby="h-frz">
        <h2 id="h-frz" class="text-base font-bold text-[rgb(var(--nx-text))]">Paused payees</h2>
        <p class="text-xs text-[rgb(var(--nx-text-2))]">People who pressed "This wasn't me" or were paused by an admin. They cannot request payouts until a super admin clears them.</p>
        @forelse ($freezes as $f)
            <div class="mt-3 flex flex-wrap items-center justify-between gap-2 border-t border-[rgb(var(--nx-line))] pt-3 text-sm" wire:key="frz-{{ $f->id }}">
                <span class="text-[rgb(var(--nx-text))]">User #{{ $f->user_id }} · {{ $f->reason }} · {{ $f->frozen_at->diffForHumans() }}</span>
                @if ($isSuper)
                    <span class="flex items-center gap-2">
                        <input type="text" wire:model="releaseNote" placeholder="Why is it safe? (required)" class="{{ $inp }} w-56" aria-label="Release note">
                        <button type="button" wire:click="releaseFreeze({{ $f->user_id }})" wire:confirm="Re-enable payouts for this payee?" class="{{ $btn2 }}">Clear</button>
                    </span>
                @endif
            </div>
        @empty
            <p class="mt-2 text-sm text-[rgb(var(--nx-text-2))]">No one is paused.</p>
        @endforelse
    </section>

    {{-- Settlement reconciliation + accounting --}}
    @if ($canFinance)
        <section class="{{ $card }}" aria-labelledby="h-rec">
            <h2 id="h-rec" class="text-base font-bold text-[rgb(var(--nx-text))]">Match against a provider statement</h2>
            <p class="text-xs text-[rgb(var(--nx-text-2))]">Upload the provider's statement (CSV with <code>reference, amount, currency</code>, optional <code>fee</code>). It only flags differences — nothing is changed automatically.</p>
            <div class="mt-3 grid gap-2 sm:grid-cols-4">
                <input type="text" wire:model="reconProvider" class="{{ $inp }}" aria-label="Provider" placeholder="paystack">
                <input type="date" wire:model="reconFrom" class="{{ $inp }}" aria-label="From">
                <input type="date" wire:model="reconTo" class="{{ $inp }}" aria-label="To">
                <input type="file" wire:model="statement" accept=".csv,.txt" class="text-xs text-[rgb(var(--nx-text-2))]" aria-label="Statement file">
            </div>
            @error('statement')<p class="mt-1 text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p>@enderror
            <button type="button" wire:click="reconcile" wire:loading.attr="disabled" wire:target="reconcile,statement" class="{{ $btn }} mt-3">Reconcile</button>

            @if ($runs->isNotEmpty())
                <p class="mt-4 text-xs font-semibold uppercase tracking-wider text-[rgb(var(--nx-text-2))]">Recent runs</p>
                <ul class="mt-1 text-sm text-[rgb(var(--nx-text))]">
                    @foreach ($runs as $r)<li>#{{ $r->id }} · {{ $r->provider }} · {{ $r->period_from->toDateString() }} → {{ $r->period_to->toDateString() }} · {{ $r->matched }} matched · {{ $r->flagged }} to review</li>@endforeach
                </ul>
            @endif

            @foreach ($items as $i)
                <div class="mt-3 flex flex-wrap items-center gap-2 border-t border-[rgb(var(--nx-line))] pt-3 text-sm" wire:key="item-{{ $i->id }}">
                    <span class="min-w-0 flex-1 text-[rgb(var(--nx-text))]"><strong>{{ str_replace('_', ' ', $i->kind) }}</strong> · {{ $i->provider_reference }} · ours {{ $i->our_amount ?? '—' }} / provider {{ $i->provider_amount ?? '—' }} {{ $i->currency }}</span>
                    <input type="text" wire:model="itemNotes.{{ $i->id }}" placeholder="Resolution note" class="{{ $inp }} w-48" aria-label="Resolution note">
                    <button type="button" wire:click="resolveItem({{ $i->id }})" class="{{ $btn2 }}">Resolve</button>
                </div>
            @endforeach
        </section>

        <section class="{{ $card }}" aria-labelledby="h-acc">
            <h2 id="h-acc" class="text-base font-bold text-[rgb(var(--nx-text))]">Month-end accounting export</h2>
            <p class="text-xs text-[rgb(var(--nx-text-2))]">Balanced double-entry lines for your accountant (liability, in transit, provider float, fees). Downloads are logged.</p>
            <div class="mt-3 flex flex-wrap items-center gap-2">
                <input type="month" wire:model="exportMonth" class="{{ $inp }} w-44" aria-label="Month">
                <button type="button" wire:click="exportAccounting" wire:loading.attr="disabled" wire:target="exportAccounting" class="{{ $btn }}">Download CSV</button>
            </div>
        </section>
    @endif

    @if ($canFinance)
        <section class="{{ $card }}" aria-labelledby="h-claw">
            <h2 id="h-claw" class="text-base font-bold text-[rgb(var(--nx-text))]">Reverse earnings after a refund or lost chargeback</h2>
            <p class="text-xs text-[rgb(var(--nx-text-2))]">Always a person's decision. Find the sale by the buyer's email or the order reference, pick the earnings it created, and reverse them. If the earner already withdrew the money their balance goes below zero as an <em>adjustment</em> that their next earnings repay; nothing is taken from them in cash, and they cannot withdraw until it is cleared.</p>
            <div class="mt-3 grid gap-2 sm:grid-cols-[1fr_auto]">
                <input type="text" wire:model="clawQuery" wire:keydown.enter="findEarnings" placeholder="Buyer email or order reference" class="{{ $inp }}" aria-label="Buyer email or order reference">
                <button type="button" wire:click="findEarnings" wire:loading.attr="disabled" wire:target="findEarnings" class="{{ $btn }}">Find earnings</button>
            </div>
            @if ($clawResults !== [])
                <input type="text" wire:model="clawReason" placeholder="Reason (shown to the earner) — required" class="{{ $inp }} mt-3" aria-label="Reason">
                @foreach ($clawResults as $c)
                    <div class="mt-3 flex flex-wrap items-center justify-between gap-2 border-t border-[rgb(var(--nx-line))] pt-3 text-sm" wire:key="claw-{{ $c['kind'] }}-{{ $c['id'] }}">
                        <span class="min-w-0 text-[rgb(var(--nx-text))]">{{ $c['owner'] }} @if ($c['owner_email'])({{ $c['owner_email'] }})@endif · ${{ number_format($c['amount'], 2) }} · {{ $c['source'] }} · {{ $c['at'] }}</span>
                        @if ($c['reversed'])
                            <span class="text-xs font-semibold text-[rgb(var(--nx-ok))]">Already reversed</span>
                        @else
                            <button type="button" wire:click="reverseEarnings('{{ $c['kind'] }}', {{ $c['id'] }})" wire:confirm="Reverse ${{ number_format($c['amount'], 2) }} of this earner's earnings?" wire:loading.attr="disabled" class="{{ $btn2 }}">Reverse ${{ number_format($c['amount'], 2) }}</button>
                        @endif
                    </div>
                @endforeach
            @endif
        </section>
    @endif

    @if ($isSuper)
        <section class="{{ $card }}" aria-labelledby="h-fx">
            <h2 id="h-fx" class="text-base font-bold text-[rgb(var(--nx-text))]">Accept a new exchange rate</h2>
            <p class="text-xs text-[rgb(var(--nx-text-2))]">If you got a "rate moved too far" alert and have checked the market, accept the new rate so quotes resume.</p>
            <div class="mt-3 flex flex-wrap items-center gap-2">
                <input type="text" wire:model="fxCurrency" maxlength="3" placeholder="KES" class="{{ $inp }} w-24" aria-label="Currency">
                <input type="number" step="any" wire:model="fxRate" placeholder="Rate per 1 USD" class="{{ $inp }} w-44" aria-label="Rate per USD">
                <button type="button" wire:click="acceptFx" wire:confirm="Accept this rate as the new reference?" class="{{ $btn2 }}">Accept rate</button>
            </div>
            @error('fxCurrency')<p class="mt-1 text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p>@enderror
            @error('fxRate')<p class="mt-1 text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p>@enderror
        </section>
    @endif

    <section class="{{ $card }}" aria-labelledby="h-ev">
        <h2 id="h-ev" class="text-base font-bold text-[rgb(var(--nx-text))]">Latest provider notifications</h2>
        @forelse ($events as $e)
            <p class="mt-2 text-xs text-[rgb(var(--nx-text-2))]" wire:key="ev-{{ $e->id }}">{{ $e->received_at->diffForHumans() }} · {{ $e->provider }} · {{ $e->event_type ?: '—' }} · {{ $e->outcome ?? 'received' }}</p>
        @empty
            <p class="mt-2 text-sm text-[rgb(var(--nx-text-2))]">None yet.</p>
        @endforelse
    </section>
</div>
