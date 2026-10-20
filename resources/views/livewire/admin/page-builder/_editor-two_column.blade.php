{{-- nx:converted (skin tokens; legacy palette classes removed 2026-10-05) --}}
@php $inp = 'w-full rounded-lg border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm text-[rgb(var(--nx-text))] focus:border-[rgb(var(--nx-teal))] focus:ring-[rgb(var(--nx-teal))]'; @endphp
<div class="space-y-4">
    <div>
        <label class="mb-1 block text-xs font-semibold text-[rgb(var(--nx-text-2))]">Eyebrow</label>
        <input type="text" wire:model="config.eyebrow" class="{{ $inp }}" maxlength="80">
    </div>
    <div>
        <label class="mb-1 block text-xs font-semibold text-[rgb(var(--nx-text-2))]">Headline</label>
        <input type="text" wire:model="config.headline" class="{{ $inp }}" maxlength="120">
        @error('config.headline') <p class="mt-1 text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p> @enderror
    </div>
    <div>
        <label class="mb-1 block text-xs font-semibold text-[rgb(var(--nx-text-2))]">Body</label>
        <textarea wire:model="config.body" rows="3" class="{{ $inp }}" maxlength="600"></textarea>
    </div>

    <div class="grid grid-cols-2 gap-3">
        <div>
            <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-[rgb(var(--nx-text-2))]">Image side</label>
            <select wire:model="config.image_side" class="{{ $inp }}">
                <option value="right">Right</option>
                <option value="left">Left</option>
            </select>
        </div>
        <div>
            <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-[rgb(var(--nx-text-2))]">Background</label>
            <select wire:model="config.bg" class="{{ $inp }}">
                <option value="transparent">None</option>
                <option value="tint">Brand tint</option>
                <option value="dark">Dark</option>
            </select>
        </div>
    </div>

    <div class="grid gap-3 sm:grid-cols-2">
        <div>
            <label class="mb-1 block text-xs font-semibold text-[rgb(var(--nx-text-2))]">CTA label</label>
            <input type="text" wire:model="config.cta_label" placeholder="Optional" class="{{ $inp }}">
        </div>
        <div>
            <label class="mb-1 block text-xs font-semibold text-[rgb(var(--nx-text-2))]">CTA target</label>
            <input type="text" wire:model="config.cta_target" placeholder="URL, /path, or route" class="{{ $inp }}">
        </div>
    </div>

    {{-- Image (optional — graceful text-only fallback when empty) --}}
    <div>
        <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-[rgb(var(--nx-text-2))]">Image <span class="font-normal normal-case opacity-60">(optional — omitted = clean text-only)</span></label>
        @if (! empty($config['image']))
            <div class="group relative mb-2 aspect-video max-w-xs overflow-hidden rounded-lg border border-[rgb(var(--nx-line))]">
                <img src="{{ $config['image'] }}" alt="" class="h-full w-full object-cover">
                <button type="button" wire:click="clearImage" class="absolute right-1 top-1 rounded-full bg-black/60 p-1 text-white opacity-0 transition group-hover:opacity-100"><x-icon name="x" class="h-3 w-3" /></button>
            </div>
        @endif
        <div class="flex items-center gap-2">
            <input type="file" wire:model="upload" accept="image/webp,image/png,image/jpeg"
                class="block w-full text-xs text-[rgb(var(--nx-text-2))] file:mr-2 file:rounded-full file:border-0 file:bg-[rgb(var(--nx-cta-b)/0.1)] file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-[rgb(var(--nx-teal-ink))]">
            <button type="button" wire:click="addImage" wire:loading.attr="disabled" wire:target="upload,addImage"
                class="shrink-0 rounded-full bg-[rgb(var(--nx-cta-b)/0.1)] px-3 py-1.5 text-xs font-semibold text-[rgb(var(--nx-teal-ink))] hover:bg-[rgb(var(--nx-cta-b)/0.2)]">Set</button>
        </div>
        <div wire:loading wire:target="upload" class="mt-1 text-xs text-[rgb(var(--nx-text-2))]">Uploading…</div>
        @error('upload') <p class="mt-1 text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p> @enderror
    </div>
</div>
