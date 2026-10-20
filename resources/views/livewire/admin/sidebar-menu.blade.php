{{-- nx:converted (skin tokens; legacy palette classes removed 2026-10-05) --}}
<div class="mx-auto max-w-3xl space-y-8">
    <div>
        <h1 class="text-2xl font-bold text-[rgb(var(--nx-text))]">Sidebar menu</h1>
        <p class="mt-1 text-sm text-[rgb(var(--nx-text-2))]">The global glass slide-out beside the notification bell. Legal pages, social handles and account deletion are always shown; add extra links and settings here.</p>
    </div>

    @if ($saved)
        <div class="flex items-center gap-2 rounded-lg bg-[rgb(var(--nx-ok)/0.12)] p-3 text-sm text-[rgb(var(--nx-ok))]">
            <x-icon name="badge-check" class="h-4 w-4" /> {{ $saved }}
        </div>
    @endif

    {{-- Always-present legal links (read-only reassurance). --}}
    <div class="rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-6">
        <h2 class="mb-3 text-base font-semibold text-[rgb(var(--nx-text))]">Always shown</h2>
        <p class="mb-3 text-xs text-[rgb(var(--nx-text-2))]">These can't be removed — app-store review needs them reachable.</p>
        <div class="flex flex-wrap gap-2">
            @foreach ($legalLinks as $l)
                <span class="inline-flex items-center gap-1.5 rounded-full bg-[rgb(var(--nx-surface-3))] px-3 py-1 text-xs font-medium text-[rgb(var(--nx-text-2))]">
                    <x-icon name="shield" class="h-3.5 w-3.5" /> {{ $l['label'] }}
                </span>
            @endforeach
            <span class="inline-flex items-center gap-1.5 rounded-full bg-[rgb(var(--nx-bad)/0.1)] px-3 py-1 text-xs font-medium text-[rgb(var(--nx-bad))]">Delete my account</span>
        </div>
    </div>

    {{-- Settings --}}
    <form wire:submit="saveSettings" class="space-y-4 rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-6">
        <h2 class="text-base font-semibold text-[rgb(var(--nx-text))]">Settings</h2>
        <div>
            <label class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">Ratings / reviews URL</label>
            <input type="url" wire:model="reviews_url" placeholder="https://…"
                   class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm">
            @error('reviews_url') <p class="mt-1 text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p> @enderror
        </div>
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">Links display mode</label>
                <select wire:model="display_mode" class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm">
                    <option value="list">List</option>
                    <option value="grid">Grid</option>
                </select>
            </div>
            <label class="flex items-end gap-2 pb-1">
                <x-ui.switch wire:model="blog_widget" label="Blog widget" />
                <span class="text-sm text-[rgb(var(--nx-text-2))]">Show latest blog posts</span>
            </label>
        </div>
        <div class="flex justify-end">
            <button type="submit" class="rounded-lg bg-[rgb(var(--nx-cta-b))] px-5 py-2.5 text-sm font-semibold text-white hover:bg-[rgb(var(--nx-cta-a))]">Save settings</button>
        </div>
    </form>

    {{-- Custom links --}}
    <div class="rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-6">
        <h2 class="mb-4 text-base font-semibold text-[rgb(var(--nx-text))]">Custom links</h2>

        <div class="mb-4 space-y-2">
            @forelse ($links as $i => $link)
                <div wire:key="link-{{ $i }}" class="flex items-center gap-3 rounded-xl border border-[rgb(var(--nx-line))] p-2.5">
                    <x-icon :name="$link['icon'] ?: 'chevron-right'" class="h-4 w-4 shrink-0 text-[rgb(var(--nx-teal-ink))]" />
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-medium text-[rgb(var(--nx-text))]">{{ $link['label'] }}</p>
                        <p class="truncate text-[11px] text-[rgb(var(--nx-text-2))]">{{ $link['url'] }}</p>
                    </div>
                    <button type="button" wire:click="removeLink({{ $i }})" wire:confirm="Remove this link?"
                            class="rounded-lg p-1.5 text-[rgb(var(--nx-text-2))] hover:bg-[rgb(var(--nx-bad)/0.1)] hover:text-[rgb(var(--nx-bad))]">
                        <x-icon name="x" class="h-4 w-4" />
                    </button>
                </div>
            @empty
                <p class="text-sm text-[rgb(var(--nx-text-2))]">No custom links yet.</p>
            @endforelse
        </div>

        <form wire:submit="addLink" class="space-y-3 rounded-xl bg-[rgb(var(--nx-surface-2))] p-4">
            <div class="grid gap-3 sm:grid-cols-3">
                <div>
                    <label class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">Label</label>
                    <input type="text" wire:model="new_label" class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm">
                    @error('new_label') <p class="mt-1 text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">Icon <span class="text-[rgb(var(--nx-text-2))]">(sprite name)</span></label>
                    <input type="text" wire:model="new_icon" placeholder="chevron-right"
                           class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">URL</label>
                    <input type="url" wire:model="new_url" placeholder="https://…"
                           class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm">
                    @error('new_url') <p class="mt-1 text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p> @enderror
                </div>
            </div>
            <div class="flex justify-end">
                <button type="submit" class="rounded-lg bg-[rgb(var(--nx-cta-b))] px-4 py-2 text-sm font-semibold text-white hover:bg-[rgb(var(--nx-cta-a))]">Add link</button>
            </div>
        </form>
    </div>
</div>
