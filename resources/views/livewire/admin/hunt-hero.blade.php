{{-- nx:converted (skin tokens; legacy palette classes removed 2026-10-05) --}}
<div class="mx-auto max-w-2xl">
    <div class="mb-6">
        <h1 class="text-xl font-bold text-[rgb(var(--nx-text))]">The Hunt hero</h1>
        <p class="text-sm text-[rgb(var(--nx-text-2))]">The same hero system as the customer dashboard home — title, description, and an optional light/dark image — shown at the top of the The Hunt storefront. Customised independently from the dashboard hero.</p>
    </div>

    @if ($saved)
        <div class="mb-4 flex items-center gap-2 rounded-lg bg-[rgb(var(--nx-ok)/0.12)] px-4 py-3 text-sm text-[rgb(var(--nx-ok))]">
            <x-icon name="badge-check" class="h-4 w-4" /> {{ $saved }}
        </div>
    @endif

    <form wire:submit="save" class="space-y-5 rounded-xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-5">
        <div class="grid gap-4 sm:grid-cols-[1fr,10rem]">
            <div>
                <label class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">Hero title</label>
                <input type="text" wire:model="title" maxlength="40"
                       placeholder="{{ \App\Support\HuntHeroBackground::DEFAULT_TITLE }}"
                       class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2.5 text-sm text-[rgb(var(--nx-text))] placeholder:text-[rgb(var(--nx-text-2))]">
                <p class="mt-1 text-[11px] text-[rgb(var(--nx-text-2))]">Leave blank for the default. The first word renders plain; the rest renders in the accent gradient.</p>
                @error('title') <p class="mt-1 text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">Title size</label>
                <select wire:model="title_size" class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2.5 text-sm text-[rgb(var(--nx-text))]">
                    @foreach (\App\Support\HuntHeroBackground::TITLE_SIZES as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
                @error('title_size') <p class="mt-1 text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p> @enderror
            </div>
        </div>

        <div>
            <label class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">Description line under the title</label>
            <input type="text" wire:model="description" maxlength="120"
                   placeholder="{{ \App\Support\HuntHeroBackground::DEFAULT_DESCRIPTION }}"
                   class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2.5 text-sm text-[rgb(var(--nx-text))] placeholder:text-[rgb(var(--nx-text-2))]">
            <p class="mt-1 text-[11px] text-[rgb(var(--nx-text-2))]">One short sentence. Leave blank to use the default.</p>
            @error('description') <p class="mt-1 text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p> @enderror
        </div>

        <div class="flex items-center justify-between gap-4 rounded-lg border border-[rgb(var(--nx-line))] p-3">
            <span>
                <span class="block text-sm font-medium text-[rgb(var(--nx-text))]">Show hero image</span>
                <span class="mt-0.5 block text-xs text-[rgb(var(--nx-text-2))]">Turn the image off without removing the uploaded art.</span>
            </span>
            <x-ui.switch wire:model="enabled" label="Show hero image" />
        </div>

        <div>
            <div class="mb-2 flex items-center justify-between">
                <p class="text-xs font-medium text-[rgb(var(--nx-text-2))]">Hero image (optional)</p>
                @if ($isSet)
                    <button type="button" wire:click="removeImages" wire:confirm="Remove your uploaded hero images and go back to the default artwork?"
                            class="text-xs font-medium text-[rgb(var(--nx-bad))] hover:underline">Reset to default art</button>
                @endif
            </div>
            <p class="mb-3 text-[11px] text-[rgb(var(--nx-text-2))]">WebP or JPG, 1600×800px recommended (2:1) — will crop to fit, under 600&nbsp;KB. Switches automatically with the user's light/dark theme. Until you upload your own, the default artwork shows; turn the switch above off for a title-only header.</p>
            <div class="grid gap-4 sm:grid-cols-2">
                @foreach ([['hero_light', 'Light mode', $currentLight], ['hero_dark', 'Dark mode', $currentDark]] as [$field, $label, $current])
                    <div>
                        <label class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">{{ $label }}</label>
                        <div class="mb-2 flex aspect-[2/1] items-center justify-center overflow-hidden rounded-lg border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface-2))]">
                            @if ($this->{$field} && $this->{$field}->isPreviewable())
                                <img src="{{ $this->{$field}->temporaryUrl() }}" class="h-full w-full object-cover">
                            @elseif ($current)
                                <img src="{{ $current }}" class="h-full w-full object-cover">
                            @else
                                <span class="text-[11px] text-[rgb(var(--nx-text-2))]">No image — title-only header</span>
                            @endif
                        </div>
                        <input type="file" wire:model="{{ $field }}" accept="image/webp,image/jpeg"
                               class="block w-full text-xs text-[rgb(var(--nx-text-2))] file:mr-3 file:rounded-lg file:border-0 file:bg-[rgb(var(--nx-cta-b)/0.1)] file:px-3 file:py-1.5 file:text-xs file:font-medium file:text-[rgb(var(--nx-teal-ink))]">
                        @error($field) <p class="mt-1 text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p> @enderror
                    </div>
                @endforeach
            </div>
        </div>

        <div class="flex justify-end">
            <button type="submit" wire:loading.attr="disabled" wire:target="save"
                    class="inline-flex items-center gap-2 rounded-lg bg-[rgb(var(--nx-cta-b))] px-5 py-2.5 text-sm font-semibold text-white hover:bg-[rgb(var(--nx-cta-a))] disabled:opacity-60">
                <span wire:loading.remove wire:target="save" class="inline-flex items-center gap-2"><x-icon name="badge-check" class="h-4 w-4" /> Save hero</span>
                <span wire:loading wire:target="save" class="inline-flex items-center gap-2"><x-ui.spinner class="h-4 w-4" /> Saving…</span>
            </button>
        </div>
    </form>
</div>
