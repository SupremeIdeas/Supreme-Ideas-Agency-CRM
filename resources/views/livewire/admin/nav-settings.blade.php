{{-- nx:converted (skin tokens; legacy palette classes removed 2026-10-05) --}}
<div class="mx-auto max-w-3xl space-y-8">
    <div>
        <h1 class="text-xl font-bold text-[rgb(var(--nx-text))]">Bottom nav</h1>
        <p class="mt-1 text-sm text-[rgb(var(--nx-text-2))]">
            Drag to reorder, or turn an item off to replace it with whatever moves up next.
            A hidden or reordered item still only shows for viewers who are actually eligible
            for it (role, feature flags, entitlements) — this never exposes a link someone
            shouldn't have.
        </p>
    </div>

    {{-- ============================ MAIN BOTTOM NAV ============================ --}}
    <section class="rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-5">
        <div class="mb-3 flex items-center justify-between">
            <h2 class="text-sm font-semibold text-[rgb(var(--nx-text))]">Main navigation</h2>
            <button type="button" wire:click="resetMain" wire:confirm="Reset the main bottom nav to platform defaults?"
                    class="text-xs font-medium text-[rgb(var(--nx-text-2))] hover:text-[rgb(var(--nx-bad))]">Reset to defaults</button>
        </div>

        <ul class="space-y-1.5" wire:key="main-{{ implode('-', $mainOrder) }}"
            x-data="{ dragId: null, order: @js($mainOrder),
                drop(target) {
                    if (this.dragId === null || this.dragId === target) return;
                    const o = [...this.order];
                    const from = o.indexOf(this.dragId), to = o.indexOf(target);
                    if (from < 0 || to < 0) return;
                    o.splice(to, 0, o.splice(from, 1)[0]);
                    this.order = o;
                    $wire.reorderMain(o); this.dragId = null;
                } }">
            @foreach ($mainOrder as $i => $key)
                @if ($i === 4)
                    <li class="my-2 flex items-center gap-2 text-[11px] font-semibold uppercase tracking-wide text-[rgb(var(--nx-text-2))]">
                        <span class="h-px flex-1 bg-[rgb(var(--nx-surface-3))]"></span>
                        Bottom bar above · More menu below
                        <span class="h-px flex-1 bg-[rgb(var(--nx-surface-3))]"></span>
                    </li>
                @endif
                <li draggable="true"
                    @dragstart="dragId = '{{ $key }}'" @dragover.prevent
                    @drop.prevent="drop('{{ $key }}')"
                    :class="dragId === '{{ $key }}' && 'opacity-50'"
                    @class([
                        'flex items-center gap-3 rounded-xl border p-2.5 transition',
                        'border-[rgb(var(--nx-line)/0.7)] bg-[rgb(var(--nx-surface-2))]' => ! in_array($key, $mainHidden, true),
                        'border-dashed border-[rgb(var(--nx-line))] opacity-50' => in_array($key, $mainHidden, true),
                    ])>
                    <span class="cursor-grab text-[rgb(var(--nx-text-2))]" title="Drag to reorder"><x-icon name="grid" class="h-4 w-4" /></span>
                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-[rgb(var(--nx-cta-b)/0.1)] text-[rgb(var(--nx-teal-ink))]">
                        <x-icon name="{{ $mainCatalog[$key]['icon'] ?? 'grid' }}" class="h-4 w-4" />
                    </span>
                    <span class="min-w-0 flex-1 truncate text-sm font-medium text-[rgb(var(--nx-text))]">{{ $mainCatalog[$key]['label'] ?? $key }}</span>
                    <button type="button" wire:click="toggleMain('{{ $key }}')" role="switch"
                            aria-checked="{{ in_array($key, $mainHidden, true) ? 'false' : 'true' }}"
                            aria-label="Toggle {{ $mainCatalog[$key]['label'] ?? $key }}"
                            class="relative inline-flex h-5 w-9 shrink-0 items-center rounded-full transition {{ in_array($key, $mainHidden, true) ? 'bg-[rgb(var(--nx-line-strong))]' : 'bg-[rgb(var(--nx-cta-b))]' }}">
                        <span class="inline-block h-4 w-4 transform rounded-full bg-[rgb(var(--nx-surface))] shadow transition {{ in_array($key, $mainHidden, true) ? 'translate-x-0.5' : 'translate-x-4' }}"></span>
                    </button>
                </li>
            @endforeach
        </ul>
    </section>

    {{-- ======================== NUMBERS SECTION BOTTOM NAV ======================== --}}
    <section class="rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-5">
        <div class="mb-1 flex items-center justify-between">
            <h2 class="text-sm font-semibold text-[rgb(var(--nx-text))]">Numbers section navigation</h2>
            <button type="button" wire:click="resetNumbers" wire:confirm="Reset the Numbers bottom nav to platform defaults?"
                    class="text-xs font-medium text-[rgb(var(--nx-text-2))] hover:text-[rgb(var(--nx-bad))]">Reset to defaults</button>
        </div>
        <p class="mb-3 text-xs text-[rgb(var(--nx-text-2))]">Always exactly 4 shown — the top 4 active rows here.</p>

        <ul class="space-y-1.5" wire:key="numbers-{{ implode('-', $numbersOrder) }}"
            x-data="{ dragId: null, order: @js($numbersOrder),
                drop(target) {
                    if (this.dragId === null || this.dragId === target) return;
                    const o = [...this.order];
                    const from = o.indexOf(this.dragId), to = o.indexOf(target);
                    if (from < 0 || to < 0) return;
                    o.splice(to, 0, o.splice(from, 1)[0]);
                    this.order = o;
                    $wire.reorderNumbers(o); this.dragId = null;
                } }">
            @foreach ($numbersOrder as $i => $key)
                @if ($i === 4)
                    <li class="my-2 flex items-center gap-2 text-[11px] font-semibold uppercase tracking-wide text-[rgb(var(--nx-text-2))]">
                        <span class="h-px flex-1 bg-[rgb(var(--nx-surface-3))]"></span>
                        Shown · Not shown
                        <span class="h-px flex-1 bg-[rgb(var(--nx-surface-3))]"></span>
                    </li>
                @endif
                <li draggable="true"
                    @dragstart="dragId = '{{ $key }}'" @dragover.prevent
                    @drop.prevent="drop('{{ $key }}')"
                    :class="dragId === '{{ $key }}' && 'opacity-50'"
                    @class([
                        'flex items-center gap-3 rounded-xl border p-2.5 transition',
                        'border-[rgb(var(--nx-line)/0.7)] bg-[rgb(var(--nx-surface-2))]' => ! in_array($key, $numbersHidden, true),
                        'border-dashed border-[rgb(var(--nx-line))] opacity-50' => in_array($key, $numbersHidden, true),
                    ])>
                    <span class="cursor-grab text-[rgb(var(--nx-text-2))]" title="Drag to reorder"><x-icon name="grid" class="h-4 w-4" /></span>
                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-[rgb(var(--nx-cta-b)/0.1)] text-[rgb(var(--nx-teal-ink))]">
                        <x-icon name="{{ $numbersCatalog[$key]['icon'] ?? 'grid' }}" class="h-4 w-4" />
                    </span>
                    <span class="min-w-0 flex-1 truncate text-sm font-medium text-[rgb(var(--nx-text))]">{{ $numbersCatalog[$key]['label'] ?? $key }}</span>
                    <button type="button" wire:click="toggleNumbers('{{ $key }}')" role="switch"
                            aria-checked="{{ in_array($key, $numbersHidden, true) ? 'false' : 'true' }}"
                            aria-label="Toggle {{ $numbersCatalog[$key]['label'] ?? $key }}"
                            class="relative inline-flex h-5 w-9 shrink-0 items-center rounded-full transition {{ in_array($key, $numbersHidden, true) ? 'bg-[rgb(var(--nx-line-strong))]' : 'bg-[rgb(var(--nx-cta-b))]' }}">
                        <span class="inline-block h-4 w-4 transform rounded-full bg-[rgb(var(--nx-surface))] shadow transition {{ in_array($key, $numbersHidden, true) ? 'translate-x-0.5' : 'translate-x-4' }}"></span>
                    </button>
                </li>
            @endforeach
        </ul>
    </section>
</div>
