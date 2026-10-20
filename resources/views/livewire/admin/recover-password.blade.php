{{-- nx:converted (skin tokens; legacy palette classes removed 2026-10-05) --}}
<div class="mx-auto w-full max-w-md px-4 py-10">
    <div class="rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-6 shadow-sm">
        <h1 class="text-lg font-bold text-[rgb(var(--nx-text))]">Recover admin access</h1>
        <p class="mt-1 text-sm text-[rgb(var(--nx-text-2))]">Answer your recovery questions to set a new password.</p>

        @if ($error)
            <div class="mt-4 rounded-lg bg-[rgb(var(--nx-bad)/0.1)] px-3 py-2 text-sm text-[rgb(var(--nx-bad))]">{{ $error }}</div>
        @endif

        @if ($step === 1)
            <form wire:submit="lookup" class="mt-5 space-y-4">
                <div>
                    <label class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">Admin email</label>
                    <input type="email" wire:model="email" autocomplete="username" class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2.5 text-sm">
                    @error('email') <p class="mt-1 text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p> @enderror
                </div>
                <button type="submit" wire:loading.attr="disabled" class="w-full rounded-lg bg-[rgb(var(--nx-cta-b))] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[rgb(var(--nx-cta-a))] disabled:opacity-60">Continue</button>
            </form>
        @elseif ($step === 2)
            <form wire:submit="verify" class="mt-5 space-y-4">
                @foreach ($prompts as $q)
                    <div>
                        <label class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">{{ $q }}</label>
                        <input type="text" wire:model="answers.{{ $q }}" autocomplete="off" class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2.5 text-sm">
                    </div>
                @endforeach
                <button type="submit" wire:loading.attr="disabled" class="w-full rounded-lg bg-[rgb(var(--nx-cta-b))] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[rgb(var(--nx-cta-a))] disabled:opacity-60">Verify answers</button>
            </form>
        @else
            <form wire:submit="resetPassword" class="mt-5 space-y-4">
                <div>
                    <label class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">New password</label>
                    <input type="password" wire:model="password" autocomplete="new-password" class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2.5 text-sm">
                    @error('password') <p class="mt-1 text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">Confirm new password</label>
                    <input type="password" wire:model="password_confirmation" autocomplete="new-password" class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2.5 text-sm">
                </div>
                <button type="submit" wire:loading.attr="disabled" class="w-full rounded-lg bg-[rgb(var(--nx-cta-b))] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[rgb(var(--nx-cta-a))] disabled:opacity-60">Set new password</button>
            </form>
        @endif

        <div class="mt-5 text-center">
            <a href="{{ route('admin.login') }}" class="text-xs font-medium text-[rgb(var(--nx-teal-ink))] hover:underline">Back to sign in</a>
        </div>
    </div>
</div>
