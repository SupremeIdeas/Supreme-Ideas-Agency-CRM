{{-- nx:converted (skin tokens; legacy palette classes removed 2026-10-05) --}}
<div class="mx-auto max-w-5xl" x-data="{ refund: false }" @close-refund.window="refund = false">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-[rgb(var(--nx-text))]">Refunds &amp; Disputes</h1>
        <p class="text-sm text-[rgb(var(--nx-text-2))]">Refund a wallet top-up, and watch card disputes. Refunds reverse the gateway charge and the wallet ledger together, and are fully audited.</p>
    </div>

    {{-- Disputes --}}
    <div class="mb-8 rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-5">
        <h2 class="mb-3 text-sm font-semibold text-[rgb(var(--nx-text))]">Card disputes</h2>
        @if ($disputes->isEmpty())
            <p class="text-sm text-[rgb(var(--nx-text-2))]">No disputes. Contested charges appear here and their funds are frozen until resolved.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="text-xs uppercase text-[rgb(var(--nx-text-2))]">
                        <tr>
                            <th class="py-2 pr-3">Gateway</th><th class="py-2 pr-3">User</th>
                            <th class="py-2 pr-3">Amount</th><th class="py-2 pr-3">Frozen</th>
                            <th class="py-2 pr-3">Status</th><th class="py-2 pr-3">When</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[rgb(var(--nx-line))]">
                        @foreach ($disputes as $d)
                            <tr wire:key="dispute-{{ $d->id }}">
                                <td class="py-2 pr-3 font-medium text-[rgb(var(--nx-text))]">{{ ucfirst($d->gateway) }}</td>
                                <td class="py-2 pr-3 text-[rgb(var(--nx-text-2))]">{{ $d->user?->email ?? '—' }}</td>
                                <td class="py-2 pr-3">{{ number_format((float) $d->amount, 2) }} {{ $d->currency }}</td>
                                <td class="py-2 pr-3">{{ number_format((float) $d->frozen_amount, 2) }}</td>
                                <td class="py-2 pr-3">
                                    @php($tone = ['open' => 'bg-[rgb(var(--nx-warn)/0.16)] text-[rgb(var(--nx-warn))]', 'won' => 'bg-[rgb(var(--nx-ok)/0.16)] text-[rgb(var(--nx-ok))]', 'lost' => 'bg-[rgb(var(--nx-bad)/0.14)] text-[rgb(var(--nx-bad))]'][$d->status] ?? '')
                                    <span class="rounded-full px-2 py-0.5 text-[10px] font-bold uppercase {{ $tone }}">{{ $d->status }}</span>
                                </td>
                                <td class="py-2 pr-3 text-[rgb(var(--nx-text-2))]">{{ $d->created_at?->diffForHumans() }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{-- Top-ups (refundable) --}}
    <div class="rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-5">
        <div class="mb-3 flex items-center justify-between gap-3">
            <h2 class="text-sm font-semibold text-[rgb(var(--nx-text))]">Wallet top-ups</h2>
            <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search email or reference…"
                   class="w-64 rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-1.5 text-sm">
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="text-xs uppercase text-[rgb(var(--nx-text-2))]">
                    <tr>
                        <th class="py-2 pr-3">User</th><th class="py-2 pr-3">Gateway</th>
                        <th class="py-2 pr-3">Amount</th><th class="py-2 pr-3">When</th><th class="py-2 pr-3 text-right">Refund</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[rgb(var(--nx-line))]">
                    @forelse ($topups as $t)
                        @php($existing = $refundByTxn[$t->id] ?? null)
                        <tr wire:key="topup-{{ $t->id }}">
                            <td class="py-2 pr-3 text-[rgb(var(--nx-text-2))]">{{ $t->user?->email ?? '—' }}</td>
                            <td class="py-2 pr-3 font-medium text-[rgb(var(--nx-text))]">{{ ucfirst(explode(':', $t->reference)[1] ?? '') }}</td>
                            <td class="py-2 pr-3">{{ number_format((float) $t->amount, 2) }} {{ $t->currency }}</td>
                            <td class="py-2 pr-3 text-[rgb(var(--nx-text-2))]">{{ $t->created_at?->diffForHumans() }}</td>
                            <td class="py-2 pr-3 text-right">
                                @if ($existing)
                                    <span class="rounded-full bg-[rgb(var(--nx-surface-3))] px-2 py-0.5 text-[10px] font-bold uppercase text-[rgb(var(--nx-text-2))]">{{ $existing->status }}</span>
                                @else
                                    <button type="button" wire:click="openRefund({{ $t->id }})" @click="refund = true"
                                            class="rounded-lg border border-[rgb(var(--nx-bad))] px-3 py-1 text-xs font-semibold text-[rgb(var(--nx-bad))] transition hover:bg-[rgb(var(--nx-bad)/0.1)]">Refund</button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="py-8 text-center text-sm text-[rgb(var(--nx-text-2))]">No top-ups yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $topups->links() }}</div>
    </div>

    {{-- Refund confirm sheet --}}
    <div x-show="refund" x-cloak class="fixed inset-0 z-[60] flex items-end justify-center sm:items-center" @keydown.escape.window="refund = false" role="dialog" aria-modal="true">
        <div class="absolute inset-0 bg-black/60" @click="refund = false"></div>
        <div x-show="refund" x-transition class="relative w-full max-w-md rounded-t-3xl bg-[rgb(var(--nx-surface))] p-5 shadow-2xl sm:rounded-3xl">
            @if ($refundTxn)
                <h2 class="mb-1 text-base font-bold text-[rgb(var(--nx-text))]">Refund this top-up?</h2>
                <p class="mb-4 text-xs text-[rgb(var(--nx-text-2))]">
                    {{ number_format((float) $refundTxn->amount, 2) }} {{ $refundTxn->currency }} to {{ $refundTxn->user?->email }}.
                    This calls the gateway's refund and reverses the wallet. It can't be undone. If the user already spent the funds, it will be refused.
                </p>
                <label class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">Reason (audited)</label>
                <input type="text" wire:model="refundReason" placeholder="e.g. duplicate charge"
                       class="mb-4 w-full rounded-xl border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2.5 text-sm">
                <button type="button" wire:click="refund" wire:loading.attr="disabled" wire:target="refund"
                        class="flex w-full items-center justify-center gap-2 rounded-2xl bg-[color-mix(in_srgb,rgb(var(--nx-bad))_58%,#000)] py-3 text-sm font-semibold text-white transition hover:bg-[color-mix(in_srgb,rgb(var(--nx-bad))_48%,#000)] disabled:opacity-60">
                    <span wire:loading.remove wire:target="refund">Refund now</span>
                    <span wire:loading wire:target="refund" class="inline-flex items-center gap-2"><x-ui.spinner class="h-4 w-4" /> Refunding…</span>
                </button>
            @endif
        </div>
    </div>
</div>
