{{-- nx:converted (skin tokens; legacy palette classes removed 2026-10-05) --}}
<div class="mx-auto max-w-6xl">
    <div class="mb-6 flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-[rgb(var(--nx-text))]">Provider Registry</h1>
            <p class="text-sm text-[rgb(var(--nx-text-2))]">Every provider, grouped by Naara product family and tiered by onboarding. Real names — admin manages providers directly.</p>
        </div>
        <div class="flex flex-wrap items-center gap-2 text-sm">
            @foreach ([
                'stack' => ['' => 'All stacks', 'esim' => 'eSIM', 'sms' => 'SMS', 'permanent' => 'Permanent'],
                'status' => ['' => 'All status', 'ok' => 'OK', 'low' => 'Low', 'down' => 'Down', 'configured' => 'Configured', 'coming_soon' => 'Coming soon'],
                'circuit' => ['' => 'All circuits', 'closed' => 'Closed', 'half_open' => 'Half-open', 'open' => 'Open'],
                'tier' => ['' => 'All tiers', 'self_service' => 'Self-service', 'individual_kyc' => 'Individual KYC', 'small_business' => 'Business', 'enterprise' => 'Enterprise'],
            ] as $field => $opts)
                <select wire:model.live="{{ $field }}" class="rounded-lg border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] px-2.5 py-1.5 text-xs text-[rgb(var(--nx-text))]">
                    @foreach ($opts as $val => $label)<option value="{{ $val }}">{{ $label }}</option>@endforeach
                </select>
            @endforeach
            <button type="button" wire:click="resetFilters" class="text-xs font-medium text-[rgb(var(--nx-text-2))] underline hover:text-[rgb(var(--nx-text-2))]">Reset</button>
        </div>
    </div>

    @forelse ($groups as $familyKey => $group)
        <div class="mb-6" wire:key="fam-{{ $familyKey }}">
            <h2 class="mb-2 flex items-center gap-2 text-sm font-semibold uppercase tracking-wide text-[rgb(var(--nx-text-2))]">
                <x-icon name="package" class="h-4 w-4" /> {{ $group['name'] }}
                <span class="rounded-full bg-[rgb(var(--nx-surface-3))] px-2 py-0.5 text-[11px] font-medium text-[rgb(var(--nx-text-2))]">{{ count($group['rows']) }}</span>
            </h2>
            <div class="overflow-x-auto rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))]">
                <table class="w-full min-w-[840px] text-left text-sm">
                    <thead class="border-b border-[rgb(var(--nx-line))] text-[11px] uppercase tracking-wide text-[rgb(var(--nx-text-2))]">
                        <tr>
                            <th class="px-4 py-2.5 font-medium">Provider</th>
                            <th class="px-3 py-2.5 font-medium">Tier</th>
                            <th class="px-3 py-2.5 font-medium">Status</th>
                            <th class="px-3 py-2.5 font-medium">Circuit</th>
                            <th class="px-3 py-2.5 font-medium">Latency</th>
                            <th class="px-3 py-2.5 font-medium">24h OK</th>
                            <th class="px-3 py-2.5 font-medium">NCI</th>
                            <th class="px-3 py-2.5 font-medium">On</th>
                            <th class="px-3 py-2.5 font-medium text-right">Links</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[rgb(var(--nx-line))]">
                        @foreach ($group['rows'] as $r)
                            <tr wire:key="row-{{ $familyKey }}-{{ $r['provider_key'] }}" class="hover:bg-[rgb(var(--nx-surface-2))]">
                                <td class="px-4 py-2.5">
                                    <a href="{{ route('admin.nci.provider', ['provider' => $r['provider_key']]) }}" wire:navigate class="font-semibold capitalize text-[rgb(var(--nx-teal-ink))] hover:underline">{{ $r['provider_key'] }}</a>
                                    <span class="ml-1 text-[10px] uppercase text-[rgb(var(--nx-text-2))]">{{ $r['stack'] }}</span>
                                </td>
                                <td class="px-3 py-2.5 text-xs text-[rgb(var(--nx-text-2))]">{{ $r['tier_label'] }}</td>
                                <td class="px-3 py-2.5">
                                    <x-nci.pill kind="status" :value="$r['status']" />
                                    @if ($r['paused_at'])
                                        <span class="ml-1 inline-flex items-center gap-1 rounded-full bg-[rgb(var(--nx-surface-3))] px-2 py-0.5 text-[10px] font-semibold text-[rgb(var(--nx-text-2))]" title="Paused {{ $r['paused_at']->diffForHumans() }}">
                                            <x-icon name="moon" class="h-2.5 w-2.5" /> Paused
                                        </span>
                                    @endif
                                </td>
                                <td class="px-3 py-2.5"><x-nci.pill kind="circuit" :value="$r['circuit']" /></td>
                                <td class="px-3 py-2.5 text-xs text-[rgb(var(--nx-text-2))]">{{ is_null($r['latency_ms']) ? '—' : $r['latency_ms'].'ms' }}</td>
                                <td class="px-3 py-2.5 text-xs text-[rgb(var(--nx-text-2))]">{{ is_null($r['success_rate_24h']) ? '—' : round($r['success_rate_24h'] * 100).'%' }}</td>
                                <td class="px-3 py-2.5 text-xs">
                                    @if (is_null($r['nci_score']))
                                        <span class="text-[rgb(var(--nx-text-2))]">—</span>
                                    @else
                                        <span class="font-semibold text-[rgb(var(--nx-text))]">{{ round($r['nci_score'] * 100) }}</span>
                                        <span class="text-[rgb(var(--nx-text-2))]">/{{ is_null($r['nci_confidence']) ? '—' : round($r['nci_confidence'] * 100).'%' }}</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2.5">
                                    <span class="inline-block h-2.5 w-2.5 rounded-full {{ $r['enabled'] ? 'bg-[rgb(var(--nx-ok))]' : 'bg-[rgb(var(--nx-line-strong))]' }}" title="{{ $r['enabled'] ? 'Enabled' : 'Disabled' }}"></span>
                                </td>
                                <td class="px-3 py-2.5 text-right">
                                    @if ($r['dashboard_login_url'])
                                        <a href="{{ $r['dashboard_login_url'] }}" target="_blank" rel="noopener"
                                           class="inline-flex items-center gap-1 rounded-lg border border-[rgb(var(--nx-line))] px-2 py-1 text-xs font-medium text-[rgb(var(--nx-text-2))] hover:border-[rgb(var(--nx-teal)/0.4)] hover:text-[rgb(var(--nx-teal-ink))]" title="Open provider dashboard">
                                            Dashboard <x-icon name="chevron-right" class="h-3 w-3 -rotate-45" />
                                        </a>
                                    @else
                                        <span class="text-[11px] text-[rgb(var(--nx-text-3))]">no link</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @empty
        <div class="rounded-2xl border border-dashed border-[rgb(var(--nx-line-strong))] p-10 text-center text-sm text-[rgb(var(--nx-text-2))]">
            No providers match. The registry fills after the first <code>providers:health-check</code> cycle runs.
        </div>
    @endforelse
</div>
