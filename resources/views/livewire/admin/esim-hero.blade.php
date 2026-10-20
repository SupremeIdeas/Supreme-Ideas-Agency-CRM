{{-- nx:converted (skin tokens; legacy palette classes removed 2026-10-05) --}}
<div class="mx-auto max-w-2xl">
    <div class="mb-6">
        <h1 class="text-xl font-bold text-[rgb(var(--nx-text))]">eSIM hero</h1>
        <p class="text-sm text-[rgb(var(--nx-text-2))]">Title, description, and up to {{ $maxImages }} images for the storefront’s interchanging-reveal hero. WebP or JPG, ≤600&nbsp;KB. Clear all to fall back to the shipped images.</p>
    </div>

    @if ($saved)
        <div class="mb-4 flex items-center gap-2 rounded-lg bg-[rgb(var(--nx-ok)/0.12)] px-4 py-3 text-sm text-[rgb(var(--nx-ok))]">
            <x-icon name="badge-check" class="h-4 w-4" /> {{ $saved }}
        </div>
    @endif

    <form wire:submit="save" class="space-y-5 rounded-xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-5">
        {{-- Catalogue section heading + subheading (below the hero banner). --}}
        <div class="grid gap-4 rounded-lg border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface-2))] p-4 sm:grid-cols-2">
            <div class="sm:col-span-2">
                <p class="text-xs font-semibold uppercase tracking-wide text-[rgb(var(--nx-text-2))]">Catalogue section heading</p>
                <p class="text-[11px] text-[rgb(var(--nx-text-2))]">The big heading + line shown below the hero banner on the eSIM page.</p>
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">Section heading</label>
                <input type="text" wire:model="section_title" maxlength="60" placeholder="eSIM Plans" class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2.5 text-sm">
                @error('section_title') <p class="mt-1 text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">Section subheading</label>
                <input type="text" wire:model="section_subtitle" maxlength="160" class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2.5 text-sm">
                @error('section_subtitle') <p class="mt-1 text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p> @enderror
            </div>
        </div>

        <div>
            <label class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">Hero title</label>
            <input type="text" wire:model="title" maxlength="120" class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2.5 text-sm">
            @error('title') <p class="mt-1 text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">Description</label>
            <textarea wire:model="description" rows="2" maxlength="250" class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2.5 text-sm"></textarea>
            @error('description') <p class="mt-1 text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p> @enderror
        </div>

        <div>
            <p class="mb-2 text-xs font-medium text-[rgb(var(--nx-text-2))]">Current images ({{ count($images) }}/{{ $maxImages }})</p>
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                @foreach ($images as $i => $src)
                    <div class="group relative aspect-[16/10] overflow-hidden rounded-lg border border-[rgb(var(--nx-line))]">
                        <img src="{{ $src }}" class="h-full w-full object-cover">
                        <button type="button" wire:click="removeImage({{ $i }})" class="absolute right-1 top-1 flex h-6 w-6 items-center justify-center rounded-full bg-black/60 text-white opacity-0 transition group-hover:opacity-100"><x-icon name="x" class="h-3.5 w-3.5" /></button>
                        <span class="absolute bottom-1 left-1 rounded bg-black/60 px-1.5 text-[10px] font-semibold text-white">{{ $i + 1 }}</span>
                    </div>
                @endforeach
            </div>
        </div>

        @if (count($images) < $maxImages)
            <div>
                <p class="mb-2 text-xs font-medium text-[rgb(var(--nx-text-2))]">Add images ({{ $maxImages - count($images) }} slot{{ $maxImages - count($images) === 1 ? '' : 's' }} left)</p>
                <input type="file" wire:model="newImages" multiple accept="image/webp,image/jpeg"
                       class="block w-full text-[11px] text-[rgb(var(--nx-text-2))] file:mr-2 file:rounded file:border-0 file:bg-[rgb(var(--nx-cta-b)/0.1)] file:px-2 file:py-1 file:text-[11px] file:font-medium file:text-[rgb(var(--nx-teal-ink))]">
                @error('newImages') <p class="mt-1 text-[11px] text-[rgb(var(--nx-bad))]">{{ $message }}</p> @enderror
                @error('newImages.*') <p class="mt-1 text-[11px] text-[rgb(var(--nx-bad))]">{{ $message }}</p> @enderror
            </div>
        @endif

        <div class="flex justify-end">
            <button type="submit" wire:loading.attr="disabled" wire:target="save"
                    class="inline-flex items-center gap-2 rounded-lg bg-[rgb(var(--nx-cta-b))] px-5 py-2.5 text-sm font-semibold text-white hover:bg-[rgb(var(--nx-cta-a))] disabled:opacity-60">
                <span wire:loading.remove wire:target="save">Save hero</span>
                <span wire:loading wire:target="save" class="inline-flex items-center gap-2"><x-ui.spinner class="h-4 w-4" /> Saving…</span>
            </button>
        </div>
    </form>
</div>
