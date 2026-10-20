{{-- nx:converted --}}
{{-- Public album smart-link page (N2N, MASTER-ONLY): the cover with its distributor in the corner, one "listen everywhere" button for the distributor smart link, and every streaming link with its logo. Skin tokens only. --}}
@php
    $cover = $album->cover_path ?: ($brand->hero_image_path ?: $brand->logo_path);
    $dist = \App\Support\N2N\MusicLogos::distributor($album->distributor);
@endphp
<x-layouts.marketing :title="$album->title.' by '.$brand->brand_name" :description="\Illuminate\Support\Str::limit($album->description ?: 'Listen to '.$album->title.' on your favourite platform.', 160)" :og-image="$cover">
    <main class="albp mx-auto max-w-md px-4 py-10" data-album-page>
        @if ($cover)<div class="albp__bg" style="background-image:url('{{ $cover }}')" aria-hidden="true"></div>@endif
        <header class="albp__hero">
            <div class="albp__cover">
                @if ($cover)<img src="{{ $cover }}" alt="{{ $album->title }} cover" decoding="async">@endif
                @if ($album->distributor && $dist)<x-music.logo kind="distributor" :slug="$album->distributor" size="md" class="alb__corner" />@endif
            </div>
            <h1 class="albp__title">{{ $album->title }}</h1>
            <p class="albp__meta"><a href="{{ route('rewards.hunt.profile', $brand) }}" class="underline">{{ $brand->brand_name }}</a>@if ($album->release_date) · {{ $album->release_date->format('F Y') }}@endif</p>
            @if ($album->description)<p class="albp__meta" style="max-width:34ch">{{ $album->description }}</p>@endif
        </header>

        @if ($album->smart_url)
            <a href="{{ $album->smart_url }}" target="_blank" rel="noopener noreferrer nofollow" class="albp__main" data-album-smart>
                <x-nx.icon name="play" /> Listen everywhere @if ($dist) <span style="font-weight:500;opacity:.85">via {{ $dist['label'] }}</span>@endif
            </a>
        @endif

        @if ($links->isNotEmpty())
            <ul class="albp__list" aria-label="Where to listen">
                @foreach ($links as $l)
                    <li><a href="{{ $l->url }}" target="_blank" rel="noopener noreferrer nofollow" class="albp__row" data-album-link>
                        <x-music.logo kind="platform" :slug="$l->platform" size="md" /><span>{{ $platforms[$l->platform]['label'] }}</span><x-nx.icon name="arrow-up-right" />
                    </a></li>
                @endforeach
            </ul>
        @elseif (! $album->smart_url)
            <p class="albp__meta" style="text-align:center;margin-top:18px">No links yet.</p>
        @endif

        <div x-data="{ done: false }" style="margin-top:22px;text-align:center">
            <button type="button" class="ns-cta ns-cta--pill ns-cta--ghost ns-cta--sm"
                    x-on:click="navigator.share ? navigator.share({title: @js($album->title), url: location.href}).catch(()=>{}) : navigator.clipboard.writeText(location.href).then(() => { done = true; setTimeout(() => done = false, 1600) })">
                <x-nx.icon name="share" /> <span x-text="done ? 'Link copied' : 'Share'">Share</span>
            </button>
        </div>
    </main>
</x-layouts.marketing>
