{{-- MASTER-ONLY. Renders the live videos of one slot ($slot). Nothing at all when the slot is empty. --}}
@php
    use App\Support\MasterMarketing\{VideoSlots, VideoSource};
    use App\Support\SiteContent;
    $items = VideoSlots::for($slot);
@endphp
@foreach ($items as $it)
    @php
        $poster = trim((string) ($it['poster_url'] ?? ''));
        $v = VideoSource::parseLink((string) $it['video_url'], $poster !== '' ? SiteContent::imageUrl($poster) : '');
    @endphp
    @continue(! $v)
    <section class="mx-auto w-full max-w-4xl px-4 py-10 sm:py-14" aria-label="{{ $it['title'] }}">
        <div class="mb-5 text-center">
            <h2 class="text-2xl font-bold text-slate-900 sm:text-3xl dark:text-white">{{ $it['title'] }}</h2>
            @if (! empty($it['description']))<p class="mx-auto mt-2 max-w-2xl text-slate-600 dark:text-slate-300">{{ $it['description'] }}</p>@endif
        </div>
        <div class="relative aspect-video w-full overflow-hidden rounded-2xl bg-[#050d16] shadow-xl ring-1 ring-black/10 dark:ring-white/10" x-data="{ on: false }">
            @if ($v['kind'] === 'file')
                <video controls playsinline preload="none" @if ($v['poster'] !== '') poster="{{ $v['poster'] }}" @endif @if ($it['loop']) loop @endif @if ($it['autoplay']) autoplay muted @endif class="absolute inset-0 h-full w-full object-contain">
                    <source src="{{ SiteContent::imageUrl($v['src']) }}" type="{{ $v['mime'] }}">
                </video>
            @else
                <template x-if="on"><iframe src="{{ $v['embed'] }}" title="{{ $it['title'] }}" allow="autoplay; fullscreen; picture-in-picture; encrypted-media" allowfullscreen referrerpolicy="strict-origin-when-cross-origin" class="absolute inset-0 h-full w-full border-0"></iframe></template>
                <button type="button" x-show="!on" @click="on = true" class="absolute inset-0 flex items-center justify-center bg-cover bg-center" @if ($v['poster'] !== '') style="background-image:url('{{ $v['poster'] }}')" @endif aria-label="Play {{ $it['title'] }}">
                    <span class="absolute inset-0 bg-black/35" aria-hidden="true"></span>
                    <span class="relative flex h-16 w-16 items-center justify-center rounded-full bg-white/90 text-[#0A6E6E] shadow-xl"><x-icon name="play" class="h-7 w-7 translate-x-0.5" /></span>
                </button>
            @endif
        </div>
    </section>
@endforeach
