{{-- nx:converted (skin tokens only; see docs/appearance/SKIN-CONTRACT.md) --}}
<div class="mx-auto max-w-4xl">
    <h1 class="mb-1 text-2xl font-bold text-[rgb(var(--nx-text))]">Staff &amp; roles</h1>
    <p class="mb-6 text-sm text-[rgb(var(--nx-text-2))]">
        Create staff members and grant granular scopes. Staff can act only within the scopes you give them, and can never delete a user account.
    </p>

    @if ($saved)
        <div class="mb-6 flex items-center gap-2 rounded-lg bg-[rgb(var(--nx-ok)/0.12)] p-3 text-sm text-[rgb(var(--nx-ok))]">
            <x-icon name="badge-check" class="h-4 w-4" /> {{ $saved }}
        </div>
    @endif

    @if ($compError)
        <div class="mb-6 flex items-start gap-2 rounded-lg bg-[rgb(var(--nx-bad)/0.1)] p-3 text-sm text-[rgb(var(--nx-bad))]">
            <x-icon name="shield" class="mt-0.5 h-4 w-4 shrink-0" /> {{ $compError }}
        </div>
    @endif

    {{-- Combined committed profit-share (partners + staff) — over-commitment safeguard (§2.2). --}}
    <div @class([
        'mb-6 flex items-center justify-between gap-3 rounded-lg border p-3 text-sm',
        'border-[rgb(var(--nx-warn))] bg-[rgb(var(--nx-warn)/0.12)] text-[rgb(var(--nx-warn))]' => $combinedPct > 100,
        'border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface-2))] text-[rgb(var(--nx-text-2))]' => $combinedPct <= 100,
    ])>
        <span>Committed profit-share across all active partners + staff</span>
        <span class="font-bold tabular-nums">{{ number_format($combinedPct, 2) }}% @if ($combinedPct > 100)<span class="ml-1">— over 100%!</span>@endif</span>
    </div>

    {{-- Promote an existing active user (blueprint Section 27) --}}
    <form wire:submit="promote" class="mb-6 space-y-4 rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-6">
        <h2 class="flex items-center gap-2 text-sm font-semibold text-[rgb(var(--nx-text))]">
            <x-icon name="badge-check" class="h-4 w-4 text-[rgb(var(--nx-teal-ink))]" /> Make an existing user staff
        </h2>
        <p class="text-xs text-[rgb(var(--nx-text-2))]">Pick any active customer by email. They keep their account and end-user access, and simply gain the scopes you choose.</p>

        @if ($promoteError)
            <div class="rounded-lg bg-[rgb(var(--nx-bad)/0.1)] p-2 text-xs text-[rgb(var(--nx-bad))]">{{ $promoteError }}</div>
        @endif

        <div>
            <label class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">User email</label>
            <input wire:model="promoteEmail" type="email" placeholder="customer@example.com" class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm">
            @error('promoteEmail') <span class="text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</span> @enderror
        </div>
        <div>
            <label class="mb-2 block text-xs font-medium text-[rgb(var(--nx-text-2))]">Scopes</label>
            <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                @foreach ($grantable as $scope)
                    <label class="flex items-center gap-2 rounded-lg border border-[rgb(var(--nx-line))] px-3 py-2 text-sm text-[rgb(var(--nx-text))]">
                        <input type="checkbox" wire:model="promoteScopes" value="{{ $scope }}" class="rounded text-[rgb(var(--nx-teal-ink))]">
                        <span>{{ $labels[$scope] ?? $scope }} <span class="text-xs text-[rgb(var(--nx-text-2))]">({{ $scope }})</span></span>
                    </label>
                @endforeach
            </div>
        </div>
        <div class="flex justify-end">
            <button type="submit" wire:loading.attr="disabled" wire:target="promote"
                    class="inline-flex items-center gap-2 rounded-lg bg-[rgb(var(--nx-cta-b))] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[rgb(var(--nx-cta-a))] disabled:opacity-60">
                <span wire:loading.remove wire:target="promote">Make staff</span>
                <span wire:loading wire:target="promote" class="inline-flex items-center gap-2"><x-ui.spinner class="h-4 w-4" /> Promoting…</span>
            </button>
        </div>
    </form>

    {{-- Create a brand-new staff account --}}
    <form wire:submit="createStaff" class="mb-8 space-y-4 rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-6">
        <h2 class="flex items-center gap-2 text-sm font-semibold text-[rgb(var(--nx-text))]">
            <x-icon name="id-card" class="h-4 w-4 text-[rgb(var(--nx-teal-ink))]" /> Or create a brand-new staff account
        </h2>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <div>
                <label class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">Name</label>
                <input wire:model="name" class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm">
                @error('name') <span class="text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</span> @enderror
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">Email</label>
                <input wire:model="email" type="email" class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm">
                @error('email') <span class="text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</span> @enderror
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">Temporary password</label>
                <input wire:model="password" type="text" class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm">
                @error('password') <span class="text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</span> @enderror
            </div>
        </div>

        <div>
            <label class="mb-2 block text-xs font-medium text-[rgb(var(--nx-text-2))]">Scopes</label>
            <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                @foreach ($grantable as $scope)
                    <label class="flex items-center gap-2 rounded-lg border border-[rgb(var(--nx-line))] px-3 py-2 text-sm text-[rgb(var(--nx-text))]">
                        <input type="checkbox" wire:model="scopes" value="{{ $scope }}" class="rounded text-[rgb(var(--nx-teal-ink))]">
                        <span>{{ $labels[$scope] ?? $scope }} <span class="text-xs text-[rgb(var(--nx-text-2))]">({{ $scope }})</span></span>
                    </label>
                @endforeach
            </div>
        </div>

        <div class="flex justify-end">
            <button type="submit" wire:loading.attr="disabled" wire:target="createStaff"
                    class="inline-flex items-center gap-2 rounded-lg bg-[rgb(var(--nx-cta-b))] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[rgb(var(--nx-cta-a))] disabled:opacity-60">
                <span wire:loading.remove wire:target="createStaff">Create staff member</span>
                <span wire:loading wire:target="createStaff" class="inline-flex items-center gap-2"><x-ui.spinner class="h-4 w-4" /> Creating…</span>
            </button>
        </div>
    </form>

    {{-- Existing staff --}}
    <div class="overflow-hidden rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))]">
        @forelse ($staff as $member)
            <div wire:key="staff-{{ $member->id }}" class="border-b border-[rgb(var(--nx-line))] px-5 py-4 last:border-0">
                <div class="flex items-center justify-between gap-4">
                    <div class="min-w-0">
                        <p class="truncate text-sm font-medium text-[rgb(var(--nx-text))]">
                            {{ $member->name }} <span class="text-[rgb(var(--nx-text-2))]">·</span> <span class="text-[rgb(var(--nx-text-2))]">{{ $member->email }}</span>
                            @if ($member->isDeactivated())
                                <span class="ml-1 rounded-full bg-[rgb(var(--nx-surface-3))] px-2 py-0.5 text-[10px] font-semibold text-[rgb(var(--nx-text-2))]">Deactivated</span>
                            @endif
                        </p>
                    </div>
                    <div class="flex shrink-0 flex-wrap items-center gap-2">
                        <button type="button" wire:click="editStaff({{ $member->id }})" class="rounded-lg border border-[rgb(var(--nx-line-strong))] px-3 py-1.5 text-xs font-medium text-[rgb(var(--nx-text-2))] hover:bg-[rgb(var(--nx-surface-2))]">Edit</button>
                        <button type="button" wire:click="toggleStaffActive({{ $member->id }})" class="rounded-lg border border-[rgb(var(--nx-line-strong))] px-3 py-1.5 text-xs font-medium text-[rgb(var(--nx-text-2))] hover:bg-[rgb(var(--nx-surface-2))]">{{ $member->isDeactivated() ? 'Reactivate' : 'Deactivate' }}</button>
                        <button type="button" wire:click="revoke({{ $member->id }})" wire:confirm="Revoke staff access for {{ $member->email }}? Their account stays but loses all staff scopes." class="rounded-lg border border-[rgb(var(--nx-line-strong))] px-3 py-1.5 text-xs font-medium text-[rgb(var(--nx-text-2))] hover:bg-[rgb(var(--nx-surface-2))]">Revoke</button>
                        <button type="button" wire:click="deleteStaff({{ $member->id }})" wire:confirm="Permanently delete {{ $member->email }}? This cannot be undone." class="rounded-lg border border-[rgb(var(--nx-bad))] px-3 py-1.5 text-xs font-medium text-[rgb(var(--nx-bad))] hover:bg-[rgb(var(--nx-bad)/0.1)]">Delete</button>
                    </div>
                </div>

                @if ($editingStaffId === $member->id)
                    <div class="mt-3 grid gap-3 rounded-lg border border-[rgb(var(--nx-line))] p-3 sm:grid-cols-3">
                        <div><label class="mb-1 block text-[11px] text-[rgb(var(--nx-text-2))]">Name</label><input type="text" wire:model="edit_name" class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-2.5 py-1.5 text-sm">@error('edit_name')<p class="mt-1 text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p>@enderror</div>
                        <div><label class="mb-1 block text-[11px] text-[rgb(var(--nx-text-2))]">Email</label><input type="email" wire:model="edit_email" class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-2.5 py-1.5 text-sm">@error('edit_email')<p class="mt-1 text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p>@enderror</div>
                        <div><label class="mb-1 block text-[11px] text-[rgb(var(--nx-text-2))]">New password (optional)</label><input type="password" wire:model="edit_password" autocomplete="new-password" class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-2.5 py-1.5 text-sm">@error('edit_password')<p class="mt-1 text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p>@enderror</div>
                        <div class="flex items-center gap-2 sm:col-span-3">
                            <button wire:click="saveStaff" class="rounded-lg bg-[rgb(var(--nx-cta-b))] px-3 py-1.5 text-xs font-semibold text-white hover:bg-[rgb(var(--nx-cta-a))]">Save</button>
                            <button wire:click="cancelEditStaff" class="rounded-lg border border-[rgb(var(--nx-line))] px-3 py-1.5 text-xs text-[rgb(var(--nx-text-2))]">Cancel</button>
                        </div>
                    </div>
                @endif
                <div class="mt-3 flex flex-wrap gap-2">
                    @foreach ($grantable as $scope)
                        @php $has = $member->hasPermissionTo($scope); @endphp
                        <button type="button" wire:click="toggleScope({{ $member->id }}, '{{ $scope }}')"
                                @class([
                                    'inline-flex items-center gap-1 rounded-full px-3 py-1 text-xs font-medium transition-colors',
                                    'bg-[rgb(var(--nx-cta-b)/0.1)] text-[rgb(var(--nx-teal-ink))]' => $has,
                                    'bg-[rgb(var(--nx-surface-3))] text-[rgb(var(--nx-text-2))] hover:bg-[rgb(var(--nx-surface-3))]' => ! $has,
                                ])>
                            <x-icon :name="$has ? 'check' : 'x'" class="h-3 w-3" /> {{ $labels[$scope] ?? $scope }}
                        </button>
                    @endforeach
                </div>

                {{-- Compensation (profit-share, BUILD-23 §2) --}}
                @php($profile = $profiles[$member->id] ?? null)
                <div class="mt-3 rounded-lg border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface-2)/0.6)] p-3">
                    <div class="flex flex-wrap items-end gap-3">
                        <div>
                            <label class="mb-1 block text-[11px] font-medium text-[rgb(var(--nx-text-2))]">Profit-share %</label>
                            <input type="number" step="0.1" min="0" max="100" wire:model="comp.{{ $member->id }}"
                                   class="w-28 rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-2.5 py-1.5 text-sm">
                        </div>
                        <button type="button" wire:click="saveCompensation({{ $member->id }})" wire:target="saveCompensation" wire:loading.attr="disabled"
                                class="rounded-lg bg-[rgb(var(--nx-cta-b))] px-3 py-1.5 text-xs font-semibold text-white hover:bg-[rgb(var(--nx-cta-a))]">Save rate</button>
                        @if ($profile)
                            <button type="button" wire:click="toggleCompensationActive({{ $member->id }})"
                                    class="rounded-lg border border-[rgb(var(--nx-line-strong))] px-3 py-1.5 text-xs font-medium text-[rgb(var(--nx-text-2))]">
                                {{ $profile->is_active ? 'Pause' : 'Activate' }}
                            </button>
                            <span @class([
                                'rounded-full px-2.5 py-0.5 text-[10px] font-semibold',
                                'bg-[rgb(var(--nx-ok)/0.16)] text-[rgb(var(--nx-ok))]' => $profile->is_active,
                                'bg-[rgb(var(--nx-surface-3))] text-[rgb(var(--nx-text-2))]' => ! $profile->is_active,
                            ])>{{ $profile->is_active ? 'Active' : 'Paused' }}</span>
                        @endif
                        @if (($projected[$member->id] ?? 0) > 0)
                            <span class="text-[11px] text-[rgb(var(--nx-text-2))]">
                                Projected this month: <span class="font-semibold text-[rgb(var(--nx-text))]">${{ number_format($projected[$member->id], 2) }}</span>
                                <span class="text-[rgb(var(--nx-text-2))]">(estimate on profit so far — not yet finalized or payable)</span>
                            </span>
                        @endif
                    </div>
                </div>
            </div>
        @empty
            <div class="px-5 py-10 text-center text-sm text-[rgb(var(--nx-text-2))]">
                <x-icon name="id-card" class="mx-auto mb-2 h-6 w-6" /> No staff members yet.
            </div>
        @endforelse
    </div>
</div>
