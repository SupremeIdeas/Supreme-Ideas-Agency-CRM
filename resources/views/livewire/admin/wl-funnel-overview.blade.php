{{-- nx:converted (skin tokens only; see docs/appearance/SKIN-CONTRACT.md) --}}
{{-- White Label purchases (MASTER-ONLY): search, sort and segments for hundreds of buyers, with a detail pane (a full-screen sheet on a phone). --}}
@php
    $in = 'w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm text-[rgb(var(--nx-text))]';
    $card = 'rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-4 sm:p-5';
    $btn = 'ns-cta ns-cta--pill ns-cta--ghost ns-cta--sm';
    $paidSet = ['paid', 'license_issued', 'setup_in_progress', 'live'];
    $pill = fn (string $s) => in_array($s, $paidSet, true) ? 'bg-[rgb(var(--nx-ok)/0.15)] text-[rgb(var(--nx-ok))]' : (in_array($s, ['abandoned', 'expired', 'rejected', 'refunded'], true) ? 'bg-[rgb(var(--nx-bad)/0.12)] text-[rgb(var(--nx-bad))]' : (in_array($s, ['payment_pending_review', 'awaiting_payment'], true) ? 'bg-[rgb(var(--nx-gold)/0.2)] text-[rgb(var(--nx-text))]' : 'bg-[rgb(var(--nx-surface-3))] text-[rgb(var(--nx-text-2))]'));
    $money = fn ($n) => '$'.number_format((float) $n, 2);
@endphp
<div class="mx-auto max-w-7xl">
    <x-admin.wl-hub active="funnel" />
    <div class="mb-4"><h1 class="text-2xl font-bold text-[rgb(var(--nx-text))]">White Label purchases</h1><p class="mt-1 text-sm text-[rgb(var(--nx-text-2))]">Everyone who started a purchase, from first click to a live White Label. Search, sort and filter, then open one for every action.</p></div>

    <section class="mb-4 grid grid-cols-2 gap-2 sm:gap-3 lg:grid-cols-4" aria-label="Funnel numbers" data-tour="wl-funnel-stats">
        @foreach ([
            ['Started', $stats['started'], $stats['started_30'].' in 30 days'],
            ['Paid', $stats['paid'], $stats['conversion'].'% of starts'],
            ['Earned', $money($stats['revenue']), 'paid invoices'],
            ['Still owed', $money($stats['outstanding']), $stats['overdue'].' overdue'],
        ] as [$label, $value, $hint])
            <div class="{{ $card }} !p-3" wire:key="st-{{ $label }}"><p class="text-xs text-[rgb(var(--nx-text-2))]">{{ $label }}</p><p class="mt-0.5 text-lg font-bold text-[rgb(var(--nx-text))]">{{ $value }}</p><p class="text-xs text-[rgb(var(--nx-text-2))]">{{ $hint }}</p></div>
        @endforeach
    </section>

    <div class="mb-3 grid grid-cols-2 gap-2 sm:grid-cols-[minmax(0,1fr)_11rem_9rem_auto]" role="search">
        <input type="search" wire:model.live.debounce.350ms="search" placeholder="Search code, brand, buyer name or email" aria-label="Search purchases" class="{{ $in }} col-span-2 sm:col-span-1">
        <select wire:model.live="sort" aria-label="Sort" class="{{ $in }}">@foreach (\App\Livewire\Admin\WlFunnelOverview::SORTS as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select>
        <select wire:model.live="planFilter" aria-label="Plan" class="{{ $in }}"><option value="">All plans</option>@foreach ($plans as $k => $n)<option value="{{ $k }}">{{ $n }}</option>@endforeach</select>
        @if ($search !== '' || $filter !== 'all' || $planFilter !== '' || $sort !== 'activity')<button type="button" wire:click="clearFilters" class="{{ $btn }} col-span-2 sm:col-span-1">Clear</button>@endif
    </div>
    <div class="-mx-1 mb-4 flex gap-2 overflow-x-auto px-1 pb-1" role="group" aria-label="Segment">
        @foreach (\App\Livewire\Admin\WlFunnelOverview::SEGMENTS as $k => $l)
            <button type="button" wire:click="setFilter('{{ $k }}')" wire:key="seg-{{ $k }}" class="ns-nt__chip whitespace-nowrap {{ $filter === $k ? 'is-on' : '' }}" aria-pressed="{{ $filter === $k ? 'true' : 'false' }}">{{ $l }} <span class="ml-1 opacity-70">{{ $counts[$k] ?? 0 }}</span></button>
        @endforeach
    </div>

    <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_27rem]">
        <section aria-label="Purchases" wire:loading.class="opacity-60" wire:target="search,sort,planFilter,setFilter,clearFilters,gotoPage,nextPage,previousPage">
            <div class="space-y-2">
                @forelse ($rows as $r)
                    @php($d = (array) $r->draft)
                    <button type="button" wire:click="view({{ $r->id }})" wire:key="f-{{ $r->id }}" class="block w-full rounded-2xl border p-3 text-left transition {{ $open === $r->id ? 'border-[rgb(var(--nx-teal))] bg-[rgb(var(--nx-surface-2))]' : 'border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] hover:bg-[rgb(var(--nx-surface-2))]' }}">
                        <span class="flex flex-wrap items-start justify-between gap-2">
                            <span class="min-w-0"><b class="block truncate text-sm text-[rgb(var(--nx-text))]">{{ $d['brand_name'] ?? 'No brand yet' }}</b><span class="block truncate text-xs text-[rgb(var(--nx-text-2))]">{{ $r->user?->email ?? 'not registered' }} · {{ $r->public_code }}</span></span>
                            <span class="rounded-full px-2 py-0.5 text-[11px] font-semibold {{ $pill($r->status) }}">{{ str_replace('_', ' ', $r->status) }}</span>
                        </span>
                        <span class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-[rgb(var(--nx-text-2))]">
                            <span class="rounded-md bg-[rgb(var(--nx-surface-3))] px-1.5 py-0.5 font-medium text-[rgb(var(--nx-text))]">{{ $plans[$r->selected_plan_key ?: $r->plan_key_at_landing] ?? ($r->selected_plan_key ?: $r->plan_key_at_landing) }}</span>
                            @if ((float) $r->paid_usd > 0)<span>Paid {{ $money($r->paid_usd) }}</span>@endif
                            @if ((float) $r->owed_usd > 0)<span class="font-semibold {{ $r->next_due_at && \Illuminate\Support\Carbon::parse($r->next_due_at)->isPast() ? 'text-[rgb(var(--nx-bad))]' : 'text-[rgb(var(--nx-text))]' }}">Owes {{ $money($r->owed_usd) }}@if ($r->next_due_at) · due {{ \Illuminate\Support\Carbon::parse($r->next_due_at)->format('d M') }}@endif</span>@endif
                            @if ($r->review_count > 0)<span class="font-semibold text-[rgb(var(--nx-text))]">Proof waiting</span>@endif
                            <span class="ml-auto">{{ $r->updated_at?->diffForHumans() }}</span>
                        </span>
                    </button>
                @empty
                    <div class="{{ $card }} text-center"><p class="text-sm text-[rgb(var(--nx-text))]">No purchases match.</p><p class="mt-1 text-xs text-[rgb(var(--nx-text-2))]">Try a different segment or clear the search.</p><button type="button" wire:click="clearFilters" class="{{ $btn }} mt-3">Clear filters</button></div>
                @endforelse
            </div>
            <div class="mt-3">{{ $rows->links() }}</div>
        </section>

        @if ($detail)
            @php($d = (array) $detail->draft)
            @php($wa = preg_replace('/\D+/', '', (string) ($intake->whatsapp_number ?? ($d['whatsapp'] ?? ''))))
            <aside x-data="{ tab: 'overview' }" aria-label="Purchase {{ $detail->public_code }}" class="max-lg:fixed max-lg:inset-0 max-lg:z-50 max-lg:overflow-y-auto max-lg:bg-[rgb(var(--nx-surface))] max-lg:p-4 lg:sticky lg:top-4 lg:max-h-[calc(100vh-2rem)] lg:self-start lg:overflow-y-auto {{ $card }}" wire:key="pane-{{ $detail->id }}">
                <div class="flex items-start justify-between gap-2">
                    <div class="min-w-0"><h2 class="truncate text-base font-semibold text-[rgb(var(--nx-text))]">{{ $d['brand_name'] ?? 'No brand yet' }}</h2><p class="text-xs text-[rgb(var(--nx-text-2))]">{{ $detail->public_code }} · <span class="rounded-full px-2 py-0.5 text-[11px] font-semibold {{ $pill($detail->status) }}">{{ str_replace('_', ' ', $detail->status) }}</span></p></div>
                    <button type="button" wire:click="close" class="{{ $btn }}" aria-label="Close details">Close</button>
                </div>
                <div class="mt-3 flex flex-wrap gap-2">
                    @if ($wa)<a class="{{ $btn }}" href="https://wa.me/{{ $wa }}?text={{ rawurlencode('Hello, this is about your White Label '.$detail->public_code) }}" target="_blank" rel="noopener">WhatsApp</a>@endif
                    @if ($detail->user?->email)<a class="{{ $btn }}" href="mailto:{{ $detail->user->email }}">Email</a>@endif
                    <button type="button" x-data="{ done: false }" @click="navigator.clipboard.writeText(@js($detail->public_code)).then(() => { done = true; setTimeout(() => done = false, 1500) })" class="{{ $btn }}"><span x-show="!done">Copy code</span><span x-show="done" x-cloak>Copied</span></button>
                    @if ($payments->contains('status', 'under_review'))<a class="{{ $btn }}" href="{{ route('admin.white-label.payment-review') }}" wire:navigate>Review proof</a>@endif
                </div>
                <div class="-mx-1 mt-4 flex gap-1 overflow-x-auto border-b border-[rgb(var(--nx-line))] px-1" role="tablist">
                    @foreach (['overview' => 'Overview', 'money' => 'Money', 'setup' => 'Setup', 'activity' => 'Notes and timeline'] as $k => $l)
                        <button type="button" role="tab" @click="tab = '{{ $k }}'" :aria-selected="tab === '{{ $k }}'" :class="tab === '{{ $k }}' ? 'border-[rgb(var(--nx-teal))] text-[rgb(var(--nx-text))]' : 'border-transparent text-[rgb(var(--nx-text-2))]'" class="whitespace-nowrap border-b-2 px-3 py-2 text-sm font-medium">{{ $l }}</button>
                    @endforeach
                </div>

                <div x-show="tab === 'overview'" class="space-y-3 pt-3">
                    <dl class="grid gap-2 text-sm text-[rgb(var(--nx-text))] sm:grid-cols-2">
                        <div><dt class="text-xs text-[rgb(var(--nx-text-2))]">Buyer</dt><dd>{{ $detail->user?->name ?? '-' }}<br><span class="text-xs">{{ $detail->user?->email }}</span></dd></div>
                        <div><dt class="text-xs text-[rgb(var(--nx-text-2))]">Plan</dt><dd>{{ $plans[$d['plan_key'] ?? ''] ?? ($d['plan_key'] ?? '-') }}</dd></div>
                        <div><dt class="text-xs text-[rgb(var(--nx-text-2))]">Hosting</dt><dd>{{ str_replace('_', ' ', $d['hosting_choice'] ?? '-') }}</dd></div>
                        <div><dt class="text-xs text-[rgb(var(--nx-text-2))]">Licence</dt><dd>{{ $license ? $license->brand_name.' ('.$license->status.', '.$license->tier.')' : 'not issued' }}</dd></div>
                        <div><dt class="text-xs text-[rgb(var(--nx-text-2))]">Palette</dt><dd>{{ $d['palette_key'] ?? '-' }}</dd></div>
                        <div><dt class="text-xs text-[rgb(var(--nx-text-2))]">Started</dt><dd>{{ $detail->created_at?->format('d M Y H:i') }}</dd></div>
                    </dl>
                    <div class="flex flex-wrap gap-2">
                        <button type="button" wire:click="requestSetupDetails" wire:loading.attr="disabled" class="{{ $btn }}">Ask for setup details</button>
                        <button type="button" wire:click="markComplete" wire:confirm="Mark setup complete and tell the buyer?" wire:loading.attr="disabled" class="ns-cta ns-cta--pill ns-cta--sm">Mark setup complete</button>
                    </div>
                    @if ($error && ! $voiding)<p class="text-sm text-[rgb(var(--nx-bad))]" role="alert">{{ $error }}</p>@endif
                </div>

                <div x-show="tab === 'money'" x-cloak class="space-y-4 pt-3">
                    <div><h3 class="mb-1 text-xs font-semibold uppercase tracking-wide text-[rgb(var(--nx-text-2))]">Payments</h3>
                        @forelse ($payments as $p)<p class="text-sm text-[rgb(var(--nx-text))]" wire:key="p-{{ $p->id }}">{{ $p->reference_code }} · {{ number_format((float) $p->expected_amount, 2) }} {{ $p->currency }} · {{ str_replace('_', ' ', $p->status) }}@if ($p->purpose === 'balance') · balance @endif</p>@empty<p class="text-sm text-[rgb(var(--nx-text-2))]">No payments yet.</p>@endforelse</div>
                    <div><h3 class="mb-1 text-xs font-semibold uppercase tracking-wide text-[rgb(var(--nx-text-2))]">Documents</h3>
                        @forelse ($docs as $x)
                            <div class="flex flex-wrap items-center gap-x-2 gap-y-1 border-b border-[rgb(var(--nx-line))] py-1.5 text-sm" wire:key="x-{{ $x->id }}">
                                <a class="underline text-[rgb(var(--nx-teal-ink))]" href="{{ route('wl-invoice.own', $x->id) }}" target="_blank" rel="noopener">{{ $x->number }}</a>
                                <span class="text-[rgb(var(--nx-text-2))]">{{ str_replace('_', ' ', $x->doc_type) }} · {{ $money($x->total) }} · {{ $x->status }}@if ($x->due_at && $x->status === 'issued') · due {{ $x->due_at->format('d M') }}@endif</span>
                                @if ($x->doc_type !== 'credit_note')<button type="button" wire:click="resendDocument({{ $x->id }})" wire:loading.attr="disabled" class="text-xs underline text-[rgb(var(--nx-text-2))]">Resend</button>@endif
                                @if ($x->doc_type === 'invoice' && in_array($x->status, ['issued', 'paid'], true))<button type="button" class="text-xs underline text-[rgb(var(--nx-bad))]" wire:click="$set('voiding', {{ $x->id }})">Cancel with credit note</button>@endif
                            </div>
                        @empty<p class="text-sm text-[rgb(var(--nx-text-2))]">No documents yet.</p>@endforelse</div>
                    @if ($voiding)
                        <div class="rounded-xl border border-[rgb(var(--nx-line))] p-3" data-tour="wl-void">
                            <h3 class="text-sm font-semibold text-[rgb(var(--nx-text))]">Cancel this invoice</h3>
                            <p class="text-xs text-[rgb(var(--nx-text-2))]">A credit note is issued and the invoice is marked void. No money is moved and the licence is not changed.</p>
                            @if ($error)<p class="mt-1 text-sm text-[rgb(var(--nx-bad))]" role="alert">{{ $error }}</p>@endif
                            <input type="text" wire:model="voidReason" maxlength="200" placeholder="Reason (required)" class="mt-2 {{ $in }}">
                            <div class="mt-2 flex gap-2">
                                <button type="button" wire:click="voidInvoice" wire:loading.attr="disabled" wire:target="voidInvoice" class="rounded-lg border border-[rgb(var(--nx-bad))] px-3 py-2 text-sm font-semibold text-[rgb(var(--nx-bad))] disabled:opacity-60">Issue credit note</button>
                                <button type="button" wire:click="$set('voiding', null)" class="rounded-lg border border-[rgb(var(--nx-line))] px-3 py-2 text-sm text-[rgb(var(--nx-text))]">Keep invoice</button>
                            </div>
                        </div>
                    @endif
                </div>

                <div x-show="tab === 'setup'" x-cloak class="space-y-3 pt-3">
                    @if ($license)
                        <div class="rounded-xl border border-[rgb(var(--nx-line))] p-3">
                            <h3 class="text-sm font-semibold text-[rgb(var(--nx-text))]">Reveal a secret</h3>
                            <p class="text-xs text-[rgb(var(--nx-text-2))]">Needs your password and a reason. Each reveal is logged. Server details are erased after setup is complete.</p>
                            @if ($error && ! $voiding)<p class="mt-1 text-sm text-[rgb(var(--nx-bad))]" role="alert">{{ $error }}</p>@endif
                            <div class="mt-2 grid gap-2"><input type="password" wire:model="password" placeholder="Your password" autocomplete="current-password" class="{{ $in }}"><input type="text" wire:model="reason" placeholder="Why do you need it?" maxlength="200" class="{{ $in }}"></div>
                            <div class="mt-2 flex flex-wrap gap-2"><button type="button" wire:click="reveal('license_key')" wire:loading.attr="disabled" class="{{ $btn }}">Reveal licence key</button>
                                @if ($intake && $intake->hosting_host)<button type="button" wire:click="reveal('server')" wire:loading.attr="disabled" class="{{ $btn }}">Reveal server details</button>@endif</div>
                            @if ($revealed)<div class="mt-2 rounded-lg bg-[rgb(var(--nx-surface-3))] p-2 text-sm" data-wl-revealed><b>{{ $revealedLabel }}</b><pre class="mt-1 whitespace-pre-wrap break-all text-[rgb(var(--nx-text))]">{{ $revealed }}</pre></div>@endif
                        </div>
                        @if ($intake)<p class="text-xs text-[rgb(var(--nx-text-2))]">Setup form: {{ $intake->status }}. @if ($intake->deploy_completed_at)Completed {{ $intake->deploy_completed_at->format('d M Y') }}.@endif</p>@endif
                    @else
                        <p class="text-sm text-[rgb(var(--nx-text-2))]">No licence yet. Secrets appear here once a payment is approved.</p>
                    @endif
                </div>

                <div x-show="tab === 'activity'" x-cloak class="space-y-3 pt-3">
                    <form wire:submit="addNote" class="flex gap-2"><input type="text" wire:model="noteBody" maxlength="500" placeholder="Private note (the buyer never sees it)" class="{{ $in }}"><button type="submit" wire:loading.attr="disabled" wire:target="addNote" class="{{ $btn }}">Add</button></form>
                    @foreach ($notes as $n)<p class="rounded-lg bg-[rgb(var(--nx-surface-3))] p-2 text-sm text-[rgb(var(--nx-text))]" wire:key="n-{{ $n->id }}">{{ $n->body }}<span class="mt-0.5 block text-[11px] text-[rgb(var(--nx-text-2))]">{{ $n->admin?->name ?? 'Admin' }} · {{ $n->created_at?->format('d M H:i') }}</span></p>@endforeach
                    <div><h3 class="mb-1 text-xs font-semibold uppercase tracking-wide text-[rgb(var(--nx-text-2))]">Timeline</h3>
                        @foreach ($detail->events->sortByDesc('id')->take(15) as $e)<p class="text-xs text-[rgb(var(--nx-text-2))]" wire:key="e-{{ $e->id }}">{{ $e->created_at?->format('d M H:i') }} · {{ $e->from_status ? str_replace('_', ' ', $e->from_status).' to ' : '' }}{{ str_replace('_', ' ', $e->to_status) }} ({{ $e->actor }})</p>@endforeach</div>
                </div>
            </aside>
        @else
            <aside class="hidden self-start lg:block {{ $card }} text-center"><p class="text-sm font-medium text-[rgb(var(--nx-text))]">Pick a purchase</p><p class="mt-1 text-xs text-[rgb(var(--nx-text-2))]">Its payments, documents, setup and notes open here.</p></aside>
        @endif
    </div>
</div>
