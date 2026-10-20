{{-- nx:converted (skin tokens; admin tool) --}}
@php($input = 'w-28 rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-2 py-1 text-sm')
<div class="mx-auto max-w-5xl">
    <div class="mb-6">
        <h1 class="text-xl font-bold text-[rgb(var(--nx-text))]">AI costs</h1>
        <p class="text-sm text-[rgb(var(--nx-text-2))]">What every Claude call actually cost, measured per call from the provider's own token counts. No prompt or reply text is stored. Costs use the price table below, which is a list price until you check it against your Anthropic invoice.</p>
        @if (\Illuminate\Support\Facades\Route::has('admin.brain'))<p class="mt-1 text-sm"><a href="{{ route('admin.brain') }}" wire:navigate class="font-semibold text-[rgb(var(--nx-teal-ink))] underline">NaaraBrain switch and floating button</a></p>@endif
    </div>

    <div class="mb-5 flex flex-wrap gap-2" role="group" aria-label="Period">
        @foreach ([1 => 'Today', 7 => '7 days', 30 => '30 days', 90 => '90 days'] as $d => $label)
            <button type="button" wire:click="setDays({{ $d }})" aria-pressed="{{ $days === $d ? 'true' : 'false' }}"
                    class="rounded-full px-3 py-1.5 text-xs font-semibold {{ $days === $d ? 'bg-[rgb(var(--nx-cta-b))] text-white' : 'bg-[rgb(var(--nx-surface-3))] text-[rgb(var(--nx-text-2))]' }}">{{ $label }}</button>
        @endforeach
    </div>

    <div class="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-4">
        @foreach ([['Calls', number_format($total['calls'])], ['Cost', '$'.number_format($total['cost'], 4)], ['Tokens in + out', number_format($total['tokens'])], ['Errors', number_format($total['errors'])]] as [$k, $v])
            <div class="rounded-xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-4"><p class="text-xs text-[rgb(var(--nx-text-2))]">{{ $k }}</p><p class="mt-1 text-lg font-bold text-[rgb(var(--nx-text))]">{{ $v }}</p></div>
        @endforeach
    </div>

    <section class="mb-8 overflow-x-auto rounded-xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-5">
        <h2 class="mb-3 text-sm font-bold text-[rgb(var(--nx-text))]">By feature</h2>
        @if ($features->isEmpty())
            <p class="text-sm text-[rgb(var(--nx-text-2))]">No Claude calls in this period yet. Rows appear as soon as any AI feature runs.</p>
        @else
            <table class="w-full text-left text-xs text-[rgb(var(--nx-text))]">
                <thead class="text-[rgb(var(--nx-text-2))]"><tr><th class="py-1 pr-3">Feature</th><th class="pr-3">Calls</th><th class="pr-3">Errors</th><th class="pr-3">In</th><th class="pr-3">Out</th><th class="pr-3">Cache hit</th><th class="pr-3">p50 / p95</th><th class="pr-3">Retries</th><th class="pr-3">Cost</th><th>Per call</th></tr></thead>
                <tbody>
                @foreach ($features as $f)
                    <tr class="border-t border-[rgb(var(--nx-line))]">
                        <td class="py-1.5 pr-3 font-semibold">{{ $f['feature'] }}</td><td class="pr-3">{{ number_format($f['calls']) }}</td><td class="pr-3">{{ $f['errors'] }}</td>
                        <td class="pr-3">{{ number_format($f['in']) }}</td><td class="pr-3">{{ number_format($f['out']) }}</td><td class="pr-3">{{ $f['cache_hit'] }}%</td>
                        <td class="pr-3">{{ number_format($f['p50']) }} / {{ number_format($f['p95']) }} ms</td><td class="pr-3">{{ $f['attempts'] }}</td>
                        <td class="pr-3">${{ number_format($f['cost'], 4) }}</td><td>${{ number_format($f['per_call'], 5) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
            <p class="mt-3 text-[11px] text-[rgb(var(--nx-text-2))]">Cache hit is the share of input that was served from the prompt cache. "Retries" is the average number of attempts per call (1 = no retry needed).</p>
        @endif
    </section>

    <div class="mb-8 grid gap-5 md:grid-cols-2">
        <section class="rounded-xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-5">
            <h2 class="mb-3 text-sm font-bold text-[rgb(var(--nx-text))]">By model</h2>
            @forelse ($models as $m)<div class="flex justify-between border-t border-[rgb(var(--nx-line))] py-1.5 text-xs text-[rgb(var(--nx-text))]"><span>{{ $m->model }}</span><span>{{ number_format($m->calls) }} calls &middot; ${{ number_format((float) $m->cost, 4) }}</span></div>@empty<p class="text-xs text-[rgb(var(--nx-text-2))]">Nothing yet.</p>@endforelse
        </section>
        <section class="rounded-xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-5">
            <h2 class="mb-3 text-sm font-bold text-[rgb(var(--nx-text))]">By day</h2>
            @forelse ($daily->take(-14) as $d)<div class="flex justify-between border-t border-[rgb(var(--nx-line))] py-1.5 text-xs text-[rgb(var(--nx-text))]"><span>{{ $d->d }}</span><span>{{ number_format($d->calls) }} calls &middot; ${{ number_format((float) $d->cost, 4) }}</span></div>@empty<p class="text-xs text-[rgb(var(--nx-text-2))]">Nothing yet.</p>@endforelse
        </section>
    </div>

    <section class="rounded-xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-5">
        <h2 class="mb-1 text-sm font-bold text-[rgb(var(--nx-text))]">Price table (USD per 1,000,000 tokens)</h2>
        <p class="mb-3 text-xs text-[rgb(var(--nx-text-2))]">Shipped values are third-party list prices and are <b>unverified</b>. Copy the real rates from your Anthropic invoice here; nothing is published to users from this table.</p>
        <div class="space-y-2">
            @foreach ($prices as $family => $row)
                <div class="flex flex-wrap items-center gap-2 text-xs text-[rgb(var(--nx-text))]">
                    <span class="w-28 font-semibold">{{ $family }}</span>
                    @foreach (['in' => 'Input', 'out' => 'Output', 'cache_write' => 'Cache write', 'cache_read' => 'Cache read'] as $f => $label)
                        <label class="flex items-center gap-1">{{ $label }} <input type="number" step="0.01" min="0" wire:model="prices.{{ $family }}.{{ $f }}" class="{{ $input }}"></label>
                    @endforeach
                </div>
            @endforeach
        </div>
        @error('prices.*.*')<p class="mt-2 text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p>@enderror
        <div class="mt-4 flex items-center gap-3">
            <button type="button" wire:click="savePrices" wire:loading.attr="disabled" class="rounded-lg bg-[rgb(var(--nx-cta-b))] px-3 py-1.5 text-xs font-semibold text-white hover:bg-[rgb(var(--nx-cta-a))]">Save prices</button>
            <button type="button" wire:click="resetPrices" wire:confirm="Reset all prices to the shipped list prices?" class="rounded-lg border border-[rgb(var(--nx-line-strong))] px-3 py-1.5 text-xs font-semibold text-[rgb(var(--nx-text-2))]">Reset</button>
            @if ($saved)<span class="text-xs font-semibold text-[rgb(var(--nx-ok))]" role="status">{{ $saved }}</span>@endif
        </div>
    </section>
</div>
