{{-- nx:converted (skin tokens; legacy palette classes removed 2026-10-05) --}}
@php($previewStyle = isset($presets[$style]) ? $style : \App\Support\ThemeToggleSettings::DEFAULT)
<div class="mx-auto max-w-4xl px-4 py-6" wire:key="theme-toggle-studio">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-[rgb(var(--nx-text))]">Theme Toggle Studio</h1>
        <p class="mt-1 text-sm text-[rgb(var(--nx-text-2))]">
            Pick the style of the site-wide dark/light switch. One choice covers every header, login page, and menu — live the moment you save.
        </p>
    </div>

    <div class="grid gap-6 lg:grid-cols-[1fr_16rem]">
        <div class="space-y-3">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-[rgb(var(--nx-text-2))]">Choose a style</h2>
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                @foreach ($presets as $slug => $meta)
                    <button type="button" wire:click="selectStyle('{{ $slug }}')" wire:key="tgl-preset-{{ $slug }}"
                            class="group flex items-center justify-between gap-4 rounded-2xl border p-4 text-left transition {{ $style === $slug ? 'border-[rgb(var(--nx-teal))] ring-2 ring-[rgb(var(--nx-teal)/0.4)]' : 'border-[rgb(var(--nx-line))] hover:border-[rgb(var(--nx-line-strong))]' }} bg-[rgb(var(--nx-surface))]">
                        <span>
                            <span class="block text-sm font-semibold text-[rgb(var(--nx-text))]">{{ $meta['label'] }}</span>
                            <span class="block text-xs text-[rgb(var(--nx-text-2))]">{{ $meta['category'] }}</span>
                        </span>
                        <span class="pointer-events-none shrink-0" wire:key="tgl-swatch-{{ $slug }}">
                            @include('components.theme-toggle.'.$slug)
                        </span>
                    </button>
                @endforeach
            </div>

            <button type="button" wire:click="save" wire:loading.attr="disabled"
                    class="mt-3 inline-flex items-center gap-2 rounded-xl bg-[rgb(var(--nx-cta-b))] px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-[rgb(var(--nx-cta-a))] disabled:opacity-60">
                <span wire:loading.remove wire:target="save">Save</span>
                <span wire:loading wire:target="save">Saving…</span>
            </button>
            @if ($saved === $style)
                <span class="ml-3 text-sm font-medium text-[rgb(var(--nx-ok))]">Saved — live now.</span>
            @endif
        </div>

        {{-- Live preview pane --}}
        <div class="lg:sticky lg:top-6">
            <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-[rgb(var(--nx-text-2))]">Live preview</h2>
            <div class="flex h-40 items-center justify-center gap-6 rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface-2))]" wire:key="tgl-preview-{{ $previewStyle }}">
                @include('components.theme-toggle.'.$previewStyle)
            </div>
            <p class="mt-2 text-xs text-[rgb(var(--nx-text-2))]">Click the switch above to preview both light and dark states — nothing is saved until you press Save.</p>
        </div>
    </div>
</div>
