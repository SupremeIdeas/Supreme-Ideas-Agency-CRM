{{-- The one emoji picker, platform-wide (Help Center / Nia overhaul). Drop
     anywhere inside a form; it $dispatch()es "emoji-picked" with the chosen
     glyph, which a parent x-data listens for and appends to its own model —
     see support-chat.blade.php / support-queue.blade.php for the two current
     call sites (customer composer, staff reply box). --}}
<div class="relative" x-data="emojiPicker()" @click.outside="close()">
    <button type="button" @click="toggle()" aria-label="Choose an emoji"
            {{ $attributes->merge(['class' => 'flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-[rgb(var(--nx-text-2))] hover:bg-[rgb(var(--nx-surface-3))]  ']) }}>
        <x-icon name="smile" class="h-5 w-5" />
    </button>

    {{-- Centered on the trigger button (owner request) rather than pinned to
         one edge — `left-1/2 -translate-x-1/2` keeps the 288px panel centred
         regardless of which side of the composer the trigger sits on. --}}
    <div x-show="open" x-cloak x-transition.origin.bottom
         class="absolute bottom-12 left-1/2 z-50 w-72 -translate-x-1/2 overflow-hidden rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] shadow-2xl  ">
        <div class="border-b border-[rgb(var(--nx-line))] p-2 ">
            <div class="flex items-center gap-2 rounded-lg bg-[rgb(var(--nx-surface-3))] px-2.5 py-1.5 ">
                <x-icon name="search" class="h-4 w-4 text-[rgb(var(--nx-text-2))]" />
                <input type="text" x-model="search" placeholder="Search emojis…"
                       class="min-w-0 flex-1 border-0 bg-transparent p-0 text-sm text-[rgb(var(--nx-text))] focus:ring-0 " />
            </div>
        </div>

        {{-- Category tabs: real SVG icons (site rule — no emoji in chrome),
             referencing symbols already inlined once by icon-sprite.blade.php. --}}
        <div class="flex items-center gap-0.5 overflow-x-auto border-b border-[rgb(var(--nx-line))] p-1.5 " x-show="search === ''"
             x-data="{ tabIcon: (slug) => ({ smileys_emotion: 'smile', people_body: 'users', animals_nature: 'leaf', food_drink: 'coffee', travel_places: 'plane', activities: 'gamepad', objects: 'bulb', symbols: 'hash', flags: 'flag' }[slug] ?? 'star') }">
            <button type="button" @click="activeGroup = 'recent'" :class="activeGroup === 'recent' ? 'bg-[rgb(var(--nx-cta-b)/0.1)] text-[rgb(var(--nx-teal-ink))]' : 'text-[rgb(var(--nx-text-2))]'"
                    class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg" title="Recently used">
                <x-icon name="clock" class="h-4 w-4" />
            </button>
            <template x-for="g in groups" :key="g.slug">
                <button type="button" @click="activeGroup = g.slug" :class="activeGroup === g.slug ? 'bg-[rgb(var(--nx-cta-b)/0.1)] text-[rgb(var(--nx-teal-ink))]' : 'text-[rgb(var(--nx-text-2))]'"
                        class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg" :title="g.label">
                    <svg class="h-4 w-4" fill="none" aria-hidden="true"><use :href="'#i-' + tabIcon(g.slug)"></use></svg>
                </button>
            </template>
        </div>

        <div class="h-64 overflow-y-auto p-3">
            <p x-show="loading" class="py-8 text-center text-xs text-[rgb(var(--nx-text-2))]">Loading emojis…</p>

            <template x-if="!loading && search === '' && activeGroup === 'recent'">
                <div>
                    <p class="mb-2 text-[11px] font-semibold uppercase tracking-wide text-[rgb(var(--nx-text-2))]">Recently used</p>
                    <p x-show="recents.length === 0" class="text-xs text-[rgb(var(--nx-text-2))]">You haven't picked any emojis yet.</p>
                    <div class="grid grid-cols-7 gap-1" x-show="recents.length > 0">
                        <template x-for="e in recents" :key="e">
                            <button type="button" @click="pick(e)" class="flex h-8 w-8 items-center justify-center rounded-lg text-xl hover:bg-[rgb(var(--nx-surface-3))] " x-text="e"></button>
                        </template>
                    </div>
                </div>
            </template>

            <template x-if="!loading && !(search === '' && activeGroup === 'recent')">
                <div class="space-y-3">
                    <template x-for="g in (search !== '' ? filtered : groups.filter(g => g.slug === activeGroup))" :key="g.slug">
                        <div>
                            <p class="mb-2 text-[11px] font-semibold uppercase tracking-wide text-[rgb(var(--nx-text-2))]" x-text="g.label"></p>
                            <div class="grid grid-cols-7 gap-1">
                                <template x-for="item in g.emojis" :key="item.e">
                                    <button type="button" @click="pick(item.e)" :title="item.n"
                                            class="flex h-8 w-8 items-center justify-center rounded-lg text-xl hover:bg-[rgb(var(--nx-surface-3))] " x-text="item.e"></button>
                                </template>
                            </div>
                        </div>
                    </template>
                    <p x-show="search !== '' && filtered.length === 0" class="py-8 text-center text-xs text-[rgb(var(--nx-text-2))]">No emojis match "<span x-text="search"></span>".</p>
                </div>
            </template>
        </div>
    </div>
</div>
