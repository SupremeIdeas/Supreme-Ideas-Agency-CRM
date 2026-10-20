{{-- nx:converted (skin tokens; legacy palette classes removed 2026-10-05) --}}
<div class="mx-auto max-w-2xl">
    <h1 class="mb-1 text-2xl font-bold text-[rgb(var(--nx-text))]">Splash screen</h1>
    <p class="mb-6 text-sm text-[rgb(var(--nx-text-2))]">The branded launch screen — product logo with “{{ $brand_tagline ?: 'from Supreme Ideas' }}” beneath. Changes apply immediately, no redeploy.</p>

    @if ($saved)
        <div class="mb-6 flex items-center gap-2 rounded-lg bg-[rgb(var(--nx-ok)/0.12)] p-3 text-sm text-[rgb(var(--nx-ok))]">
            <x-icon name="badge-check" class="h-4 w-4" /> {{ $saved }}
        </div>
    @endif

    <form wire:submit="save" class="space-y-5 rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-6">
        <label class="flex items-center justify-between">
            <span class="text-sm font-medium text-[rgb(var(--nx-text))]">Show splash on app open</span>
            <input type="checkbox" wire:model="enabled" class="h-5 w-5 rounded text-[rgb(var(--nx-teal-ink))]">
        </label>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
                <label class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">Product name</label>
                <input wire:model="product_name" class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm">
                @error('product_name') <span class="text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</span> @enderror
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">“from …” tagline</label>
                <input wire:model="brand_tagline" class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm">
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">Minimum display (ms, max 4000)</label>
                <input type="number" wire:model="duration_ms" class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm">
                @error('duration_ms') <span class="text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</span> @enderror
            </div>
            <label class="flex items-center gap-2 self-end pb-2 text-sm text-[rgb(var(--nx-text-2))]">
                <input type="checkbox" wire:model="show_once_per_session" class="rounded text-[rgb(var(--nx-teal-ink))]"> Show once per session
            </label>
        </div>

        <div class="border-t border-[rgb(var(--nx-line))] pt-4">
            <h2 class="mb-1 text-xs font-semibold uppercase tracking-wide text-[rgb(var(--nx-text-2))]">Logos — light &amp; dark</h2>
            <p class="mb-3 text-xs text-[rgb(var(--nx-text-2))]">
                Upload PNG, JPEG, WebP, GIF or SVG (up to 2 MB; SVGs are sanitized). Or paste a URL. Set BOTH light and
                dark variants so the logo always reads on its background. Stored on Wasabi if configured, otherwise on
                this server.
            </p>

            @if ($uploadError)
                <div class="mb-3 rounded-lg bg-[rgb(var(--nx-bad)/0.1)] p-2 text-xs text-[rgb(var(--nx-bad))]">{{ $uploadError }}</div>
            @endif

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                @foreach ([
                    {{-- nx:allow:start literal preview backdrops for the logo --}}'product_logo_light' => ['Product logo (light)', '#F8F9FA'],
                    'product_logo_dark' => ['Product logo (dark)', '#0D1B2A'],
                    'brand_logo_light' => ['Brand logo (light)', '#F8F9FA'],
                    'brand_logo_dark' => ['Brand logo (dark)', '#0D1B2A'],{{-- nx:allow:end --}}
                ] as $field => [$label, $preview])
                    <div wire:key="logo-{{ $field }}">
                        <label class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">{{ $label }}</label>
                        <div class="flex items-center gap-2">
                            <div class="flex h-11 w-16 shrink-0 items-center justify-center overflow-hidden rounded-lg border border-[rgb(var(--nx-line))]" style="background: {{ $preview }}">
                                @if ($$field)
                                    <img src="{{ $$field }}" alt="{{ $label }}" class="max-h-9 max-w-[3.5rem] object-contain">
                                @else
                                    <x-icon name="package" class="h-4 w-4 {{ str_contains($preview, '0D1B2A') ? 'text-white/85' : 'text-black/70' }}" />
                                @endif
                            </div>
                            <div class="min-w-0 flex-1">
                                <input wire:model="{{ $field }}" placeholder="https://…  or upload →" class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-xs">
                                <label class="mt-1 flex cursor-pointer items-center gap-1 text-xs font-medium text-[rgb(var(--nx-teal-ink))] hover:underline">
                                    <x-icon name="inbox" class="h-3.5 w-3.5" />
                                    <span wire:loading.remove wire:target="{{ $field }}_file">Upload file</span>
                                    <span wire:loading wire:target="{{ $field }}_file">Uploading…</span>
                                    <input type="file" wire:model="{{ $field }}_file" accept="{{ \App\Support\MediaStorage::acceptAttribute() }}" class="hidden">
                                </label>
                            </div>
                        </div>
                        @error($field) <span class="text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</span> @enderror
                    </div>
                @endforeach
            </div>
        </div>

        <div class="flex justify-end">
            <button type="submit" wire:loading.attr="disabled" wire:target="save"
                    class="inline-flex items-center gap-2 rounded-lg bg-[rgb(var(--nx-cta-b))] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[rgb(var(--nx-cta-a))] disabled:opacity-60">
                <span wire:loading.remove wire:target="save">Save splash</span>
                <span wire:loading wire:target="save" class="inline-flex items-center gap-2"><x-ui.spinner class="h-4 w-4" /> Saving…</span>
            </button>
        </div>
    </form>
</div>
