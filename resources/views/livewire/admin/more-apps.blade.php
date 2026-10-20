{{-- nx:converted (skin tokens; legacy palette classes removed 2026-10-05) --}}
<div class="mx-auto max-w-4xl">
    <h1 class="mb-1 text-2xl font-bold text-[rgb(var(--nx-text))]">More Apps</h1>
    <p class="mb-6 text-sm text-[rgb(var(--nx-text-2))]">Cross-promote companion Supreme Ideas apps in the mobile "More" sheet — an icon, a name, and a link. Master-only feature.</p>

    @if ($saved)
        <div class="mb-6 flex items-center gap-2 rounded-lg bg-[rgb(var(--nx-cta-b)/0.1)] p-3 text-sm text-[rgb(var(--nx-teal-ink))]">
            <x-icon name="badge-check" class="h-4 w-4 shrink-0" /> {{ $saved }}
        </div>
    @endif

    {{-- Create --}}
    <section class="mb-8 rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-6">
        <h2 class="flex items-center gap-2 text-sm font-semibold text-[rgb(var(--nx-text))]">
            <x-icon name="grid" class="h-4 w-4 text-[rgb(var(--nx-teal-ink))]" /> New app
        </h2>

        <form wire:submit="save" class="mt-4 grid gap-4 sm:grid-cols-2">
            <div>
                <label class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">App name</label>
                <input wire:model="name" type="text" placeholder="Naara Business"
                       class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm text-[rgb(var(--nx-text))]">
                @error('name') <span class="mt-1 block text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</span> @enderror
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">Icon (JPG/PNG/WebP/SVG, ≤ 1 MB)</label>
                <input wire:model="icon" type="file" accept="image/jpeg,image/png,image/webp,image/svg+xml"
                       class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm text-[rgb(var(--nx-text-2))] file:mr-3 file:rounded-md file:border-0 file:bg-[rgb(var(--nx-cta-b)/0.1)] file:px-3 file:py-1 file:text-xs file:font-semibold file:text-[rgb(var(--nx-teal-ink))]">
                <div wire:loading wire:target="icon" class="mt-1 text-[11px] text-[rgb(var(--nx-text-2))]">Uploading…</div>
                @error('icon') <span class="mt-1 block text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</span> @enderror
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">Link <span class="font-normal text-[rgb(var(--nx-text-2))]">(/path or https://…)</span></label>
                <input wire:model="link_url" type="text" placeholder="https://business.naarasim.com"
                       class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm text-[rgb(var(--nx-text))]">
                @error('link_url') <span class="mt-1 block text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</span> @enderror
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">Order</label>
                <input wire:model="sort_order" type="number" min="0" max="999"
                       class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm text-[rgb(var(--nx-text))]">
            </div>
            <div class="flex items-end sm:col-span-2">
                <button type="submit" wire:loading.attr="disabled" wire:target="save,icon"
                        class="inline-flex items-center gap-2 rounded-lg bg-[rgb(var(--nx-cta-b))] px-5 py-2.5 text-sm font-semibold text-white hover:bg-[rgb(var(--nx-cta-a))] disabled:opacity-60">
                    <span wire:loading.remove wire:target="save" class="inline-flex items-center gap-2"><x-icon name="check" class="h-4 w-4" /> Add app</span>
                    <span wire:loading wire:target="save" class="inline-flex items-center gap-2"><x-ui.spinner class="h-4 w-4" /> Adding…</span>
                </button>
            </div>
        </form>
    </section>

    {{-- List --}}
    <div class="space-y-3">
        @forelse ($apps as $app)
            <div wire:key="more-app-{{ $app->id }}" class="flex flex-wrap items-center gap-4 rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-4">
                <img src="{{ $app->icon_url }}" alt="{{ $app->name }}" class="h-12 w-12 shrink-0 rounded-xl border border-[rgb(var(--nx-line))] object-cover">
                <div class="min-w-0 flex-1">
                    <div class="truncate font-semibold text-[rgb(var(--nx-text))]">{{ $app->name }}</div>
                    <div class="mt-0.5 flex flex-wrap items-center gap-2 text-xs text-[rgb(var(--nx-text-2))]">
                        <span class="inline-flex items-center gap-1 truncate"><x-icon name="globe" class="h-3 w-3" /> {{ $app->route_name ? 'In-app: '.$app->route_name : $app->link_url }}</span>
                        <span class="rounded-full bg-[rgb(var(--nx-surface-3))] px-2 py-0.5">Order {{ $app->sort_order }}</span>
                    </div>
                </div>
                <div class="flex flex-wrap items-center gap-3">
                    <x-ui.tag :variant="$app->is_active ? 'live' : 'soon'">{{ $app->is_active ? 'Live' : 'Paused' }}</x-ui.tag>
                    @if ($app->is_system)<x-ui.tag variant="soon">Built-in</x-ui.tag>@endif
                    <button type="button" wire:click="move({{ $app->id }}, 'up')" class="text-xs font-medium text-[rgb(var(--nx-text-2))] underline">Up</button>
                    <button type="button" wire:click="move({{ $app->id }}, 'down')" class="text-xs font-medium text-[rgb(var(--nx-text-2))] underline">Down</button>
                    <button type="button" wire:click="toggle({{ $app->id }})" wire:loading.attr="disabled"
                            class="text-xs font-medium text-[rgb(var(--nx-text-2))] underline hover:text-[rgb(var(--nx-teal-ink))]">
                        {{ $app->is_active ? 'Pause' : 'Resume' }}
                    </button>
                    @unless ($app->is_system)
                        <button type="button" wire:click="delete({{ $app->id }})" wire:loading.attr="disabled"
                                wire:confirm="Remove this app? Users stop seeing it immediately."
                                class="text-xs font-medium text-[rgb(var(--nx-bad))] underline hover:text-[rgb(var(--nx-bad))]">
                            Delete
                        </button>
                    @endunless
                </div>
                <div class="flex w-full flex-wrap items-center gap-2 border-t border-[rgb(var(--nx-line))] pt-3">
                    <input type="text" wire:model="names.{{ $app->id }}" placeholder="{{ $app->name }}" maxlength="60" class="min-w-0 flex-1 rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-1.5 text-sm text-[rgb(var(--nx-text))]">
                    <button type="button" wire:click="rename({{ $app->id }})" class="rounded-lg border border-[rgb(var(--nx-line))] px-3 py-1.5 text-xs font-medium text-[rgb(var(--nx-text))]">Rename</button>
                    <input type="file" wire:model="logos.{{ $app->id }}" accept="image/jpeg,image/png,image/webp,image/svg+xml" class="max-w-[11rem] text-xs text-[rgb(var(--nx-text-2))]">
                    <button type="button" wire:click="replaceLogo({{ $app->id }})" wire:loading.attr="disabled" wire:target="logos.{{ $app->id }},replaceLogo" class="rounded-lg border border-[rgb(var(--nx-line))] px-3 py-1.5 text-xs font-medium text-[rgb(var(--nx-text))]">Replace logo</button>
                    @error('names.'.$app->id)<span class="w-full text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</span>@enderror
                    @error('logos.'.$app->id)<span class="w-full text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</span>@enderror
                </div>
            </div>
        @empty
            <div class="rounded-2xl border border-dashed border-[rgb(var(--nx-line-strong))] p-10 text-center text-sm text-[rgb(var(--nx-text-2))]">
                No apps yet. Add one above — it appears in the mobile More sheet instantly.
            </div>
        @endforelse
    </div>
</div>
