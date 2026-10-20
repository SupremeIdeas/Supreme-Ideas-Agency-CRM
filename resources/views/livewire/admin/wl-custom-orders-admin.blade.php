{{-- nx:converted (skin tokens only; see docs/appearance/SKIN-CONTRACT.md) --}}
{{-- White Label custom orders queue (MASTER-ONLY). --}}
@php
    $in = 'w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm text-[rgb(var(--nx-text))]';
    $card = 'rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-4 sm:p-5';
    $total = collect($items)->sum(fn ($i) => is_numeric($i['amount'] ?? null) ? (float) $i['amount'] : 0);
@endphp
<div class="mx-auto max-w-6xl">
    <x-admin.wl-hub active="custom" />
    <div class="mb-4"><h1 class="text-2xl font-bold text-[rgb(var(--nx-text))]">Custom orders</h1><p class="mt-1 text-sm text-[rgb(var(--nx-text-2))]">Buyers describe a custom build here. Price it, and they accept and pay from their wallet, or you mark a bank or crypto transfer paid.</p></div>

    <div class="grid gap-4 lg:grid-cols-[minmax(0,22rem)_minmax(0,1fr)]">
        <section class="{{ $card }}" data-wlco-queue>
            <div class="flex flex-wrap gap-2">
                @foreach (['open' => 'Open', 'quoted' => 'Quoted', 'paid' => 'Paid', 'all' => 'All'] as $k => $l)
                    <button type="button" wire:click="$set('filter', '{{ $k }}')" class="ns-nt__chip {{ $filter === $k ? 'is-on' : '' }}" aria-pressed="{{ $filter === $k ? 'true' : 'false' }}">{{ $l }}</button>
                @endforeach
            </div>
            <label class="sr-only" for="wlco-search">Search</label>
            <input id="wlco-search" type="search" wire:model.live.debounce.300ms="search" placeholder="Reference, brand or email" class="{{ $in }} mt-3">
            <ul class="mt-3 grid gap-2">
                @forelse ($orders as $o)
                    <li wire:key="o-{{ $o->id }}"><button type="button" wire:click="open({{ $o->id }})" class="w-full rounded-xl border p-3 text-left {{ $selectedId === $o->id ? 'border-[rgb(var(--nx-cta-b))]' : 'border-[rgb(var(--nx-line))]' }}">
                        <span class="flex justify-between gap-2"><b class="text-sm text-[rgb(var(--nx-text))]">{{ $o->brand_name }}</b><span class="text-xs text-[rgb(var(--nx-text-2))]">{{ ucfirst($o->status) }}</span></span>
                        <span class="block text-xs text-[rgb(var(--nx-text-2))]">{{ $o->reference }} · {{ $o->user?->email }}@if ($o->quote_total_usd) · ${{ number_format((float) $o->quote_total_usd, 2) }}@endif</span></button></li>
                @empty
                    <li class="text-sm text-[rgb(var(--nx-text-2))]">Nothing here.</li>
                @endforelse
            </ul>
        </section>

        <section class="{{ $card }}" data-wlco-detail>
            @if (! $sel)
                <p class="text-sm text-[rgb(var(--nx-text-2))]">Pick a request to read it and send a quote.</p>
            @else
                @if ($error)<p class="mb-3 text-sm text-[rgb(var(--nx-bad))]" role="alert" data-wlco-error>{{ $error }}</p>@endif
                @if ($notice)<p class="mb-3 text-sm text-[rgb(var(--nx-ok))]" role="status">{{ $notice }}</p>@endif
                <h2 class="text-base font-semibold text-[rgb(var(--nx-text))]">{{ $sel->brand_name }} <span class="text-sm font-normal text-[rgb(var(--nx-text-2))]">{{ $sel->reference }} · {{ ucfirst($sel->status) }}</span></h2>
                <p class="mt-1 text-sm text-[rgb(var(--nx-text-2))]">{{ $sel->user?->name }} · {{ $sel->contact_email }}@if ($sel->contact_whatsapp) · <a href="https://wa.me/{{ preg_replace('/\D/', '', $sel->contact_whatsapp) }}" target="_blank" rel="noopener">WhatsApp</a>@endif</p>
                <p class="mt-2 text-sm text-[rgb(var(--nx-text-2))]">{{ collect($sel->modules)->map(fn ($k) => $moduleOptions[$k] ?? $k)->implode(', ') }}@if ($sel->budget_band) · Budget {{ \App\Services\WhiteLabel\Funnel\WlCustomOrders::BUDGETS[$sel->budget_band] ?? $sel->budget_band }}@endif @if ($sel->timeline) · {{ \App\Services\WhiteLabel\Funnel\WlCustomOrders::TIMELINES[$sel->timeline] ?? $sel->timeline }}@endif</p>
                <p class="mt-2 whitespace-pre-line text-sm text-[rgb(var(--nx-text))]">{{ $sel->brief }}</p>
                @if ($sel->reference_link)<p class="mt-1 text-sm"><a href="{{ $sel->reference_link }}" target="_blank" rel="noopener noreferrer nofollow">Reference link</a></p>@endif

                @if (in_array($sel->status, ['requested', 'quoted'], true))
                    <form wire:submit="sendQuote" class="mt-4 grid gap-2" data-wlco-quote-form>
                        <h3 class="text-sm font-semibold text-[rgb(var(--nx-text))]">Quote (USD)</h3>
                        @foreach ($items as $i => $line)
                            <div class="grid grid-cols-[minmax(0,1fr)_7rem_auto] gap-2" wire:key="line-{{ $i }}">
                                <input type="text" wire:model="items.{{ $i }}.label" aria-label="Line {{ $i + 1 }} description" maxlength="160" placeholder="What this line covers" class="{{ $in }}">
                                <input type="number" step="0.01" min="0" wire:model.live="items.{{ $i }}.amount" aria-label="Line {{ $i + 1 }} amount" class="{{ $in }}">
                                <button type="button" wire:click="removeLine({{ $i }})" class="ns-cta ns-cta--pill ns-cta--ghost ns-cta--sm" aria-label="Remove line {{ $i + 1 }}">Remove</button>
                            </div>
                        @endforeach
                        <div class="flex flex-wrap items-center gap-3"><button type="button" wire:click="addLine" class="ns-cta ns-cta--pill ns-cta--ghost ns-cta--sm">Add a line</button><b class="text-sm text-[rgb(var(--nx-text))]" data-wlco-sum>Total ${{ number_format($total, 2) }}</b></div>
                        <div class="grid gap-2 sm:grid-cols-[8rem_minmax(0,1fr)]">
                            <div><label class="text-xs text-[rgb(var(--nx-text-2))]" for="wlco-days">Valid for (days)</label><input id="wlco-days" type="number" min="1" max="60" wire:model="validDays" class="{{ $in }}"></div>
                            <div><label class="text-xs text-[rgb(var(--nx-text-2))]" for="wlco-note">Note to the buyer</label><input id="wlco-note" type="text" wire:model="note" maxlength="2000" class="{{ $in }}"></div>
                        </div>
                        <div><button type="submit" wire:loading.attr="disabled" wire:target="sendQuote" class="ns-cta ns-cta--pill ns-cta--sm"><span wire:loading.remove wire:target="sendQuote">{{ $sel->status === 'quoted' ? 'Send a revised quote' : 'Send quote' }}</span><span wire:loading wire:target="sendQuote">Sending...</span></button></div>
                    </form>
                @endif
                @if ($sel->status === 'quoted' || $sel->status === 'accepted')
                    <p class="mt-3 text-sm text-[rgb(var(--nx-text-2))]">Quoted ${{ number_format((float) $sel->quote_total_usd, 2) }}, valid until {{ $sel->quote_valid_until?->format('M j, Y') }}@if ($sel->accepted_at). Accepted {{ $sel->accepted_at->diffForHumans() }}@endif.</p>
                @endif
                @if ($sel->status === 'accepted')
                    <form wire:submit="markPaid" class="mt-3 flex flex-wrap gap-2" data-wlco-offline>
                        <label class="sr-only" for="wlco-paidref">Bank or transaction reference</label>
                        <input id="wlco-paidref" type="text" wire:model="paidReference" maxlength="120" placeholder="Bank or transaction reference you checked" class="{{ $in }} sm:max-w-sm">
                        <button type="submit" wire:loading.attr="disabled" wire:target="markPaid" wire:confirm="Mark ${{ number_format((float) $sel->quote_total_usd, 2) }} as received?" class="ns-cta ns-cta--pill ns-cta--sm">Mark paid (transfer)</button>
                    </form>
                @endif
                @if ($sel->status === 'paid')<p class="mt-3 text-sm text-[rgb(var(--nx-text))]">Paid ${{ number_format((float) $sel->paid_amount_usd, 2) }} by {{ $sel->paid_via }} on {{ $sel->paid_at?->format('M j, Y') }} ({{ $sel->paid_reference }}).</p>@endif
                @if (in_array($sel->status, ['requested', 'quoted', 'accepted'], true))
                    <form wire:submit="reject" class="mt-3 flex flex-wrap gap-2">
                        <label class="sr-only" for="wlco-reject">Reason</label>
                        <input id="wlco-reject" type="text" wire:model="rejectReason" maxlength="500" placeholder="Why we cannot take this on" class="{{ $in }} sm:max-w-sm">
                        <button type="submit" wire:loading.attr="disabled" wire:target="reject" class="ns-cta ns-cta--pill ns-cta--ghost ns-cta--sm">Decline request</button>
                    </form>
                @endif

                <h3 class="mt-5 text-sm font-semibold text-[rgb(var(--nx-text))]">Conversation</h3>
                <div class="mt-2 grid gap-2" data-wlco-thread>
                    @forelse ($sel->messages as $m)
                        <div wire:key="m-{{ $m->id }}" class="rounded-xl bg-[rgb(var(--nx-surface-3))] p-2 text-sm {{ $m->from_admin ? '' : 'justify-self-end' }}"><span class="block text-xs text-[rgb(var(--nx-text-2))]">{{ $m->from_admin ? 'Team' : 'Buyer' }} · {{ $m->created_at->diffForHumans() }}</span><span class="whitespace-pre-line text-[rgb(var(--nx-text))]">{{ $m->body }}</span></div>
                    @empty
                        <p class="text-sm text-[rgb(var(--nx-text-2))]">No messages yet.</p>
                    @endforelse
                </div>
                <form wire:submit="send" class="mt-2 flex gap-2"><label class="sr-only" for="wlco-reply">Reply</label><input id="wlco-reply" type="text" wire:model="reply" maxlength="2000" placeholder="Reply to the buyer" class="{{ $in }}"><button type="submit" wire:loading.attr="disabled" wire:target="send" class="ns-cta ns-cta--pill ns-cta--sm">Send</button></form>
            @endif
        </section>
    </div>
</div>
