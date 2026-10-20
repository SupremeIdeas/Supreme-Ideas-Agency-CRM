{{-- Testimonials (CMS: home.testimonials) — Apple-inspired snap carousel on the TEAL scene: big quote cards, the next card peeks in,
     centred snapping, pager + arrows. Star ratings reuse the Module 20 component. --}}
@if (\App\Support\Trustpilot\TrustpilotSettings::defaultTestimonialsOn() || ! \App\Support\Trustpilot\TrustpilotSettings::placementOn('home.carousel'))
<section class="overflow-hidden bg-[#07403f] py-20 text-teal-50 sm:py-24" data-bg="teal">
    <div class="mx-auto max-w-6xl px-4">
        <div class="max-w-2xl">
            <p data-reveal class="text-xs font-semibold uppercase tracking-[0.25em] text-accent">{{ $s['eyebrow'] }}</p>
            <h2 data-reveal class="mt-3 text-3xl font-bold leading-tight sm:text-5xl">{{ $s['headline'] }}</h2>
        </div>
    </div>
    <x-marketing.snap-carousel class="mt-10" label="Customer stories" data-reveal>
        @foreach ([1, 2, 3, 4, 5, 6] as $n)
            @continue(blank($s["t{$n}_quote"] ?? null))
            <figure class="flex min-h-[22rem] w-[84%] max-w-[26rem] flex-col rounded-[28px] border border-white/15 bg-white/[0.07] p-7 shadow-[0_24px_60px_-30px_rgba(0,0,0,0.6)] backdrop-blur sm:w-[24rem] sm:p-8">
                <x-ui.star-rating :value="5" />
                <blockquote class="mt-5 flex-1 text-[1.15rem] font-medium leading-snug tracking-tight text-white sm:text-[1.3rem]">&ldquo;{{ $s["t{$n}_quote"] }}&rdquo;</blockquote>
                <figcaption class="mt-7 flex items-center gap-3">
                    <x-avatar :src="\App\Support\SiteContent::imageUrl($s['t'.$n.'_image'] ?? '')" :name="$s['t'.$n.'_name']" size="h-11 w-11" :on-dark="true" />
                    <span>
                        <p class="font-semibold text-white">{{ $s["t{$n}_name"] }}</p>
                        <p class="text-sm text-accent">{{ $s["t{$n}_route"] }}</p>
                    </span>
                </figcaption>
            </figure>
        @endforeach
    </x-marketing.snap-carousel>
</section>
@endif
@if (\App\Support\Trustpilot\TrustpilotSettings::placementOn('home.carousel'))
    <section class="bg-white py-10 dark:bg-slate-900"><div class="mx-auto max-w-6xl px-4"><x-trust.reviews placement="home.carousel" /></div></section>
@endif
