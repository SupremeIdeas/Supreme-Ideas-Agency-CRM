{{-- nx:converted (skin tokens; legacy palette classes removed 2026-10-05) --}}
<div class="mx-auto max-w-4xl">
    <h1 class="mb-1 text-2xl font-bold text-[rgb(var(--nx-text))]">Legal hold records</h1>
    <p class="mb-6 text-sm text-[rgb(var(--nx-text-2))]">
        Read-only lookup of an <span class="font-semibold">anonymized</span> account's retained financial/order trail —
        for a legal hold, chargeback dispute, or AML/regulator request. This never restores the account's name or email;
        that's irreversible once erasure ran. Every lookup requires a case reference and reason and is written to the
        audit log.
    </p>

    @unless ($canView)
        <div class="mb-6 flex items-center gap-2 rounded-lg bg-[rgb(var(--nx-warn)/0.12)] p-3 text-sm text-[rgb(var(--nx-warn))]">
            <x-icon name="shield" class="h-4 w-4" /> Only a super admin may view retained records under legal hold.
        </div>
    @else
        <div class="mb-6 rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-5">
            <div class="grid gap-4 sm:grid-cols-3">
                <div>
                    <label class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">Account id</label>
                    <input type="text" wire:model="userId" class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">Case reference</label>
                    <input type="text" wire:model="caseReference" class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">Reason</label>
                    <input type="text" wire:model="reason" class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] px-3 py-2 text-sm">
                </div>
            </div>
            <button type="button" wire:click="lookup" wire:loading.attr="disabled" wire:target="lookup"
                    class="mt-4 inline-flex items-center gap-2 rounded-lg bg-[rgb(var(--nx-cta-b))] px-4 py-2 text-sm font-medium text-white hover:bg-[rgb(var(--nx-cta-a))] disabled:opacity-60">
                <x-icon name="search" class="h-4 w-4" /> Look up retained records
            </button>
        </div>

        @if ($error)
            <div class="mb-6 flex items-center gap-2 rounded-lg bg-[rgb(var(--nx-bad)/0.1)] p-3 text-sm text-[rgb(var(--nx-bad))]">
                <x-icon name="shield-alert" class="h-4 w-4" /> {{ $error }}
            </div>
        @endif

        @if ($result)
            <div class="space-y-4">
                <div class="rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-5">
                    <p class="text-sm text-[rgb(var(--nx-text-2))]">
                        User #{{ $result['user_id'] }} · anonymized {{ $result['anonymized_at'] }} ·
                        purge due {{ $result['retention_purge_due_at'] }}
                    </p>
                </div>

                @foreach (['wallet_transactions' => 'Wallet transactions', 'esim_orders' => 'eSIM orders', 'sms_orders' => 'SMS orders', 'virtual_numbers' => 'Virtual numbers'] as $key => $label)
                    <div class="overflow-hidden rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))]">
                        <div class="border-b border-[rgb(var(--nx-line))] px-5 py-3 text-sm font-semibold text-[rgb(var(--nx-text))]">
                            {{ $label }} ({{ count($result[$key]) }})
                        </div>
                        @forelse ($result[$key] as $row)
                            <div class="border-b border-[rgb(var(--nx-line))] px-5 py-2 text-xs text-[rgb(var(--nx-text-2))] last:border-0">
                                #{{ $row->id }} · {{ $row->created_at }}
                            </div>
                        @empty
                            <div class="px-5 py-4 text-xs text-[rgb(var(--nx-text-2))]">None retained.</div>
                        @endforelse
                    </div>
                @endforeach
            </div>
        @endif
    @endunless
</div>
