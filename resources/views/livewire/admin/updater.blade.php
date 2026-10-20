{{-- nx:converted (skin tokens only; see docs/appearance/SKIN-CONTRACT.md) --}}
<div class="mx-auto max-w-4xl" wire:poll.5s>
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-[rgb(var(--nx-text))]">Platform updater</h1>
        <p class="text-sm text-[rgb(var(--nx-text-2))]">
            Install signed <code class="rounded bg-[rgb(var(--nx-surface-3))] px-1">.naaraupdate</code> packages — code
            updates or themes. Current version:
            <span class="font-semibold text-[rgb(var(--nx-text))]">{{ $this->currentVersion ?? 'not yet recorded' }}</span>
        </p>
    </div>

    {{-- Tabs --}}
    <div class="mb-6 flex gap-1 border-b border-[rgb(var(--nx-line))]">
        <button type="button" wire:click="$set('tab', 'code')"
            class="border-b-2 px-4 py-2 text-sm font-medium {{ $tab === 'code' ? 'border-[rgb(var(--nx-teal))] text-[rgb(var(--nx-text))]' : 'border-transparent text-[rgb(var(--nx-text-2))] hover:text-[rgb(var(--nx-text))]' }}">
            Code updates
        </button>
        <button type="button" wire:click="$set('tab', 'themes')"
            class="border-b-2 px-4 py-2 text-sm font-medium {{ $tab === 'themes' ? 'border-[rgb(var(--nx-teal))] text-[rgb(var(--nx-text))]' : 'border-transparent text-[rgb(var(--nx-text-2))] hover:text-[rgb(var(--nx-text))]' }}">
            Themes
        </button>
        <button type="button" wire:click="$set('tab', 'radar')"
            class="border-b-2 px-4 py-2 text-sm font-medium {{ $tab === 'radar' ? 'border-[rgb(var(--nx-teal))] text-[rgb(var(--nx-text))]' : 'border-transparent text-[rgb(var(--nx-text-2))] hover:text-[rgb(var(--nx-text))]' }}">
            Capability Radar
            @if ($this->capabilityProposals->count() > 0)
                <span class="ml-1 rounded-full bg-[rgb(var(--nx-gold))] px-1.5 py-0.5 text-[10px] font-bold text-[rgb(var(--nx-on-gold))]">{{ $this->capabilityProposals->count() }}</span>
            @endif
        </button>
        @if (class_exists(\App\Services\ProviderRadar\ApiVersionWatch::class))
        <button type="button" wire:click="$set('tab', 'versions')" data-tab-versions
            class="border-b-2 px-4 py-2 text-sm font-medium {{ $tab === 'versions' ? 'border-[rgb(var(--nx-teal))] text-[rgb(var(--nx-text))]' : 'border-transparent text-[rgb(var(--nx-text-2))] hover:text-[rgb(var(--nx-text))]' }}">
            API versions
            @if ($this->apiWatches->filter->needsAttention()->count() > 0)
                <span class="ml-1 rounded-full bg-[rgb(var(--nx-gold))] px-1.5 py-0.5 text-[10px] font-bold text-[rgb(var(--nx-on-gold))]">{{ $this->apiWatches->filter->needsAttention()->count() }}</span>
            @endif
        </button>
        @endif
    </div>

    @if ($tab === 'code')
        @if ($status)
            <div class="mb-5 rounded-2xl border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-teal)/0.1)] p-4 text-sm text-[rgb(var(--nx-teal-ink))]">
                <x-icon name="info" class="mr-1 inline h-4 w-4" /> {{ $status }}
            </div>
        @endif

        {{-- Upload + verify --}}
        <div class="mb-6 rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-5">
            <h2 class="mb-3 text-lg font-semibold text-[rgb(var(--nx-text))]">Upload a package</h2>
            <p class="mb-3 text-xs text-[rgb(var(--nx-text-2))]">Every apply takes a full backup first and rolls itself back automatically if anything fails.</p>

            <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
                <div class="flex-1">
                    <label class="mb-1 block text-sm font-medium text-[rgb(var(--nx-text-2))]">Package file (.naaraupdate)</label>
                    <input type="file" wire:model="package" accept=".naaraupdate"
                        class="block w-full rounded-lg border border-[rgb(var(--nx-line))] text-sm text-[rgb(var(--nx-text-2))] file:mr-3 file:border-0 file:bg-[rgb(var(--nx-surface-3))] file:px-3 file:py-2 file:text-sm" />
                </div>
                <button type="button" wire:click="verifyPackage"
                    wire:loading.attr="disabled" wire:target="verifyPackage,package"
                    class="inline-flex items-center justify-center rounded-lg bg-black/80 px-4 py-2 text-sm font-medium text-white hover:bg-black/80 disabled:opacity-50">
                    <span wire:loading.remove wire:target="verifyPackage,package">Verify</span>
                    <span wire:loading wire:target="verifyPackage,package">Verifying…</span>
                </button>
            </div>

            @error('package')
                <p class="mt-2 text-sm text-[rgb(var(--nx-bad))]">{{ $message }}</p>
            @enderror

            @if ($verifyError)
                <div class="mt-3 rounded-lg border border-[rgb(var(--nx-bad))] bg-[rgb(var(--nx-bad)/0.1)] p-3 text-sm text-[rgb(var(--nx-bad))]">
                    <span class="font-semibold">Rejected:</span> {{ $verifyError }}
                </div>
            @endif
        </div>

        {{-- Verified manifest → confirm apply --}}
        @if ($verified)
            <div class="mb-6 rounded-2xl border border-[rgb(var(--nx-ok))] bg-[rgb(var(--nx-ok)/0.12)] p-5">
                <p class="mb-3 flex items-center gap-2 font-semibold text-[rgb(var(--nx-ok))]">
                    <x-icon name="check" class="h-5 w-5" /> Verified — genuine and untampered
                </p>
                <dl class="grid grid-cols-2 gap-x-6 gap-y-1 text-sm text-[rgb(var(--nx-text))] sm:grid-cols-3">
                    <div><dt class="text-[rgb(var(--nx-text-2))]">Version</dt><dd class="font-medium">{{ $verified['version'] }}</dd></div>
                    <div><dt class="text-[rgb(var(--nx-text-2))]">Product</dt><dd class="font-medium">{{ $verified['product'] }}</dd></div>
                    <div><dt class="text-[rgb(var(--nx-text-2))]">Type</dt><dd class="font-medium">{{ $verified['package_type'] }}</dd></div>
                    <div><dt class="text-[rgb(var(--nx-text-2))]">Min compatible</dt><dd class="font-medium">{{ $verified['min_compatible'] }}</dd></div>
                    <div><dt class="text-[rgb(var(--nx-text-2))]">Files</dt><dd class="font-medium">{{ $verified['files'] }}</dd></div>
                    <div><dt class="text-[rgb(var(--nx-text-2))]">Migrations</dt><dd class="font-medium">{{ $verified['migrations'] }}</dd></div>
                </dl>
                @if ($verified['changelog'])
                    <p class="mt-3 text-sm text-[rgb(var(--nx-text-2))]"><span class="font-semibold">Changelog:</span> {{ $verified['changelog'] }}</p>
                @endif

                <button type="button" wire:click="applyPackage"
                    wire:confirm="Apply {{ $verified['version'] }}? The platform enters maintenance mode, takes a full backup, applies files and migrations, and rolls back automatically if the health check fails."
                    wire:loading.attr="disabled" wire:target="applyPackage"
                    class="mt-4 inline-flex items-center justify-center rounded-lg bg-[rgb(var(--nx-cta-b))] px-5 py-2.5 text-sm font-semibold text-white hover:bg-[rgb(var(--nx-cta-a))] disabled:opacity-50">
                    <span wire:loading.remove wire:target="applyPackage">Apply this update</span>
                    <span wire:loading wire:target="applyPackage">Queueing…</span>
                </button>
            </div>
        @endif

        {{-- History --}}
        <div class="rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-5">
            <h2 class="mb-3 text-lg font-semibold text-[rgb(var(--nx-text))]">Apply history</h2>

            @if ($this->attempts->isEmpty())
                <p class="text-sm text-[rgb(var(--nx-text-2))]">No updates have been applied yet.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="text-xs uppercase text-[rgb(var(--nx-text-2))]">
                            <tr>
                                <th class="py-2 pr-4">When</th>
                                <th class="py-2 pr-4">Version</th>
                                <th class="py-2 pr-4">Status</th>
                                <th class="py-2 pr-4">Files</th>
                                <th class="py-2 pr-4">Migrations</th>
                                <th class="py-2 pr-4">Downtime</th>
                                <th class="py-2 pr-4">What's New</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[rgb(var(--nx-line))]">
                            @foreach ($this->attempts as $a)
                                <tr class="text-[rgb(var(--nx-text))]">
                                    <td class="py-2 pr-4 text-[rgb(var(--nx-text-2))]">{{ $a->created_at->diffForHumans() }}</td>
                                    <td class="py-2 pr-4">{{ $a->from_version ?? '—' }} → {{ $a->to_version }}</td>
                                    <td class="py-2 pr-4">
                                        @php
                                            $tone = match ($a->status) {
                                                'succeeded' => 'bg-[rgb(var(--nx-ok)/0.16)] text-[rgb(var(--nx-ok))]',
                                                'rolled_back' => 'bg-[rgb(var(--nx-warn)/0.16)] text-[rgb(var(--nx-warn))]',
                                                'failed_unrecoverable' => 'bg-[rgb(var(--nx-bad)/0.14)] text-[rgb(var(--nx-bad))]',
                                                default => 'bg-[rgb(var(--nx-surface-3))] text-[rgb(var(--nx-text-2))]',
                                            };
                                        @endphp
                                        <span class="rounded-full px-2 py-0.5 text-xs font-medium{{ $tone }}">{{ str_replace('_', ' ', $a->status) }}</span>
                                    </td>
                                    <td class="py-2 pr-4">{{ $a->files_changed_count ?? '—' }}</td>
                                    <td class="py-2 pr-4">{{ $a->migrations_run_count ?? '—' }}</td>
                                    <td class="py-2 pr-4">{{ $a->downtime_seconds !== null ? $a->downtime_seconds.'s' : '—' }}</td>
                                    <td class="py-2 pr-4">
                                        @if ($a->status !== 'succeeded')
                                            <span class="text-[rgb(var(--nx-text-2))]">—</span>
                                        @elseif ($this->hasChangelogEntry($a->to_version))
                                            <span class="inline-flex items-center gap-1 text-xs font-medium text-[rgb(var(--nx-ok))]">
                                                <x-icon name="badge-check" class="h-3.5 w-3.5" /> Published
                                            </span>
                                        @elseif ($draftingForAttemptId === $a->id)
                                            <span class="text-xs text-[rgb(var(--nx-text-2))]">Drafting below…</span>
                                        @else
                                            <button type="button" wire:click="startChangelogDraft({{ $a->id }})"
                                                    class="text-xs font-semibold text-[rgb(var(--nx-teal-ink))] hover:underline">
                                                Draft entry
                                            </button>
                                        @endif
                                    </td>
                                </tr>
                                @if ($a->failure_reason)
                                    <tr><td colspan="7" class="pb-2 text-xs text-[rgb(var(--nx-bad))]">{{ $a->failure_reason }}</td></tr>
                                @endif
                                @if ($draftingForAttemptId === $a->id)
                                    <tr>
                                        <td colspan="7" class="pb-4">
                                            <div class="mt-2 space-y-3 rounded-xl border border-[rgb(var(--nx-teal)/0.3)] bg-[rgb(var(--nx-cta-b)/0.05)] p-4">
                                                <div class="flex items-center justify-between gap-3">
                                                    <p class="text-xs font-semibold uppercase tracking-wide text-[rgb(var(--nx-text-2))]">
                                                        "What's New" entry — {{ $a->to_version }}
                                                    </p>
                                                    <button type="button" wire:click="aiDraftChangelog" @disabled(! $aiEnabled)
                                                            wire:loading.attr="disabled" wire:target="aiDraftChangelog"
                                                            class="inline-flex items-center gap-1.5 rounded-lg border border-[rgb(var(--nx-teal))] px-2.5 py-1 text-xs font-semibold text-[rgb(var(--nx-teal-ink))] transition hover:bg-[rgb(var(--nx-cta-b)/0.1)] disabled:cursor-not-allowed disabled:opacity-50">
                                                        <x-icon name="zap" class="h-3.5 w-3.5" wire:loading.remove wire:target="aiDraftChangelog" />
                                                        <x-ui.spinner class="h-3.5 w-3.5" wire:loading wire:target="aiDraftChangelog" />
                                                        <span wire:loading.remove wire:target="aiDraftChangelog">Draft with Claude</span>
                                                        <span wire:loading wire:target="aiDraftChangelog">Writing…</span>
                                                    </button>
                                                </div>

                                                @if ($changelogAiError)
                                                    <p class="text-xs text-[rgb(var(--nx-bad))]">{{ $changelogAiError }}</p>
                                                @endif

                                                <div>
                                                    <label class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">Summary (headline)</label>
                                                    <input type="text" wire:model="changelogSummary" maxlength="500" placeholder="Faster eSIM activation and a redesigned wallet"
                                                           class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm">
                                                    @error('changelogSummary') <p class="mt-1 text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p> @enderror
                                                </div>
                                                <div>
                                                    <label class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">Notes (optional detail)</label>
                                                    <textarea wire:model="changelogNotes" rows="3" maxlength="5000"
                                                              class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm"></textarea>
                                                </div>
                                                <div class="flex items-center gap-2">
                                                    <button type="button" wire:click="saveChangelogEntry" wire:loading.attr="disabled" wire:target="saveChangelogEntry"
                                                            class="inline-flex items-center gap-2 rounded-lg bg-[rgb(var(--nx-cta-b))] px-4 py-2 text-sm font-semibold text-white hover:bg-[rgb(var(--nx-cta-a))] disabled:opacity-60">
                                                        <span wire:loading.remove wire:target="saveChangelogEntry">Publish entry</span>
                                                        <span wire:loading wire:target="saveChangelogEntry">Publishing…</span>
                                                    </button>
                                                    <button type="button" wire:click="cancelChangelogDraft" class="text-sm text-[rgb(var(--nx-text-2))] hover:underline">Cancel</button>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                @endif
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        {{-- Recent "What's New" entries + one-click announce --}}
        @if ($changelogStatus)
            <div class="mt-5 rounded-xl bg-[rgb(var(--nx-ok)/0.12)] p-3 text-sm text-[rgb(var(--nx-ok))]">
                <x-icon name="badge-check" class="mr-1 inline h-4 w-4" /> {{ $changelogStatus }}
            </div>
        @endif
        @if ($this->changelogEntries->isNotEmpty())
            <div class="mt-5 rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-5">
                <h2 class="mb-3 text-lg font-semibold text-[rgb(var(--nx-text))]">Recent "What's New" entries</h2>
                <div class="space-y-2">
                    @foreach ($this->changelogEntries as $entry)
                        <div wire:key="cl-{{ $entry->id }}" class="flex items-start justify-between gap-3 rounded-xl border border-[rgb(var(--nx-line))] p-3">
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-semibold text-[rgb(var(--nx-text))]">{{ $entry->version }} — {{ $entry->summary }}</p>
                                <p class="mt-0.5 text-xs text-[rgb(var(--nx-text-2))]">{{ $entry->released_at->format('j M Y') }}</p>
                            </div>
                            <a href="{{ route('admin.announcements', ['prefill_title' => 'New in '.$entry->version, 'prefill_body' => $entry->summary]) }}"
                               wire:navigate
                               class="inline-flex shrink-0 items-center gap-1.5 rounded-lg border border-[rgb(var(--nx-teal))] px-2.5 py-1.5 text-xs font-semibold text-[rgb(var(--nx-teal-ink))] hover:bg-[rgb(var(--nx-cta-b)/0.1)]">
                                <x-icon name="bell" class="h-3.5 w-3.5" /> Announce this update
                            </a>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    @else
        {{-- Themes tab --}}
        @if ($themeStatus)
            <div class="mb-5 rounded-2xl border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-teal)/0.1)] p-4 text-sm text-[rgb(var(--nx-teal-ink))]">
                <x-icon name="info" class="mr-1 inline h-4 w-4" /> {{ $themeStatus }}
            </div>
        @endif

        {{-- Upload + preview --}}
        <div class="mb-6 rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-5">
            <h2 class="mb-3 text-lg font-semibold text-[rgb(var(--nx-text))]">Install a theme package</h2>
            <p class="mb-3 text-xs text-[rgb(var(--nx-text-2))]">A theme is paint, not plumbing — installing one writes a single row and copies a few images; no maintenance mode, no backup needed.</p>

            <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
                <div class="flex-1">
                    <label class="mb-1 block text-sm font-medium text-[rgb(var(--nx-text-2))]">Theme package file (.naaraupdate)</label>
                    <input type="file" wire:model="themePackage" accept=".naaraupdate"
                        class="block w-full rounded-lg border border-[rgb(var(--nx-line))] text-sm text-[rgb(var(--nx-text-2))] file:mr-3 file:border-0 file:bg-[rgb(var(--nx-surface-3))] file:px-3 file:py-2 file:text-sm" />
                </div>
                <button type="button" wire:click="verifyTheme"
                    wire:loading.attr="disabled" wire:target="verifyTheme,themePackage"
                    class="inline-flex items-center justify-center rounded-lg bg-black/80 px-4 py-2 text-sm font-medium text-white hover:bg-black/80 disabled:opacity-50">
                    <span wire:loading.remove wire:target="verifyTheme,themePackage">Verify</span>
                    <span wire:loading wire:target="verifyTheme,themePackage">Verifying…</span>
                </button>
            </div>

            @error('themePackage')
                <p class="mt-2 text-sm text-[rgb(var(--nx-bad))]">{{ $message }}</p>
            @enderror

            @if ($themeVerifyError)
                <div class="mt-3 rounded-lg border border-[rgb(var(--nx-bad))] bg-[rgb(var(--nx-bad)/0.1)] p-3 text-sm text-[rgb(var(--nx-bad))]">
                    <span class="font-semibold">Rejected:</span> {{ $themeVerifyError }}
                </div>
            @endif
        </div>

        {{-- Verified theme preview → confirm install --}}
        @if ($themePreview)
            <div class="mb-6 rounded-2xl border border-[rgb(var(--nx-ok))] bg-[rgb(var(--nx-ok)/0.12)] p-5">
                <p class="mb-3 flex items-center gap-2 font-semibold text-[rgb(var(--nx-ok))]">
                    <x-icon name="check" class="h-5 w-5" /> Verified — genuine and untampered
                </p>
                <dl class="grid grid-cols-2 gap-x-6 gap-y-1 text-sm text-[rgb(var(--nx-text))] sm:grid-cols-3">
                    <div><dt class="text-[rgb(var(--nx-text-2))]">Name</dt><dd class="font-medium">{{ $themePreview['name'] }}</dd></div>
                    <div><dt class="text-[rgb(var(--nx-text-2))]">Slug</dt><dd class="font-medium">{{ $themePreview['slug'] }}</dd></div>
                    <div><dt class="text-[rgb(var(--nx-text-2))]">Icon set</dt><dd class="font-medium">{{ $themePreview['icon_family']['style'] ?? '—' }} / {{ $themePreview['icon_family']['set'] ?? '—' }}</dd></div>
                </dl>
                @if ($themePreview['persona'])
                    <p class="mt-2 text-sm text-[rgb(var(--nx-text-2))]">{{ $themePreview['persona'] }}</p>
                @endif

                @if (! empty($themePreview['swatches']))
                    <div class="mt-3">
                        <p class="mb-1 text-xs font-medium uppercase text-[rgb(var(--nx-text-2))]">Palette preview</p>
                        <div class="flex overflow-hidden rounded-lg border border-[rgb(var(--nx-line))]" style="height:2.5rem">
                            @foreach ($themePreview['swatches'] as $key => $rgb)
                                <span class="flex-1" style="background: rgb({{ $rgb }})" title="{{ $key }}"></span>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if (! empty($themePreview['font_warnings']))
                    <div class="mt-3 space-y-2">
                        @foreach ($themePreview['font_warnings'] as $warning)
                            <div class="rounded-lg border border-[rgb(var(--nx-warn))] bg-[rgb(var(--nx-warn)/0.12)] p-3 text-xs text-[rgb(var(--nx-warn))]">
                                {{ $warning }}
                            </div>
                        @endforeach
                    </div>
                @endif

                <button type="button" wire:click="installTheme"
                    wire:loading.attr="disabled" wire:target="installTheme"
                    class="mt-4 inline-flex items-center justify-center rounded-lg bg-[rgb(var(--nx-cta-b))] px-5 py-2.5 text-sm font-semibold text-white hover:bg-[rgb(var(--nx-cta-a))] disabled:opacity-50">
                    <span wire:loading.remove wire:target="installTheme">Install this theme</span>
                    <span wire:loading wire:target="installTheme">Installing…</span>
                </button>
            </div>
        @endif

        {{-- Installed themes --}}
        <div class="rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-5">
            <h2 class="mb-3 text-lg font-semibold text-[rgb(var(--nx-text))]">Installed themes</h2>

            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                @foreach ($this->installedThemes as $t)
                    <div class="rounded-xl border border-[rgb(var(--nx-line))] p-3">
                        <div class="flex items-center justify-between gap-2">
                            <div>
                                <p class="font-medium text-[rgb(var(--nx-text))]">{{ $t['name'] }}</p>
                                <p class="text-xs text-[rgb(var(--nx-text-2))]">{{ $t['slug'] }}</p>
                            </div>
                            @if ($t['slug'] === $this->activeThemeSlug)
                                <span class="rounded-full bg-[rgb(var(--nx-ok)/0.16)] px-2 py-0.5 text-xs font-medium text-[rgb(var(--nx-ok))]">Active</span>
                            @endif
                        </div>
                        <div class="mt-2 flex gap-2">
                            @if ($t['slug'] !== $this->activeThemeSlug)
                                <button type="button" wire:click="activateTheme('{{ $t['slug'] }}')" wire:confirm="Activate {{ $t['name'] }} platform-wide?"
                                    class="rounded-lg border border-[rgb(var(--nx-line))] px-2.5 py-1 text-xs font-medium text-[rgb(var(--nx-text-2))] hover:bg-[rgb(var(--nx-surface-2))]">
                                    Activate
                                </button>
                            @endif
                            @if (! $t['is_built_in'])
                                <button type="button" wire:click="removeTheme('{{ $t['slug'] }}')" wire:confirm="Remove {{ $t['name'] }}? This deletes its stored images too."
                                    class="rounded-lg border border-[rgb(var(--nx-bad))] px-2.5 py-1 text-xs font-medium text-[rgb(var(--nx-bad))] hover:bg-[rgb(var(--nx-bad)/0.1)]">
                                    Remove
                                </button>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    @if ($tab === 'versions' && class_exists(\App\Services\ProviderRadar\ApiVersionWatch::class))
        <div class="mb-5 rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface-2))] p-4 text-sm text-[rgb(var(--nx-text-2))]" data-api-watch>
            <x-icon name="info" class="mr-1 inline h-4 w-4" />
            Once a day, a read-only check asks each provider's API whether a <strong>newer version</strong> now answers, and whether it announces a <strong>Deprecation</strong> or <strong>Sunset</strong> date. No credentials are sent and nothing changes by itself: a finding stays here until you acknowledge it, and comes back only if it changes.
            <div class="mt-3 flex flex-wrap items-center gap-2">
                <button type="button" wire:click="runApiWatchNow" wire:loading.attr="disabled" wire:target="runApiWatchNow" class="rounded-lg bg-[rgb(var(--nx-surface-3))] px-3 py-1.5 text-xs font-semibold text-[rgb(var(--nx-text))]"><span wire:loading.remove wire:target="runApiWatchNow">Check now</span><span wire:loading wire:target="runApiWatchNow">Queuing...</span></button>
                @if ($apiWatchNote)<span role="status" class="text-xs text-[rgb(var(--nx-ok))]">{{ $apiWatchNote }}</span>@endif
            </div>
        </div>
        @php($labels = \App\Services\ProviderRadar\ApiVersionWatch::PROVIDERS)
        @forelse ($this->apiWatches as $w)
            <div class="mb-2 flex flex-wrap items-center gap-3 rounded-xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-3 text-sm" data-api-watch-row="{{ $w->provider }}" wire:key="aw-{{ $w->provider }}">
                <div class="min-w-0 flex-1">
                    <p class="font-semibold text-[rgb(var(--nx-text))]">{{ $labels[$w->provider] ?? $w->provider }} <span class="text-xs font-normal text-[rgb(var(--nx-text-2))]">pinned to {{ $w->pinned_version ?? 'no version in its address' }}@if ($w->observed_version), reports {{ $w->observed_version }} @endif</span></p>
                    @if ($w->newer_versions)<p class="text-xs text-[rgb(var(--nx-warn))]">A newer version now answers: {{ implode(', ', $w->newer_versions) }}. Read its changelog before moving.</p>@endif
                    @if ($w->deprecation)<p class="text-xs text-[rgb(var(--nx-bad))]">{{ $w->deprecation }}</p>@endif
                    <p class="text-xs text-[rgb(var(--nx-text-2))]">Checked {{ $w->last_checked_at?->diffForHumans() ?? 'never' }}@if ($w->status === 'unreachable'), but the provider did not answer @endif</p>
                </div>
                <span class="rounded-full px-2 py-0.5 text-[11px] font-bold {{ match ($w->status) { 'ok' => 'bg-[rgb(var(--nx-ok)/0.14)] text-[rgb(var(--nx-ok))]', 'unreachable' => 'bg-[rgb(var(--nx-surface-3))] text-[rgb(var(--nx-text-2))]', default => 'bg-[rgb(var(--nx-warn)/0.16)] text-[rgb(var(--nx-warn))]' } }}">{{ match ($w->status) { 'ok' => 'Up to date', 'newer_available' => 'Newer version', 'deprecated' => 'Deprecated', 'changed' => 'Version changed', default => 'No answer' } }}</span>
                @if ($w->needsAttention())<button type="button" wire:click="acknowledgeApiWatch('{{ $w->provider }}')" wire:loading.attr="disabled" wire:target="acknowledgeApiWatch('{{ $w->provider }}')" class="rounded-lg bg-[rgb(var(--nx-surface-3))] px-3 py-1.5 text-xs font-semibold text-[rgb(var(--nx-text))]">Acknowledge</button>@endif
            </div>
        @empty
            <p class="rounded-xl border border-[rgb(var(--nx-line))] p-4 text-sm text-[rgb(var(--nx-text-2))]">No check has run yet. Press Check now, or wait for the daily run.</p>
        @endforelse
    @endif

    @if ($tab === 'radar')
        <div class="mb-5 rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface-2))] p-4 text-sm text-[rgb(var(--nx-text-2))]">
            <x-icon name="info" class="mr-1 inline h-4 w-4" />
            Watches each provider's own API for real, evidence-linked signs of new capability and turns a genuine
            change into a proposal here — <strong>never</strong> an automatic code change. Every proposal links back
            to the literal fetched evidence so you can verify the claim yourself before acting on it.
        </div>

        @if ($this->capabilityProposals->isEmpty())
            <p class="rounded-xl border border-dashed border-[rgb(var(--nx-line))] p-6 text-center text-sm text-[rgb(var(--nx-text-2))]">
                No open proposals right now.
            </p>
        @else
            <div class="space-y-3">
                @foreach ($this->capabilityProposals as $p)
                    <div wire:key="proposal-{{ $p->id }}" class="rounded-2xl border border-[rgb(var(--nx-line))] p-4">
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <div>
                                <span class="rounded-full bg-[rgb(var(--nx-surface-3))] px-2 py-0.5 text-xs font-semibold uppercase tracking-wide text-[rgb(var(--nx-text-2))]">{{ $p->provider }}</span>
                                <span class="ml-2 rounded-full px-2 py-0.5 text-xs font-medium {{ $p->confidence === 'high' ? 'bg-[rgb(var(--nx-ok)/0.16)] text-[rgb(var(--nx-ok))]' : ($p->confidence === 'medium' ? 'bg-[rgb(var(--nx-warn)/0.16)] text-[rgb(var(--nx-warn))]' : 'bg-[rgb(var(--nx-surface-3))] text-[rgb(var(--nx-text-2))]') }}">
                                    {{ ucfirst($p->confidence) }} confidence
                                </span>
                                <span class="ml-1 rounded-full bg-[rgb(var(--nx-surface-3))] px-2 py-0.5 text-xs font-medium text-[rgb(var(--nx-text-2))]">{{ ucfirst($p->estimated_effort) }} effort</span>
                            </div>
                            <span class="text-xs text-[rgb(var(--nx-text-2))]">{{ $p->created_at->diffForHumans() }}</span>
                        </div>

                        <p class="mt-2 text-sm font-semibold text-[rgb(var(--nx-text))]">{{ $p->proposed_feature }}</p>
                        <p class="mt-1 text-sm text-[rgb(var(--nx-text-2))]">{{ $p->summary }}</p>

                        <dl class="mt-3 grid gap-2 text-xs sm:grid-cols-2">
                            <div>
                                <dt class="font-medium text-[rgb(var(--nx-text-2))]">Capability added</dt>
                                <dd class="text-[rgb(var(--nx-text))]">{{ $p->capability_added }}</dd>
                            </div>
                            <div>
                                <dt class="font-medium text-[rgb(var(--nx-text-2))]">Relevance to NaaraSim</dt>
                                <dd class="text-[rgb(var(--nx-text))]">{{ $p->relevance_to_naara }}</dd>
                            </div>
                        </dl>

                        <div class="mt-3 rounded-lg border border-[rgb(var(--nx-warn))] bg-[rgb(var(--nx-warn)/0.12)] p-2.5 text-xs text-[rgb(var(--nx-warn))]">
                            <x-icon name="alert-triangle" class="mr-1 inline h-3.5 w-3.5" />
                            Verify before acting: {{ $p->requires_human_verification_because }}
                        </div>

                        <button type="button" wire:click="toggleProposalEvidence({{ $p->id }})"
                            class="mt-3 text-xs font-medium text-[rgb(var(--nx-teal-ink))] hover:underline">
                            {{ $expandedProposalId === $p->id ? 'Hide raw evidence' : 'View raw evidence' }}
                        </button>

                        @if ($expandedProposalId === $p->id)
                            <div class="mt-2 space-y-2">
                                <p class="text-xs text-[rgb(var(--nx-text-2))]">
                                    Source: <span class="break-all font-mono">{{ $p->sourceSnapshot?->source_type }}</span>
                                </p>
                                <pre class="max-h-64 overflow-auto rounded-lg bg-black/80 p-3 text-xs text-[rgb(var(--nx-text))]">{{ $p->raw_diff }}</pre>
                            </div>
                        @endif

                        <div class="mt-4 flex flex-wrap items-center gap-2">
                            <button type="button" wire:click="acceptProposal({{ $p->id }})" wire:confirm="Accept this proposal? It's marked accepted for planning — nothing is built automatically."
                                class="rounded-lg bg-[rgb(var(--nx-cta-b))] px-3 py-1.5 text-xs font-semibold text-white hover:bg-[rgb(var(--nx-cta-a))]">
                                Accept
                            </button>
                            <div class="flex items-center gap-1.5">
                                <button type="button" wire:click="deferProposal({{ $p->id }})"
                                    class="rounded-lg border border-[rgb(var(--nx-line))] px-3 py-1.5 text-xs font-medium text-[rgb(var(--nx-text-2))] hover:bg-[rgb(var(--nx-surface-2))]">
                                    Defer
                                </button>
                                <select wire:model="deferDays" class="rounded-lg border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] px-1.5 py-1 text-xs">
                                    <option value="7">7 days</option>
                                    <option value="30">30 days</option>
                                    <option value="90">90 days</option>
                                </select>
                            </div>
                            @if ($dismissingProposalId === $p->id)
                                <div class="flex flex-1 items-center gap-1.5">
                                    <input type="text" wire:model="dismissReasonInput" placeholder="Reason (optional)"
                                        class="w-full max-w-xs rounded-lg border border-[rgb(var(--nx-line))] px-2 py-1 text-xs" />
                                    <button type="button" wire:click="dismissProposal({{ $p->id }})"
                                        class="rounded-lg border border-[rgb(var(--nx-bad))] px-2.5 py-1 text-xs font-medium text-[rgb(var(--nx-bad))] hover:bg-[rgb(var(--nx-bad)/0.1)]">
                                        Confirm dismiss
                                    </button>
                                    <button type="button" wire:click="cancelDismiss" class="text-xs text-[rgb(var(--nx-text-2))] hover:text-[rgb(var(--nx-text-2))]">Cancel</button>
                                </div>
                            @else
                                <button type="button" wire:click="startDismiss({{ $p->id }})"
                                    class="rounded-lg border border-[rgb(var(--nx-line))] px-3 py-1.5 text-xs font-medium text-[rgb(var(--nx-text-2))] hover:bg-[rgb(var(--nx-surface-2))]">
                                    Dismiss
                                </button>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        @if ($this->reviewedCapabilityProposals->isNotEmpty())
            <div class="mt-8">
                <h2 class="mb-2 text-sm font-semibold text-[rgb(var(--nx-text))]">Recently reviewed</h2>
                <div class="space-y-1.5">
                    @foreach ($this->reviewedCapabilityProposals as $p)
                        <div class="flex items-center justify-between rounded-lg border border-[rgb(var(--nx-line))] px-3 py-2 text-xs">
                            <span class="text-[rgb(var(--nx-text-2))]">{{ $p->provider }} — {{ \Illuminate\Support\Str::limit($p->proposed_feature, 60) }}</span>
                            <span class="rounded-full px-2 py-0.5 font-medium {{ $p->status === 'accepted' ? 'bg-[rgb(var(--nx-ok)/0.16)] text-[rgb(var(--nx-ok))]' : 'bg-[rgb(var(--nx-surface-3))] text-[rgb(var(--nx-text-2))]' }}">
                                {{ ucfirst($p->status) }}
                            </span>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    @endif
</div>
