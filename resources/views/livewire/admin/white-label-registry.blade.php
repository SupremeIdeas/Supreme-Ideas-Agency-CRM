{{-- nx:converted --}}
<div class="mx-auto max-w-5xl" x-data="{ tab: 'licences' }">
    <x-admin.wl-hub active="licences" />
    <div class="mb-6 flex items-start justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-[rgb(var(--nx-text))]">White-label oversight</h1>
            <p class="text-sm text-[rgb(var(--nx-text-2))]">Registered brand instances, their tier and status, and every call their deployed copy makes to the distribution API.</p>
        </div>
        <button type="button" wire:click="toggleApi"
            class="rounded-lg border px-3 py-1.5 text-sm font-medium {{ $this->apiEnabled ? 'border-[rgb(var(--nx-ok))] bg-[rgb(var(--nx-ok)/0.12)] text-[rgb(var(--nx-text))]' : 'border-[rgb(var(--nx-line))] text-[rgb(var(--nx-text-2))]' }}">
            Distribution API: {{ $this->apiEnabled ? 'On' : 'Off' }}
        </button>
    </div>

    {{-- One-time credential reveal --}}
    <div class="-mx-1 mb-4 flex gap-2 overflow-x-auto px-1 pb-1" role="tablist" aria-label="Licence sections">
        @foreach (['licences' => 'Licences', 'plans' => 'Plans and rules', 'money' => 'Earnings and packages'] as $k => $l)
            <button type="button" role="tab" @click="tab = '{{ $k }}'" :aria-selected="tab === '{{ $k }}'" :class="tab === '{{ $k }}' ? 'is-on' : ''" class="ns-nt__chip whitespace-nowrap">{{ $l }}</button>
        @endforeach
    </div>

    @if ($revealedKey || $revealedToken)
        <div class="mb-6 rounded-2xl border border-[rgb(var(--nx-teal)/0.4)] bg-[rgb(var(--nx-cta-b)/0.08)] p-5">
            <div class="flex items-start justify-between gap-4">
                <div>
                    @if ($revealedKey)
                        <h3 class="text-sm font-semibold text-[rgb(var(--nx-teal-ink))]">License key issued</h3>
                        <p class="mt-1 text-xs text-[rgb(var(--nx-teal-ink))]">Hand this to the brand owner. They enter it in their deployment to activate and receive an API token. Keep it safe — regenerating it invalidates the old one.</p>
                        <code class="mt-2 inline-block rounded-lg bg-[rgb(var(--nx-surface))] px-3 py-1.5 font-mono text-sm text-[rgb(var(--nx-teal-ink))]">{{ $revealedKey }}</code>
                    @endif
                    @if ($revealedToken)
                        <h3 class="{{ $revealedKey ? 'mt-3 ' : '' }}text-sm font-semibold text-[rgb(var(--nx-teal-ink))]">API token (shown once)</h3>
                        <p class="mt-1 text-xs text-[rgb(var(--nx-teal-ink))]">This is a bearer credential — it will not be shown again. Copy it now and hand it over securely for a fork that cannot reach the activate endpoint.</p>
                        <code class="mt-2 block break-all rounded-lg bg-[rgb(var(--nx-surface))] px-3 py-1.5 font-mono text-xs text-[rgb(var(--nx-teal-ink))]">{{ $revealedToken }}</code>
                    @endif
                </div>
                <button type="button" wire:click="clearReveal" class="shrink-0 rounded-lg border border-[rgb(var(--nx-teal)/0.4)] px-2.5 py-1 text-xs font-medium text-[rgb(var(--nx-teal-ink))] hover:bg-[rgb(var(--nx-cta-b)/0.14)]">Dismiss</button>
            </div>
        </div>
    @endif

    <div x-show="tab === 'licences'" @if ('licences' !== 'licences') x-cloak @endif>
    {{-- Issue a new license --}}
    <div class="mb-6 rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-5">
        <h2 class="mb-3 text-lg font-semibold text-[rgb(var(--nx-text))]">Issue a license</h2>
        <p class="mb-3 text-xs text-[rgb(var(--nx-text-2))]">Registers a brand instance and mints its license key at the chosen tier. The tier decides which distributable packages the brand is entitled to.</p>
        <form wire:submit="issueNewLicense" class="grid gap-3 sm:grid-cols-4">
            <div class="sm:col-span-1">
                <input type="text" wire:model="newBrand" placeholder="Brand name" class="w-full rounded-lg border border-[rgb(var(--nx-line))] px-3 py-2 text-sm" />
                @error('newBrand') <span class="text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</span> @enderror
            </div>
            <div class="sm:col-span-1">
                <input type="email" wire:model="newEmail" placeholder="Contact email" class="w-full rounded-lg border border-[rgb(var(--nx-line))] px-3 py-2 text-sm" />
                @error('newEmail') <span class="text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</span> @enderror
            </div>
            <div class="sm:col-span-1">
                <select wire:model="newTier" class="w-full rounded-lg border border-[rgb(var(--nx-line))] px-3 py-2 text-sm">
                    @foreach ($this->tierOptions as $t)
                        <option value="{{ $t }}">{{ ucfirst($t) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="sm:col-span-1">
                <button type="submit" wire:loading.attr="disabled" wire:target="issueNewLicense"
                    class="w-full rounded-lg bg-[rgb(var(--nx-cta-b))] px-3 py-2 text-sm font-medium text-white hover:bg-[rgb(var(--nx-cta-a))] disabled:opacity-60">
                    <span wire:loading.remove wire:target="issueNewLicense">Issue license</span>
                    <span wire:loading wire:target="issueNewLicense">Issuing…</span>
                </button>
            </div>
        </form>
    </div>

    {{-- Registry --}}
    <div class="mb-6 rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-5">
        <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
            <h2 class="text-lg font-semibold text-[rgb(var(--nx-text))]">Registered instances</h2>
            <input type="text" wire:model.live.debounce.300ms="instanceSearch" placeholder="Search brand, email, or domain…"
                class="w-full max-w-xs rounded-lg border border-[rgb(var(--nx-line))] px-3 py-1.5 text-sm" />
        </div>

        @if ($this->instances->isEmpty())
            <p class="text-sm text-[rgb(var(--nx-text-2))]">
                @if (trim($instanceSearch) !== '')
                    No registered instance matches "{{ $instanceSearch }}".
                @else
                    No white-label instances are registered yet. Issue a license above, or a brand can file a self-serve registration request.
                @endif
            </p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="text-xs uppercase text-[rgb(var(--nx-text-2))]">
                        <tr>
                            <th class="py-2 pr-4">Brand</th>
                            <th class="py-2 pr-4">Status</th>
                            <th class="py-2 pr-4">Tier</th>
                            <th class="py-2 pr-4">License</th>
                            <th class="py-2 pr-4">Version</th>
                            <th class="py-2 pr-4">Domain</th>
                            <th class="py-2 pr-4">Last check-in</th>
                            <th class="py-2 pr-4"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[rgb(var(--nx-line))]">
                        @foreach ($this->instances as $i)
                            <tr class="text-[rgb(var(--nx-text))]">
                                <td class="py-2 pr-4">
                                    <span class="font-medium">{{ $i->brand_name }}</span>
                                    <span class="block text-xs text-[rgb(var(--nx-text-2))]">{{ $i->slug }}</span>
                                    @if ($i->acquisition_method === \App\Models\WhiteLabelInstance::ACQUISITION_MERCHANT_SELF_SERVICE)
                                        <span class="mt-1 inline-flex items-center gap-1 rounded-full bg-[rgb(var(--nx-gold)/0.16)] px-2 py-0.5 text-[11px] font-medium text-[rgb(var(--nx-gold-ink))]">
                                            Self-service{{ $i->licensePlan ? ' · '.$i->licensePlan->name : '' }}
                                        </span>
                                        @if ($i->price_usd)
                                            <span class="block text-[11px] text-[rgb(var(--nx-text-2))]">Priced ${{ number_format((float) $i->price_usd, 2) }} · paid ${{ number_format($i->amountPaidTotal(), 2) }}</span>
                                        @endif
                                        @if ($i->hasThemeAddon())
                                            <span class="mt-1 block text-[11px] font-medium text-[rgb(var(--nx-gold-ink))]">{{ \App\Support\ThemeAddonCatalog::labelFor($i->theme_addon) }} — ${{ number_format((float) $i->theme_addon_price_usd, 2) }}</span>
                                        @endif
                                    @endif
                                </td>
                                <td class="py-2 pr-4">
                                    @php
                                        $tone = match ($i->status) {
                                            'active' => 'bg-[rgb(var(--nx-ok)/0.16)] text-[rgb(var(--nx-ok))]',
                                            'suspended' => 'bg-[rgb(var(--nx-warn)/0.16)] text-[rgb(var(--nx-warn))]',
                                            'rejected' => 'bg-[rgb(var(--nx-bad)/0.14)] text-[rgb(var(--nx-bad))]',
                                            default => 'bg-[rgb(var(--nx-surface-3))] text-[rgb(var(--nx-text-2))]',
                                        };
                                    @endphp
                                    <span class="rounded-full px-2 py-0.5 text-xs font-medium{{ $tone }}">{{ $i->status }}</span>
                                </td>
                                <td class="py-2 pr-4">
                                    <span>{{ $i->tier ? ucfirst($i->tier) : '—' }}</span>
                                    {{-- Batch 8: feature-entitlement level. Live-editable for an active
                                         instance (raise a paid Normal fork basic→standard). --}}
                                    @if ($i->status === 'active' && $i->entitlement_level)
                                        <select wire:change="setLevel({{ $i->id }}, $event.target.value)"
                                            class="mt-1 block w-full rounded-md border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] px-1.5 py-0.5 text-[11px] text-[rgb(var(--nx-text-2))]">
                                            @foreach ($this->featureLevels as $lvl)
                                                <option value="{{ $lvl }}" @selected($i->entitlement_level === $lvl)>{{ ucfirst($lvl) }}</option>
                                            @endforeach
                                        </select>
                                    @elseif ($i->entitlement_level)
                                        <span class="block text-[11px] text-[rgb(var(--nx-text-2))]">{{ ucfirst($i->entitlement_level) }}</span>
                                    @endif
                                    {{-- Prompt 22: skins this licence unlocks (effective), with an optional per-instance override. --}}
                                    <label class="mt-1 flex items-center gap-1 text-[11px] text-[rgb(var(--nx-text-2))]">
                                        Skins:
                                        <input type="number" min="1" max="{{ $this->skinCatalogueSize }}" value="{{ $i->skin_allowance_override }}"
                                            placeholder="{{ \App\Support\WhiteLabel\SkinAllowance::forTier($i->tier) }}" aria-label="Skin allowance override for {{ $i->brand_name }}"
                                            wire:change="setSkinOverride({{ $i->id }}, $event.target.value)"
                                            class="w-14 rounded-md border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] px-1.5 py-0.5 text-[11px] text-[rgb(var(--nx-text-2))]">
                                        <span>= {{ \App\Support\WhiteLabel\SkinAllowance::forInstance($i) }}</span>
                                    </label>
                                </td>
                                <td class="py-2 pr-4">
                                    @if ($i->license_revoked_at)
                                        <span class="text-xs font-medium text-[rgb(var(--nx-bad))]">Revoked</span>
                                    @elseif ($i->license_key)
                                        <span class="text-xs text-[rgb(var(--nx-text-2))]">Issued</span>
                                        @if ($i->api_token_last_four)
                                            <span class="block text-[11px] text-[rgb(var(--nx-text-2))]">token …{{ $i->api_token_last_four }}</span>
                                        @endif
                                    @else
                                        <span class="text-xs text-[rgb(var(--nx-text-2))]">—</span>
                                    @endif
                                </td>
                                <td class="py-2 pr-4">{{ $i->current_platform_version ?? '—' }}</td>
                                <td class="py-2 pr-4">
                                    @if ($i->activation_domain)
                                        <span class="text-xs text-[rgb(var(--nx-text-2))]">{{ $i->activation_domain }}</span>
                                        @if ($i->activation_domain_mismatch_at)
                                            <span class="mt-1 block w-fit rounded-full bg-[rgb(var(--nx-bad)/0.14)] px-2 py-0.5 text-[11px] font-medium text-[rgb(var(--nx-bad))]"
                                                title="Activated from a domain that doesn't match the fingerprint recorded at first activation.">
                                                Domain mismatch
                                            </span>
                                        @endif
                                    @else
                                        <span class="text-xs text-[rgb(var(--nx-text-2))]">—</span>
                                    @endif
                                </td>
                                <td class="py-2 pr-4 text-[rgb(var(--nx-text-2))]">{{ $i->last_checked_in_at?->diffForHumans() ?? 'never' }}</td>
                                <td class="py-2 pr-4">
                                    <div class="flex flex-wrap justify-end gap-1.5">
                                        @if (in_array($i->status, ['pending', 'approved_awaiting_funds']) && $i->acquisition_method === \App\Models\WhiteLabelInstance::ACQUISITION_MERCHANT_SELF_SERVICE)
                                            {{-- Content/billing/hot-menu blueprint Phase F1 — a self-service request
                                                 is PRICED here, then APPROVED here: approving is the ONLY action that
                                                 charges the merchant's wallet and issues the license
                                                 (approveAndActivate, super-admin only). The merchant no longer has
                                                 a self-serve "pay now" of their own. --}}
                                            @if ($i->status === 'pending')
                                                <div class="flex items-center gap-1">
                                                    <span class="text-[rgb(var(--nx-text-2))]">$</span>
                                                    <input type="number" step="0.01" min="0.01"
                                                        wire:model="priceInputs.{{ $i->id }}"
                                                        value="{{ $priceInputs[$i->id] ?? $i->price_usd ?? $i->licensePlan?->price_usd }}"
                                                        class="w-24 rounded-lg border border-[rgb(var(--nx-line))] px-2 py-1 text-xs"
                                                        placeholder="Price">
                                                    <button type="button" wire:click="priceInstance({{ $i->id }})" class="rounded-lg border border-[rgb(var(--nx-ok))] px-2 py-1 text-xs font-medium text-[rgb(var(--nx-ok))] hover:bg-[rgb(var(--nx-ok)/0.12)]">{{ $i->price_usd ? 'Update price' : 'Set price' }}</button>
                                                </div>
                                            @else
                                                <span class="rounded-lg bg-[rgb(var(--nx-warn)/0.16)] px-2 py-1 text-xs font-medium text-[rgb(var(--nx-warn))]">Awaiting funds</span>
                                            @endif
                                            @if ($i->price_usd !== null && $this->isSuperAdmin)
                                                <button type="button" wire:click="approveAndActivate({{ $i->id }})"
                                                    wire:confirm="Approve this request? ${{ number_format((float) $i->totalDueUsd(), 2) }} will be charged to {{ $i->brand_name }}'s wallet in the background."
                                                    class="rounded-lg border border-[rgb(var(--nx-teal))] bg-[rgb(var(--nx-cta-b)/0.1)] px-2 py-1 text-xs font-semibold text-[rgb(var(--nx-teal-ink))] hover:bg-[rgb(var(--nx-cta-b)/0.2)]">
                                                    {{ $i->status === 'approved_awaiting_funds' ? 'Retry approval' : 'Approve & activate' }}
                                                </button>
                                            @endif
                                            @if ($i->status === 'pending')
                                                <button type="button" wire:click="rejectInstance({{ $i->id }})" wire:confirm="Reject this registration request?" class="rounded-lg border border-[rgb(var(--nx-bad))] px-2 py-1 text-xs font-medium text-[rgb(var(--nx-bad))] hover:bg-[rgb(var(--nx-bad)/0.1)]">Reject</button>
                                            @endif
                                        @elseif ($i->status === 'pending')
                                            <button type="button" wire:click="approveInstance({{ $i->id }}, 'normal')" class="rounded-lg border border-[rgb(var(--nx-ok))] px-2 py-1 text-xs font-medium text-[rgb(var(--nx-ok))] hover:bg-[rgb(var(--nx-ok)/0.12)]">Approve · Normal</button>
                                            <button type="button" wire:click="approveInstance({{ $i->id }}, 'extended')" class="rounded-lg border border-[rgb(var(--nx-ok))] px-2 py-1 text-xs font-medium text-[rgb(var(--nx-ok))] hover:bg-[rgb(var(--nx-ok)/0.12)]">Approve · Extended</button>
                                            <button type="button" wire:click="rejectInstance({{ $i->id }})" wire:confirm="Reject this registration request?" class="rounded-lg border border-[rgb(var(--nx-bad))] px-2 py-1 text-xs font-medium text-[rgb(var(--nx-bad))] hover:bg-[rgb(var(--nx-bad)/0.1)]">Reject</button>
                                        @elseif ($i->status === 'active')
                                            <button type="button" wire:click="issueTokenFor({{ $i->id }})" class="rounded-lg border border-[rgb(var(--nx-line))] px-2 py-1 text-xs font-medium text-[rgb(var(--nx-text-2))] hover:bg-[rgb(var(--nx-surface-2))]">Issue token</button>
                                            <button type="button" wire:click="regenerateKey({{ $i->id }})" wire:confirm="Generate a new license key? The current key stops working immediately." class="rounded-lg border border-[rgb(var(--nx-line))] px-2 py-1 text-xs font-medium text-[rgb(var(--nx-text-2))] hover:bg-[rgb(var(--nx-surface-2))]">Regenerate key</button>
                                            <button type="button" wire:click="suspendInstance({{ $i->id }})" wire:confirm="Suspend this instance? Its token is revoked until restored." class="rounded-lg border border-[rgb(var(--nx-warn))] px-2 py-1 text-xs font-medium text-[rgb(var(--nx-warn))] hover:bg-[rgb(var(--nx-warn)/0.12)]">Suspend</button>
                                            <button type="button" wire:click="revokeLicense({{ $i->id }})" wire:confirm="Permanently revoke this license? The key can never be used again." class="rounded-lg border border-[rgb(var(--nx-bad))] px-2 py-1 text-xs font-medium text-[rgb(var(--nx-bad))] hover:bg-[rgb(var(--nx-bad)/0.1)]">Revoke</button>
                                        @elseif ($i->status === 'suspended')
                                            @unless ($i->license_revoked_at)
                                                <button type="button" wire:click="restoreInstance({{ $i->id }})" class="rounded-lg border border-[rgb(var(--nx-ok))] px-2 py-1 text-xs font-medium text-[rgb(var(--nx-ok))] hover:bg-[rgb(var(--nx-ok)/0.12)]">Restore</button>
                                                <button type="button" wire:click="revokeLicense({{ $i->id }})" wire:confirm="Permanently revoke this license? The key can never be used again." class="rounded-lg border border-[rgb(var(--nx-bad))] px-2 py-1 text-xs font-medium text-[rgb(var(--nx-bad))] hover:bg-[rgb(var(--nx-bad)/0.1)]">Revoke</button>
                                            @endunless
                                        @endif
                                        @if ($i->upgrade_target_plan_id !== null)
                                            {{-- Content/billing/hot-menu blueprint Phase F2 — the merchant proposed
                                                 an installment upgrade; the admin confirms the ENFORCED deadline
                                                 here (the merchant's proposed days is never unilaterally binding). --}}
                                            @if ($i->upgrade_deadline_at === null)
                                                <div class="flex items-center gap-1 rounded-lg border border-[rgb(var(--nx-warn))] bg-[rgb(var(--nx-warn)/0.12)] px-2 py-1">
                                                    <span class="text-[11px] text-[rgb(var(--nx-warn))]">${{ number_format((float) $i->upgrade_balance_due, 2) }} / {{ $i->upgrade_proposed_days }}d</span>
                                                    <input type="date" wire:model="deadlineInputs.{{ $i->id }}"
                                                        class="w-32 rounded-md border border-[rgb(var(--nx-warn))] bg-[rgb(var(--nx-surface))] px-1.5 py-0.5 text-[11px]">
                                                    <button type="button" wire:click="setUpgradeDeadline({{ $i->id }})" class="rounded-md border border-[rgb(var(--nx-ok))] px-1.5 py-0.5 text-[11px] font-medium text-[rgb(var(--nx-ok))] hover:bg-[rgb(var(--nx-ok)/0.12)]">Confirm</button>
                                                </div>
                                            @else
                                                <span class="rounded-lg bg-[rgb(var(--nx-cta-b)/0.1)] px-2 py-1 text-[11px] font-medium text-[rgb(var(--nx-teal-ink))]" title="${{ number_format((float) $i->upgrade_balance_due, 2) }} due">
                                                    Upgrade due {{ $i->upgrade_deadline_at->format('j M Y') }}
                                                </span>
                                            @endif
                                        @endif
                                        @if ($i->intake)
                                            <button type="button" wire:click="toggleIntakeDetail({{ $i->id }})"
                                                class="rounded-lg border px-2 py-1 text-xs font-medium {{ $i->intake->status === 'pending' ? 'border-[rgb(var(--nx-warn))] text-[rgb(var(--nx-warn))] hover:bg-[rgb(var(--nx-warn)/0.12)]' : 'border-[rgb(var(--nx-line))] text-[rgb(var(--nx-text-2))] hover:bg-[rgb(var(--nx-surface-2))]' }}">
                                                {{ $expandedIntakeInstanceId === $i->id ? 'Hide project' : 'Project ('.ucfirst(str_replace('_', ' ', $i->intake->status)).')' }}
                                            </button>
                                        @endif
                                        <button type="button" wire:click="selectInstance({{ $i->id }})"
                                            class="rounded-lg border border-[rgb(var(--nx-line))] px-2 py-1 text-xs font-medium text-[rgb(var(--nx-text-2))] hover:bg-[rgb(var(--nx-surface-2))]">
                                            {{ $selectedInstanceId === $i->id ? 'Hide log' : 'View log' }}
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            @if ($expandedIntakeInstanceId === $i->id && $i->intake)
                                @php $intake = $i->intake; $progress = $intake ? ($this->intakeProgress)($intake) : null; $dayOf = $intake ? ($this->intakeDayOf)($intake) : null; @endphp
                                <tr>
                                    <td colspan="8" class="bg-[rgb(var(--nx-surface-2))] p-4">
                                        <div class="grid gap-4 sm:grid-cols-2">
                                            <div class="space-y-1 text-xs text-[rgb(var(--nx-text-2))]">
                                                <p><span class="font-medium text-[rgb(var(--nx-text))]">Desired name:</span> {{ $intake->desired_brand_name }}</p>
                                                <p><span class="font-medium text-[rgb(var(--nx-text))]">WhatsApp:</span> {{ $intake->whatsapp_number }}</p>
                                                <p class="flex items-center gap-2">
                                                    <span class="font-medium text-[rgb(var(--nx-text))]">Brand colours:</span>
                                                    <span class="inline-block h-4 w-4 rounded-full border border-[rgb(var(--nx-line-strong))]" style="background:{{ $intake->brand_primary_color }}"></span>
                                                    <span class="inline-block h-4 w-4 rounded-full border border-[rgb(var(--nx-line-strong))]" style="background:{{ $intake->brand_accent_color }}"></span>
                                                    {{ $intake->brand_primary_color }} / {{ $intake->brand_accent_color }}
                                                </p>
                                                @if ($intake->logo_url)
                                                    <p><span class="font-medium text-[rgb(var(--nx-text))]">Logo:</span> <a href="{{ $intake->logo_url }}" target="_blank" class="text-[rgb(var(--nx-teal-ink))] underline">View uploaded logo</a></p>
                                                @elseif ($intake->logo_design_reference)
                                                    <p><span class="font-medium text-[rgb(var(--nx-text))]">Logo design reference:</span> {{ $intake->logo_design_reference }}</p>
                                                @endif
                                                @if ($intake->banner_reference_url)
                                                    <p><span class="font-medium text-[rgb(var(--nx-text))]">Banner reference:</span> <a href="{{ $intake->banner_reference_url }}" target="_blank" class="text-[rgb(var(--nx-teal-ink))] underline">View uploaded reference</a></p>
                                                @endif
                                                @if ($intake->banner_design_request)
                                                    <p><span class="font-medium text-[rgb(var(--nx-text))]">Banner request:</span> {{ $intake->banner_design_request }}</p>
                                                @endif
                                                <p><span class="font-medium text-[rgb(var(--nx-text))]">Hosting:</span> {{ str_replace('_', ' ', ucfirst($intake->hosting_choice)) }}</p>
                                                @if ($intake->isSelfHosted())
                                                    <p><span class="font-medium text-[rgb(var(--nx-text))]">Host:</span> {{ $intake->hosting_host }}</p>
                                                    <p><span class="font-medium text-[rgb(var(--nx-text))]">Username:</span> {{ $intake->hosting_username }}</p>
                                                    <p><span class="font-medium text-[rgb(var(--nx-text))]">Password:</span> <span class="font-mono">{{ $intake->hosting_password }}</span></p>
                                                    @if ($intake->hosting_notes)
                                                        <p><span class="font-medium text-[rgb(var(--nx-text))]">Access notes:</span> {{ $intake->hosting_notes }}</p>
                                                    @endif
                                                @endif
                                                @if ($intake->additional_notes)
                                                    <p><span class="font-medium text-[rgb(var(--nx-text))]">Additional notes:</span> {{ $intake->additional_notes }}</p>
                                                @endif
                                            </div>
                                            <div class="space-y-3">
                                                @if ($intake->status === 'pending')
                                                    <button type="button" wire:click="markIntakeSeen({{ $intake->id }})" class="rounded-lg border border-[rgb(var(--nx-ok))] px-3 py-1.5 text-xs font-medium text-[rgb(var(--nx-ok))] hover:bg-[rgb(var(--nx-ok)/0.12)]">Mark seen</button>
                                                @elseif ($intake->status === 'seen')
                                                    <div class="flex items-center gap-2">
                                                        <input type="number" min="1" wire:model="deployDaysInputs.{{ $intake->id }}" placeholder="Days" class="w-24 rounded-lg border border-[rgb(var(--nx-line))] px-2 py-1.5 text-xs">
                                                        <button type="button" wire:click="setIntakeDeployTimeline({{ $intake->id }})" class="rounded-lg border border-[rgb(var(--nx-ok))] px-3 py-1.5 text-xs font-medium text-[rgb(var(--nx-ok))] hover:bg-[rgb(var(--nx-ok)/0.12)]">Set deploy timeline</button>
                                                    </div>
                                                @elseif ($intake->status === 'in_progress')
                                                    <p class="text-xs text-[rgb(var(--nx-text-2))]">Day {{ $dayOf['day'] }} of {{ $dayOf['of'] }} — {{ $progress }}%</p>
                                                    <div class="h-2 w-full overflow-hidden rounded-full bg-[rgb(var(--nx-surface-3))]">
                                                        <div class="h-full rounded-full bg-[rgb(var(--nx-cta-b))]" style="width: {{ $progress }}%"></div>
                                                    </div>
                                                @elseif ($intake->status === 'completed')
                                                    <p class="text-xs font-medium text-[rgb(var(--nx-ok))]">Deployment complete.</p>
                                                @endif
                                                <a href="{{ route('admin.white-label.intake.pdf', ['intake' => $intake->id]) }}" target="_blank" class="inline-flex items-center gap-1 rounded-lg border border-[rgb(var(--nx-line))] px-3 py-1.5 text-xs font-medium text-[rgb(var(--nx-text-2))] hover:bg-[rgb(var(--nx-surface-2))]">
                                                    <x-icon name="download" class="h-3.5 w-3.5" /> Export PDF
                                                </a>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @endif
                            @if ($selectedInstanceId === $i->id)
                                <tr>
                                    <td colspan="8" class="bg-[rgb(var(--nx-surface-2))] p-3">
                                        @if ($this->selectedLogs->isEmpty())
                                            <p class="text-xs text-[rgb(var(--nx-text-2))]">No API calls recorded for this instance yet.</p>
                                        @else
                                            <table class="w-full text-left text-xs">
                                                <thead class="uppercase text-[rgb(var(--nx-text-2))]">
                                                    <tr><th class="py-1 pr-3">When</th><th class="py-1 pr-3">Endpoint</th><th class="py-1 pr-3">Status</th><th class="py-1 pr-3">IP</th></tr>
                                                </thead>
                                                <tbody>
                                                    @foreach ($this->selectedLogs as $log)
                                                        <tr class="text-[rgb(var(--nx-text-2))]">
                                                            <td class="py-1 pr-3">{{ $log->created_at?->diffForHumans() }}</td>
                                                            <td class="py-1 pr-3">{{ $log->method }} {{ $log->endpoint }}</td>
                                                            <td class="py-1 pr-3">{{ $log->response_status }}</td>
                                                            <td class="py-1 pr-3">{{ $log->ip_address ?? '—' }}</td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        @endif
                                    </td>
                                </tr>
                            @endif
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{-- Oversight/Withdrawal blueprint Phase E3 — a double-confirmation glance
         at every self-service approval the queue has processed, not a second
         approval gate: nothing here blocks anything. --}}
    @if ($this->systemProcessedApprovals->isNotEmpty())
        <div class="mb-6 rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-5">
            <h2 class="mb-1 text-lg font-semibold text-[rgb(var(--nx-text))]">System-processed approvals</h2>
            <p class="mb-3 text-xs text-[rgb(var(--nx-text-2))]">Every self-service approval handed to the background queue, most recent first — the charge and activation happen off this screen; this is just visibility that they landed correctly.</p>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="text-xs uppercase text-[rgb(var(--nx-text-2))]">
                        <tr>
                            <th class="py-2 pr-4">Brand</th>
                            <th class="py-2 pr-4">Queued</th>
                            <th class="py-2 pr-4">Outcome</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[rgb(var(--nx-line))]">
                        @foreach ($this->systemProcessedApprovals as $p)
                            <tr class="text-[rgb(var(--nx-text))]">
                                <td class="py-2 pr-4 font-medium">{{ $p->brand_name }}</td>
                                <td class="py-2 pr-4 text-[rgb(var(--nx-text-2))]">{{ $p->approval_queued_at?->diffForHumans() }}</td>
                                <td class="py-2 pr-4">
                                    @if ($p->status === 'active')
                                        <span class="rounded-full bg-[rgb(var(--nx-ok)/0.16)] px-2 py-0.5 text-xs font-medium text-[rgb(var(--nx-ok))]">Activated</span>
                                    @elseif ($p->status === 'approved_awaiting_funds')
                                        <span class="rounded-full bg-[rgb(var(--nx-warn)/0.16)] px-2 py-0.5 text-xs font-medium text-[rgb(var(--nx-warn))]">Wallet was short — awaiting top-up</span>
                                    @else
                                        <span class="rounded-full bg-[rgb(var(--nx-surface-3))] px-2 py-0.5 text-xs font-medium text-[rgb(var(--nx-text-2))]">{{ ucfirst($p->status) }}</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
    </div>

    <div x-show="tab === 'plans'" @if ('plans' !== 'licences') x-cloak @endif>
    {{-- Prompt 21-EXT §1.4/§6.5 — license plan catalog + resell-status gating --}}
    <div class="mb-6 rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-5">
        <h2 class="mb-3 text-lg font-semibold text-[rgb(var(--nx-text))]">License plans &amp; resell status</h2>

        {{-- Resell-status toggles with live threshold counts --}}
        <div class="mb-5 grid gap-3 sm:grid-cols-2">
            @foreach ($this->resellStatus as $tier => $status)
                <div class="rounded-xl border border-[rgb(var(--nx-line))] p-3">
                    <div class="flex items-center justify-between">
                        <p class="text-sm font-medium text-[rgb(var(--nx-text))]">{{ ucfirst($tier) }} resell</p>
                        <button type="button" wire:click="toggleResell('{{ $tier }}')"
                            class="rounded-full px-2.5 py-1 text-xs font-medium {{ $status['open'] ? 'bg-[rgb(var(--nx-ok)/0.16)] text-[rgb(var(--nx-text))]' : 'bg-[rgb(var(--nx-bad)/0.14)] text-[rgb(var(--nx-bad))]' }}">
                            {{ $status['open'] ? 'Open' : 'Closed' }}
                        </button>
                    </div>
                    <p class="mt-1 flex items-center gap-1.5 text-xs text-[rgb(var(--nx-text-2))]">
                        {{ $status['count'] }} /
                        <input type="number" min="1"
                               value="{{ $status['threshold'] }}"
                               wire:change="setResellThreshold('{{ $tier }}', $event.target.value)"
                               class="w-20 rounded border border-[rgb(var(--nx-line))] bg-transparent px-1.5 py-0.5 text-xs text-[rgb(var(--nx-text-2))]" />
                        self-service sales (auto-closes at threshold)
                    </p>
                </div>
            @endforeach
        </div>

        {{-- Plan create/edit form --}}
        <form wire:submit="savePlan" class="mb-5 grid gap-3 rounded-xl border border-[rgb(var(--nx-line))] p-4 sm:grid-cols-2">
            <div class="sm:col-span-2 flex items-center justify-between">
                <p class="text-sm font-semibold text-[rgb(var(--nx-text))]">{{ $editingPlanId ? 'Edit plan' : 'New plan' }}</p>
                @if ($editingPlanId)
                    <button type="button" wire:click="newPlanForm" class="text-xs font-medium text-[rgb(var(--nx-text-2))] hover:text-[rgb(var(--nx-text-2))]">Cancel edit</button>
                @endif
            </div>
            <div>
                <input type="text" wire:model="planName" placeholder="Name" class="w-full rounded-lg border border-[rgb(var(--nx-line))] px-3 py-2 text-sm" />
                @error('planName') <span class="text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</span> @enderror
            </div>
            <div>
                <input type="text" wire:model="planTagline" placeholder="Tagline" class="w-full rounded-lg border border-[rgb(var(--nx-line))] px-3 py-2 text-sm" />
                @error('planTagline') <span class="text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</span> @enderror
            </div>
            <div class="sm:col-span-2">
                <textarea wire:model="planDescription" rows="2" placeholder="Description" class="w-full rounded-lg border border-[rgb(var(--nx-line))] px-3 py-2 text-sm"></textarea>
                @error('planDescription') <span class="text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</span> @enderror
            </div>
            <div>
                <input type="number" step="0.01" min="0.01" wire:model="planPrice" placeholder="Price (USD)" class="w-full rounded-lg border border-[rgb(var(--nx-line))] px-3 py-2 text-sm" />
                @error('planPrice') <span class="text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</span> @enderror
            </div>
            <div>
                <input type="number" min="0" wire:model="planSortOrder" placeholder="Sort order" class="w-full rounded-lg border border-[rgb(var(--nx-line))] px-3 py-2 text-sm" />
            </div>
            <div>
                <select wire:model="planTier" class="w-full rounded-lg border border-[rgb(var(--nx-line))] px-3 py-2 text-sm">
                    @foreach ($this->tierOptions as $t)
                        <option value="{{ $t }}">{{ ucfirst($t) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <select wire:model="planSupportLevel" class="w-full rounded-lg border border-[rgb(var(--nx-line))] px-3 py-2 text-sm">
                    <option value="standard">Standard support</option>
                    <option value="priority">Priority support</option>
                </select>
            </div>
            <div class="sm:col-span-2">
                <textarea wire:model="planFeaturesText" rows="3" placeholder="One feature per line" class="w-full rounded-lg border border-[rgb(var(--nx-line))] px-3 py-2 text-sm"></textarea>
            </div>
            <div class="sm:col-span-2">
                <input type="file" wire:model="planCoverUpload" accept="image/webp,image/jpeg,image/png" class="block w-full text-xs text-[rgb(var(--nx-text-2))]" />
                @error('planCoverUpload') <span class="text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</span> @enderror
                <div wire:loading wire:target="planCoverUpload" class="mt-1 text-xs text-[rgb(var(--nx-text-2))]">Uploading…</div>
            </div>
            <label class="flex items-center gap-2 text-sm text-[rgb(var(--nx-text-2))]">
                <input type="checkbox" wire:model="planIsActive" class="h-4 w-4 rounded border-[rgb(var(--nx-line-strong))] text-[rgb(var(--nx-teal-ink))] focus:ring-[rgb(var(--nx-teal))]"> Active
            </label>
            <div class="flex justify-end">
                <button type="submit" wire:loading.attr="disabled" wire:target="savePlan"
                    class="rounded-lg bg-[rgb(var(--nx-cta-b))] px-4 py-2 text-sm font-semibold text-white hover:bg-[rgb(var(--nx-cta-a))] disabled:opacity-60">
                    {{ $editingPlanId ? 'Update plan' : 'Create plan' }}
                </button>
            </div>
        </form>

        {{-- Plan list --}}
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="text-xs uppercase text-[rgb(var(--nx-text-2))]">
                    <tr>
                        <th class="py-2 pr-4">Cover</th>
                        <th class="py-2 pr-4">Plan</th>
                        <th class="py-2 pr-4">Price</th>
                        <th class="py-2 pr-4">Tier</th>
                        <th class="py-2 pr-4">Support</th>
                        <th class="py-2 pr-4">Active</th>
                        <th class="py-2 pr-4"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[rgb(var(--nx-line))]">
                    @foreach ($this->licensePlans as $plan)
                        <tr class="text-[rgb(var(--nx-text))]">
                            <td class="py-2 pr-4">
                                @if ($plan->cover_image_url)
                                    <img src="{{ $plan->cover_image_url }}" alt="" class="h-10 w-16 rounded-md object-cover">
                                @else
                                    <div class="flex h-10 w-16 items-center justify-center rounded-md bg-[rgb(var(--nx-surface-3))] text-[rgb(var(--nx-text-3))]"><x-icon name="image" class="h-4 w-4" /></div>
                                @endif
                            </td>
                            <td class="py-2 pr-4 font-medium">{{ $plan->name }}<span class="block text-xs font-normal text-[rgb(var(--nx-text-2))]">{{ $plan->tagline }}</span></td>
                            <td class="py-2 pr-4">${{ number_format((float) $plan->price_usd, 2) }}</td>
                            <td class="py-2 pr-4">{{ ucfirst($plan->tier) }}</td>
                            <td class="py-2 pr-4">{{ ucfirst($plan->support_level) }}</td>
                            <td class="py-2 pr-4">
                                <button type="button" wire:click="togglePlanActive({{ $plan->id }})" class="rounded-full px-2 py-0.5 text-xs font-medium {{ $plan->is_active ? 'bg-[rgb(var(--nx-ok)/0.16)] text-[rgb(var(--nx-ok))]' : 'bg-[rgb(var(--nx-surface-3))] text-[rgb(var(--nx-text-2))]' }}">
                                    {{ $plan->is_active ? 'Active' : 'Inactive' }}
                                </button>
                            </td>
                            <td class="py-2 pr-4">
                                <button type="button" wire:click="editPlan({{ $plan->id }})" class="rounded-lg border border-[rgb(var(--nx-line))] px-2 py-1 text-xs font-medium text-[rgb(var(--nx-text-2))] hover:bg-[rgb(var(--nx-surface-2))]">Edit</button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    {{-- Owner request (2026-09-15) — merchant guide reference links. Shown
         inside the in-app Merchant White Label Guide, contextually by the
         hosting choice a merchant picks. Editing the URL here is the ONLY
         step needed to point it at an affiliate link instead. --}}
    <div class="mb-6 rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-5">
        <h2 class="mb-1 text-lg font-semibold text-[rgb(var(--nx-text))]">Guide links</h2>
        <p class="mb-4 text-sm text-[rgb(var(--nx-text-2))]">Reference links shown to merchants inside their in-app onboarding guide, grouped by category — shown only when relevant to the hosting option they've chosen. Change a URL here (e.g. to your own affiliate link) any time; it takes effect immediately, no redeploy.</p>

        <form wire:submit="saveGuideLink" class="mb-5 grid gap-3 rounded-xl border border-[rgb(var(--nx-line))] p-4 sm:grid-cols-2">
            <div class="sm:col-span-2 flex items-center justify-between">
                <p class="text-sm font-semibold text-[rgb(var(--nx-text))]">{{ $editingGuideLinkId ? 'Edit link' : 'New link' }}</p>
                @if ($editingGuideLinkId)
                    <button type="button" wire:click="newGuideLinkForm" class="text-xs font-medium text-[rgb(var(--nx-text-2))] hover:text-[rgb(var(--nx-text-2))]">Cancel edit</button>
                @endif
            </div>
            <div>
                <select wire:model="guideLinkCategory" class="w-full rounded-lg border border-[rgb(var(--nx-line))] px-3 py-2 text-sm">
                    @foreach ($this->guideLinkCategories as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <input type="number" min="0" wire:model="guideLinkSortOrder" placeholder="Sort order" class="w-full rounded-lg border border-[rgb(var(--nx-line))] px-3 py-2 text-sm" />
            </div>
            <div class="sm:col-span-2">
                <input type="text" wire:model="guideLinkLabel" placeholder="Label, e.g. Namecheap — register your domain" class="w-full rounded-lg border border-[rgb(var(--nx-line))] px-3 py-2 text-sm" />
                @error('guideLinkLabel') <span class="text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</span> @enderror
            </div>
            <div class="sm:col-span-2">
                <input type="url" wire:model="guideLinkUrl" placeholder="https://... (swap in your affiliate link here)" class="w-full rounded-lg border border-[rgb(var(--nx-line))] px-3 py-2 text-sm" />
                @error('guideLinkUrl') <span class="text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</span> @enderror
            </div>
            <div class="sm:col-span-2">
                <input type="text" wire:model="guideLinkDescription" placeholder="Short description shown under the link (optional)" class="w-full rounded-lg border border-[rgb(var(--nx-line))] px-3 py-2 text-sm" />
                @error('guideLinkDescription') <span class="text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</span> @enderror
            </div>
            <label class="flex items-center gap-2 text-sm text-[rgb(var(--nx-text-2))]">
                <input type="checkbox" wire:model="guideLinkIsActive" class="h-4 w-4 rounded border-[rgb(var(--nx-line-strong))] text-[rgb(var(--nx-teal-ink))] focus:ring-[rgb(var(--nx-teal))]"> Visible in the guide
            </label>
            <div class="flex justify-end">
                <button type="submit" wire:loading.attr="disabled" wire:target="saveGuideLink"
                    class="rounded-lg bg-[rgb(var(--nx-cta-b))] px-4 py-2 text-sm font-semibold text-white hover:bg-[rgb(var(--nx-cta-a))] disabled:opacity-60">
                    {{ $editingGuideLinkId ? 'Update link' : 'Add link' }}
                </button>
            </div>
        </form>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="text-xs uppercase text-[rgb(var(--nx-text-2))]">
                    <tr>
                        <th class="py-2 pr-4">Category</th>
                        <th class="py-2 pr-4">Label</th>
                        <th class="py-2 pr-4">URL</th>
                        <th class="py-2 pr-4">Visible</th>
                        <th class="py-2 pr-4"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[rgb(var(--nx-line))]">
                    @forelse ($this->guideLinks as $link)
                        <tr class="text-[rgb(var(--nx-text))]">
                            <td class="py-2 pr-4">{{ $this->guideLinkCategories[$link->category] ?? $link->category }}</td>
                            <td class="py-2 pr-4 font-medium">{{ $link->label }}{{ $link->description ? ' ' : '' }}<span class="block text-xs font-normal text-[rgb(var(--nx-text-2))]">{{ $link->description }}</span></td>
                            <td class="max-w-xs truncate py-2 pr-4 text-xs text-[rgb(var(--nx-text-2))]"><a href="{{ $link->url }}" target="_blank" rel="noopener" class="hover:underline">{{ $link->url }}</a></td>
                            <td class="py-2 pr-4">
                                <button type="button" wire:click="toggleGuideLinkActive({{ $link->id }})" class="rounded-full px-2 py-0.5 text-xs font-medium {{ $link->is_active ? 'bg-[rgb(var(--nx-ok)/0.16)] text-[rgb(var(--nx-ok))]' : 'bg-[rgb(var(--nx-surface-3))] text-[rgb(var(--nx-text-2))]' }}">
                                    {{ $link->is_active ? 'Visible' : 'Hidden' }}
                                </button>
                            </td>
                            <td class="py-2 pr-4 whitespace-nowrap">
                                <button type="button" wire:click="editGuideLink({{ $link->id }})" class="rounded-lg border border-[rgb(var(--nx-line))] px-2 py-1 text-xs font-medium text-[rgb(var(--nx-text-2))] hover:bg-[rgb(var(--nx-surface-2))]">Edit</button>
                                <button type="button" wire:click="deleteGuideLink({{ $link->id }})" wire:confirm="Remove this link from the guide?" class="ml-1 rounded-lg border border-[rgb(var(--nx-bad))] px-2 py-1 text-xs font-medium text-[rgb(var(--nx-bad))] hover:bg-[rgb(var(--nx-bad)/0.1)]">Delete</button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="py-4 text-center text-sm text-[rgb(var(--nx-text-2))]">No guide links yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Prompt 21-EXT §5.3/§5.5 — platform earnings wallet + withdrawal.
         super_admin only: this bucket is deliberately kept separate from
         general platform-profit reporting, so cashing it out is a stricter
         action than the day-to-day oversight this whole screen otherwise
         allows for "admin" too. --}}
    </div>
    <div x-show="tab === 'money'" @if ('money' !== 'licences') x-cloak @endif>
    @if ($this->isSuperAdmin)
        <div class="mb-6 rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-5">
            <h2 class="mb-1 text-lg font-semibold text-[rgb(var(--nx-text))]">Platform earnings</h2>
            <p class="mb-3 text-xs text-[rgb(var(--nx-text-2))]">Core-business margin (net of staff, partner, merchant and referral shares) plus 100% of White-Label license revenue. Withdraw to your own verified payout account, same as any other cash-out.</p>

            <div class="mb-4 rounded-xl border border-[rgb(var(--nx-line))] p-3">
                <p class="text-xs text-[rgb(var(--nx-text-2))]">Available balance (live)</p>
                <p class="text-2xl font-bold text-[rgb(var(--nx-text))]">${{ number_format($this->platformBalance, 2) }}</p>

                @php($b = $this->platformBalanceBreakdown)
                <div x-data="{ open: false }">
                    <button type="button" x-on:click="open = ! open" class="mt-2 text-xs font-medium text-[rgb(var(--nx-teal-ink))] hover:underline" x-text="open ? 'Hide breakdown' : 'Show breakdown'"></button>
                    <div x-show="open" x-cloak class="mt-2 space-y-1 text-xs text-[rgb(var(--nx-text-2))]">
                        <div class="flex justify-between"><span>Raw core margin (eSIM + Numbers, all-time)</span><span>${{ number_format($b['raw_core_margin'], 2) }}</span></div>
                        <div class="flex justify-between"><span>&minus; Staff share outstanding</span><span>${{ number_format($b['staff_outstanding'], 2) }}</span></div>
                        <div class="flex justify-between"><span>&minus; Partner share outstanding</span><span>${{ number_format($b['partner_outstanding'], 2) }}</span></div>
                        <div class="flex justify-between"><span>&minus; Merchant share outstanding</span><span>${{ number_format($b['merchant_outstanding'], 2) }}</span></div>
                        <div class="flex justify-between"><span>&minus; Referral share outstanding</span><span>${{ number_format($b['referral_outstanding'], 2) }}</span></div>
                        <div class="flex justify-between"><span>&minus; Already withdrawn from core margin</span><span>${{ number_format($b['already_withdrawn_core'], 2) }}</span></div>
                        <div class="flex justify-between font-medium text-[rgb(var(--nx-text))]"><span>= Core margin pool</span><span>${{ number_format($b['core_margin_pool'], 2) }}</span></div>
                        <div class="flex justify-between"><span>+ White-Label revenue (100%)</span><span>${{ number_format($b['white_label_balance'], 2) }}</span></div>
                        <div class="flex justify-between border-t border-[rgb(var(--nx-line))] pt-1 font-semibold text-[rgb(var(--nx-text))]"><span>= Total withdrawable</span><span>${{ number_format($b['total_withdrawable'], 2) }}</span></div>
                    </div>
                </div>
            </div>

            @if ($platformWithdrawError)
                <div class="mb-3 rounded-lg border border-[rgb(var(--nx-bad))] bg-[rgb(var(--nx-bad)/0.1)] p-2 text-xs text-[rgb(var(--nx-bad))]">{{ $platformWithdrawError }}</div>
            @endif

            @if ($this->platformAccounts->isEmpty())
                <p class="text-xs text-[rgb(var(--nx-text-2))]">Add a verified payout account to your own profile before withdrawing.</p>
            @else
                <form wire:submit="withdrawPlatformEarnings" class="grid gap-3 sm:grid-cols-3">
                    <select wire:model="platformAccountId" class="w-full rounded-lg border border-[rgb(var(--nx-line))] px-3 py-2 text-sm">
                        @foreach ($this->platformAccounts as $account)
                            <option value="{{ $account->id }}">{{ $account->bank_name ?? $account->bank_code }} · {{ $account->account_name }}</option>
                        @endforeach
                    </select>
                    <input type="number" step="0.01" min="0.01" wire:model="platformAmountUsd" placeholder="Amount (USD)" class="w-full rounded-lg border border-[rgb(var(--nx-line))] px-3 py-2 text-sm" />
                    @error('platformAmountUsd') <span class="text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</span> @enderror
                    <button type="submit" wire:loading.attr="disabled" wire:target="withdrawPlatformEarnings"
                        class="rounded-lg bg-[rgb(var(--nx-cta-b))] px-4 py-2 text-sm font-semibold text-white hover:bg-[rgb(var(--nx-cta-a))] disabled:opacity-60">
                        Withdraw
                    </button>
                </form>
            @endif
        </div>
    @endif

    </div>
    <div x-show="tab === 'plans'" @if ('plans' !== 'licences') x-cloak @endif>
    {{-- Skin allowance by licence tier (Prompt 22) --}}
    <div class="mb-6 rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-5">
        <h2 class="mb-1 text-lg font-semibold text-[rgb(var(--nx-text))]">Skin allowance by licence tier</h2>
        <p class="mb-3 text-xs text-[rgb(var(--nx-text-2))]">How many dashboard skins a white-label licence unlocks once it is activated. The default skin counts toward the number (2 = default plus one switch skin). Forks never decide this: they receive the number from here on their next check-in. A per-instance override above beats these defaults.</p>
        <div class="flex flex-wrap items-end gap-6">
            @foreach (\App\Models\WhiteLabelInstance::TIERS as $tier)
                <label class="text-sm font-medium text-[rgb(var(--nx-text))]">
                    {{ ucfirst($tier) }}{{ $tier === 'extended' ? ' (and Extended V2)' : '' }}
                    <span class="mt-1 flex items-center gap-2">
                        <input type="number" min="1" max="{{ $this->skinCatalogueSize }}" value="{{ $this->skinTierDefaults[$tier] ?? 1 }}"
                            wire:change="saveSkinTierDefault('{{ $tier }}', $event.target.value)"
                            class="w-20 rounded-lg border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] px-2 py-1.5 text-sm">
                        <span class="text-xs font-normal text-[rgb(var(--nx-text-2))]">of {{ $this->skinCatalogueSize }}</span>
                    </span>
                </label>
            @endforeach
        </div>
    </div>

    {{-- Feature locks by level (Batch 8) --}}
    <div class="mb-6 rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-5">
        <h2 class="mb-1 text-lg font-semibold text-[rgb(var(--nx-text))]">Feature locks by level</h2>
        <p class="mb-3 text-xs text-[rgb(var(--nx-text-2))]">Which features each entitlement level locks for white-label forks. A ticked box means the feature is <strong>locked</strong> at that level. Basic is a Normal fork before it pays up; Standard after; Full is Extended (everything open). Forks pick up changes on their next check-in.</p>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="text-xs uppercase text-[rgb(var(--nx-text-2))]">
                    <tr>
                        <th class="py-2 pr-4">Feature</th>
                        @foreach ($this->featureLevels as $lvl)
                            <th class="py-2 pr-4 text-center">{{ ucfirst($lvl) }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-[rgb(var(--nx-line))]">
                    @foreach ($this->featureCatalog as $key => $label)
                        <tr class="text-[rgb(var(--nx-text))]">
                            <td class="py-2 pr-4 font-medium">{{ $label }}</td>
                            @foreach ($this->featureLevels as $lvl)
                                <td class="py-2 pr-4 text-center">
                                    <input type="checkbox" wire:click="toggleFeatureLock('{{ $lvl }}', '{{ $key }}')"
                                        @checked(in_array($key, $this->featureLocks[$lvl] ?? [], true))
                                        class="h-4 w-4 rounded border-[rgb(var(--nx-line-strong))] text-[rgb(var(--nx-teal-ink))] focus:ring-[rgb(var(--nx-teal))]">
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    </div>
    <div x-show="tab === 'money'" @if ('money' !== 'licences') x-cloak @endif>
    {{-- Published packages --}}
    <div class="rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-5">
        <h2 class="mb-3 text-lg font-semibold text-[rgb(var(--nx-text))]">Distributable packages</h2>
        <p class="mb-3 text-xs text-[rgb(var(--nx-text-2))]">Building a package and publishing it to instances are two deliberate steps. Only published packages are ever offered to white-label instances.</p>

        @if ($this->packages->isEmpty())
            <p class="text-sm text-[rgb(var(--nx-text-2))]">No packages registered for distribution yet. Build one with <code class="rounded bg-[rgb(var(--nx-surface-3))] px-1">php artisan update:package --publish</code>.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="text-xs uppercase text-[rgb(var(--nx-text-2))]">
                        <tr>
                            <th class="py-2 pr-4">Version</th>
                            <th class="py-2 pr-4">Product</th>
                            <th class="py-2 pr-4">Type</th>
                            <th class="py-2 pr-4">Tier</th>
                            <th class="py-2 pr-4">Published</th>
                            <th class="py-2 pr-4"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[rgb(var(--nx-line))]">
                        @foreach ($this->packages as $p)
                            <tr class="text-[rgb(var(--nx-text))]">
                                <td class="py-2 pr-4 font-medium">{{ $p->version }}</td>
                                <td class="py-2 pr-4">{{ $p->product }}</td>
                                <td class="py-2 pr-4">{{ $p->package_type }}</td>
                                <td class="py-2 pr-4">{{ $p->tier_requirement ?? '—' }}</td>
                                <td class="py-2 pr-4">
                                    @if ($p->isMasterOnly())
                                        <span class="inline-flex items-center gap-1 rounded-full bg-black/80 px-2 py-0.5 text-xs font-medium text-white">
                                            <x-icon name="lock" class="h-3 w-3" /> Master-only
                                        </span>
                                    @elseif ($p->is_published)
                                        <span class="rounded-full bg-[rgb(var(--nx-ok)/0.16)] px-2 py-0.5 text-xs font-medium text-[rgb(var(--nx-ok))]">Published</span>
                                    @else
                                        <span class="rounded-full bg-[rgb(var(--nx-surface-3))] px-2 py-0.5 text-xs font-medium text-[rgb(var(--nx-text-2))]">Staged</span>
                                    @endif
                                </td>
                                <td class="py-2 pr-4">
                                    <div class="flex gap-2">
                                        @if ($p->isMasterOnly())
                                            {{-- Master-Only Distribution Lock: this package can NEVER be
                                                 published to white label — enforced server-side in
                                                 PackagePublisher, not just hidden here. No Publish
                                                 button at all, so an admin never wonders why nothing
                                                 happened. --}}
                                            <span class="flex items-center gap-1 rounded-lg border border-[rgb(var(--nx-line))] px-2.5 py-1 text-xs text-[rgb(var(--nx-text-2))]" title="Master-only packages can never be distributed to white label.">
                                                <x-icon name="lock" class="h-3 w-3" /> Cannot be distributed
                                            </span>
                                        @else
                                            <button type="button" wire:click="togglePublish({{ $p->id }})"
                                                class="rounded-lg border border-[rgb(var(--nx-line))] px-2.5 py-1 text-xs font-medium text-[rgb(var(--nx-text-2))] hover:bg-[rgb(var(--nx-surface-2))]">
                                                {{ $p->is_published ? 'Unpublish' : 'Publish' }}
                                            </button>
                                        @endif
                                        <button type="button" wire:click="withdrawPackage({{ $p->id }})" wire:confirm="Withdraw {{ $p->version }} from distribution and delete its file?"
                                            class="rounded-lg border border-[rgb(var(--nx-bad))] px-2.5 py-1 text-xs font-medium text-[rgb(var(--nx-bad))] hover:bg-[rgb(var(--nx-bad)/0.1)]">
                                            Withdraw
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
    </div>
</div>
