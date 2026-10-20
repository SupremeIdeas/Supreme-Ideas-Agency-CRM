{{-- nx:converted (skin tokens; legacy palette classes removed 2026-10-05) --}}
<div class="mx-auto max-w-5xl">
    <div class="mb-5">
        <h1 class="text-2xl font-bold text-[rgb(var(--nx-text))]">Port-in requests</h1>
        <p class="mt-1 text-sm text-[rgb(var(--nx-text-2))]">
            Customers bringing a US/Canada number to Naara. Review the carrier details, advance the
            status as the port progresses, or reject with a reason. Account number and PIN are cleared
            automatically once a request closes.
        </p>
    </div>

    @if ($flash)
        <div class="mb-4 rounded-xl bg-[rgb(var(--nx-ok)/0.12)] px-4 py-2 text-sm text-[rgb(var(--nx-ok))]">{{ $flash }}</div>
    @endif

    <div class="mb-4 inline-flex flex-wrap gap-1 rounded-full border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface-3))] p-1">
        <button wire:click="$set('filter', '')" class="rounded-full px-3 py-1.5 text-sm font-semibold transition {{ $filter === '' ? 'bg-[rgb(var(--nx-surface))] text-[rgb(var(--nx-teal-ink))] shadow-sm' : 'text-[rgb(var(--nx-text-2))]' }}">All</button>
        @foreach ($statuses as $status)
            <button wire:click="$set('filter', '{{ $status }}')" class="rounded-full px-3 py-1.5 text-sm font-semibold transition {{ $filter === $status ? 'bg-[rgb(var(--nx-surface))] text-[rgb(var(--nx-teal-ink))] shadow-sm' : 'text-[rgb(var(--nx-text-2))]' }}">
                {{ \App\Models\PortInRequest::statusLabel($status) }}
            </button>
        @endforeach
    </div>

    <div class="space-y-3">
        @forelse ($requests as $request)
            <div class="rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-4">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            <span class="font-semibold text-[rgb(var(--nx-text))]">{{ $request->phone_number }}</span>
                            <span class="rounded-full bg-[rgb(var(--nx-surface-3))] px-2 py-0.5 text-xs font-semibold text-[rgb(var(--nx-text-2))]">
                                {{ \App\Models\PortInRequest::statusLabel($request->status) }}
                            </span>
                        </div>
                        <div class="mt-0.5 text-xs text-[rgb(var(--nx-text-2))]">
                            {{ $request->user?->email ?? 'user #'.$request->user_id }} · requested {{ $request->created_at->format('M j, Y') }}
                            @if ($request->reviewer) · last touched by {{ $request->reviewer->name }} @endif
                        </div>
                    </div>
                </div>

                {{-- Carrier details (admin-only). Cleared once the request closes. --}}
                <div class="mt-3 grid grid-cols-1 gap-x-6 gap-y-1 text-xs text-[rgb(var(--nx-text-2))] sm:grid-cols-2">
                    <div><span class="text-[rgb(var(--nx-text-2))]">Account holder:</span> {{ $request->billing_name }}</div>
                    <div><span class="text-[rgb(var(--nx-text-2))]">Billing address:</span> {{ $request->billing_address }}</div>
                    <div><span class="text-[rgb(var(--nx-text-2))]">Account #:</span> {{ $request->account_number ?: '— cleared —' }}</div>
                    <div><span class="text-[rgb(var(--nx-text-2))]">Transfer PIN:</span> {{ $request->pin ?: '— cleared —' }}</div>
                    @if ($request->notes)<div class="sm:col-span-2"><span class="text-[rgb(var(--nx-text-2))]">Customer notes:</span> {{ $request->notes }}</div>@endif
                    @if ($request->provider)<div><span class="text-[rgb(var(--nx-text-2))]">Target provider:</span> {{ $request->provider }}</div>@endif
                    @if ($request->admin_notes)<div class="sm:col-span-2"><span class="text-[rgb(var(--nx-text-2))]">Ops notes:</span> {{ $request->admin_notes }}</div>@endif
                    @if ($request->rejection_reason)<div class="sm:col-span-2 text-[rgb(var(--nx-bad))]"><span class="text-[rgb(var(--nx-text-2))]">Rejected:</span> {{ $request->rejection_reason }}</div>@endif
                </div>

                {{-- Actions (open requests only). --}}
                @if ($request->isOpen())
                    <div class="mt-3 flex flex-wrap items-center gap-2 border-t border-[rgb(var(--nx-line))] pt-3">
                        @if ($request->status !== \App\Models\PortInRequest::STATUS_IN_REVIEW)
                            <button wire:click="setStatus({{ $request->id }}, 'in_review')" class="rounded-lg border border-[rgb(var(--nx-line))] px-2.5 py-1 text-xs font-semibold text-[rgb(var(--nx-text-2))] hover:border-[rgb(var(--nx-teal)/0.4)] hover:text-[rgb(var(--nx-teal-ink))]">Mark in review</button>
                        @endif
                        @if ($request->status !== \App\Models\PortInRequest::STATUS_SUBMITTED_TO_CARRIER)
                            <button wire:click="setStatus({{ $request->id }}, 'submitted_to_carrier')" class="rounded-lg border border-[rgb(var(--nx-line))] px-2.5 py-1 text-xs font-semibold text-[rgb(var(--nx-text-2))] hover:border-[rgb(var(--nx-teal)/0.4)] hover:text-[rgb(var(--nx-teal-ink))]">Submitted to carrier</button>
                        @endif
                        <button wire:click="setStatus({{ $request->id }}, 'completed')" wire:confirm="Mark this port-in complete? The account number and PIN will be cleared." class="rounded-lg bg-[color-mix(in_srgb,rgb(var(--nx-ok))_58%,#000)] px-2.5 py-1 text-xs font-semibold text-white hover:bg-[color-mix(in_srgb,rgb(var(--nx-ok))_48%,#000)]">Mark completed</button>
                        <button wire:click="editDetails({{ $request->id }})" class="rounded-lg border border-[rgb(var(--nx-line))] px-2.5 py-1 text-xs font-semibold text-[rgb(var(--nx-text-2))] hover:border-[rgb(var(--nx-teal)/0.4)] hover:text-[rgb(var(--nx-teal-ink))]">Notes / provider</button>
                        <button wire:click="startReject({{ $request->id }})" class="rounded-lg border border-[rgb(var(--nx-bad))] px-2.5 py-1 text-xs font-semibold text-[rgb(var(--nx-bad))] hover:bg-[rgb(var(--nx-bad)/0.1)]">Reject</button>
                    </div>

                    @if ($rejectingId === $request->id)
                        <div class="mt-2 rounded-xl bg-[rgb(var(--nx-surface-2))] p-3">
                            <input type="text" wire:model="rejectReason" placeholder="Reason (shown to the customer)…"
                                   class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] px-2 py-1 text-xs">
                            @error('rejectReason') <p class="mt-1 text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p> @enderror
                            <div class="mt-2 flex gap-2">
                                <button wire:click="reject({{ $request->id }})" class="rounded-lg bg-[color-mix(in_srgb,rgb(var(--nx-bad))_58%,#000)] px-2.5 py-1 text-xs font-semibold text-white">Confirm reject</button>
                                <button wire:click="cancelReject" class="text-xs text-[rgb(var(--nx-text-2))]">Cancel</button>
                            </div>
                        </div>
                    @endif

                    @if ($editingId === $request->id)
                        <div class="mt-2 space-y-2 rounded-xl bg-[rgb(var(--nx-surface-2))] p-3">
                            <input type="text" wire:model="providerValue" placeholder="Target provider (internal, e.g. twilio)…"
                                   class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] px-2 py-1 text-xs">
                            <textarea wire:model="adminNotesValue" rows="2" placeholder="Ops notes…"
                                      class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] px-2 py-1 text-xs"></textarea>
                            <div class="flex gap-2">
                                <button wire:click="saveDetails({{ $request->id }})" class="rounded-lg bg-[rgb(var(--nx-cta-b))] px-2.5 py-1 text-xs font-semibold text-white">Save</button>
                                <button wire:click="cancelEdit" class="text-xs text-[rgb(var(--nx-text-2))]">Cancel</button>
                            </div>
                        </div>
                    @endif
                @endif
            </div>
        @empty
            <div class="rounded-2xl border border-dashed border-[rgb(var(--nx-line-strong))] p-10 text-center text-sm text-[rgb(var(--nx-text-2))]">
                No port-in requests{{ $filter ? ' with this status' : '' }} yet.
            </div>
        @endforelse
    </div>

    <div class="mt-4">{{ $requests->links() }}</div>
</div>
