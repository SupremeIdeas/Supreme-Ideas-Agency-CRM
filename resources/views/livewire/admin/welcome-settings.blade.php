{{-- nx:converted (skin tokens; legacy palette classes removed 2026-10-05) --}}
@php $inp = 'w-full rounded-lg border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm text-[rgb(var(--nx-text))] focus:border-[rgb(var(--nx-teal))] focus:ring-[rgb(var(--nx-teal))]'; @endphp
<div class="mx-auto max-w-2xl px-4 py-6 sm:px-6">
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-xl font-bold text-[rgb(var(--nx-text))]">Welcome animation</h1>
            <p class="mt-0.5 text-sm text-[rgb(var(--nx-text-2))]">The Aurora entrance new users see once, right after signup.</p>
        </div>
        <a href="{{ route('welcome', ['preview' => 1]) }}" target="_blank"
            class="inline-flex items-center gap-2 rounded-full bg-[rgb(var(--nx-surface-3))] px-4 py-2 text-sm font-semibold text-[rgb(var(--nx-text))] transition hover:bg-[rgb(var(--nx-surface-3))]">
            <x-icon name="zap" class="h-4 w-4" /> Live preview
        </a>
    </div>

    <div class="rounded-2xl border border-[rgb(var(--nx-line)/0.7)] bg-[rgb(var(--nx-surface))] p-5">
        {{-- Enable toggle --}}
        <label class="mb-5 flex items-center gap-3 text-sm font-medium text-[rgb(var(--nx-text))]">
            <input type="checkbox" wire:model="form.enabled" class="rounded border-[rgb(var(--nx-line-strong))] text-[rgb(var(--nx-teal-ink))] focus:ring-[rgb(var(--nx-teal))]">
            Show the welcome animation after signup
        </label>

        {{-- Entrance style (two presets) --}}
        <div class="mb-5">
            <label class="mb-1 block text-xs font-semibold text-[rgb(var(--nx-text-2))]">Entrance style</label>
            <select wire:model="form.style" class="{{ $inp }}">
                <option value="aurora">Aurora — drifting brand blobs</option>
                <option value="spotlight">Spotlight — radial beam + shimmer</option>
            </select>
            @error('form.style') <p class="mt-1 text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p> @enderror
            <p class="mt-1 text-[11px] text-[rgb(var(--nx-text-2))]">Both use your colours below; Spotlight is calmer and more “premium hero”.</p>
        </div>

        {{-- Copy --}}
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="mb-1 block text-xs font-semibold text-[rgb(var(--nx-text-2))]">Welcome text</label>
                <input type="text" wire:model="form.welcome_text" class="{{ $inp }}">
                @error('form.welcome_text') <p class="mt-1 text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="mb-1 block text-xs font-semibold text-[rgb(var(--nx-text-2))]">Tagline</label>
                <input type="text" wire:model="form.tagline_text" class="{{ $inp }}">
                @error('form.tagline_text') <p class="mt-1 text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p> @enderror
            </div>
        </div>

        {{-- Timings --}}
        <h2 class="mb-3 mt-6 text-sm font-semibold text-[rgb(var(--nx-text))]">Timing</h2>
        <div class="grid gap-4 sm:grid-cols-3">
            <div>
                <label class="mb-1 block text-xs font-semibold text-[rgb(var(--nx-text-2))]">Logo reveal <span class="text-[rgb(var(--nx-text-2))]">(ms)</span></label>
                <input type="number" wire:model="form.logo_reveal_speed" class="{{ $inp }}">
                @error('form.logo_reveal_speed') <p class="mt-1 text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="mb-1 block text-xs font-semibold text-[rgb(var(--nx-text-2))]">Tagline delay <span class="text-[rgb(var(--nx-text-2))]">(ms)</span></label>
                <input type="number" wire:model="form.tagline_reveal_delay" class="{{ $inp }}">
                @error('form.tagline_reveal_delay') <p class="mt-1 text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="mb-1 block text-xs font-semibold text-[rgb(var(--nx-text-2))]">Total <span class="text-[rgb(var(--nx-text-2))]">(ms)</span></label>
                <input type="number" wire:model="form.animation_total_duration" class="{{ $inp }}">
                @error('form.animation_total_duration') <p class="mt-1 text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="mb-1 block text-xs font-semibold text-[rgb(var(--nx-text-2))]">Aurora loop <span class="text-[rgb(var(--nx-text-2))]">(s)</span></label>
                <input type="number" wire:model="form.aurora_speed" class="{{ $inp }}">
                @error('form.aurora_speed') <p class="mt-1 text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p> @enderror
            </div>
        </div>

        {{-- Colours --}}
        <h2 class="mb-3 mt-6 text-sm font-semibold text-[rgb(var(--nx-text))]">Aurora colours</h2>
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="mb-1 block text-xs font-semibold text-[rgb(var(--nx-text-2))]">Colour 1</label>
                <input type="color" wire:model="form.brand_color_1" class="h-10 w-full rounded-lg border border-[rgb(var(--nx-line))]">
                @error('form.brand_color_1') <p class="mt-1 text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="mb-1 block text-xs font-semibold text-[rgb(var(--nx-text-2))]">Colour 2</label>
                <input type="color" wire:model="form.brand_color_2" class="h-10 w-full rounded-lg border border-[rgb(var(--nx-line))]">
                @error('form.brand_color_2') <p class="mt-1 text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p> @enderror
            </div>
        </div>

        {{-- Actions --}}
        <div class="mt-6 flex flex-wrap gap-2">
            <button type="button" wire:click="save" wire:loading.attr="disabled"
                class="flex-1 rounded-full bg-[rgb(var(--nx-cta-b))] px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-[rgb(var(--nx-cta-a))] disabled:opacity-60">
                Save settings
            </button>
            <button type="button" wire:click="resetDefaults" wire:confirm="Reset the welcome animation to defaults?"
                class="rounded-full border border-[rgb(var(--nx-line-strong))] px-5 py-2.5 text-sm font-semibold text-[rgb(var(--nx-text-2))] transition hover:bg-[rgb(var(--nx-surface-2))]">
                Reset to default
            </button>
        </div>
    </div>
</div>
