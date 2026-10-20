{{-- nx:converted (skin tokens; legacy palette classes removed 2026-10-05) --}}
<div class="mx-auto max-w-4xl">
    <h1 class="mb-1 text-2xl font-bold text-[rgb(var(--nx-text))]">Backups &amp; data</h1>
    <p class="mb-6 text-sm text-[rgb(var(--nx-text-2))]">
        Database backups are stored on <span class="font-medium">{{ $disk }}</span> and encrypted. Dump engine on this host: <span class="font-medium">{{ $engine }}</span>.
    </p>

    @if ($status)
        <div class="mb-6 flex items-center gap-2 rounded-lg bg-[rgb(var(--nx-ok)/0.12)] p-3 text-sm text-[rgb(var(--nx-ok))]">
            <x-icon name="badge-check" class="h-4 w-4" /> {{ $status }}
        </div>
    @endif
    @if ($error)
        <div class="mb-6 flex items-center gap-2 rounded-lg bg-[rgb(var(--nx-bad)/0.1)] p-3 text-sm text-[rgb(var(--nx-bad))]">
            <x-icon name="x" class="h-4 w-4" /> {{ $error }}
        </div>
    @endif

    {{-- Backups --}}
    <section class="mb-8 rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-6">
        <div class="mb-4 flex items-center justify-between">
            <h2 class="flex items-center gap-2 text-sm font-semibold text-[rgb(var(--nx-text))]">
                <x-icon name="package" class="h-4 w-4 text-[rgb(var(--nx-teal-ink))]" /> Database backups
            </h2>
            <button type="button" wire:click="backupNow" wire:loading.attr="disabled" wire:target="backupNow"
                    class="inline-flex items-center gap-2 rounded-lg bg-[rgb(var(--nx-cta-b))] px-4 py-2 text-sm font-semibold text-white hover:bg-[rgb(var(--nx-cta-a))] disabled:opacity-60">
                <span wire:loading.remove wire:target="backupNow" class="inline-flex items-center gap-2"><x-icon name="refresh" class="h-4 w-4" /> Back up now</span>
                <span wire:loading wire:target="backupNow" class="inline-flex items-center gap-2"><x-ui.spinner class="h-4 w-4" /> Starting…</span>
            </button>
        </div>

        <div class="divide-y divide-[rgb(var(--nx-line))]">
            @forelse ($backups as $b)
                <div wire:key="bk-{{ $b['name'] }}" class="flex items-center justify-between gap-3 py-3">
                    <div class="min-w-0">
                        <p class="truncate text-sm font-medium text-[rgb(var(--nx-text))]">{{ $b['name'] }}</p>
                        <p class="text-xs text-[rgb(var(--nx-text-2))]">{{ number_format($b['size'] / 1048576, 2) }} MB · {{ \Carbon\Carbon::createFromTimestamp($b['last_modified'])->diffForHumans() }}</p>
                    </div>
                    <div class="flex shrink-0 items-center gap-1.5">
                        <button type="button" wire:click="download('{{ $b['path'] }}')" class="inline-flex items-center gap-1 rounded-lg border border-[rgb(var(--nx-line-strong))] px-2.5 py-1.5 text-xs font-medium text-[rgb(var(--nx-text-2))] hover:bg-[rgb(var(--nx-surface-2))]">
                            <x-icon name="download" class="h-3.5 w-3.5" /> Download
                        </button>
                        <button type="button" wire:click="restore('{{ $b['path'] }}')"
                                wire:confirm="Restore the database from {{ $b['name'] }}? The app will go into maintenance mode and a safety snapshot is taken first. This overwrites current data."
                                class="inline-flex items-center gap-1 rounded-lg border border-[rgb(var(--nx-warn))] px-2.5 py-1.5 text-xs font-medium text-[rgb(var(--nx-warn))] hover:bg-[rgb(var(--nx-warn)/0.12)]">
                            <x-icon name="refresh" class="h-3.5 w-3.5" /> Restore
                        </button>
                        <button type="button" wire:click="deleteBackup('{{ $b['path'] }}')" wire:confirm="Delete {{ $b['name'] }}?"
                                class="inline-flex items-center gap-1 rounded-lg border border-[rgb(var(--nx-bad))] px-2.5 py-1.5 text-xs font-medium text-[rgb(var(--nx-bad))] hover:bg-[rgb(var(--nx-bad)/0.1)]">
                            <x-icon name="trash" class="h-3.5 w-3.5" />
                        </button>
                    </div>
                </div>
            @empty
                <p class="py-6 text-center text-sm text-[rgb(var(--nx-text-2))]">No backups yet. Run one above, or the scheduler makes them daily.</p>
            @endforelse
        </div>
    </section>

    {{-- Portable configuration backup --}}
    <section class="mb-8 rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-6" data-config-backup>
        <h2 class="mb-1 flex items-center gap-2 text-sm font-semibold text-[rgb(var(--nx-text))]">
            <x-icon name="shield" class="h-4 w-4 text-[rgb(var(--nx-teal-ink))]" /> Configuration backup (works on a fresh install)
        </h2>
        <p class="mb-4 text-xs text-[rgb(var(--nx-text-2))]">
            One encrypted file with everything you configured: all settings, pages, posts, FAQs, menus, themes, plans, installers and more ({{ count($cfgTables) }} content tables).
            Restore it on a new install and the site comes back as you left it. It does <span class="font-semibold">not</span> include the Admin &gt; API Keys secrets, users, wallets or orders; add those keys again on the new install.
            Keep the passphrase safe: without it the file cannot be opened.
        </p>

        <div class="mb-5 flex flex-wrap items-end gap-3">
            <div>
                <label class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">Passphrase (8+ characters)</label>
                <input type="password" wire:model="cfgPassphrase" autocomplete="new-password" class="w-64 rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-1.5 text-sm text-[rgb(var(--nx-text))]">
            </div>
            <button type="button" wire:click="exportConfig" wire:loading.attr="disabled" wire:target="exportConfig" @disabled(strlen($cfgPassphrase) < 8)
                    class="inline-flex items-center gap-2 rounded-lg bg-[rgb(var(--nx-cta-b))] px-4 py-2 text-sm font-semibold text-white hover:bg-[rgb(var(--nx-cta-a))] disabled:opacity-50">
                <span wire:loading.remove wire:target="exportConfig" class="inline-flex items-center gap-2"><x-icon name="download" class="h-4 w-4" /> Download configuration backup</span>
                <span wire:loading wire:target="exportConfig" class="inline-flex items-center gap-2"><x-ui.spinner class="h-4 w-4" /> Preparing...</span>
            </button>
        </div>

        <div class="border-t border-[rgb(var(--nx-line))] pt-4">
            <label class="mb-2 block text-xs font-medium text-[rgb(var(--nx-text-2))]">Restore from a configuration backup (.naaracfg)</label>
            <div class="flex flex-wrap items-center gap-3">
                <input type="file" wire:model="cfgFile" class="text-sm text-[rgb(var(--nx-text-2))]">
                <input type="password" wire:model="cfgRestorePassphrase" placeholder="Passphrase" autocomplete="off" class="w-56 rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-1.5 text-sm text-[rgb(var(--nx-text))]">
                <button type="button" wire:click="previewConfig" wire:loading.attr="disabled" wire:target="previewConfig,cfgFile" @disabled(! $cfgFile)
                        class="inline-flex items-center gap-2 rounded-lg border border-[rgb(var(--nx-line-strong))] px-3 py-1.5 text-sm font-medium text-[rgb(var(--nx-text))] hover:bg-[rgb(var(--nx-surface-2))] disabled:opacity-50">Check the file</button>
            </div>

            @if ($cfgPreview !== null)
                <div class="mt-4 rounded-xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface-2))] p-4" data-config-preview>
                    <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-[rgb(var(--nx-text-2))]">Made {{ $cfgPreview['exported_at'] }} on {{ $cfgPreview['source'] ?? 'unknown' }}. Nothing written yet.</p>
                    <ul class="space-y-1 text-sm text-[rgb(var(--nx-text))]">
                        <li><span class="font-medium">Settings</span>: {{ $cfgPreview['settings']['new'] }} new, {{ $cfgPreview['settings']['changed'] }} will change, {{ $cfgPreview['settings']['same'] }} identical</li>
                        @foreach ($cfgPreview['tables'] as $t => $c)
                            <li><span class="font-medium">{{ $t }}</span>: {{ $c['current'] }} now, replaced by {{ $c['incoming'] }}</li>
                        @endforeach
                    </ul>
                    <button type="button" wire:click="restoreConfig" wire:loading.attr="disabled" wire:target="restoreConfig" wire:confirm="Restore this configuration? Settings in the file overwrite the ones here and the listed content tables are replaced."
                            class="mt-3 inline-flex items-center gap-2 rounded-lg bg-[rgb(var(--nx-cta-b))] px-4 py-2 text-sm font-semibold text-white hover:bg-[rgb(var(--nx-cta-a))] disabled:opacity-60">
                        <span wire:loading.remove wire:target="restoreConfig" class="inline-flex items-center gap-2"><x-icon name="check" class="h-4 w-4" /> Restore this configuration</span>
                        <span wire:loading wire:target="restoreConfig" class="inline-flex items-center gap-2"><x-ui.spinner class="h-4 w-4" /> Restoring...</span>
                    </button>
                </div>
            @endif
        </div>
    </section>

    {{-- Dataset export / import --}}
    <section class="rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-6">
        <h2 class="mb-1 flex items-center gap-2 text-sm font-semibold text-[rgb(var(--nx-text))]">
            <x-icon name="file-text" class="h-4 w-4 text-[rgb(var(--nx-teal-ink))]" /> Portable datasets
        </h2>
        <p class="mb-4 text-xs text-[rgb(var(--nx-text-2))]">Move reference data between environments. Import always previews changes (dry-run) before you commit, and commits inside a transaction.</p>

        {{-- Export --}}
        <div class="mb-6">
            <label class="mb-2 block text-xs font-medium text-[rgb(var(--nx-text-2))]">Export tables</label>
            <div class="flex flex-wrap gap-2">
                @foreach ($exportable as $table)
                    <label class="flex items-center gap-2 rounded-lg border border-[rgb(var(--nx-line))] px-3 py-1.5 text-sm text-[rgb(var(--nx-text))]">
                        <input type="checkbox" wire:model="exportTables" value="{{ $table }}" class="rounded text-[rgb(var(--nx-teal-ink))]"> {{ $table }}
                    </label>
                @endforeach
                <button type="button" wire:click="exportDataset" @disabled(empty($exportTables))
                        class="inline-flex items-center gap-2 rounded-lg border border-[rgb(var(--nx-line-strong))] px-3 py-1.5 text-sm font-medium text-[rgb(var(--nx-text))] hover:bg-[rgb(var(--nx-surface-2))] disabled:opacity-50">
                    <x-icon name="download" class="h-4 w-4" /> Export selected
                </button>
            </div>
        </div>

        {{-- Import --}}
        <div class="border-t border-[rgb(var(--nx-line))] pt-4">
            <label class="mb-2 block text-xs font-medium text-[rgb(var(--nx-text-2))]">Import a dataset (.json)</label>
            <div class="flex flex-wrap items-center gap-3">
                <input type="file" wire:model="datasetFile" accept="application/json,.json" class="text-sm text-[rgb(var(--nx-text-2))]">
                <button type="button" wire:click="analyzeImport" wire:loading.attr="disabled" wire:target="analyzeImport,datasetFile" @disabled(! $datasetFile)
                        class="inline-flex items-center gap-2 rounded-lg border border-[rgb(var(--nx-line-strong))] px-3 py-1.5 text-sm font-medium text-[rgb(var(--nx-text))] hover:bg-[rgb(var(--nx-surface-2))] disabled:opacity-50">
                    Preview changes
                </button>
            </div>

            @if ($dryRun !== null)
                <div class="mt-4 rounded-xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface-2))] p-4">
                    <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-[rgb(var(--nx-text-2))]">Dry-run — nothing written yet</p>
                    <ul class="space-y-1 text-sm text-[rgb(var(--nx-text))]">
                        @foreach ($dryRun as $table => $counts)
                            <li><span class="font-medium">{{ $table }}</span>: {{ $counts['new'] }} new, {{ $counts['existing'] }} already present</li>
                        @endforeach
                    </ul>
                    <button type="button" wire:click="commitImport" wire:confirm="Commit this import?"
                            class="mt-3 inline-flex items-center gap-2 rounded-lg bg-[rgb(var(--nx-cta-b))] px-4 py-2 text-sm font-semibold text-white hover:bg-[rgb(var(--nx-cta-a))]">
                        <x-icon name="check" class="h-4 w-4" /> Commit import
                    </button>
                </div>
            @endif
        </div>
    </section>
</div>
