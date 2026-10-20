{{-- Features (CMS: home.features) — premium cards with reveal stagger. --}}
@php($icons = ['send', 'phone', 'globe', 'wallet', 'refresh', 'gift'])
<section class="mx-auto max-w-6xl px-4 py-20" data-bg="light">
    <div class="text-center">
        <p data-reveal class="text-xs font-semibold uppercase tracking-[0.25em] text-accent-dark dark:text-accent">{{ $s['eyebrow'] }}</p>
        <h2 data-reveal class="mt-3 text-3xl font-bold text-slate-900 sm:text-4xl dark:text-white">{{ $s['headline'] }}</h2>
    </div>
    {{-- The real dashboard on a desktop, so the six promises below are shown, not just claimed (scripts/mockups). --}}
    <figure data-reveal class="mx-auto mt-10 max-w-4xl overflow-hidden rounded-[2rem] shadow-2xl shadow-slate-900/15 ring-1 ring-slate-200 dark:shadow-black/50 dark:ring-white/10">
        <img src="{{ asset('images/marketing/mockups/home/features-desktop.webp') }}" alt="The Naara dashboard on a desktop: eSIMs, numbers, wallet and rewards in one place"
             loading="lazy" decoding="async" width="1440" height="1060" class="h-auto w-full">
    </figure>
    <div class="mt-12 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
        @foreach (range(1, 6) as $n)
            <div data-reveal style="--reveal-delay: {{ ($n - 1) * 0.07 }}s" class="nx-card">
                <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-primary/10 text-primary dark:bg-primary/20">
                    <x-icon :name="$icons[$n - 1]" class="h-5 w-5" />
                </span>
                <h3 class="mt-4 font-bold text-slate-900 dark:text-white">{{ $s["f{$n}_title"] }}</h3>
                <p class="mt-2 text-sm leading-relaxed text-slate-600 dark:text-slate-300">{{ $s["f{$n}_text"] }}</p>
            </div>
        @endforeach
    </div>
</section>
