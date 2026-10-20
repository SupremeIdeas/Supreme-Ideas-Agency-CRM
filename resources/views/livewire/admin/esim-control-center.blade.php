{{-- nx:converted (skin tokens only; see docs/appearance/SKIN-CONTRACT.md) --}}
<div class="mx-auto max-w-5xl">
    <div class="mb-6">
        <h1 class="text-xl font-bold text-[rgb(var(--nx-text))]">eSIM Control Center</h1>
        <p class="text-sm text-[rgb(var(--nx-text-2))]">Sync catalogues, curate the Popular tab, tune margins, manage tooltips and navigation imagery — one place for the whole eSIM store.</p>
    </div>

    @if ($flash)
        <div class="mb-4 flex items-center gap-2 rounded-lg bg-[rgb(var(--nx-ok)/0.12)] px-4 py-3 text-sm text-[rgb(var(--nx-ok))]">
            <x-icon name="badge-check" class="h-4 w-4" /> {{ $flash }}
        </div>
    @endif

    {{-- Section tabs --}}
    <div class="mb-6 flex flex-wrap gap-2">
        @foreach (['sync' => 'Sync status', 'plans' => 'Plans & margins', 'images' => 'Images'] as $key => $label)
            <button wire:click="setSection('{{ $key }}')"
                    class="rounded-full border px-4 py-1.5 text-sm font-semibold transition {{ $section === $key ? 'border-[rgb(var(--nx-teal))] bg-[rgb(var(--nx-cta-b))] text-white' : 'border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] text-[rgb(var(--nx-text-2))] hover:bg-[rgb(var(--nx-surface-2))]' }}">
                {{ $label }}
            </button>
        @endforeach
    </div>

    {{-- ================================ SYNC ================================ --}}
    @if ($section === 'sync')
        <div class="mb-4 flex justify-end">
            <button wire:click="syncAll" wire:loading.attr="disabled"
                    class="inline-flex items-center gap-1.5 rounded-lg bg-[rgb(var(--nx-cta-b))] px-4 py-2 text-sm font-semibold text-white hover:bg-[rgb(var(--nx-cta-a))] disabled:opacity-60">
                <x-icon name="refresh" class="h-4 w-4" /> Sync all
            </button>
        </div>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($providers as $key => $label)
                @php($s = $status[$key] ?? null)
                <div class="rounded-xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-4">
                    <div class="flex items-center justify-between">
                        <h3 class="font-semibold text-[rgb(var(--nx-text))]">{{ $label }}</h3>
                        @if ($s === null)
                            <span class="rounded-full bg-[rgb(var(--nx-surface-3))] px-2 py-0.5 text-xs text-[rgb(var(--nx-text-2))]">Never synced</span>
                        @elseif ($s['ok'])
                            <span class="inline-flex items-center gap-1 rounded-full bg-[rgb(var(--nx-ok)/0.16)] px-2 py-0.5 text-xs font-semibold text-[rgb(var(--nx-ok))]"><x-icon name="badge-check" class="h-3 w-3" /> OK</span>
                        @else
                            <span class="inline-flex items-center gap-1 rounded-full bg-[rgb(var(--nx-bad)/0.14)] px-2 py-0.5 text-xs font-semibold text-[rgb(var(--nx-bad))]"><x-icon name="x" class="h-3 w-3" /> Failed</span>
                        @endif
                    </div>
                    <dl class="mt-3 space-y-1 text-xs text-[rgb(var(--nx-text-2))]">
                        <div class="flex justify-between"><dt>Last sync</dt><dd>{{ $s ? \Illuminate\Support\Carbon::parse($s['at'])->diffForHumans() : '—' }}</dd></div>
                        <div class="flex justify-between"><dt>Plans</dt><dd>{{ $s['count'] ?? '—' }}</dd></div>
                    </dl>
                    @if ($s && ! $s['ok'] && $s['error'])
                        <p class="mt-2 rounded bg-[rgb(var(--nx-bad)/0.1)] p-2 text-xs text-[rgb(var(--nx-bad))]">{{ $s['error'] }}</p>
                    @endif
                    <button wire:click="syncProvider('{{ $key }}')" wire:loading.attr="disabled"
                            class="mt-3 inline-flex w-full items-center justify-center gap-1.5 rounded-lg border border-[rgb(var(--nx-line))] px-3 py-2 text-sm font-semibold text-[rgb(var(--nx-text))] hover:bg-[rgb(var(--nx-surface-2))] disabled:opacity-60">
                        <x-icon name="refresh" class="h-4 w-4" /> Sync now
                    </button>
                </div>
            @endforeach
        </div>

    {{-- ============================== PLANS ================================= --}}
    @elseif ($section === 'plans')
        {{-- Filters --}}
        <div class="mb-4 grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-5">
            <input type="text" wire:model.live.debounce.300ms="fSearch" placeholder="Search name…"
                   class="rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm">
            <select wire:model.live="fProvider" class="rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm">
                <option value="">All providers</option>
                @foreach ($providers as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach
            </select>
            <select wire:model.live="fCoverage" class="rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm">
                <option value="">All coverage</option>
                @foreach ($coverageTypes as $c)<option value="{{ $c }}">{{ ucfirst($c) }}</option>@endforeach
            </select>
            <select wire:model.live="fRegion" class="rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm">
                <option value="">All regions</option>
                @foreach ($regions as $slug => $label)<option value="{{ $slug }}">{{ $label }}</option>@endforeach
            </select>
            <input type="text" wire:model.live.debounce.300ms="fCountry" placeholder="Country ISO (e.g. NG)" maxlength="2"
                   class="rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm uppercase">
        </div>

        {{-- Bulk margin apply --}}
        <div class="mb-4 flex flex-wrap items-center gap-2 rounded-lg border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface-2))] p-3 text-sm">
            <span class="font-semibold text-[rgb(var(--nx-text))]">Bulk margin for the filtered set:</span>
            <input type="number" step="0.1" min="0" wire:model="bulkMargin" placeholder="e.g. 35"
                   class="w-28 rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-2 py-1.5">
            <span class="text-[rgb(var(--nx-text-2))]">%</span>
            <button wire:click="applyBulkMargin" wire:confirm="Apply this margin to every plan matching the current filter?"
                    class="rounded-lg bg-[rgb(var(--nx-cta-b))] px-3 py-1.5 font-semibold text-white hover:bg-[rgb(var(--nx-cta-a))]">Apply</button>
            @error('bulkMargin') <span class="text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</span> @enderror
        </div>

        <div class="overflow-x-auto rounded-xl border border-[rgb(var(--nx-line))]">
            <table class="min-w-full divide-y divide-[rgb(var(--nx-line))] text-sm">
                <thead class="bg-[rgb(var(--nx-surface-2))] text-left text-xs font-semibold uppercase text-[rgb(var(--nx-text-2))]">
                    <tr>
                        <th class="px-3 py-2">Plan</th>
                        <th class="px-3 py-2">Coverage</th>
                        <th class="px-3 py-2">Cost → Retail</th>
                        <th class="px-3 py-2">Margin %</th>
                        <th class="px-3 py-2">Popular</th>
                        <th class="px-3 py-2">Tooltip</th>
                        <th class="px-3 py-2">Fair usage</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[rgb(var(--nx-line))]">
                    @forelse ($plans as $plan)
                        @php($p = $profits[$plan->id] ?? null)
                        <tr class="align-top text-[rgb(var(--nx-text))]">
                            <td class="px-3 py-3">
                                <div class="font-semibold text-[rgb(var(--nx-text))]">{{ $plan->name }}</div>
                                <div class="text-xs text-[rgb(var(--nx-text-2))]">{{ $providers[$plan->provider] ?? $plan->provider }}</div>
                            </td>
                            <td class="px-3 py-3 text-xs">
                                <span class="rounded-full bg-[rgb(var(--nx-surface-3))] px-2 py-0.5">{{ ucfirst($plan->coverage_type ?? '—') }}</span>
                                @if ($plan->region_slug)<div class="mt-1 text-[rgb(var(--nx-text-2))]">{{ $plan->region_slug }}</div>@endif
                            </td>
                            <td class="px-3 py-3 text-xs">
                                @if ($p)
                                    <div>${{ number_format($p['cost_price'], 2) }} → <strong>${{ number_format($p['retail_price'], 2) }}</strong></div>
                                    <div class="text-[rgb(var(--nx-ok))]">+${{ number_format($p['profit_usd'], 2) }} ({{ $p['profit_pct'] }}%)</div>
                                @endif
                            </td>
                            <td class="px-3 py-3">
                                @if ($editingMarginId === $plan->id)
                                    <div class="flex items-center gap-1">
                                        <input type="number" step="0.1" min="0" wire:model="marginValue" placeholder="global"
                                               class="w-20 rounded border border-[rgb(var(--nx-line-strong))] px-2 py-1 text-xs">
                                        <button wire:click="saveMargin({{ $plan->id }})" class="rounded bg-[rgb(var(--nx-cta-b))] px-2 py-1 text-xs font-semibold text-white">Save</button>
                                        <button wire:click="cancelMargin" aria-label="Cancel" class="text-[rgb(var(--nx-text-2))]"><x-icon name="x" class="h-3.5 w-3.5" /></button>
                                    </div>
                                    @error('marginValue') <p class="mt-1 text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p> @enderror
                                @else
                                    <button wire:click="editMargin({{ $plan->id }})" class="inline-flex items-center gap-1 rounded border border-[rgb(var(--nx-line))] px-2 py-1 text-xs hover:bg-[rgb(var(--nx-surface-2))]">
                                        {{ $plan->override_markup_pct !== null ? $plan->override_markup_pct.'%' : 'global' }} <x-icon name="settings" class="h-3 w-3" />
                                    </button>
                                @endif
                                @if ($aiEnabled)
                                    <div class="mt-1">
                                        @if (isset($suggestions[$plan->id]))
                                            <div class="rounded bg-[rgb(var(--nx-gold)/0.1)] p-2 text-xs text-[rgb(var(--nx-text-2))]">
                                                <div class="font-semibold text-[rgb(var(--nx-gold-ink))]">Claude: {{ $suggestions[$plan->id]['markup'] }}%</div>
                                                <div>{{ $suggestions[$plan->id]['rationale'] }}</div>
                                                <div class="mt-1 flex gap-2">
                                                    <button wire:click="applySuggestion({{ $plan->id }})" class="font-semibold text-[rgb(var(--nx-teal-ink))]">Use</button>
                                                    <button wire:click="dismissSuggestion({{ $plan->id }})" class="text-[rgb(var(--nx-text-2))]">Dismiss</button>
                                                </div>
                                            </div>
                                        @else
                                            <button wire:click="suggestMargin({{ $plan->id }})" wire:loading.attr="disabled" class="text-xs font-semibold text-[rgb(var(--nx-gold-ink))] hover:underline">
                                                <x-icon name="star" class="mr-0.5 inline h-3 w-3" />Suggest
                                            </button>
                                        @endif
                                    </div>
                                @endif
                            </td>
                            <td class="px-3 py-3">
                                <button wire:click="togglePopular({{ $plan->id }})"
                                        class="inline-flex h-6 w-11 items-center rounded-full transition {{ $plan->is_featured ? 'bg-[rgb(var(--nx-cta-b))]' : 'bg-[rgb(var(--nx-line-strong))]' }}">
                                    <span class="ml-0.5 h-5 w-5 rounded-full bg-[rgb(var(--nx-surface))] transition {{ $plan->is_featured ? 'translate-x-5' : '' }}"></span>
                                </button>
                            </td>
                            <td class="px-3 py-3 text-xs">
                                @if ($editingTooltipId === $plan->id)
                                    <textarea wire:model="tooltipValue" rows="3" maxlength="400" placeholder="Manual override…"
                                              class="w-56 rounded border border-[rgb(var(--nx-line-strong))] px-2 py-1 text-xs"></textarea>
                                    <div class="mt-1 flex gap-2">
                                        <button wire:click="saveTooltip({{ $plan->id }})" class="rounded bg-[rgb(var(--nx-cta-b))] px-2 py-1 font-semibold text-white">Save</button>
                                        <button wire:click="cancelTooltip" class="text-[rgb(var(--nx-text-2))]">Cancel</button>
                                    </div>
                                @else
                                    <p class="max-w-xs text-[rgb(var(--nx-text-2))]">
                                        {{ \Illuminate\Support\Str::limit($plan->display_tooltip ?: '— no tooltip —', 90) }}
                                        @if ($plan->ai_tooltip_override)<span class="ml-1 rounded bg-[rgb(var(--nx-warn)/0.16)] px-1 text-[10px] font-semibold text-[rgb(var(--nx-warn))]">override</span>@endif
                                    </p>
                                    <div class="mt-1 flex gap-2">
                                        <button wire:click="editTooltip({{ $plan->id }})" class="font-semibold text-[rgb(var(--nx-teal-ink))]">Override</button>
                                        @if ($aiEnabled)<button wire:click="regenerateTooltip({{ $plan->id }})" class="text-[rgb(var(--nx-text-2))]">Regenerate</button>@endif
                                    </div>
                                @endif
                            </td>
                            <td class="px-3 py-3 text-xs">
                                @if ($plan->data_mb !== null)
                                    <span class="text-[rgb(var(--nx-text-2))]">— data-limited, N/A —</span>
                                @elseif ($editingFairUsageId === $plan->id)
                                    <textarea wire:model="fairUsageValue" rows="3" maxlength="300" placeholder="Real known threshold, e.g. 3GB/day @ 20Mbps, then 1Mbps…"
                                              class="w-56 rounded border border-[rgb(var(--nx-line-strong))] px-2 py-1 text-xs"></textarea>
                                    <div class="mt-1 flex gap-2">
                                        <button wire:click="saveFairUsage({{ $plan->id }})" class="rounded bg-[rgb(var(--nx-cta-b))] px-2 py-1 font-semibold text-white">Save</button>
                                        <button wire:click="cancelFairUsage" class="text-[rgb(var(--nx-text-2))]">Cancel</button>
                                    </div>
                                @else
                                    <p class="max-w-xs text-[rgb(var(--nx-text-2))]">
                                        {{ \Illuminate\Support\Str::limit($plan->display_fair_usage_note, 90) }}
                                        @if ($plan->fair_usage_note)<span class="ml-1 rounded bg-[rgb(var(--nx-warn)/0.16)] px-1 text-[10px] font-semibold text-[rgb(var(--nx-warn))]">override</span>@else<span class="ml-1 rounded bg-[rgb(var(--nx-surface-3))] px-1 text-[10px] font-semibold text-[rgb(var(--nx-text-2))]">generic</span>@endif
                                    </p>
                                    <button wire:click="editFairUsage({{ $plan->id }})" class="mt-1 font-semibold text-[rgb(var(--nx-teal-ink))]">Set real threshold</button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-3 py-10 text-center text-[rgb(var(--nx-text-2))]">No plans match. Sync a provider or adjust the filter.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $plans->links() }}</div>

    {{-- ============================== IMAGES ================================ --}}
    @else
        <div class="mb-4 inline-flex rounded-full border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface-3))] p-1">
            @foreach (['country' => 'Countries', 'region' => 'Regions & Global'] as $key => $label)
                <button wire:click="setImageKind('{{ $key }}')" class="rounded-full px-4 py-1.5 text-sm font-semibold transition {{ $imageKind === $key ? 'bg-[rgb(var(--nx-surface))] text-[rgb(var(--nx-teal-ink))] shadow-sm' : 'text-[rgb(var(--nx-text-2))]' }}">{{ $label }}</button>
            @endforeach
        </div>

        <form wire:submit="saveImage" class="mb-6 grid grid-cols-1 gap-4 rounded-xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-5 sm:grid-cols-3">
            <div>
                <label class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">{{ $imageKind === 'country' ? 'Country' : 'Region' }}</label>
                @if ($imageKind === 'country')
                                        <select wire:model="imgCountry" class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm">
                        <option value="">Choose a country…</option>
                        @foreach ($countryOptions as $code => $label)<option value="{{ $code }}">{{ $label }}</option>@endforeach
                    </select>
                    @error('imgCountry') <p class="mt-1 text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p> @enderror
                @else
                    <select wire:model="imgRegion" class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm">
                        <option value="">Choose…</option>
                        @foreach ($regionOptions as $slug => $label)<option value="{{ $slug }}">{{ $label }}</option>@endforeach
                    </select>
                    @error('imgRegion') <p class="mt-1 text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p> @enderror
                @endif
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">Grid icon (webp)</label>
                <input type="file" wire:model="iconUpload" accept="image/webp,image/png,image/jpeg" class="w-full text-xs text-[rgb(var(--nx-text-2))]">
                @error('iconUpload') <p class="mt-1 text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p> @enderror
                <div wire:loading wire:target="iconUpload" class="mt-1 text-xs text-[rgb(var(--nx-teal-ink))]">Uploading…</div>
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">Detail banner (webp)</label>
                <input type="file" wire:model="detailUpload" accept="image/webp,image/png,image/jpeg" class="w-full text-xs text-[rgb(var(--nx-text-2))]">
                @error('detailUpload') <p class="mt-1 text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p> @enderror
                <div wire:loading wire:target="detailUpload" class="mt-1 text-xs text-[rgb(var(--nx-teal-ink))]">Uploading…</div>
            </div>
            <div class="sm:col-span-3">
                <button type="submit" class="inline-flex items-center gap-1.5 rounded-lg bg-[rgb(var(--nx-cta-b))] px-4 py-2 text-sm font-semibold text-white hover:bg-[rgb(var(--nx-cta-a))]">
                    <x-icon name="upload" class="h-4 w-4" /> Save image
                </button>
            </div>
        </form>

        @if ($imageKind === 'country')
            <label class="mb-3 inline-flex cursor-pointer items-center gap-2 text-sm text-[rgb(var(--nx-text-2))]">
                <input type="checkbox" wire:model.live="onlyMissing" class="h-4 w-4 rounded border-[rgb(var(--nx-line-strong))] text-[rgb(var(--nx-teal-ink))] focus:ring-[rgb(var(--nx-teal))]">
                Show only countries without images
            </label>
        @endif

        @if ($imageKind === 'country' && $onlyMissing)
            <div class="mb-4 divide-y divide-[rgb(var(--nx-line))] rounded-xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))]">
                @forelse ($missingCountries as $m)
                    <button type="button" wire:click="pickCountry('{{ $m['code'] }}')" class="flex w-full items-center justify-between px-4 py-2 text-left text-sm hover:bg-[rgb(var(--nx-surface-2))]">
                        <span class="font-medium text-[rgb(var(--nx-text))]">{{ $m['name'] }} <span class="text-xs text-[rgb(var(--nx-text-2))]">{{ $m['code'] }}</span></span>
                        <span class="text-xs text-[rgb(var(--nx-warn))]">
                            Missing {{ $m['missing_icon'] && $m['missing_banner'] ? 'icon + banner' : ($m['missing_icon'] ? 'icon' : 'banner') }}
                        </span>
                    </button>
                @empty
                    <p class="px-4 py-6 text-center text-sm text-[rgb(var(--nx-text-2))]">Every country in the catalogue has an icon and a banner.</p>
                @endforelse
            </div>
        @endif

        <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
            @if ($imageKind === 'country')
                @forelse ($countryImages as $img)
                    <div class="overflow-hidden rounded-xl border border-[rgb(var(--nx-line))]">
                        <div class="flex h-24 items-center justify-center bg-[rgb(var(--nx-surface-3))]">
                            @if ($img->icon_path)<img src="{{ $img->icon_path }}" class="h-full w-full object-cover">@else<x-country-flag :country="$img->country_code" class="h-10 w-14 rounded" />@endif
                        </div>
                        <div class="flex items-center justify-between p-2 text-xs">
                            <span class="min-w-0 truncate font-semibold text-[rgb(var(--nx-text))]" title="{{ $img->country_code }}">{{ \App\Support\CountryNames::name($img->country_code) }}</span>
                            <button wire:click="deleteCountryImage({{ $img->id }})" wire:confirm="Remove this image?" class="text-[rgb(var(--nx-bad))] hover:underline">Remove</button>
                        </div>
                    </div>
                @empty
                    <p class="col-span-full py-8 text-center text-sm text-[rgb(var(--nx-text-2))]">No country images yet.</p>
                @endforelse
            @else
                @forelse ($regionImages as $img)
                    <div class="overflow-hidden rounded-xl border border-[rgb(var(--nx-line))]">
                        <div class="flex h-24 items-center justify-center bg-[rgb(var(--nx-surface-3))]">
                            @if ($img->icon_path)<img src="{{ $img->icon_path }}" class="h-full w-full object-cover">@else<x-icon name="globe" class="h-8 w-8 text-[rgb(var(--nx-teal-ink)/0.6)]" gradient />@endif
                        </div>
                        <div class="flex items-center justify-between p-2 text-xs">
                            <span class="font-semibold text-[rgb(var(--nx-text))]">{{ $img->region_slug }}</span>
                            <button wire:click="deleteRegionImage({{ $img->id }})" wire:confirm="Remove this image?" class="text-[rgb(var(--nx-bad))] hover:underline">Remove</button>
                        </div>
                    </div>
                @empty
                    <p class="col-span-full py-8 text-center text-sm text-[rgb(var(--nx-text-2))]">No region images yet.</p>
                @endforelse
            @endif
        </div>
    @endif
</div>
