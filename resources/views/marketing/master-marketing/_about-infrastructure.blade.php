{{-- The infrastructure statement on About (MASTER-ONLY). Copy and images = Site Editor page `about`, section `infrastructure`. Expects $q (that section, or null). --}}
@php($img = fn ($v) => \App\Support\SiteContent::imageUrl($v ?? ''))
@if (! empty($q))
<section class="relative isolate overflow-hidden rounded-t-[30px] bg-[#0D1B2A] px-4 py-20 text-white sm:py-24 -mt-8 nx-ambient nx-ambient--rich" data-bg="dark">
    <div class="mx-auto grid max-w-6xl items-center gap-14 lg:grid-cols-[1.05fr_.95fr]">
        <div>
            <p data-reveal class="text-xs font-semibold uppercase tracking-[0.25em] text-accent">{{ $q['eyebrow'] }}</p>
            <svg class="mt-6 h-10 w-10 text-accent/70" viewBox="0 0 40 40" fill="currentColor" aria-hidden="true"><path d="M8 24c0-7 3-12 10-14l1.5 3c-3.5 1.4-5 4-5 6.5H18V30H8V24Zm14 0c0-7 3-12 10-14l1.5 3c-3.5 1.4-5 4-5 6.5H32V30H22V24Z"/></svg>
            <blockquote data-reveal class="mt-4 text-3xl font-bold leading-[1.15] tracking-tight sm:text-4xl lg:text-[2.6rem]">
                {{ $q['quote_before'] }}<span class="bg-gradient-to-r from-accent via-[#7FE0D8] to-accent bg-clip-text text-transparent">{{ $q['quote_accent'] }}</span>{{ $q['quote_after'] }}
            </blockquote>
            <p data-reveal class="mt-5 text-sm font-semibold uppercase tracking-widest text-white/60">{{ $q['attribution'] }}</p>
            <p data-reveal class="mt-6 max-w-xl text-lg leading-relaxed text-slate-300">{{ $q['subtext'] }}</p>
            <dl class="mt-10 grid grid-cols-2 gap-3 sm:grid-cols-4">
                @foreach ([1, 2, 3, 4] as $n)
                    <div data-reveal style="--reveal-delay: {{ $n * 0.06 }}s" class="rounded-2xl border border-white/10 bg-white/5 px-4 py-4 backdrop-blur">
                        <dt class="font-display text-2xl font-bold text-accent">{{ $q["s{$n}_value"] }}</dt>
                        <dd class="mt-1 text-xs leading-snug text-slate-300">{{ $q["s{$n}_label"] }}</dd>
                    </div>
                @endforeach
            </dl>
        </div>
        <div class="relative pb-8 pr-2 sm:pr-10">
            @if ($img($q['desk_image'] ?? ''))
                <figure data-reveal class="overflow-hidden rounded-3xl shadow-2xl shadow-black/50 ring-1 ring-white/15">
                    <img src="{{ $img($q['desk_image']) }}" alt="{{ $q['desk_alt'] }}" width="1440" height="1060" loading="lazy" decoding="async" class="block w-full">
                </figure>
            @endif
            @if ($img($q['phone_image'] ?? ''))
                <div data-reveal style="--reveal-delay: .15s" class="absolute -bottom-2 right-0 w-[30%] max-w-[9.5rem] rotate-[4deg] overflow-hidden rounded-[1.4rem] border-[4px] border-slate-900 bg-slate-900 shadow-2xl shadow-black/60 sm:w-[28%]">
                    <img src="{{ $img($q['phone_image']) }}" alt="{{ $q['phone_alt'] }}" width="480" height="1039" loading="lazy" decoding="async" class="block aspect-[360/780] w-full object-cover object-top">
                </div>
            @endif
        </div>
    </div>
</section>
@endif
