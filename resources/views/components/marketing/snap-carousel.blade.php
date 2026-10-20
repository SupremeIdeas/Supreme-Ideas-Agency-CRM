{{-- Apple-style snap carousel. Slot = the cards (direct children of the track). Inherits text colour, so it works on light or dark bands.
     Keyboard: the track is focusable and scrolls with the arrow keys; the arrows and pager are real buttons with labels. --}}
@props(['label' => 'Carousel'])
<div {{ $attributes->merge(['class' => 'nx-snap']) }} role="region" aria-roledescription="carousel" aria-label="{{ $label }}"
     x-data="{
        i: 0, count: 0, atStart: true, atEnd: false,
        init() {
            const t = this.$refs.track;
            const sync = () => { this.count = t.children.length; this.update(); };
            new MutationObserver(sync).observe(t, { childList: true });
            window.addEventListener('resize', () => this.update(), { passive: true });
            this.$nextTick(sync);
        },
        update() {
            const t = this.$refs.track, mid = t.scrollLeft + t.clientWidth / 2;
            let best = 0, bd = Infinity;
            [...t.children].forEach((c, k) => { const d = Math.abs(c.offsetLeft + c.offsetWidth / 2 - mid); if (d < bd) { bd = d; best = k; } });
            [...t.children].forEach((c, k) => c.classList.toggle('is-active', k === best));
            this.i = best; this.atStart = t.scrollLeft < 4; this.atEnd = t.scrollLeft + t.clientWidth >= t.scrollWidth - 4;
        },
        go(k) {
            const t = this.$refs.track, c = t.children[Math.max(0, Math.min(this.count - 1, k))];
            if (!c) return;
            const still = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            t.scrollTo({ left: c.offsetLeft - (t.clientWidth - c.offsetWidth) / 2, behavior: still ? 'auto' : 'smooth' });
        },
     }">
    <div class="nx-snap__track" x-ref="track" tabindex="0" @scroll.passive="update()" aria-live="polite"
         @keydown.arrow-right.prevent="go(i + 1)" @keydown.arrow-left.prevent="go(i - 1)">{{ $slot }}</div>
    <div class="nx-snap__bar" x-show="count > 1" x-cloak>
        <div class="nx-snap__dots" role="tablist" aria-label="{{ $label }}">
            <template x-for="k in count" :key="k">
                <button type="button" class="nx-snap__dot" role="tab" :class="i === k - 1 ? 'is-on' : ''" :aria-selected="i === k - 1" :aria-label="'Go to item ' + k" @click="go(k - 1)"></button>
            </template>
        </div>
        <div class="nx-snap__arrows">
            <button type="button" class="nx-snap__arrow" @click="go(i - 1)" :disabled="atStart" aria-label="Previous"><x-icon name="chevron-right" class="h-5 w-5 rotate-180" /></button>
            <button type="button" class="nx-snap__arrow" @click="go(i + 1)" :disabled="atEnd" aria-label="Next"><x-icon name="chevron-right" class="h-5 w-5" /></button>
        </div>
    </div>
</div>
