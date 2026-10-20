{{-- nx:converted (skin tokens; legacy palette classes removed 2026-10-05) --}}
<div class="mx-auto max-w-4xl">
    <h1 class="text-2xl font-bold text-[rgb(var(--nx-text))]">Identity (KYC)</h1>
    <p class="mt-1 text-sm text-[rgb(var(--nx-text-2))]">
        Choose your verification provider and review identity checks. Approving marks
        the user verified — L2 unlocks withdrawals, L3 (business) unlocks merchants.
    </p>

    @if ($saved)
        <div class="mt-4 flex items-center gap-2 rounded-lg bg-[rgb(var(--nx-ok)/0.12)] p-3 text-sm text-[rgb(var(--nx-ok))]">
            <x-icon name="check" class="h-4 w-4" /> {{ $saved }}
        </div>
    @endif

    {{-- Provider --}}
    <div class="mt-5 rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-5 shadow-sm">
        <label class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">Active provider</label>
        <div class="flex flex-wrap items-end gap-3">
            <select wire:model="provider" class="rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm text-[rgb(var(--nx-text))]">
                <option value="manual">Manual review (default)</option>
                <option value="smileid">Smile ID</option>
                <option value="dojah">Dojah</option>
                <option value="stripe_identity">Stripe Identity (global, non-African users)</option>
            </select>
            <button type="button" wire:click="saveProvider" wire:loading.attr="disabled" wire:target="saveProvider"
                    class="rounded-lg bg-[rgb(var(--nx-cta-b))] px-4 py-2 text-sm font-semibold text-white transition hover:bg-[rgb(var(--nx-cta-a))] disabled:opacity-60">Save</button>
        </div>
        <p class="mt-2 text-[11px] text-[rgb(var(--nx-text-2))]">Smile ID / Dojah require their keys on Admin → API keys. Without keys, checks fall back to manual review.</p>
    </div>

    {{-- Pending review --}}
    <h2 class="mt-6 text-sm font-semibold text-[rgb(var(--nx-text))]">Pending review</h2>
    <div class="mt-3 overflow-x-auto rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] shadow-sm">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-[rgb(var(--nx-line))] text-left text-xs uppercase tracking-wide text-[rgb(var(--nx-text-2))]">
                    <th class="px-4 py-3">User</th>
                    <th class="px-4 py-3">Level</th>
                    <th class="px-4 py-3">Via</th>
                    <th class="px-4 py-3">AI triage</th>
                    <th class="px-4 py-3 text-right">Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($pending as $v)
                    <tr class="border-b border-[rgb(var(--nx-line))]" wire:key="kyc-pending-{{ $v->id }}">
                        <td class="px-4 py-3 text-[rgb(var(--nx-text-2))]">{{ $v->user?->email ?? '—' }}</td>
                        <td class="px-4 py-3 text-[rgb(var(--nx-text-2))]">{{ $v->level === 3 ? 'Business (L3)' : 'Individual (L2)' }}</td>
                        <td class="px-4 py-3 text-xs text-[rgb(var(--nx-text-2))] capitalize">{{ $v->provider }}</td>
                        <td class="px-4 py-3 max-w-xs">
                            {{-- Oversight blueprint Phase E2: advisory only — Claude's own
                                 read, never a decision. The reviewer still clicks Approve/Reject. --}}
                            @if ($v->ai_assessment)
                                @php($tone = $v->ai_assessment['consistent'] ? 'text-[rgb(var(--nx-ok))]' : 'text-[rgb(var(--nx-warn))]')
                                <p class="text-xs font-medium {{ $tone }}">
                                    {{ $v->ai_assessment['consistent'] ? 'Consistent' : 'Flagged' }}
                                    <span class="text-[rgb(var(--nx-text-2))]">· {{ ucfirst($v->ai_assessment['confidence']) }} confidence</span>
                                </p>
                                <p class="mt-0.5 text-[11px] text-[rgb(var(--nx-text-2))]">{{ $v->ai_assessment['reasoning'] }}</p>
                            @else
                                <span class="text-[11px] text-[rgb(var(--nx-text-2))]">Not yet assessed</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-end gap-2">
                                <button type="button" wire:click="approve({{ $v->id }})" wire:confirm="Approve this identity check?"
                                        class="rounded-lg bg-[rgb(var(--nx-cta-b))] px-3 py-1.5 text-xs font-semibold text-white hover:bg-[rgb(var(--nx-cta-a))]">Approve</button>
                                <button type="button" wire:click="reject({{ $v->id }})" wire:confirm="Reject this identity check?"
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

    {{-- Recent --}}
    <h2 class="mt-6 text-sm font-semibold text-[rgb(var(--nx-text))]">Recent decisions</h2>
    <div class="mt-3 overflow-x-auto rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] shadow-sm">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-[rgb(var(--nx-line))] text-left text-xs uppercase tracking-wide text-[rgb(var(--nx-text-2))]">
                    <th class="px-4 py-3">User</th>
                    <th class="px-4 py-3">Level</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3">When</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($recent as $v)
                    @php($badge = $v->status === 'approved'
                        ? 'bg-[rgb(var(--nx-ok)/0.16)] text-[rgb(var(--nx-ok))]'
                        : 'bg-[rgb(var(--nx-bad)/0.14)] text-[rgb(var(--nx-bad))]')
                    <tr class="border-b border-[rgb(var(--nx-line))]" wire:key="kyc-recent-{{ $v->id }}">
                        <td class="px-4 py-3 text-[rgb(var(--nx-text-2))]">{{ $v->user?->email ?? '—' }}</td>
                        <td class="px-4 py-3 text-[rgb(var(--nx-text-2))]">L{{ $v->level }}</td>
                        <td class="px-4 py-3"><span class="rounded-full px-2 py-0.5 text-[11px] font-semibold capitalize {{ $badge }}">{{ $v->status }}</span></td>
                        <td class="px-4 py-3 text-xs text-[rgb(var(--nx-text-2))]">{{ $v->reviewed_at?->diffForHumans() ?? $v->updated_at?->diffForHumans() }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-4 py-8 text-center text-sm text-[rgb(var(--nx-text-2))]">No decisions yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
