{{-- nx:converted (skin tokens; legacy palette classes removed 2026-10-05) --}}
<div class="mx-auto max-w-2xl space-y-6">
    <div>
        <h1 class="text-xl font-bold text-[rgb(var(--nx-text))]">My account</h1>
        <p class="text-sm text-[rgb(var(--nx-text-2))]">Manage your own name, email, password and recovery. Site-wide protection lives on <a href="{{ route('admin.security') }}" wire:navigate class="text-[rgb(var(--nx-teal-ink))] hover:underline">Security</a>.</p>
    </div>

    @if ($flash)
        <div class="flex items-center gap-2 rounded-lg px-4 py-3 text-sm {{ $flashType === 'error' ? 'bg-[rgb(var(--nx-bad)/0.1)] text-[rgb(var(--nx-bad))]' : 'bg-[rgb(var(--nx-ok)/0.12)] text-[rgb(var(--nx-ok))]' }}">
            <x-icon :name="$flashType === 'error' ? 'x' : 'badge-check'" class="h-4 w-4" /> {{ $flash }}
        </div>
    @endif

    {{-- Profile --}}
    <form wire:submit="updateProfile" class="space-y-4 rounded-xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-5">
        <h2 class="text-sm font-semibold text-[rgb(var(--nx-text))]">Profile</h2>
        <div class="flex items-center gap-4">
            <span class="flex h-14 w-14 shrink-0 items-center justify-center overflow-hidden rounded-full bg-[rgb(var(--nx-surface-3))]">
                @if ($avatar && $avatar->isPreviewable())
                    <img src="{{ $avatar->temporaryUrl() }}" class="h-full w-full object-cover">
                @elseif ($user->avatar)
                    <img src="{{ $user->avatar }}" class="h-full w-full object-cover">
                @else
                    <x-icon name="id-card" class="h-6 w-6 text-[rgb(var(--nx-text-2))]" />
                @endif
            </span>
            <div class="flex-1">
                <label class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">Avatar</label>
                <input type="file" wire:model="avatar" accept="image/png,image/jpeg,image/webp"
                       class="block w-full text-xs text-[rgb(var(--nx-text-2))] file:mr-3 file:rounded-lg file:border-0 file:bg-[rgb(var(--nx-cta-b)/0.1)] file:px-3 file:py-1.5 file:text-xs file:font-medium file:text-[rgb(var(--nx-teal-ink))]">
                @error('avatar') <p class="mt-1 text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p> @enderror
            </div>
        </div>
        <div>
            <label class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">Name</label>
            <input type="text" wire:model="name" class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2.5 text-sm">
            @error('name') <p class="mt-1 text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p> @enderror
        </div>
        <div class="flex justify-end">
            <button type="submit" class="rounded-lg bg-[rgb(var(--nx-cta-b))] px-4 py-2 text-sm font-semibold text-white hover:bg-[rgb(var(--nx-cta-a))]">Save profile</button>
        </div>
    </form>

    {{-- Email --}}
    <form wire:submit="updateEmail" class="space-y-4 rounded-xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-5">
        <h2 class="text-sm font-semibold text-[rgb(var(--nx-text))]">Email</h2>
        <p class="text-xs text-[rgb(var(--nx-text-2))]">Changing your email requires your password and a re-verification of the new address.</p>
        <div>
            <label class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">Email address</label>
            <input type="email" wire:model="new_email" class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2.5 text-sm">
            @error('new_email') <p class="mt-1 text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">Current password</label>
            <input type="password" wire:model="email_password" autocomplete="current-password" class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2.5 text-sm">
            @error('email_password') <p class="mt-1 text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p> @enderror
        </div>
        <div class="flex justify-end">
            <button type="submit" class="rounded-lg bg-[rgb(var(--nx-cta-b))] px-4 py-2 text-sm font-semibold text-white hover:bg-[rgb(var(--nx-cta-a))]">Change email</button>
        </div>
    </form>

    {{-- Password --}}
    <form wire:submit="updatePassword" class="space-y-4 rounded-xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-5">
        <h2 class="text-sm font-semibold text-[rgb(var(--nx-text))]">Password</h2>
        <div>
            <label class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">Current password</label>
            <input type="password" wire:model="current_password" autocomplete="current-password" class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2.5 text-sm">
            @error('current_password') <p class="mt-1 text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p> @enderror
        </div>
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">New password</label>
                <input type="password" wire:model="password" autocomplete="new-password" class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2.5 text-sm">
                @error('password') <p class="mt-1 text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">Confirm new password</label>
                <input type="password" wire:model="password_confirmation" autocomplete="new-password" class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2.5 text-sm">
            </div>
        </div>
        <div class="flex justify-end">
            <button type="submit" class="rounded-lg bg-[rgb(var(--nx-cta-b))] px-4 py-2 text-sm font-semibold text-white hover:bg-[rgb(var(--nx-cta-a))]">Update password</button>
        </div>
    </form>

    {{-- Security questions --}}
    <form wire:submit="saveSecurityQuestions" class="space-y-4 rounded-xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-5">
        <div class="flex items-center justify-between">
            <h2 class="text-sm font-semibold text-[rgb(var(--nx-text))]">Recovery questions</h2>
            @if ($questionsConfigured)
                <span class="rounded-full bg-[rgb(var(--nx-ok)/0.16)] px-2 py-0.5 text-[10px] font-semibold text-[rgb(var(--nx-ok))]">Configured</span>
            @endif
        </div>
        <p class="text-xs text-[rgb(var(--nx-text-2))]">Set three questions to recover your account if email reset isn’t available. Answers are hashed — we can never read them.</p>
        @foreach ($questions as $i => $q)
            <div class="grid gap-2 sm:grid-cols-2">
                <select wire:model="questions.{{ $i }}.question" class="rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm">
                    @foreach ($presets as $preset)
                        <option value="{{ $preset }}">{{ $preset }}</option>
                    @endforeach
                </select>
                <input type="text" wire:model="questions.{{ $i }}.answer" placeholder="Your answer" autocomplete="off"
                       class="rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm">
                @error("questions.$i.answer") <p class="text-xs text-[rgb(var(--nx-bad))] sm:col-span-2">{{ $message }}</p> @enderror
            </div>
        @endforeach
        <div class="flex justify-end">
            <button type="submit" class="rounded-lg bg-[rgb(var(--nx-cta-b))] px-4 py-2 text-sm font-semibold text-white hover:bg-[rgb(var(--nx-cta-a))]">Save recovery questions</button>
        </div>
    </form>

    <div class="rounded-xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-5 text-sm">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-semibold text-[rgb(var(--nx-text))]">Two-factor authentication</h2>
                <p class="text-xs text-[rgb(var(--nx-text-2))]">Manage your authenticator app and recovery codes.</p>
            </div>
            <a href="{{ route('admin.security') }}" wire:navigate class="rounded-lg border border-[rgb(var(--nx-line-strong))] px-3 py-1.5 text-xs font-medium text-[rgb(var(--nx-text-2))] hover:bg-[rgb(var(--nx-surface-2))]">Manage 2FA</a>
        </div>
    </div>
</div>
