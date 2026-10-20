{{-- MASTER ONLY. "From the Naara blog": a premium carousel of the latest posts, just above the footer of every marketing page. Swipe or drag, arrows, a progress line that follows the scroll,
     and a gentle auto-advance that stops on hover or focus and never runs for reduced motion. Nothing when there are no posts. $exclude = a post id to leave out (the one being read). --}}
@php
    // never let a decoration take a page down (a fresh install before migrations, a database hiccup): no posts, no carousel
    try {
        $posts = \Illuminate\Support\Facades\Cache::remember('mm.blog_carousel.v1', 600, fn () => \App\Models\Post::published()->latest('published_at')->limit(9)->get(['id', 'title', 'slug', 'category', 'excerpt', 'cover_image_url', 'accent_color', 'published_at']));
    } catch (\Throwable) {
        $posts = collect();
    }
    $posts = $posts->reject(fn ($p) => ($exclude ?? null) && $p->id === $exclude)->values();
@endphp
@if ($posts->count() >= 3)
<section class="relative -mb-8 rounded-t-[30px] bg-[#0B1626] px-4 pb-14 pt-9 text-white sm:px-6" aria-label="From the blog" data-blog-carousel>
    <div class="mx-auto max-w-7xl" x-data="blogCarousel()" x-init="init()" @mouseenter="hover = true" @mouseleave="hover = false" @focusin="hover = true" @focusout="hover = false">
        <div class="flex items-center justify-between gap-4">
            <div class="flex items-baseline gap-3">
                <h2 class="text-lg font-bold sm:text-xl">From the Naara blog</h2>
                @if (Route::has('blog'))<a href="{{ route('blog') }}" class="text-xs font-semibold text-teal-300 hover:text-white">All posts</a>@endif
            </div>
            <div class="flex items-center gap-1.5">
                <button type="button" @click="step(-1)" :disabled="atStart" class="flex h-8 w-8 items-center justify-center rounded-full bg-white/10 ring-1 ring-white/15 transition hover:bg-white/20 disabled:opacity-35" aria-label="Previous posts"><x-icon name="chevron-left" class="h-4 w-4" /></button>
                <button type="button" @click="step(1)" :disabled="atEnd" class="flex h-8 w-8 items-center justify-center rounded-full bg-white/10 ring-1 ring-white/15 transition hover:bg-white/20 disabled:opacity-35" aria-label="Next posts"><x-icon name="chevron-right" class="h-4 w-4" /></button>
            </div>
        </div>

        <div x-ref="track" @scroll.passive="onScroll()" class="mt-4 flex snap-x snap-mandatory gap-3 overflow-x-auto scroll-smooth pb-2 [-ms-overflow-style:none] [scrollbar-width:none] [&::-webkit-scrollbar]:hidden" tabindex="-1">
            @foreach ($posts as $post)
                {{-- compact card: a small picture on one side, title and a short line on the other --}}
                <a href="{{ route('blog.show', $post) }}" wire:navigate class="group flex w-[86%] shrink-0 snap-start items-center gap-3 rounded-2xl bg-white/[0.05] p-2.5 ring-1 ring-white/10 transition duration-200 hover:bg-white/[0.09] hover:ring-white/25 sm:w-[48%] lg:w-[32%] xl:w-[24%]" data-post="{{ $post->id }}">
                    <span class="relative block h-[76px] w-[96px] shrink-0 overflow-hidden rounded-xl bg-gradient-to-br from-[#0D1B2A] to-[#0A6E6E]">
                        @if ($post->cover_image_url)<img src="{{ $post->cover_image_url }}" alt="" loading="lazy" decoding="async" draggable="false" class="h-full w-full object-cover transition duration-300 group-hover:scale-105" x-on:error="$el.remove()">@endif
                        <span class="absolute bottom-1 left-1 right-1 truncate rounded-full px-1.5 py-px text-center text-[9px] font-semibold uppercase tracking-wide text-white" style="background: {{ $post->accentColor() }}d9">{{ $post->category }}</span>
                    </span>
                    <span class="min-w-0 flex-1">
                        <span class="line-clamp-2 text-[13px] font-bold leading-snug">{{ $post->title }}</span>
                        @if ($post->excerpt)<span class="mt-0.5 line-clamp-2 text-[11px] leading-snug text-slate-400">{{ $post->excerpt }}</span>@endif
                        <span class="mt-1 block text-[10px] text-slate-500">{{ optional($post->published_at)->format('M j, Y') }}</span>
                    </span>
                </a>
            @endforeach
        </div>

        <div class="mt-2 h-0.5 overflow-hidden rounded-full bg-white/10" aria-hidden="true"><div class="h-full rounded-full bg-gradient-to-r from-[#0A6E6E] via-teal-300 to-[#D4A017] transition-[width] duration-200" :style="'width:' + progress + '%'"></div></div>
    </div>
</section>
@once
<script>
    function blogCarousel() {
        return {
            hover: false, atStart: true, atEnd: false, progress: 0, timer: null,
            init() {
                this.onScroll();
                const calm = !(window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches);
                if (calm) this.timer = setInterval(() => { if (!this.hover && !document.hidden && this.inView()) this.atEnd ? this.$refs.track.scrollTo({ left: 0 }) : this.step(1); }, 5200);
            },
            inView() { const r = this.$el.getBoundingClientRect(); return r.top < innerHeight && r.bottom > 0; },
            step(dir) { const t = this.$refs.track, card = t.firstElementChild; t.scrollBy({ left: dir * (card ? card.getBoundingClientRect().width + 12 : 320), behavior: 'smooth' }); },
            onScroll() { const t = this.$refs.track, max = t.scrollWidth - t.clientWidth; this.atStart = t.scrollLeft < 4; this.atEnd = t.scrollLeft > max - 4; this.progress = max > 0 ? Math.round(10 + 90 * (t.scrollLeft / max)) : 100; },
        };
    }
</script>
@endonce
@endif
