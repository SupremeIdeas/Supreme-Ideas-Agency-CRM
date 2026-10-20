{{-- nx:converted (skin tokens only; see docs/appearance/SKIN-CONTRACT.md) --}}
@php
    $in = 'w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm text-[rgb(var(--nx-text))]';
    $card = 'rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-4 sm:p-5';
@endphp
<div class="mx-auto max-w-5xl space-y-6">
    <x-admin.wl-hub active="review" />
    <div>
        <h1 class="text-2xl font-bold text-[rgb(var(--nx-text))]">White Label payment review</h1>
        <p class="mt-1 text-sm text-[rgb(var(--nx-text-2))]">Check the proof against what was expected. Only an approval issues a licence, and only once.</p>
    </div>

    <section class="{{ $card }}">
        <h2 class="text-base font-semibold text-[rgb(var(--nx-text))]">Waiting for review ({{ $queue->count() }})</h2>
        @forelse ($queue as $q)
            <button type="button" wire:click="view({{ $q->id }})" wire:key="q-{{ $q->id }}" class="mt-2 flex w-full flex-wrap items-center justify-between gap-2 rounded-xl border border-[rgb(var(--nx-line))] px-3 py-2 text-left text-sm text-[rgb(var(--nx-text))]">
                <span><b>{{ $q->reference_code }}</b> · {{ $q->instance->draft['brand_name'] ?? 'No brand' }}</span>
                <span class="text-[rgb(var(--nx-text-2))]">{{ number_format((float) $q->expected_amount, 2) }} {{ $q->currency }}{{ $q->network ? ' ('.$q->network.')' : '' }} · {{ $q->updated_at->diffForHumans() }}</span>
            </button>
        @empty
            <p class="mt-2 text-sm text-[rgb(var(--nx-text-2))]">Nothing is waiting.</p>
        @endforelse
    </section>

    @if ($detail)
        <section class="{{ $card }} space-y-4" aria-label="Payment {{ $detail->reference_code }}">
            <div class="flex items-start justify-between gap-2"><h2 class="text-base font-semibold text-[rgb(var(--nx-text))]">{{ $detail->reference_code }}</h2><button type="button" wire:click="close" class="text-sm text-[rgb(var(--nx-text-2))]">Close</button></div>
            @if ($error)<p class="rounded-lg bg-[rgb(var(--nx-bad)/0.12)] p-2 text-sm text-[rgb(var(--nx-bad))]" role="alert">{{ $error }}</p>@endif
            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <h3 class="mb-2 text-xs font-semibold uppercase tracking-wide text-[rgb(var(--nx-text-2))]">Proof</h3>
                    @forelse ((array) $detail->proof as $i => $f)
                        @if (str_starts_with($f['mime'], 'image/'))
                            <a href="{{ route('admin.wl-funnel-proof', [$detail->id, $i]) }}" target="_blank" rel="noopener"><img src="{{ route('admin.wl-funnel-proof', [$detail->id, $i]) }}" alt="Proof {{ $i + 1 }}" class="mb-2 max-h-72 rounded-lg border border-[rgb(var(--nx-line))] object-contain"></a>
                        @else
                            <a href="{{ route('admin.wl-funnel-proof', [$detail->id, $i]) }}" target="_blank" rel="noopener" class="mb-2 block text-sm text-[rgb(var(--nx-teal-ink))] underline">Open PDF {{ $i + 1 }}</a>
                        @endif
                    @empty
                        <p class="text-sm text-[rgb(var(--nx-text-2))]">No files.</p>
                    @endforelse
                </div>
                <dl class="grid gap-2 text-sm text-[rgb(var(--nx-text))]">
                    <div><dt class="text-xs text-[rgb(var(--nx-text-2))]">Buyer</dt><dd>{{ $detail->instance->user?->name }} · {{ $detail->instance->user?->email }}</dd></div>
                    <div><dt class="text-xs text-[rgb(var(--nx-text-2))]">Plan and price</dt><dd>{{ $detail->plan_key }} · ${{ number_format((float) $detail->price_usd, 2) }}</dd></div>
                    <div><dt class="text-xs text-[rgb(var(--nx-text-2))]">Expected</dt><dd>{{ number_format((float) $detail->expected_amount, 2) }} {{ $detail->currency }}{{ $detail->network ? ' on '.$detail->network : '' }} (rate {{ rtrim(rtrim(number_format((float) $detail->rate, 6, '.', ''), '0'), '.') }}, {{ $detail->rate_source }})</dd></div>
                    <div><dt class="text-xs text-[rgb(var(--nx-text-2))]">Payer says</dt><dd>{{ $detail->payer_name }}{{ $detail->payer_bank ? ', '.$detail->payer_bank : '' }}{{ $detail->paid_at_claimed ? ', '.$detail->paid_at_claimed->format('Y-m-d H:i') : '' }}</dd></div>
                    @if ($detail->tx_hash)<div><dt class="text-xs text-[rgb(var(--nx-text-2))]">Transaction hash</dt><dd class="break-all">{{ $detail->tx_hash }}</dd></div>@endif
                    @if ($detail->reject_reason)<div><dt class="text-xs text-[rgb(var(--nx-text-2))]">Last rejection</dt><dd>{{ $detail->reject_reason }}</dd></div>@endif
                </dl>
            </div>
            <ul class="space-y-1 text-sm" aria-label="Automatic checks">
                @foreach ($checks as $c)<li class="{{ $c['ok'] ? 'text-[rgb(var(--nx-ok))]' : 'text-[rgb(var(--nx-bad))]' }}">{{ $c['ok'] ? 'Pass' : 'Check' }}: {{ $c['label'] }}</li>@endforeach
            </ul>
            <div class="grid gap-3 md:grid-cols-2">
                <div class="space-y-2">
                    <label class="block text-xs font-medium text-[rgb(var(--nx-text-2))]" for="wl-recv">Amount that arrived ({{ $detail->currency }})</label>
                    <input id="wl-recv" type="number" step="0.01" wire:model.live="received" class="{{ $in }}">
                    @error('received')<p class="text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p>@enderror
                    <div class="flex flex-wrap gap-2">
                        <button type="button" wire:click="approve" wire:confirm="Approve this payment and issue the licence?" wire:loading.attr="disabled" wire:target="approve" class="ns-cta ns-cta--pill"><span wire:loading.remove wire:target="approve">Approve</span><span wire:loading wire:target="approve">Issuing...</span></button>
                        <button type="button" wire:click="underpaid" wire:loading.attr="disabled" wire:target="underpaid" class="ns-cta ns-cta--pill ns-cta--ghost">Mark underpaid</button>
                    </div>
                </div>
                <div class="space-y-2">
                    <label class="block text-xs font-medium text-[rgb(var(--nx-text-2))]" for="wl-reason">Reason for rejecting (the buyer sees this)</label>
                    <input id="wl-reason" type="text" wire:model="reason" maxlength="300" class="{{ $in }}">
                    <button type="button" wire:click="reject" wire:loading.attr="disabled" wire:target="reject" class="ns-cta ns-cta--pill ns-cta--ghost">Reject</button>
                </div>
            </div>
        </section>
    @endif

    @if ($recent->isNotEmpty())
        <section class="{{ $card }}"><h2 class="text-base font-semibold text-[rgb(var(--nx-text))]">Recently approved</h2>
            @foreach ($recent as $r)<p class="mt-1 text-sm text-[rgb(var(--nx-text))]" wire:key="r-{{ $r->id }}">{{ $r->reference_code }} · {{ number_format((float) $r->approved_amount, 2) }} {{ $r->currency }} · {{ $r->reviewed_at?->diffForHumans() }}</p>@endforeach
        </section>
    @endif
</div>
