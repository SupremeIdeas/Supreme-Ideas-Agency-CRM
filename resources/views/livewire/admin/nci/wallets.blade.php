{{-- nx:converted (skin tokens; legacy palette classes removed 2026-10-05) --}}
<div class="mx-auto max-w-3xl">
    <h1 class="text-2xl font-bold text-[rgb(var(--nx-text))]">Wallets</h1>
    <p class="mb-5 text-sm text-[rgb(var(--nx-text-2))]">Provider wallet balances, a low-balance flag, and a volume-based burn estimate from recent orders.</p>

    <div class="overflow-x-auto rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))]">
        <table class="w-full min-w-[520px] text-left text-sm">
            <thead class="border-b border-[rgb(var(--nx-line))] text-[11px] uppercase tracking-wide text-[rgb(var(--nx-text-2))]">
                <tr><th class="px-4 py-2.5 font-medium">Provider</th><th class="px-3 py-2.5 font-medium">Balance</th><th class="px-3 py-2.5 font-medium">Burn (est/day)</th><th class="px-3 py-2.5 font-medium">Flag</th></tr>
            </thead>
            <tbody class="divide-y divide-[rgb(var(--nx-line))]">
                @forelse ($rows as $r)
                    <tr wire:key="w-{{ $r['provider_key'] }}" class="hover:bg-[rgb(var(--nx-surface-2))]">
                        <td class="px-4 py-2.5"><a href="{{ route('admin.nci.provider', ['provider' => $r['provider_key']]) }}" wire:navigate class="font-semibold capitalize text-[rgb(var(--nx-teal-ink))] hover:underline">{{ $r['provider_key'] }}</a> <span class="ml-1 text-[10px] uppercase text-[rgb(var(--nx-text-2))]">{{ $r['stack'] }}</span></td>
                        <td class="px-3 py-2.5 font-semibold text-[rgb(var(--nx-text))]">${{ number_format((float) $r['balance'], 2) }}</td>
                        <td class="px-3 py-2.5 text-[rgb(var(--nx-text-2))]">{{ $r['burn'] }} orders/day</td>
                        <td class="px-3 py-2.5">@if ($r['low'])<x-nci.pill kind="status" value="low" />@else <span class="text-xs text-[rgb(var(--nx-text-2))]">—</span>@endif</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="p-8 text-center text-sm text-[rgb(var(--nx-text-2))]">No wallet-funded providers with a balance yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
