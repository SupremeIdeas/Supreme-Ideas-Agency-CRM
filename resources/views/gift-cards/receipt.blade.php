<x-layouts.customer :title="'Gift card · '.$order->brand_name">
    @php $r = (array) $order->receipt; $mode = $order->redemptionMode(); @endphp
    <div class="mx-auto max-w-md">
        <a href="{{ route('gift-cards.orders') }}" wire:navigate class="mb-4 inline-flex items-center gap-1 text-sm text-[rgb(var(--nx-text-2))] hover:text-[rgb(var(--nx-teal-ink))] ">
            <x-icon name="chevron-right" class="h-4 w-4 rotate-180" /> Your gift cards
        </a>

        {{-- Status banner --}}
        @if ($order->status === 'delivered')
            <div class="mb-5 rounded-2xl bg-gradient-to-br from-[rgb(var(--nx-cta-b))] via-[rgb(var(--nx-cta-a))] to-black/60 p-6 text-center text-white">
                <span class="mx-auto mb-2 flex h-12 w-12 items-center justify-center rounded-2xl bg-white/15"><x-icon name="gift" class="h-6 w-6" /></span>
                <h1 class="text-xl font-bold">{{ $order->brand_name }}</h1>
                <p class="text-sm text-white/80">{{ $order->currency }} {{ number_format((float) $order->face_value, 2) }} · delivered</p>
            </div>
        @elseif (in_array($order->status, ['processing', 'pending', 'review']))
            <div class="mb-5 rounded-2xl bg-[rgb(var(--nx-warn)/0.12)] p-5 text-center text-[rgb(var(--nx-warn))]  ">
                <p class="font-semibold">{{ $order->status === 'review' ? 'Under review' : 'Processing' }}</p>
                <p class="mt-1 text-sm">{{ $order->status === 'review' ? 'This purchase is being verified and will be delivered shortly.' : 'Your gift card is being delivered — check back in a moment.' }}</p>
            </div>
        @elseif (in_array($order->status, ['failed', 'refunded']))
            <div class="mb-5 rounded-2xl bg-[rgb(var(--nx-bad)/0.1)] p-5 text-center text-[rgb(var(--nx-bad))]  ">
                <p class="font-semibold">{{ $order->status === 'refunded' ? 'Refunded' : 'Could not be delivered' }}</p>
                <p class="mt-1 text-sm">Your wallet was refunded in full.</p>
            </div>
        @endif

        {{-- Three-state redemption --}}
        @if ($order->status === 'delivered')
            @if ($mode === 'code')
                <div class="rounded-2xl border border-[rgb(var(--nx-line)/0.7)] bg-[rgb(var(--nx-surface))] p-5  " x-data="{ copied: false }">
                    <p class="text-xs font-semibold uppercase tracking-wide text-[rgb(var(--nx-text-2))]">Your code</p>
                    <div class="mt-2 flex items-center justify-between gap-3">
                        <span class="font-mono text-lg font-bold tracking-wider text-[rgb(var(--nx-text))] ">{{ $r['epin'] ?? $r['code'] }}</span>
                        <button type="button" @click="navigator.clipboard.writeText(@js($r['epin'] ?? $r['code'] ?? '')); copied = true; setTimeout(() => copied = false, 1500)"
                                class="inline-flex items-center gap-1 rounded-full bg-[rgb(var(--nx-cta-b)/0.1)] px-3 py-1.5 text-xs font-semibold text-[rgb(var(--nx-teal-ink))] "><x-icon name="copy" class="h-3.5 w-3.5" /> <span x-text="copied ? 'Copied!' : 'Copy'"></span></button>
                    </div>
                    @if (! empty($r['code']) && ! empty($r['epin']) && $r['code'] !== $r['epin'])
                        <p class="mt-2 text-xs text-[rgb(var(--nx-text-2))] ">Card number: <span class="font-mono">{{ $r['code'] }}</span></p>
                    @endif
                    @if (! empty($r['expires_at']))<p class="mt-2 text-xs text-[rgb(var(--nx-text-2))]">Expires: {{ $r['expires_at'] }}</p>@endif
                </div>
            @elseif ($mode === 'link')
                <a href="{{ $r['redemption_url'] }}" target="_blank" rel="noopener"
                   class="flex w-full items-center justify-center gap-2 rounded-2xl bg-[rgb(var(--nx-cta-b))] py-3.5 text-sm font-semibold text-white transition hover:bg-[rgb(var(--nx-cta-a))]">
                    <x-icon name="link" class="h-4 w-4" /> Redeem now
                </a>
            @elseif ($mode === 'account')
                <div class="rounded-2xl border border-[rgb(var(--nx-line)/0.7)] bg-[rgb(var(--nx-surface))] p-5  ">
                    <p class="text-xs font-semibold uppercase tracking-wide text-[rgb(var(--nx-text-2))]">Credited to account</p>
                    <p class="mt-1 font-mono text-[rgb(var(--nx-text))] ">{{ $r['account_id'] }}</p>
                </div>
            @endif

            @if (! empty($r['instructions']))
                <div class="mt-4 rounded-2xl bg-[rgb(var(--nx-surface-2))] p-4 text-sm text-[rgb(var(--nx-text-2))]  ">
                    <p class="mb-1 font-semibold">How to redeem</p>
                    {{ \Illuminate\Support\Str::limit(strip_tags($r['instructions']), 600) }}
                </div>
            @endif
            @if (! empty($r['terms']))<p class="mt-3 text-xs text-[rgb(var(--nx-text-2))]">{{ \Illuminate\Support\Str::limit(strip_tags($r['terms']), 300) }}</p>@endif

            {{-- Real capability only — shown solely when the provider actually
                 supports checking an issued card's remaining balance. --}}
            @if ($canCheckBalance)
                <div class="mt-4" x-data="{ loading: false, result: null, error: null }">
                    <button type="button" :disabled="loading"
                            @click="
                                loading = true; error = null;
                                fetch(@js(route('gift-cards.order.balance', $order)), {
                                    method: 'POST',
                                    headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '', 'Accept': 'application/json' },
                                }).then(r => r.json()).then(data => {
                                    loading = false;
                                    if (data.error) { error = data.error; return; }
                                    result = data;
                                }).catch(() => { loading = false; error = 'Could not reach the balance check right now.'; })
                            "
                            class="flex w-full items-center justify-center gap-2 rounded-2xl border border-[rgb(var(--nx-line))] py-3 text-sm font-semibold text-[rgb(var(--nx-text-2))] transition hover:border-[rgb(var(--nx-teal))] hover:text-[rgb(var(--nx-teal-ink))] disabled:opacity-60  ">
                        <span x-show="!loading">Check remaining balance</span>
                        <span x-show="loading" x-cloak class="inline-flex items-center gap-2"><x-ui.spinner class="h-4 w-4" /> Checking…</span>
                    </button>
                    <div x-show="result" x-cloak class="mt-2 rounded-xl bg-[rgb(var(--nx-cta-b)/0.1)] p-3 text-center text-sm font-semibold text-[rgb(var(--nx-teal-ink))] ">
                        <span x-text="result ? result.currency + ' ' + Number(result.balance).toFixed(2) + ' remaining' : ''"></span>
                    </div>
                    <p x-show="error" x-cloak class="mt-2 text-center text-xs text-[rgb(var(--nx-bad))]" x-text="error"></p>
                </div>
            @endif
        @endif
    </div>
</x-layouts.customer>
