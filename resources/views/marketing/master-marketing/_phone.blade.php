{{-- A plain phone frame around a real dashboard screenshot (MASTER-ONLY marketing). Used where a lot of screens sit together (skin showrooms, chat skins), so a
     photographed hand would only add weight. Images are committed WebP assets, swappable from the Site Editor like every other image. --}}
@props(['src', 'alt' => '', 'caption' => null, 'priority' => false])
<figure {{ $attributes->merge(['class' => 'mm-phone relative mx-auto w-full']) }}>
    <div class="relative overflow-hidden rounded-[1.9rem] border-[5px] border-slate-900 bg-slate-900 pt-4 shadow-2xl shadow-slate-900/25 ring-1 ring-black/10 dark:border-slate-600 dark:bg-slate-700 dark:shadow-black/50 dark:ring-white/10">
        <span class="absolute left-1/2 top-1 z-10 h-2.5 w-12 -translate-x-1/2 rounded-full bg-slate-950 dark:bg-black" aria-hidden="true"></span>
        <img src="{{ $src }}" alt="{{ $alt }}" width="360" height="780" decoding="async" @if ($priority) fetchpriority="high" @else loading="lazy" @endif
             class="block aspect-[360/780] w-full rounded-b-[1.4rem] bg-white object-cover object-top dark:bg-slate-900">
    </div>
    @if ($caption)
        <figcaption class="mt-3 text-center text-sm font-semibold text-slate-700 dark:text-slate-200">{{ $caption }}</figcaption>
    @endif
</figure>
