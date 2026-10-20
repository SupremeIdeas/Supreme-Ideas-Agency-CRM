{{-- nx:converted (skin tokens only) --}}
<div class="mx-auto max-w-3xl space-y-4 p-4">
    <div>
        <a href="{{ route('admin.n2n') }}" wire:navigate class="text-sm text-[rgb(var(--nx-text-2))]">N2N</a>
        <h1 class="text-xl font-bold text-[rgb(var(--nx-text))]">Chat notices</h1>
    </div>
    @if ($saved)<div role="status" class="rounded-xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-3 text-sm text-[rgb(var(--nx-text))]">{{ $saved }}</div>@endif

    <form wire:submit="save" class="space-y-3 rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-4 text-sm text-[rgb(var(--nx-text))]">
        <h2 class="font-semibold">{{ $editing ? 'Edit notice' : 'New notice' }}</h2>
        <p class="text-xs text-[rgb(var(--nx-text-2))]">
            Shown at the top of Naara chat. Say only what is true: chat is encrypted in transit and at rest, {{ $e2ee ? 'and end-to-end encryption is switched on' : 'and is NOT end-to-end encrypted, so end-to-end wording is blocked' }}.
        </p>
        <label class="block"><span>Title</span>
            <input type="text" wire:model="title" maxlength="80" class="mt-1 w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-[rgb(var(--nx-text))]"></label>
        @error('title')<p class="text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p>@enderror
        <label class="block"><span>Message (max 280)</span>
            <textarea wire:model="body" rows="3" maxlength="280" class="mt-1 w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-[rgb(var(--nx-text))]"></textarea></label>
        @error('body')<p class="text-xs text-[rgb(var(--nx-bad))]" role="alert">{{ $message }}</p>@enderror
        <div class="grid gap-2 sm:grid-cols-2">
            <label class="block"><span>Starts (optional)</span><input type="datetime-local" wire:model="startsAt" class="mt-1 w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-2 py-1 text-[rgb(var(--nx-text))]"></label>
            <label class="block"><span>Ends (optional)</span><input type="datetime-local" wire:model="endsAt" class="mt-1 w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-2 py-1 text-[rgb(var(--nx-text))]"></label>
        </div>
        @error('endsAt')<p class="text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p>@enderror
        <div class="flex flex-wrap items-center gap-4">
            <label class="flex items-center gap-2"><span>Tone</span>
                <select wire:model="tone" class="rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-2 py-1 text-[rgb(var(--nx-text))]"><option value="info">Info</option><option value="warn">Heads-up</option></select></label>
            <label class="flex items-center gap-2"><input type="checkbox" wire:model="dismissible"> <span>Members can dismiss it</span></label>
        </div>
        <div class="flex gap-2">
            <button type="submit" wire:loading.attr="disabled" wire:target="save" class="ns-cta ns-cta--pill ns-cta--sm">Save</button>
            @if ($editing)<button type="button" wire:click="resetForm" class="nc__chip">Cancel</button>@endif
        </div>
    </form>

    <section class="space-y-2">
        @forelse ($rows as [$n, $st])
            <article wire:key="n-{{ $n->id }}" class="rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-4 text-sm text-[rgb(var(--nx-text))]">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h3 class="font-semibold">{{ $n->title }} <span class="text-xs font-normal text-[rgb(var(--nx-text-2))]">{{ $n->active ? 'On' : 'Off' }}</span></h3>
                    <div class="flex gap-2">
                        <button type="button" wire:click="edit({{ $n->id }})" class="nc__chip">Edit</button>
                        <button type="button" wire:click="toggle({{ $n->id }})" wire:loading.attr="disabled" class="nc__chip">{{ $n->active ? 'Turn off' : 'Turn on' }}</button>
                        <button type="button" wire:click="delete({{ $n->id }})" wire:confirm="Delete this notice?" class="nc__chip">Delete</button>
                    </div>
                </div>
                <p class="mt-1 text-[rgb(var(--nx-text-2))]">{{ $n->body }}</p>
                <p class="mt-1 text-xs text-[rgb(var(--nx-text-2))]">Seen by {{ $st['seen'] }} · dismissed by {{ $st['dismissed'] }}</p>
            </article>
        @empty
            <p class="text-sm text-[rgb(var(--nx-text-2))]">No notices yet.</p>
        @endforelse
    </section>
</div>
