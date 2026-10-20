{{-- nx:converted (skin tokens; legacy palette classes removed 2026-10-05) --}}
<div class="mx-auto max-w-4xl">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-[rgb(var(--nx-text))]">Partners</h1>
        <p class="text-sm text-[rgb(var(--nx-text-2))]">Profit-sharing partners earn an admin-set share of platform-wide profit each period. The share % is confidential — partners only ever see dollar figures.</p>
    </div>

    @if ($saved)
        <div class="mb-4 rounded-lg border border-[rgb(var(--nx-ok))] bg-[rgb(var(--nx-ok)/0.12)] px-4 py-2.5 text-sm text-[rgb(var(--nx-ok))]">{{ $saved }}</div>
    @endif

    {{-- Global settings --}}
    <div class="mb-6 rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-5">
        <h2 class="mb-3 text-sm font-bold text-[rgb(var(--nx-text))]">Program settings</h2>
        <div class="flex flex-wrap items-end gap-4">
            <label class="flex items-center gap-2 text-sm text-[rgb(var(--nx-text))]">
                <input type="checkbox" wire:model="enabled" class="h-4 w-4 rounded border-[rgb(var(--nx-line-strong))] text-[rgb(var(--nx-teal-ink))] focus:ring-[rgb(var(--nx-teal))]"> Program enabled
            </label>
            <div>
                <label class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">Default cadence</label>
                <select wire:model="defaultCadence" class="rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm">
                    <option value="monthly">Monthly</option><option value="weekly">Weekly</option>
                </select>
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">Default payout mode</label>
                <select wire:model="defaultMode" class="rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm">
                    <option value="manual">Admin-approved</option><option value="auto">Automated</option>
                </select>
            </div>
            <button type="button" wire:click="saveSettings" class="rounded-lg bg-[rgb(var(--nx-cta-b))] px-4 py-2 text-sm font-semibold text-white hover:bg-[rgb(var(--nx-cta-a))]">Save</button>
        </div>
    </div>

    {{-- Add a partner --}}
    <div class="mb-6 rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-5">
        <h2 class="mb-1 text-sm font-bold text-[rgb(var(--nx-text))]">Add a partner</h2>
        <p class="mb-3 text-xs text-[rgb(var(--nx-text-2))]">Existing user? Just enter their email. New person? Add a name too and we'll create the account with a one-time password — they land on /partner at first login.</p>
        <div class="flex flex-wrap items-end gap-3">
            <div class="min-w-[16rem] flex-1">
                <label class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">Email</label>
                <input type="email" wire:model="newEmail" placeholder="person@example.com" class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm">
                @error('newEmail') <span class="text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</span> @enderror
            </div>
            <div class="min-w-[10rem] flex-1">
                <label class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">Name <span class="text-[rgb(var(--nx-text-2))]">(new account)</span></label>
                <input type="text" wire:model="newName" placeholder="Optional" class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm">
            </div>
            <div class="w-28">
                <label class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">Share %</label>
                <input type="number" step="0.1" min="0" max="100" wire:model="newShare" class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm">
                @error('newShare') <span class="text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</span> @enderror
            </div>
            <button type="button" wire:click="addPartner" class="rounded-lg bg-[rgb(var(--nx-cta-b))] px-4 py-2 text-sm font-semibold text-white hover:bg-[rgb(var(--nx-cta-a))]">Add partner</button>
        </div>
        @if ($newTempPassword)
            <div class="mt-3 flex flex-wrap items-center gap-2 rounded-lg border border-[rgb(var(--nx-teal)/0.3)] bg-[rgb(var(--nx-cta-b)/0.05)] p-3 text-sm" x-data="{ copied: false }">
                <span class="text-[rgb(var(--nx-text-2))]">New account created. Temporary password:</span>
                <code class="rounded bg-[rgb(var(--nx-surface))] px-2 py-1 font-mono font-semibold text-[rgb(var(--nx-teal-ink))]">{{ $newTempPassword }}</code>
                <button type="button" @click="navigator.clipboard.writeText('{{ $newTempPassword }}'); copied = true; setTimeout(() => copied = false, 1500)"
                        class="inline-flex items-center gap-1 rounded-lg bg-[rgb(var(--nx-cta-b))] px-2.5 py-1 text-xs font-semibold text-white"><x-icon name="copy" class="h-3.5 w-3.5" /> <span x-text="copied ? 'Copied' : 'Copy'"></span></button>
            </div>
        @endif
    </div>

    {{-- Partner list --}}
    <div class="overflow-hidden rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))]">
        <table class="w-full text-sm">
            <thead class="bg-[rgb(var(--nx-surface-2))] text-left text-xs uppercase tracking-wide text-[rgb(var(--nx-text-2))]">
                <tr><th class="px-4 py-3">Partner</th><th class="px-4 py-3">Share</th><th class="px-4 py-3">Cadence</th><th class="px-4 py-3">Mode</th><th class="px-4 py-3">Balance</th><th class="px-4 py-3">Status</th><th class="px-4 py-3"></th></tr>
            </thead>
            <tbody class="divide-y divide-[rgb(var(--nx-line))]">
                @forelse ($partners as $p)
                    <tr wire:key="p-{{ $p->id }}">
                        <td class="px-4 py-3">
                            <p class="font-semibold text-[rgb(var(--nx-text))]">{{ $p->owner?->name }}</p>
                            <p class="text-xs text-[rgb(var(--nx-text-2))]">{{ $p->owner?->email }}</p>
                        </td>
                        @if ($editingId === $p->id)
                            <td class="px-4 py-3"><input type="number" step="0.1" min="0" max="100" wire:model="editShare" class="w-20 rounded border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-2 py-1 text-sm"></td>
                            <td class="px-4 py-3"><select wire:model="editCadence" class="rounded border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-2 py-1 text-sm"><option value="monthly">Monthly</option><option value="weekly">Weekly</option></select></td>
                            <td class="px-4 py-3"><select wire:model="editMode" class="rounded border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-2 py-1 text-sm"><option value="manual">Approve</option><option value="auto">Auto</option></select></td>
                            <td class="px-4 py-3 font-semibold text-[rgb(var(--nx-text))]">${{ number_format($p->admin_balance, 2) }}</td>
                            <td class="px-4 py-3" colspan="2">
                                <button type="button" wire:click="saveTerms" class="rounded bg-[rgb(var(--nx-cta-b))] px-3 py-1 text-xs font-semibold text-white">Save</button>
                                <button type="button" wire:click="$set('editingId', null)" class="ml-1 text-xs text-[rgb(var(--nx-text-2))]">Cancel</button>
                            </td>
                        @else
                            <td class="px-4 py-3 font-mono text-[rgb(var(--nx-text-2))]">{{ number_format((float) $p->profit_share_pct, 2) }}%</td>
                            <td class="px-4 py-3 text-[rgb(var(--nx-text-2))]">{{ ucfirst($p->payout_cadence) }}</td>
                            <td class="px-4 py-3 text-[rgb(var(--nx-text-2))]">{{ $p->payout_mode === 'auto' ? 'Automated' : 'Approve' }}</td>
                            <td class="px-4 py-3 font-semibold text-[rgb(var(--nx-text))]">${{ number_format($p->admin_balance, 2) }}</td>
                            <td class="px-4 py-3">
                                <span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ ['active' => 'bg-[rgb(var(--nx-ok)/0.16)] text-[rgb(var(--nx-ok))]', 'suspended' => 'bg-[rgb(var(--nx-warn)/0.16)] text-[rgb(var(--nx-warn))]', 'pending' => 'bg-[rgb(var(--nx-surface-3))] text-[rgb(var(--nx-text-2))]'][$p->status] ?? '' }}">{{ ucfirst($p->status) }}</span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <button type="button" wire:click="edit({{ $p->id }})" class="text-xs font-semibold text-[rgb(var(--nx-teal-ink))] hover:underline">Edit</button>
                                @if ($p->status === 'active')
                                    <button type="button" wire:click="suspend({{ $p->id }})" class="ml-2 text-xs font-semibold text-[rgb(var(--nx-warn))] hover:underline">Suspend</button>
                                @else
                                    <button type="button" wire:click="activate({{ $p->id }})" class="ml-2 text-xs font-semibold text-[rgb(var(--nx-ok))] hover:underline">Activate</button>
                                @endif
                                <button type="button" wire:click="remove({{ $p->id }})" wire:confirm="Remove this partner?" class="ml-2 text-xs font-semibold text-[rgb(var(--nx-bad))] hover:underline">Remove</button>
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-8 text-center text-sm text-[rgb(var(--nx-text-2))]">No partners yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $partners->links() }}</div>
</div>
