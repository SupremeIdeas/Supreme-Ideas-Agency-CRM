<div class="mx-auto max-w-4xl" data-menu-icons>
    <h1 class="mb-1 text-2xl font-bold text-[rgb(var(--nx-text))]">Menu icons</h1>
    <p class="mb-5 text-sm text-[rgb(var(--nx-text-2))]">
        Every menu item has its own icon slot. The bundled Naara set shows by default (it switches between its light and dark versions on its own);
        upload a <strong>WebP, SVG or PNG</strong> up to 512&nbsp;KB to replace one. A replacement is used in both light and dark mode, so pick artwork that reads on either.
    </p>

    @php
        $packs = \App\Support\IconPacks\IconPacks::all();
        $activePack = \App\Support\IconPacks\IconPacks::activeSlug();
    @endphp
    <div class="mb-5 rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-4" data-icon-pack-card>
        <div class="flex flex-wrap items-end gap-3">
            <div class="min-w-0 flex-1">
                <label for="icon-pack" class="mb-1 block text-sm font-semibold text-[rgb(var(--nx-text))]">Icon pack</label>
                <p class="mb-2 text-xs text-[rgb(var(--nx-text-2))]">One set of icon art, 40 colourways. <strong>Follow the theme</strong> (recommended) picks the pack that matches the site theme, so changing the theme recolours every menu with no extra step. Currently showing: <strong>{{ $packs[$activePack]['label'] ?? $activePack }}</strong>.</p>
                <select id="icon-pack" wire:model="pack" class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm text-[rgb(var(--nx-text))]" data-icon-pack-select>
                    <option value="follow-theme">Follow the theme (recommended)</option>
                    @foreach ($packs as $slug => $p)
                        <option value="{{ $slug }}">{{ $p['label'] }}</option>
                    @endforeach
                </select>
            </div>
            <x-ui.btn variant="primary" type="button" wire:click="savePack" target="savePack" icon="check">Save pack</x-ui.btn>
        </div>
        <div class="mt-3 flex flex-wrap items-center gap-2" aria-label="Preview of the pack in use">
            @foreach (['more/app-more-numbers', 'more/app-more-esims', 'more/app-more-wallet', 'more/app-more-home', 'more/app-more-help-support'] as $pv)
                @php [$pg, $pf] = explode('/', $pv); @endphp
                <span class="flex h-12 w-12 items-center justify-center rounded-xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface-2))]"><img src="{{ \App\Support\IconPacks\IconPacks::url($pg, $pf, $pack === 'follow-theme' ? null : $pack) }}" alt="" width="32" height="32" loading="lazy"></span>
            @endforeach
        </div>
    </div>

    <div class="mb-4 flex flex-wrap items-center gap-2">
        @foreach ($this::SURFACES as $key => $label)
            <button type="button" wire:click="$set('surface', '{{ $key }}')" @class([
                'rounded-full border px-3.5 py-1.5 text-xs font-semibold transition',
                'border-[rgb(var(--nx-cta-b))] bg-[rgb(var(--nx-cta-b))] text-white' => $surface === $key,
                'border-[rgb(var(--nx-line-strong))] text-[rgb(var(--nx-text-2))] hover:text-[rgb(var(--nx-text))]' => $surface !== $key,
            ])>{{ $label }}</button>
        @endforeach
        <input type="search" wire:model.live.debounce.300ms="search" placeholder="Search icons"
               class="ml-auto w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm text-[rgb(var(--nx-text))] sm:w-56">
    </div>

    @if ($target)
        <div class="mb-5 rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-4" data-menu-icons-upload>
            <p class="mb-3 text-sm font-semibold text-[rgb(var(--nx-text))]">Replace: <span class="font-mono text-xs text-[rgb(var(--nx-text-2))]">{{ $target }}</span></p>
            <div class="flex flex-wrap items-center gap-3">
                <input type="file" wire:model="upload" accept="image/webp,image/svg+xml,image/png"
                       class="block min-w-0 flex-1 text-xs text-[rgb(var(--nx-text-2))] file:mr-3 file:rounded-lg file:border-0 file:bg-[rgb(var(--nx-cta-b))] file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-white">
                <x-ui.btn variant="primary" type="button" wire:click="save" target="save" icon="upload">Save icon</x-ui.btn>
                <button type="button" wire:click="choose('')" class="text-xs font-medium text-[rgb(var(--nx-text-2))] hover:underline">Cancel</button>
            </div>
            @error('upload') <p class="mt-2 text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p> @enderror
            <div wire:loading wire:target="upload" class="mt-2 text-xs text-[rgb(var(--nx-text-2))]">Uploading…</div>
        </div>
    @endif

    <p class="mb-2 text-xs text-[rgb(var(--nx-text-2))]">{{ $rows->count() }} icons · {{ $customCount }} replaced</p>

    @if ($rows->isEmpty())
        <p class="rounded-xl border border-dashed border-[rgb(var(--nx-line-strong))] p-6 text-center text-sm text-[rgb(var(--nx-text-2))]">No icon matches that search.</p>
    @endif

    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4">
        @foreach ($rows as $row)
            <div wire:key="mi-{{ $row['slot'] }}" @class(['nx-card relative !p-4 text-center', 'ring-2 ring-[rgb(var(--nx-cta-b))]' => $target === $row['slot']])>
                @if ($row['custom'])
                    <span class="absolute right-2 top-2 rounded-full bg-[rgb(var(--nx-cta-b)/0.14)] px-1.5 py-0.5 text-[9px] font-bold uppercase text-[rgb(var(--nx-teal-ink))]">Custom</span>
                @endif
                <span class="mx-auto flex h-14 w-14 items-center justify-center">
                    <x-master-marketing.menu-icon :slot-key="$row['slot']" fallback="grid" :size="48" />
                </span>
                <p class="mt-2 truncate text-xs font-semibold text-[rgb(var(--nx-text))]" title="{{ $row['slot'] }}">{{ $row['label'] }}</p>
                <div class="mt-1 flex items-center justify-center gap-3 text-[11px] font-medium">
                    <button type="button" wire:click="choose('{{ $row['slot'] }}')" class="text-[rgb(var(--nx-teal-ink))] hover:underline">{{ $row['custom'] ? 'Change' : 'Replace' }}</button>
                    @if ($row['custom'])
                        <button type="button" wire:click="remove('{{ $row['slot'] }}')" wire:confirm="Go back to the built-in icon?" class="text-[rgb(var(--nx-bad))] hover:underline">Reset</button>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
</div>
