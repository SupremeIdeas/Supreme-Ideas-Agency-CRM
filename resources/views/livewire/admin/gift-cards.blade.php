{{-- nx:converted (skin tokens only; see docs/appearance/SKIN-CONTRACT.md) --}}
<div class="mx-auto max-w-5xl">
    <h1 class="text-2xl font-bold text-[rgb(var(--nx-text))]">Naara Gift</h1>
    <p class="mt-1 text-sm text-[rgb(var(--nx-text-2))]">
        All {{ count($providers) }} registered providers run together, ranked Primary → Secondary → Tertiary → …
        — each fills the gaps the higher-ranked ones don't carry for a brand.
        The storefront only ever sells the primary provider per brand, and never sees cost.
        A provider stays "Coming Soon" until its keys are added — Tillo additionally needs its enterprise account set up first.
    </p>

    {{-- Providers side by side --}}
    <div class="mt-5 grid gap-4 sm:grid-cols-2">
        @foreach ($providers as $p)
            <div class="rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-5 shadow-sm" wire:key="prov-{{ $p['key'] }}">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <div class="flex items-center gap-2">
                            <h2 class="text-base font-semibold text-[rgb(var(--nx-text))]">{{ $p['label'] }}</h2>
                            <span class="rounded-full bg-[rgb(var(--nx-cta-b)/0.1)] px-2 py-0.5 text-[11px] font-semibold text-[rgb(var(--nx-teal-ink))]">{{ $p['role'] }}</span>
                        </div>
                        <p class="mt-0.5 text-xs {{ $p['configured'] ? 'text-[rgb(var(--nx-ok))]' : 'text-[rgb(var(--nx-warn))]' }}">
                            {{ $p['configured'] ? 'Keys configured · Active' : 'Add keys on Admin → API keys' }}
                        </p>
                    </div>
                    <div class="flex shrink-0 items-center gap-2">
                        <button type="button" wire:click="preflight('{{ $p['key'] }}')" wire:loading.attr="disabled" wire:target="preflight('{{ $p['key'] }}')"
                                class="rounded-lg border border-[rgb(var(--nx-line))] px-3 py-1.5 text-xs font-semibold text-[rgb(var(--nx-text-2))] transition hover:border-[rgb(var(--nx-teal))] hover:text-[rgb(var(--nx-teal-ink))] disabled:opacity-60">
                            <span wire:loading.remove wire:target="preflight('{{ $p['key'] }}')">Test</span>
                            <span wire:loading wire:target="preflight('{{ $p['key'] }}')">Testing…</span>
                        </button>
                        <button type="button" wire:click="sync('{{ $p['key'] }}')" wire:loading.attr="disabled" wire:target="sync('{{ $p['key'] }}')"
                                class="rounded-lg bg-[rgb(var(--nx-cta-b))] px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-[rgb(var(--nx-cta-a))] disabled:opacity-60">
                            <span wire:loading.remove wire:target="sync('{{ $p['key'] }}')">Sync now</span>
                            <span wire:loading wire:target="sync('{{ $p['key'] }}')">Syncing…</span>
                        </button>
                    </div>
                </div>

                @if (! empty($probe[$p['key']]))
                    @php $pr = $probe[$p['key']]; @endphp
                    <div class="mt-3 rounded-xl border p-3 text-xs {{ $pr['ok'] ? 'border-[rgb(var(--nx-ok)/0.4)] bg-[rgb(var(--nx-ok)/0.12)] text-[rgb(var(--nx-ok))]' : 'border-[rgb(var(--nx-bad))] bg-[rgb(var(--nx-bad)/0.1)] text-[rgb(var(--nx-bad))]' }}">
                        @if ($pr['ok'])
                            <span class="font-semibold">Connected.</span>
                            @if ($pr['balance'] !== null) Balance {{ $pr['currency'] ?: '' }} {{ number_format((float) $pr['balance'], 2) }}.@endif
                            @if ($pr['products'] !== null) {{ number_format($pr['products']) }} products available.@endif
                        @else
                            <span class="font-semibold">Failed.</span> {{ $pr['error'] }}
                        @endif
                    </div>
                @endif
                <dl class="mt-4 grid grid-cols-2 gap-3 text-sm">
                    <div class="rounded-xl bg-[rgb(var(--nx-surface-2))] p-3">
                        <dt class="text-[11px] uppercase tracking-wide text-[rgb(var(--nx-text-2))]">Products</dt>
                        <dd class="mt-0.5 font-bold text-[rgb(var(--nx-text))]">{{ number_format($p['total']) }}</dd>
                    </div>
                    <div class="rounded-xl bg-[rgb(var(--nx-surface-2))] p-3">
                        <dt class="text-[11px] uppercase tracking-wide text-[rgb(var(--nx-text-2))]">Live in store</dt>
                        <dd class="mt-0.5 font-bold text-[rgb(var(--nx-text))]">{{ number_format($p['live']) }}</dd>
                    </div>
                </dl>
                <p class="mt-3 text-[11px] text-[rgb(var(--nx-text-2))]">
                    @if ($p['sync'])
                        Last sync {{ \Illuminate\Support\Carbon::parse($p['sync']['at'])->diffForHumans() }} ·
                        <span class="{{ $p['sync']['ok'] ? 'text-[rgb(var(--nx-ok))]' : 'text-[rgb(var(--nx-bad))]' }}">{{ $p['sync']['ok'] ? 'OK' : 'Failed' }}</span>
                        @if (! $p['sync']['ok'] && $p['sync']['error'])<span class="text-[rgb(var(--nx-bad))]"> — {{ $p['sync']['error'] }}</span>@endif
                    @else
                        Never synced.
                    @endif
                </p>
            </div>
        @endforeach
    </div>

    {{-- Logo Wizard: every brand gets a real, locally stored logo. Approved ones are never replaced by a re-run. --}}
    <div class="mt-6 rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-5 shadow-sm" data-giftcard-logo-wizard>
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 class="text-base font-semibold text-[rgb(var(--nx-text))]">Logo Wizard</h2>
                <p class="mt-0.5 text-xs text-[rgb(var(--nx-text-2))]">Fetches each brand's logo (provider image, then Simple Icons, then the official site), stores a square WebP on our own storage and never hot-links. Brand logos are trademarks: confirm each provider's terms allow showing them for resale.</p>
            </div>
            <button type="button" wire:click="runLogoWizard" wire:loading.attr="disabled" wire:target="runLogoWizard" class="rounded-lg bg-[rgb(var(--nx-cta-b))] px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-[rgb(var(--nx-cta-a))] disabled:opacity-60">
                <span wire:loading.remove wire:target="runLogoWizard">Run Logo Wizard</span><span wire:loading wire:target="runLogoWizard">Queuing...</span>
            </button>
        </div>
        <dl class="mt-4 grid grid-cols-3 gap-3 text-sm">
            <div class="rounded-xl bg-[rgb(var(--nx-surface-2))] p-3"><dt class="text-[11px] uppercase tracking-wide text-[rgb(var(--nx-text-2))]">Approved</dt><dd class="mt-0.5 font-bold text-[rgb(var(--nx-text))]">{{ number_format((int) ($logoStats['approved'] ?? 0)) }}</dd></div>
            <div class="rounded-xl bg-[rgb(var(--nx-surface-2))] p-3"><dt class="text-[11px] uppercase tracking-wide text-[rgb(var(--nx-text-2))]">Needs review</dt><dd class="mt-0.5 font-bold text-[rgb(var(--nx-text))]">{{ number_format((int) ($logoStats['review'] ?? 0)) }}</dd></div>
            <div class="rounded-xl bg-[rgb(var(--nx-surface-2))] p-3"><dt class="text-[11px] uppercase tracking-wide text-[rgb(var(--nx-text-2))]">Monogram</dt><dd class="mt-0.5 font-bold text-[rgb(var(--nx-text))]">{{ number_format((int) ($logoStats['fallback'] ?? 0)) }}</dd></div>
        </dl>
        @if ($logoQueue->isNotEmpty())
            <ul class="mt-4 divide-y divide-[rgb(var(--nx-line))]" aria-label="Logos waiting for review">
                @foreach ($logoQueue as $l)
                    <li class="flex flex-wrap items-center gap-3 py-3" wire:key="lq-{{ $l->brand_key }}">
                        <img src="{{ $logoUrls->url($l) }}" alt="" width="44" height="44" class="h-11 w-11 rounded-lg border border-[rgb(var(--nx-line))] object-contain" loading="lazy">
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-semibold text-[rgb(var(--nx-text))]">{{ $l->brand_name }}</p>
                            <p class="text-[11px] text-[rgb(var(--nx-text-2))]">{{ $l->status === 'fallback' ? 'Monogram placeholder' : 'Found on '.($l->domain ?: $l->source) }}@if ($l->confidence !== null) · confidence {{ $l->confidence }}%@endif</p>
                        </div>
                        <input type="text" wire:model="logoDomain.{{ $l->brand_key }}" placeholder="official domain, e.g. brand.com" aria-label="Official domain for {{ $l->brand_name }}" class="w-44 rounded-lg border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] px-2 py-1 text-xs text-[rgb(var(--nx-text))]">
                        <button type="button" wire:click="fetchLogo('{{ $l->brand_key }}')" wire:loading.attr="disabled" wire:target="fetchLogo('{{ $l->brand_key }}')" class="rounded-lg border border-[rgb(var(--nx-line))] px-2.5 py-1 text-xs font-semibold text-[rgb(var(--nx-text-2))]">Fetch</button>
                        @if ($l->status === 'review')<button type="button" wire:click="approveLogo('{{ $l->brand_key }}')" class="rounded-lg bg-[rgb(var(--nx-cta-b))] px-2.5 py-1 text-xs font-semibold text-white">Accept</button>@endif
                        <label class="cursor-pointer rounded-lg border border-[rgb(var(--nx-line))] px-2.5 py-1 text-xs font-semibold text-[rgb(var(--nx-text-2))]">Upload<input type="file" wire:model="brandUploads.{{ $l->brand_key }}" accept="image/png,image/webp,image/jpeg" class="hidden"></label>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    {{-- Review queue --}}
    <h2 class="mt-8 flex items-center gap-2 text-sm font-semibold text-[rgb(var(--nx-text))]">
        <x-icon name="shield" class="h-4 w-4" /> Manual review
        @if ($review->isNotEmpty())<span class="rounded-full bg-[rgb(var(--nx-warn)/0.16)] px-2 py-0.5 text-[11px] font-semibold text-[rgb(var(--nx-warn))]">{{ $review->count() }}</span>@endif
    </h2>
    <p class="mt-1 text-xs text-[rgb(var(--nx-text-2))]">High-value purchases are held here — the buyer's wallet is already debited. Approve to deliver, reject to refund.</p>
    <div class="mt-3 overflow-x-auto rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] shadow-sm">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-[rgb(var(--nx-line))] text-left text-xs uppercase tracking-wide text-[rgb(var(--nx-text-2))]">
                    <th class="px-4 py-3">Buyer</th>
                    <th class="px-4 py-3">Brand</th>
                    <th class="px-4 py-3">Face</th>
                    <th class="px-4 py-3">Charged</th>
                    <th class="px-4 py-3 text-right">Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($review as $o)
                    <tr class="border-b border-[rgb(var(--nx-line))]" wire:key="rev-{{ $o->id }}">
                        <td class="px-4 py-3 text-[rgb(var(--nx-text-2))]">{{ $o->user?->email ?? '—' }}</td>
                        <td class="px-4 py-3 text-[rgb(var(--nx-text-2))]">{{ $o->brand_name }}</td>
                        <td class="px-4 py-3 text-[rgb(var(--nx-text-2))]">{{ $o->currency }} {{ number_format((float) $o->face_value, 2) }}</td>
                        <td class="px-4 py-3 font-medium text-[rgb(var(--nx-text))]">${{ number_format((float) $o->price_charged, 2) }}</td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-end gap-2">
                                <button type="button" wire:click="approve({{ $o->id }})" wire:confirm="Approve and deliver this gift card?"
                                        class="rounded-lg bg-[rgb(var(--nx-cta-b))] px-3 py-1.5 text-xs font-semibold text-white hover:bg-[rgb(var(--nx-cta-a))]">Approve</button>
                                <button type="button" wire:click="reject({{ $o->id }})" wire:confirm="Reject and refund this order?"
                                        class="rounded-lg border border-[rgb(var(--nx-line))] px-3 py-1.5 text-xs font-semibold text-[rgb(var(--nx-text-2))] hover:border-[rgb(var(--nx-bad))] hover:text-[rgb(var(--nx-bad))]">Reject</button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-8 text-center text-sm text-[rgb(var(--nx-text-2))]">Nothing awaiting review.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Fraud controls --}}
    <h2 class="mt-8 text-sm font-semibold text-[rgb(var(--nx-text))]">Fraud controls</h2>
    <p class="mt-1 text-xs text-[rgb(var(--nx-text-2))]">Per-account limits. Purchases at or above the review threshold hold for manual approval.</p>
    <div class="mt-3 rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-5 shadow-sm">
        <div class="grid gap-4 sm:grid-cols-3">
            @php $labels = [
                'max_amount_24h' => ['Max spend / 24h (USD)', 'number', '0.01'],
                'max_count_24h' => ['Max orders / 24h', 'number', '1'],
                'max_amount_7d' => ['Max spend / 7 days (USD)', 'number', '0.01'],
                'cooloff_value' => ['New-account cool-off cap (USD)', 'number', '0.01'],
                'cooloff_hours' => ['Cool-off window (hours)', 'number', '1'],
                'review_threshold' => ['Manual-review threshold (USD)', 'number', '0.01'],
            ]; @endphp
            @foreach ($labels as $field => $meta)
                <div wire:key="fraud-{{ $field }}">
                    <label class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">{{ $meta[0] }}</label>
                    <input type="{{ $meta[1] }}" step="{{ $meta[2] }}" min="0" wire:model="{{ $field }}"
                           class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm text-[rgb(var(--nx-text))]" />
                    @error($field)<p class="mt-1 text-[11px] text-[rgb(var(--nx-bad))]">{{ $message }}</p>@enderror
                </div>
            @endforeach
        </div>
        <div class="mt-4">
            <button type="button" wire:click="saveFraud" wire:loading.attr="disabled" wire:target="saveFraud"
                    class="rounded-lg bg-[rgb(var(--nx-cta-b))] px-4 py-2 text-sm font-semibold text-white transition hover:bg-[rgb(var(--nx-cta-a))] disabled:opacity-60">Save controls</button>
        </div>
    </div>

    {{-- Catalogue --}}
    <div class="mt-8 flex flex-wrap items-center justify-between gap-3">
        <h2 class="text-sm font-semibold text-[rgb(var(--nx-text))]">Catalogue</h2>
        <div class="flex items-center gap-2">
            <select wire:model.live="providerFilter" class="rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-2.5 py-1.5 text-xs text-[rgb(var(--nx-text))]">
                <option value="">All providers</option>
                @foreach ($providers as $p)
                    <option value="{{ $p['key'] }}">{{ $p['label'] }}</option>
                @endforeach
            </select>
            <input type="search" wire:model.live.debounce.300ms="search" placeholder="Search brand…"
                   class="rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-1.5 text-xs text-[rgb(var(--nx-text))]" />
        </div>
    </div>
    <div class="mt-3 overflow-x-auto rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] shadow-sm">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-[rgb(var(--nx-line))] text-left text-xs uppercase tracking-wide text-[rgb(var(--nx-text-2))]">
                    <th class="px-4 py-3">Brand</th>
                    <th class="px-4 py-3">Provider</th>
                    <th class="px-4 py-3">Country</th>
                    <th class="px-4 py-3">Primary</th>
                    <th class="px-4 py-3 text-center">In store</th>
                    <th class="px-4 py-3 text-center">Featured</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($catalogue as $c)
                    <tr class="border-b border-[rgb(var(--nx-line))]" wire:key="cat-{{ $c->id }}">
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-2">
                                @if ($c->logo_url)
                                    <img src="{{ $c->logo_url }}" alt="" class="h-6 w-6 rounded object-contain" loading="lazy" />
                                @else
                                    <span class="flex h-6 w-6 items-center justify-center rounded bg-[rgb(var(--nx-surface-3))] text-[10px] font-bold text-[rgb(var(--nx-text-2))]">{{ mb_substr($c->brand_name, 0, 1) }}</span>
                                @endif
                                <span class="font-medium text-[rgb(var(--nx-text))]">{{ $c->brand_name }}</span>
                                {{-- Add / replace a logo (for brands that synced without one). --}}
                                <label class="cursor-pointer text-[11px] font-semibold text-[rgb(var(--nx-teal-ink))] hover:underline" title="Upload a logo">
                                    {{ $c->logo_url ? 'Replace' : 'Add logo' }}
                                    <input type="file" wire:model="logoUploads.{{ $c->id }}" accept="image/png,image/webp,image/svg+xml,image/jpeg" class="hidden" />
                                </label>
                                <span wire:loading wire:target="logoUploads.{{ $c->id }}" class="text-[11px] text-[rgb(var(--nx-text-2))]">Uploading…</span>
                            </div>
                            @error('logoUploads.'.$c->id)<p class="mt-1 text-[11px] text-[rgb(var(--nx-bad))]">{{ $message }}</p>@enderror
                        </td>
                        <td class="px-4 py-3 text-xs capitalize text-[rgb(var(--nx-text-2))]">{{ $c->provider }}</td>
                        <td class="px-4 py-3 text-xs text-[rgb(var(--nx-text-2))]">{{ $c->country ?: 'Global' }}</td>
                        <td class="px-4 py-3">
                            @if ($c->is_primary)
                                <span class="rounded-full bg-[rgb(var(--nx-cta-b)/0.1)] px-2 py-0.5 text-[11px] font-semibold text-[rgb(var(--nx-teal-ink))]">Primary</span>
                            @else
                                <span class="text-[11px] text-[rgb(var(--nx-text-2))]">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-center">
                            <button type="button" wire:click="toggle({{ $c->id }}, 'admin_enabled')"
                                    class="inline-flex h-6 w-11 items-center rounded-full transition {{ $c->admin_enabled ? 'bg-[rgb(var(--nx-cta-b))]' : 'bg-[rgb(var(--nx-line-strong))]' }}">
                                <span class="ml-0.5 inline-block h-5 w-5 rounded-full bg-[rgb(var(--nx-surface))] shadow transition {{ $c->admin_enabled ? 'translate-x-5' : '' }}"></span>
                            </button>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <button type="button" wire:click="toggle({{ $c->id }}, 'featured')"
                                    class="inline-flex items-center justify-center rounded-lg p-1.5 transition {{ $c->featured ? 'text-[rgb(var(--nx-gold-ink))]' : 'text-[rgb(var(--nx-text-3))] hover:text-[rgb(var(--nx-text-2))]' }}">
                                <x-icon name="star" class="h-4 w-4" />
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-8 text-center text-sm text-[rgb(var(--nx-text-2))]">No products yet — sync a provider above.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $catalogue->links() }}</div>

    {{-- Recent orders --}}
    <div class="mt-8 flex items-center justify-between gap-3">
        <h2 class="text-sm font-semibold text-[rgb(var(--nx-text))]">Recent orders</h2>
        <button type="button" wire:click="exportCsv"
                class="inline-flex items-center gap-1.5 rounded-lg border border-[rgb(var(--nx-line))] px-3 py-1.5 text-xs font-semibold text-[rgb(var(--nx-text-2))] transition hover:border-[rgb(var(--nx-teal))] hover:text-[rgb(var(--nx-teal-ink))]">
            <x-icon name="file-text" class="h-3.5 w-3.5" /> Export CSV
        </button>
    </div>
    <div class="mt-3 overflow-x-auto rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] shadow-sm">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-[rgb(var(--nx-line))] text-left text-xs uppercase tracking-wide text-[rgb(var(--nx-text-2))]">
                    <th class="px-4 py-3">Buyer</th>
                    <th class="px-4 py-3">Brand</th>
                    <th class="px-4 py-3">Charged</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3">When</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($recent as $o)
                    @php $tone = match($o->status){ 'delivered' => 'bg-[rgb(var(--nx-ok)/0.16)] text-[rgb(var(--nx-ok))]', 'failed','refunded' => 'bg-[rgb(var(--nx-bad)/0.14)] text-[rgb(var(--nx-bad))]', default => 'bg-[rgb(var(--nx-warn)/0.16)] text-[rgb(var(--nx-warn))]' }; @endphp
                    <tr class="border-b border-[rgb(var(--nx-line))]" wire:key="ord-{{ $o->id }}">
                        <td class="px-4 py-3 text-[rgb(var(--nx-text-2))]">{{ $o->user?->email ?? '—' }}</td>
                        <td class="px-4 py-3 text-[rgb(var(--nx-text-2))]">{{ $o->brand_name }}</td>
                        <td class="px-4 py-3 font-medium text-[rgb(var(--nx-text))]">${{ number_format((float) $o->price_charged, 2) }}</td>
                        <td class="px-4 py-3"><span class="rounded-full px-2 py-0.5 text-[11px] font-semibold capitalize{{ $tone }}">{{ $o->status }}</span></td>
                        <td class="px-4 py-3 text-xs text-[rgb(var(--nx-text-2))]">{{ $o->created_at?->diffForHumans() }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-8 text-center text-sm text-[rgb(var(--nx-text-2))]">No orders yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
