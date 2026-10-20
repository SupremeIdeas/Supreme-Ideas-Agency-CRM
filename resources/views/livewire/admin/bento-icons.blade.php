{{-- nx:converted (skin tokens; legacy palette classes removed 2026-10-05) --}}
<div class="mx-auto max-w-3xl">
    <div class="mb-6">
        <h1 class="text-xl font-bold text-[rgb(var(--nx-text))]">Bento icons</h1>
        <p class="text-sm text-[rgb(var(--nx-text-2))]">The 3D illustrated icon on every product card. Upload a replacement image and tune its opacity per card — changes go live everywhere with no deploy. Icons are transparent WebP/PNG, shown at their shipped default until you upload your own.</p>
    </div>

    @php($groups = [
        'showcase' => ['Homepage cards', 'The icon sits as a background watermark behind the text — opacity is the fade of that watermark (default 80%).'],
        'tile' => ['Action tiles', 'The icon sits in the foreground of the compact tiles (Browse by country / Check compatibility / Buy eSIM / Get number). Opacity default 100%.'],
    ])

    @foreach ($groups as $group => [$groupTitle, $groupHint])
        <div class="mb-4 mt-2">
            <h2 class="text-sm font-bold uppercase tracking-wide text-[rgb(var(--nx-text-2))]">{{ $groupTitle }}</h2>
            <p class="text-xs text-[rgb(var(--nx-text-2))]">{{ $groupHint }}</p>
        </div>

        <div class="mb-8 space-y-4">
            @foreach ($cards as $key => $def)
                @continue($def['group'] !== $group)
                @php($icon = \App\Support\BentoIcons::icon($key))
                <div wire:key="bento-{{ $key }}"
                     x-data="{ op: @entangle('opacity.'.$key).live, sc: @entangle('scale.'.$key).live }"
                     class="rounded-xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-5">
                    <div class="flex flex-col gap-5 sm:flex-row sm:items-start">
                        {{-- Live preview on a card-like surface (reflects size + opacity). --}}
                        <div class="flex h-28 w-28 shrink-0 items-center justify-center overflow-hidden rounded-2xl border border-[rgb(var(--nx-line))] bg-gradient-to-br from-[rgb(var(--nx-surface))] to-[rgb(var(--nx-surface-2))]">
                            @if ($icon)
                                <img src="{{ $icon }}" alt="{{ $def['label'] }}"
                                     class="h-14 w-14 object-contain transition-transform"
                                     :style="`opacity: ${op/100}; transform: scale(${sc})`">
                            @else
                                <div class="flex h-14 w-14 items-center justify-center rounded-xl bg-[rgb(var(--nx-surface-3))] text-[rgb(var(--nx-text-2))]">—</div>
                            @endif
                        </div>

                        <div class="min-w-0 flex-1">
                            <div class="flex items-center justify-between gap-3">
                                <div>
                                    <p class="font-semibold text-[rgb(var(--nx-text))]">{{ $def['label'] }}</p>
                                    <p class="text-xs text-[rgb(var(--nx-text-2))]">{{ $key }}</p>
                                </div>
                                @if ($saved === $key)
                                    <span class="rounded-full bg-[rgb(var(--nx-ok)/0.16)] px-2.5 py-1 text-xs font-semibold text-[rgb(var(--nx-ok))]">Saved</span>
                                @endif
                            </div>

                            {{-- Upload --}}
                            <label class="mt-3 block text-xs font-medium text-[rgb(var(--nx-text-2))]">Replace icon (transparent WebP/PNG, ≤ 800 KB)</label>
                            <input type="file" wire:model="images.{{ $key }}" accept="image/webp,image/png,image/jpeg"
                                   class="mt-1 block w-full text-sm text-[rgb(var(--nx-text-2))] file:mr-3 file:rounded-lg file:border-0 file:bg-[rgb(var(--nx-cta-b)/0.1)] file:px-3 file:py-1.5 file:text-sm file:font-semibold file:text-[rgb(var(--nx-teal-ink))] hover:file:bg-[rgb(var(--nx-cta-b)/0.2)]">
                            <div wire:loading wire:target="images.{{ $key }}" class="mt-1 text-xs text-[rgb(var(--nx-text-2))]">Uploading…</div>
                            @error('images.'.$key) <p class="mt-1 text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p> @enderror

                            {{-- Opacity slider --}}
                            <div class="mt-4">
                                <div class="flex items-center justify-between text-xs font-medium text-[rgb(var(--nx-text-2))]">
                                    <span>Opacity</span>
                                    <span class="tabular-nums text-[rgb(var(--nx-teal-ink))]" x-text="op + '%'"></span>
                                </div>
                                <input type="range" min="20" max="100" step="5" x-model.number="op"
                                       class="mt-1.5 w-full accent-primary">
                            </div>
                            @error('opacity.'.$key) <p class="mt-1 text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p> @enderror

                            {{-- Size (boldness) slider --}}
                            <div class="mt-4">
                                <div class="flex items-center justify-between text-xs font-medium text-[rgb(var(--nx-text-2))]">
                                    <span>Size (boldness)</span>
                                    <span class="tabular-nums text-[rgb(var(--nx-teal-ink))]" x-text="Number(sc).toFixed(2) + '×'"></span>
                                </div>
                                <input type="range" min="0.5" max="3" step="0.05" x-model.number="sc"
                                       class="mt-1.5 w-full accent-primary">
                            </div>
                            @error('scale.'.$key) <p class="mt-1 text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p> @enderror

                            <div class="mt-4 flex items-center gap-2">
                                <button type="button" wire:click="save('{{ $key }}')" wire:loading.attr="disabled" wire:target="save('{{ $key }}'),images.{{ $key }}"
                                        class="rounded-lg bg-[rgb(var(--nx-cta-b))] px-4 py-2 text-sm font-semibold text-white hover:bg-[rgb(var(--nx-cta-a))] disabled:opacity-60">
                                    <span wire:loading.remove wire:target="save('{{ $key }}')">Save</span>
                                    <span wire:loading wire:target="save('{{ $key }}')">Saving…</span>
                                </button>
                                <button type="button" wire:click="revert('{{ $key }}')"
                                        class="rounded-lg border border-[rgb(var(--nx-line))] px-4 py-2 text-sm font-semibold text-[rgb(var(--nx-text-2))] hover:bg-[rgb(var(--nx-surface-2))]">
                                    Reset
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endforeach
</div>
