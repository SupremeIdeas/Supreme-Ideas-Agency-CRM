@props([
    'placeholder' => 'Search…',
    'searchVar' => 'q',      // Alpine var (in the parent scope) holding the query
    'viewVar' => 'view',     // Alpine var holding 'list' | 'grid'
])

{{--
    Shared collection controls (Numbers V6 §3): one search box + a grid/list
    toggle, reused by the pickers, Contacts, and any browse surface. List is the
    default everywhere; switching to Grid is remembered per-device via Alpine
    $persist (never changes the platform default for other users/sessions).

    Lives inside a parent x-data that declares the two vars, e.g.:
      x-data="{ q: '', view: $persist('list').as('nx_collection_view') }"
--}}
<div {{ $attributes->merge(['class' => 'flex items-center gap-2']) }}>
    <div class="relative flex-1">
        <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-[rgb(var(--nx-text-2))]" />
        <input type="text" x-model="{{ $searchVar }}" placeholder="{{ $placeholder }}"
               class="w-full rounded-xl border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] py-2.5 pl-9 pr-3 text-sm text-[rgb(var(--nx-text))] placeholder-[rgb(var(--nx-text-2))] focus:border-[rgb(var(--nx-teal))] focus:ring-2 focus:ring-[rgb(var(--nx-teal)/0.4)]   ">
    </div>

    <div class="flex shrink-0 items-center gap-1 rounded-xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface-3))] p-1  ">
        <button type="button" @click="{{ $viewVar }} = 'list'" aria-label="List view"
                :class="{{ $viewVar }} === 'list' ? 'bg-[rgb(var(--nx-surface))] text-[rgb(var(--nx-teal-ink))] shadow-sm  ' : 'text-[rgb(var(--nx-text-2))]'"
                class="flex h-8 w-8 items-center justify-center rounded-lg transition">
            <x-icon name="list" class="h-4 w-4" />
        </button>
        <button type="button" @click="{{ $viewVar }} = 'grid'" aria-label="Grid view"
                :class="{{ $viewVar }} === 'grid' ? 'bg-[rgb(var(--nx-surface))] text-[rgb(var(--nx-teal-ink))] shadow-sm  ' : 'text-[rgb(var(--nx-text-2))]'"
                class="flex h-8 w-8 items-center justify-center rounded-lg transition">
            <x-icon name="grid" class="h-4 w-4" />
        </button>
    </div>
</div>
