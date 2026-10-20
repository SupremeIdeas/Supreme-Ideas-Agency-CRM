{{-- FAQ (CMS: home.faq) — topic-grouped accordion, no external JS. Curated
     from the fuller general FAQ (owner request, 2026-09-21: "take 40%
     important content from our general FAQ grouped by the topic... seed to
     replace the FAQ in our homepage section so it still feel premium").
     Groups are flat g{n}_label/g{n}_q{m}/g{n}_a{m} keys (up to 6 groups, 4
     Q&A each) so the generic admin field editor still works. --}}
@php
    $faqGroups = collect(range(1, 6))
        ->filter(fn ($g) => ! empty($s["g{$g}_label"] ?? null))
        ->map(function ($g) use ($s) {
            $items = collect(range(1, 4))
                ->filter(fn ($n) => ! empty($s["g{$g}_q{$n}"] ?? null))
                ->map(fn ($n) => ['q' => $s["g{$g}_q{$n}"], 'a' => $s["g{$g}_a{$n}"]])
                ->values()->all();

            return ['label' => $s["g{$g}_label"], 'items' => $items];
        })
        ->values();
@endphp
<section class="mx-auto max-w-3xl px-4 py-20" data-bg="light" x-data="{ activeFaqGroup: 0 }">
    <h2 data-reveal class="text-center text-3xl font-bold text-slate-900 sm:text-4xl dark:text-white">{{ $s['headline'] }}</h2>

    <div data-reveal class="mt-8 flex flex-wrap justify-center gap-2">
        @foreach ($faqGroups as $i => $group)
            <button type="button" @click="activeFaqGroup = {{ $i }}"
                    :class="activeFaqGroup === {{ $i }} ? 'bg-primary text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200 dark:bg-white/5 dark:text-slate-300 dark:hover:bg-white/10'"
                    class="rounded-full px-4 py-2 text-sm font-semibold transition">
                {{ $group['label'] }}
            </button>
        @endforeach
    </div>

    @foreach ($faqGroups as $i => $group)
        <div x-show="activeFaqGroup === {{ $i }}" x-cloak class="mt-8 space-y-3">
            @foreach ($group['items'] as $n => $item)
                <details data-reveal style="--reveal-delay: {{ $n * 0.05 }}s"
                         class="group rounded-2xl border border-slate-200 bg-white p-5 dark:border-[var(--brand-card-border-dark)] dark:bg-[#16233d]">
                    <summary class="flex cursor-pointer list-none items-center justify-between gap-3 font-semibold text-slate-900 dark:text-white">
                        {{ $item['q'] }}
                        <x-icon name="chevron-right" class="h-4 w-4 shrink-0 text-slate-400 transition group-open:rotate-90" />
                    </summary>
                    <p class="mt-3 text-sm leading-relaxed text-slate-600 dark:text-slate-300">{{ $item['a'] }}</p>
                </details>
            @endforeach
        </div>
    @endforeach

    <p data-reveal class="mt-8 text-center text-sm text-slate-500 dark:text-slate-400">
        {{ $s['cta'] }} <a href="{{ route('contact') }}" class="font-semibold text-primary hover:underline">{{ $s['cta_contact'] ?? '' }}</a>
        &middot; <a href="{{ route('faq') }}" wire:navigate class="font-semibold text-primary hover:underline">{{ $s['cta_all'] ?? '' }}</a>
    </p>
</section>
