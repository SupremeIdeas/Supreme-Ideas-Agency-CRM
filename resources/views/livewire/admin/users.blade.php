{{-- nx:converted (skin tokens only; see docs/appearance/SKIN-CONTRACT.md) --}}
<div>
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-2xl font-bold text-[rgb(var(--nx-text))]">Users</h1>
        <div class="flex items-center gap-2 text-xs text-[rgb(var(--nx-text-2))]">
            <span class="rounded-full bg-[rgb(var(--nx-surface-3))] px-2.5 py-1">{{ number_format($totals['all']) }} total</span>
            <span class="rounded-full bg-[rgb(var(--nx-ok)/0.12)] px-2.5 py-1 text-[rgb(var(--nx-ok))]">{{ number_format($totals['active']) }} active</span>
            <span class="rounded-full bg-[rgb(var(--nx-bad)/0.1)] px-2.5 py-1 text-[rgb(var(--nx-bad))]">{{ number_format($totals['deactivated']) }} paused</span>
        </div>
    </div>

    @if (class_exists(\App\Livewire\Admin\N2N\IdentityLookup::class) && \App\Support\N2N\N2NSettings::enabled())
        @livewire(\App\Livewire\Admin\N2N\IdentityLookup::class)
    @endif

    {{-- Search + filters --}}
    <div class="mb-4 flex flex-wrap items-center gap-2">
        <div class="relative flex-1 min-w-[12rem]">
            <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-[rgb(var(--nx-text-2))]" />
            <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search by name or email…"
                   class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] py-2 pl-9 pr-3 text-sm text-[rgb(var(--nx-text))]">
        </div>
        <select wire:model.live="filter" class="rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm text-[rgb(var(--nx-text))]">
            <option value="all">All users</option>
            <option value="active">Active</option>
            <option value="deactivated">Deactivated</option>
            <option value="staff">Staff & admins</option>
            <option value="merchants">Merchants</option>
            <option value="ready">Ready to promote</option>
        </select>
    </div>

    {{-- Skeleton while a search/filter round-trips (premium loading feel). --}}
    <div wire:loading.flex wire:target="search,filter" class="overflow-hidden rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))]">
        <x-ui.skeleton-rows :count="6" class="w-full" />
    </div>

    <div wire:loading.remove wire:target="search,filter" class="overflow-hidden rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))]">
        <table class="w-full text-left text-sm">
            <thead class="border-b border-[rgb(var(--nx-line))] text-xs uppercase tracking-wide text-[rgb(var(--nx-text-2))]">
                <tr>
                    <th class="px-4 py-3">User</th>
                    <th class="px-4 py-3">Joined</th>
                    <th class="px-4 py-3">Engagement</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-[rgb(var(--nx-line))]">
                @forelse ($users as $u)
                    <tr wire:key="user-{{ $u->id }}" class="hover:bg-[rgb(var(--nx-surface-2))]">
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-3">
                                <span class="flex h-9 w-9 items-center justify-center rounded-full bg-[rgb(var(--nx-cta-b)/0.1)] text-xs font-bold uppercase text-[rgb(var(--nx-teal-ink))]">{{ \Illuminate\Support\Str::of($u->name)->trim()->substr(0, 2) }}</span>
                                <div class="min-w-0">
                                    <p class="truncate font-semibold text-[rgb(var(--nx-text))]">{{ $u->name }}</p>
                                    <p class="truncate text-xs text-[rgb(var(--nx-text-2))]">{{ $u->email }}</p>
                                </div>
                                @foreach ($u->getRoleNames()->take(2) as $role)
                                    <span class="rounded bg-[rgb(var(--nx-surface-3))] px-1.5 py-0.5 text-[10px] font-semibold uppercase text-[rgb(var(--nx-text-2))]">{{ str_replace('_', ' ', $role) }}</span>
                                @endforeach
                            </div>
                        </td>
                        <td class="px-4 py-3 text-[rgb(var(--nx-text-2))]">{{ $u->created_at?->format('d M Y') }}</td>
                        <td class="px-4 py-3">
                            @php($sc = $scores[$u->id] ?? null)
                            @if ($sc)
                                <span title="{{ $sc['referrals'] }} referrals · {{ $sc['verified_referrals'] }} verified · ${{ number_format($sc['spend'], 0) }} spent{{ $sc['verified'] ? ' · ID verified' : '' }}"
                                      class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-semibold {{ $sc['score'] >= 40 ? 'bg-[rgb(var(--nx-cta-b)/0.1)] text-[rgb(var(--nx-teal-ink))]' : 'bg-[rgb(var(--nx-surface-3))] text-[rgb(var(--nx-text-2))]' }}">
                                    <x-icon name="zap" class="h-3 w-3" /> {{ $sc['score'] }}
                                </span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            @if ($u->isDeactivated())
                                <span class="inline-flex items-center gap-1 rounded-full bg-[rgb(var(--nx-bad)/0.1)] px-2 py-0.5 text-xs font-medium text-[rgb(var(--nx-bad))]"><x-icon name="pause" class="h-3 w-3" /> Paused</span>
                            @else
                                <span class="inline-flex items-center gap-1 rounded-full bg-[rgb(var(--nx-ok)/0.12)] px-2 py-0.5 text-xs font-medium text-[rgb(var(--nx-ok))]"><x-icon name="badge-check" class="h-3 w-3" /> Active</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-end gap-2">
                                {{-- One-click promote to Merchant V1/V2 (§4.2). --}}
                                <div x-data="{ open: false }" class="relative">
                                    <button type="button" @click="open = !open" @click.outside="open = false" :aria-expanded="open"
                                            class="inline-flex items-center gap-1 rounded-lg border border-[rgb(var(--nx-teal)/0.3)] bg-[rgb(var(--nx-cta-b)/0.05)] px-2.5 py-1.5 text-xs font-semibold text-[rgb(var(--nx-teal-ink))] hover:bg-[rgb(var(--nx-cta-b)/0.1)]">
                                        <x-icon name="zap" class="h-3.5 w-3.5" /> Promote
                                    </button>
                                    <div x-show="open" x-cloak class="mt-1 flex w-full flex-col overflow-hidden rounded-lg border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] text-left shadow-lg">
                                        <button wire:click="promote({{ $u->id }}, 'v1')" @click="open = false" wire:confirm="Promote {{ $u->name }} to Merchant V1 (free, no application)?"
                                                class="block w-full px-3 py-2 text-xs font-medium text-[rgb(var(--nx-text))] hover:bg-[rgb(var(--nx-surface-2))]">Merchant V1</button>
                                        <button wire:click="promote({{ $u->id }}, 'v2')" @click="open = false" wire:confirm="Promote {{ $u->name }} to Merchant V2 (client management)?"
                                                class="block w-full px-3 py-2 text-xs font-medium text-[rgb(var(--nx-text))] hover:bg-[rgb(var(--nx-surface-2))]">Merchant V2</button>
                                    </div>
                                </div>
                                <button type="button" wire:click="view({{ $u->id }})" class="rounded-lg border border-[rgb(var(--nx-line))] px-2.5 py-1.5 text-xs font-semibold text-[rgb(var(--nx-text-2))] hover:bg-[rgb(var(--nx-surface-2))]">
                                    {{ $viewing?->id === $u->id ? 'Hide' : 'View' }}
                                </button>
                                <button type="button" wire:click="toggleActive({{ $u->id }})"
                                        wire:confirm="{{ $u->isDeactivated() ? 'Reactivate' : 'Deactivate' }} {{ $u->name }}?"
                                        class="rounded-lg px-2.5 py-1.5 text-xs font-semibold {{ $u->isDeactivated() ? 'bg-[rgb(var(--nx-cta-b))] text-white hover:bg-[rgb(var(--nx-cta-a))]' : 'border border-[rgb(var(--nx-line))] text-[rgb(var(--nx-text-2))] hover:border-[rgb(var(--nx-bad))] hover:text-[rgb(var(--nx-bad))]' }}">
                                    {{ $u->isDeactivated() ? 'Reactivate' : 'Deactivate' }}
                                </button>
                            </div>
                        </td>
                    </tr>
                    @if ($viewing?->id === $u->id)
                        <tr wire:key="view-{{ $u->id }}" class="bg-[rgb(var(--nx-surface-2))]">
                            <td colspan="5" class="px-4 py-4">
                                <div class="grid gap-4 sm:grid-cols-4">
                                    <div><p class="text-xs text-[rgb(var(--nx-text-2))]">USD wallet</p><p class="font-semibold text-[rgb(var(--nx-text))]">${{ number_format((float) ($viewing->wallet->usd_balance ?? 0), 2) }}</p></div>
                                    {{-- Unified USD Wallet (Part B): ngn_balance no longer grows from new
                                         top-ups — a nonzero figure here is a pre-migration legacy balance. --}}
                                    <div><p class="text-xs text-[rgb(var(--nx-text-2))]">NGN wallet (legacy)</p><p class="font-semibold text-[rgb(var(--nx-text))]">₦{{ number_format((float) ($viewing->wallet->ngn_balance ?? 0), 0) }}</p></div>
                                    <div><p class="text-xs text-[rgb(var(--nx-text-2))]">eSIM orders</p><p class="font-semibold text-[rgb(var(--nx-text))]">{{ $viewing->esimOrders()->count() }}</p></div>
                                    <div><p class="text-xs text-[rgb(var(--nx-text-2))]">Number orders</p><p class="font-semibold text-[rgb(var(--nx-text))]">{{ $viewing->smsOrders()->count() }}</p></div>
                                    <div><p class="text-xs text-[rgb(var(--nx-text-2))]">Country</p><p class="font-semibold text-[rgb(var(--nx-text))]">{{ $viewing->country_code ?: '—' }}</p></div>
                                    <div><p class="text-xs text-[rgb(var(--nx-text-2))]">Display currency</p><p class="font-semibold text-[rgb(var(--nx-text))]">{{ $viewing->display_currency ?: 'USD' }}</p></div>
                                    <div><p class="text-xs text-[rgb(var(--nx-text-2))]">Verified</p><p class="font-semibold text-[rgb(var(--nx-text))]">{{ $viewing->email_verified_at ? 'Yes' : 'No' }}</p></div>
                                    <div><p class="text-xs text-[rgb(var(--nx-text-2))]">Roles</p><p class="font-semibold capitalize text-[rgb(var(--nx-text))]">{{ str_replace('_', ' ', $viewing->getRoleNames()->implode(', ')) ?: 'user' }}</p></div>
                                </div>

                                {{-- Management (owner request — fix_admin Part 4) --}}
                                @if ($tempPassword)
                                    <div class="mt-4 rounded-lg border border-[rgb(var(--nx-warn))] bg-[rgb(var(--nx-warn)/0.12)] p-3 text-sm">
                                        <p class="font-semibold text-[rgb(var(--nx-warn))]">Temporary password (shown once)</p>
                                        <p class="mt-1 font-mono text-[rgb(var(--nx-warn))]">{{ $tempPassword }}</p>
                                        <p class="mt-1 text-[11px] text-[rgb(var(--nx-warn))]">Relay it securely; the user is signed out everywhere and should change it after signing in.</p>
                                    </div>
                                @endif

                                @if ($editing)
                                    <div class="mt-4 grid gap-3 rounded-lg border border-[rgb(var(--nx-line))] p-3 sm:grid-cols-3">
                                        <div><label class="mb-1 block text-[11px] text-[rgb(var(--nx-text-2))]">Name</label><input type="text" wire:model="edit_name" class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-2.5 py-1.5 text-sm">@error('edit_name')<p class="mt-1 text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p>@enderror</div>
                                        <div><label class="mb-1 block text-[11px] text-[rgb(var(--nx-text-2))]">Email</label><input type="email" wire:model="edit_email" class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-2.5 py-1.5 text-sm">@error('edit_email')<p class="mt-1 text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p>@enderror</div>
                                        <div><label class="mb-1 block text-[11px] text-[rgb(var(--nx-text-2))]">Phone</label><input type="text" wire:model="edit_phone" class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-2.5 py-1.5 text-sm"></div>
                                        <div class="flex items-end gap-2 sm:col-span-3">
                                            <button wire:click="saveUser" class="rounded-lg bg-[rgb(var(--nx-cta-b))] px-3 py-1.5 text-xs font-semibold text-white hover:bg-[rgb(var(--nx-cta-a))]">Save changes</button>
                                            <button wire:click="cancelEdit" class="rounded-lg border border-[rgb(var(--nx-line))] px-3 py-1.5 text-xs text-[rgb(var(--nx-text-2))]">Cancel</button>
                                            <span class="text-[11px] text-[rgb(var(--nx-text-2))]">Changing email re-sends a verification link.</span>
                                        </div>
                                    </div>
                                @else
                                    <div class="mt-4 flex flex-wrap gap-2">
                                        <button wire:click="editUser({{ $viewing->id }})" class="rounded-lg border border-[rgb(var(--nx-line))] px-3 py-1.5 text-xs font-medium text-[rgb(var(--nx-text-2))] hover:bg-[rgb(var(--nx-surface-2))]">Edit profile</button>
                                        <button wire:click="sendPasswordReset({{ $viewing->id }})" class="rounded-lg border border-[rgb(var(--nx-line))] px-3 py-1.5 text-xs font-medium text-[rgb(var(--nx-text-2))] hover:bg-[rgb(var(--nx-surface-2))]">Email reset link</button>
                                        <button wire:click="generateTempPassword({{ $viewing->id }})" wire:confirm="Set a temporary password and sign this user out everywhere?" class="rounded-lg border border-[rgb(var(--nx-line))] px-3 py-1.5 text-xs font-medium text-[rgb(var(--nx-text-2))] hover:bg-[rgb(var(--nx-surface-2))]">Temp password</button>
                                        <button wire:click="forceLogout({{ $viewing->id }})" wire:confirm="Sign {{ $viewing->name }} out of all devices?" class="rounded-lg border border-[rgb(var(--nx-line))] px-3 py-1.5 text-xs font-medium text-[rgb(var(--nx-text-2))] hover:bg-[rgb(var(--nx-surface-2))]">Force logout</button>
                                        @role('super_admin')
                                            @if ($viewing->id !== auth()->id())
                                                <button wire:click="toggleAdmin({{ $viewing->id }})" wire:confirm="Change admin role for {{ $viewing->name }}?" class="rounded-lg border border-[rgb(var(--nx-teal)/0.4)] px-3 py-1.5 text-xs font-medium text-[rgb(var(--nx-teal-ink))] hover:bg-[rgb(var(--nx-cta-b)/0.1)]">{{ $viewing->hasRole('admin') ? 'Revoke admin' : 'Make admin' }}</button>
                                            @endif
                                        @endrole
                                    </div>
                                @endif

                                @if ($sessions->isNotEmpty())
                                    <div class="mt-4">
                                        <p class="mb-1 text-[11px] font-semibold uppercase tracking-wide text-[rgb(var(--nx-text-2))]">Recent sessions</p>
                                        <div class="space-y-1">
                                            @foreach ($sessions as $s)
                                                <div class="flex items-center gap-3 text-[11px] text-[rgb(var(--nx-text-2))]">
                                                    <span class="font-mono">{{ $s['ip'] ?: '—' }}</span>
                                                    <span class="truncate">{{ $s['agent'] ?: 'unknown device' }}</span>
                                                    <span class="ml-auto shrink-0">{{ $s['when'] }}</span>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @endif
                @empty
                    <tr><td colspan="5" class="px-4 py-8 text-center text-sm text-[rgb(var(--nx-text-2))]">No users match your search.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $users->links() }}</div>
</div>
