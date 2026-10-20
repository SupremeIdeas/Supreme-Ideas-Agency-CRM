{{-- nx:converted (skin tokens; legacy palette classes removed 2026-10-05) --}}
<div class="mx-auto max-w-4xl">
    <h1 class="mb-1 text-2xl font-bold text-[rgb(var(--nx-text))]">Account deletions</h1>
    <p class="mb-6 text-sm text-[rgb(var(--nx-text-2))]">
        GDPR deletion requests awaiting approval. Approving <span class="font-semibold text-[rgb(var(--nx-bad))]">anonymizes</span> the account immediately — the user is deactivated and every PII field is wiped — while wallet/eSIM/SMS/number records are retained under the account's id until the configured retention window elapses, then permanently purged. Only a super admin can approve. See <a href="{{ route('admin.legal-hold') }}" class="underline">Legal hold records</a> to look up a retained trail.
    </p>

    @if ($status)
        <div class="mb-6 flex items-center gap-2 rounded-lg bg-[rgb(var(--nx-ok)/0.12)] p-3 text-sm text-[rgb(var(--nx-ok))]">
            <x-icon name="badge-check" class="h-4 w-4" /> {{ $status }}
        </div>
    @endif

    @unless ($canApprove)
        <div class="mb-6 flex items-center gap-2 rounded-lg bg-[rgb(var(--nx-warn)/0.12)] p-3 text-sm text-[rgb(var(--nx-warn))]">
            <x-icon name="shield" class="h-4 w-4" /> You can review this queue, but only a super admin may approve a deletion.
        </div>
    @endunless

    <div class="overflow-hidden rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))]">
        @forelse ($pending as $u)
            <div class="flex items-center justify-between gap-4 border-b border-[rgb(var(--nx-line))] px-5 py-4 last:border-0">
                <div class="min-w-0">
                    <p class="truncate text-sm font-medium text-[rgb(var(--nx-text))]">{{ $u->name }} <span class="text-[rgb(var(--nx-text-2))]">·</span> <span class="text-[rgb(var(--nx-text-2))]">{{ $u->email }}</span></p>
                    <p class="mt-0.5 text-xs text-[rgb(var(--nx-text-2))]">Requested {{ $u->deletion_requested_at?->diffForHumans() }} · user #{{ $u->id }}</p>
                </div>
                @if ($canApprove)
                    <button type="button" wire:click="approve({{ $u->id }})" wire:loading.attr="disabled" wire:target="approve({{ $u->id }})"
                            wire:confirm="Anonymize {{ $u->email }}? Their PII is wiped immediately and cannot be recovered; financial/order records stay retained until the retention window purges them."
                            class="inline-flex shrink-0 items-center gap-2 rounded-lg border border-[rgb(var(--nx-bad))] px-3 py-2 text-sm font-medium text-[rgb(var(--nx-bad))] hover:bg-[rgb(var(--nx-bad)/0.1)] disabled:opacity-60">
                        <x-icon name="trash" class="h-4 w-4" /> Approve &amp; anonymize
                    </button>
                @endif
            </div>
        @empty
            <div class="px-5 py-10 text-center text-sm text-[rgb(var(--nx-text-2))]">
                <x-icon name="badge-check" class="mx-auto mb-2 h-6 w-6" />
                No pending deletion requests.
            </div>
        @endforelse
    </div>
</div>
