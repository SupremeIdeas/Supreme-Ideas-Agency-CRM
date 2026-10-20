{{-- nx:converted (skin tokens; legacy palette classes removed 2026-10-05) --}}
<div>
    <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
        <h1 class="text-2xl font-bold text-[rgb(var(--nx-text))]">Error log</h1>
        <div class="flex items-end gap-2">
            <div>
                <label class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">Day</label>
                <input type="date" wire:model.live="date"
                       class="rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm text-[rgb(var(--nx-text))]">
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">Severity</label>
                <select wire:model.live="severity" class="rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm text-[rgb(var(--nx-text))]">
                    <option value="">All</option>
                    <option value="critical">Critical</option>
                    <option value="error">Error</option>
                    <option value="warning">Warning</option>
                </select>
            </div>
            <button type="button" wire:click="exportCsv" class="inline-flex items-center gap-1.5 rounded-lg border border-[rgb(var(--nx-line-strong))] px-3 py-2 text-sm font-semibold text-[rgb(var(--nx-text))] hover:bg-[rgb(var(--nx-surface-2))]">
                <x-icon name="file-text" class="h-4 w-4" /> CSV
            </button>
            <button type="button" wire:click="exportJson" class="inline-flex items-center gap-1.5 rounded-lg border border-[rgb(var(--nx-line-strong))] px-3 py-2 text-sm font-semibold text-[rgb(var(--nx-text))] hover:bg-[rgb(var(--nx-surface-2))]">
                <x-icon name="file-text" class="h-4 w-4" /> JSON
            </button>
        </div>
    </div>

    <div class="overflow-hidden rounded-xl border border-[rgb(var(--nx-line))]">
        <table class="w-full text-left text-sm">
            <thead class="bg-[rgb(var(--nx-surface-2))] text-[rgb(var(--nx-text-2))]">
                <tr>
                    <th class="px-4 py-2 font-medium">Severity</th>
                    <th class="px-4 py-2 font-medium">Code</th>
                    <th class="px-4 py-2 font-medium">Message</th>
                    <th class="px-4 py-2 font-medium">When</th>
                </tr>
            </thead>
            <tbody wire:loading wire:target="date,severity,gotoPage,nextPage,previousPage" class="divide-y divide-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))]">
                <x-ui.skeleton-table-rows :cols="4" />
            </tbody>
            <tbody wire:loading.remove wire:target="date,severity,gotoPage,nextPage,previousPage" class="divide-y divide-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))]">
                @forelse ($logs as $log)
                    <tr wire:key="log-{{ $log->id }}" class="text-[rgb(var(--nx-text))]">
                        <td class="px-4 py-2">
                            <span @class([
                                'rounded-full px-2 py-0.5 text-xs font-semibold',
                                'bg-[rgb(var(--nx-bad)/0.14)] text-[rgb(var(--nx-bad))]' => $log->severity === 'critical',
                                'bg-[rgb(var(--nx-warn)/0.16)] text-[rgb(var(--nx-warn))]' => $log->severity === 'warning',
                                'bg-[rgb(var(--nx-surface-3))] text-[rgb(var(--nx-text-2))]' => ! in_array($log->severity, ['critical', 'warning']),
                            ])>{{ ucfirst($log->severity) }}</span>
                        </td>
                        <td class="px-4 py-2 font-mono text-xs text-[rgb(var(--nx-text-2))]">{{ $log->code ?? '—' }}</td>
                        <td class="max-w-md truncate px-4 py-2">{{ $log->message }}</td>
                        <td class="whitespace-nowrap px-4 py-2 text-[rgb(var(--nx-text-2))]">{{ $log->created_at->format('H:i:s') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-4 py-10 text-center text-[rgb(var(--nx-text-2))]">No errors for this day. All quiet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $logs->links() }}</div>
</div>
