{{-- How it works (CMS: home.how) — STACKING image+text step cards on scroll.
     Each card is sticky, so the next stacks over it. The step artwork is
     admin-swappable (Admin → Pages → Home → "Connected in Three Steps"). --}}
<section class="mx-auto max-w-5xl px-4 py-20" data-bg="light">
    <div class="text-center">
        <p data-reveal class="text-xs font-semibold uppercase tracking-[0.25em] text-accent-dark dark:text-accent">{{ $s['eyebrow'] }}</p>
        <h2 data-reveal class="mt-3 text-3xl font-bold text-slate-900 sm:text-4xl dark:text-white">{{ $s['headline'] }}</h2>
        <p data-reveal class="mx-auto mt-3 max-w-xl text-slate-600 dark:text-slate-300">{{ $s['subtext'] }}</p>
    </div>

    <div class="mkt-stack mt-12">
        @foreach ([1, 2, 3] as $n)
            @php($img = \App\Support\SiteContent::imageUrl($s["step_{$n}_image"] ?? ''))
            <div class="mkt-stack__card overflow-hidden border border-slate-200 bg-white dark:border-[var(--brand-card-border-dark)] dark:bg-[#16233d]" style="top: calc(5.5rem + {{ ($n - 1) * 1.25 }}rem)">
                <div class="grid items-center gap-6 sm:gap-10 {{ $img ? 'md:grid-cols-2' : '' }} {{ $n % 2 === 0 ? 'md:[&>figure]:order-first' : '' }}">
                    <div>
                        <div class="flex items-start gap-5">
                            <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-primary font-display text-xl font-bold text-white shadow-lg shadow-primary/30">{{ $n }}</span>
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-widest text-primary/70">Step {{ $n }}</p>
                                <h3 class="mt-1 text-xl font-bold text-slate-900 sm:text-2xl dark:text-white">{{ $s["step_{$n}_title"] }}</h3>
                            </div>
                        </div>
                        <p class="mt-4 leading-relaxed text-slate-600 dark:text-slate-300">{{ $s["step_{$n}_text"] }}</p>
                    </div>

                    @if ($img)
                        {{-- Real dashboard screen inside a device mockup (resources/mockups library). The photo carries its own
                             backdrop, so it sits in a rounded panel rather than floating on the card. --}}
                        <figure class="relative mx-auto w-full max-w-[19rem] overflow-hidden rounded-[2rem] md:max-w-[22rem] shadow-xl shadow-slate-900/10 ring-1 ring-slate-200 dark:shadow-black/40 dark:ring-white/10">
                            <img src="{{ $img }}" alt="{{ $s["step_{$n}_alt"] ?? $s["step_{$n}_title"] }}" loading="lazy" decoding="async"
                                 class="aspect-[3/4] w-full object-cover object-center" width="720" height="900" />
                        </figure>
                    @endif
                </div>
            </div>
        @endforeach
    </div>

    <div data-reveal class="mt-12 text-center">
        <a href="{{ auth()->check() ? route('catalogue') : route('register') }}" class="nx-btn nx-btn--primary !px-8 !py-3">{{ $s['cta'] }}</a>
    </div>
</section>
