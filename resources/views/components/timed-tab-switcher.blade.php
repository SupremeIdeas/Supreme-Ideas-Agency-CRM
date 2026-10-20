{{-- Reusable time-based tab/slide switcher (owner request, 2026-09-21:
     "reuse that our time based tabs switcher container"). Extracted from
     the original "Who Naara Is For" hero component so every section that
     wants this browse-with-auto-advance mechanic (progress-fill timer,
     "Premium Glass" image card, floating glow blobs) shares one container
     instead of a copy — see resources/js/timed-tab-switcher.js and the
     .tt-* rules in sections.css.
     Props:
       items    — array of ['title' => string, 'desc' => string, 'image' => url|null]
       tone     — 'dark' (on a dark/navy section) | 'light' (on a white/light section)
       duration — ms per slide before auto-advancing (default 11000, the
                  reference's own tuned dwell time)
       id       — unique prefix so two instances on the same page never clash --}}
@props([
    'items' => [],
    'eyebrow' => '',
    'heading' => '',
    'subtext' => '',
    'tone' => 'dark',
    'duration' => 11000,
    'id' => 'tt',
])
<div {{ $attributes->merge(['class' => 'tt-wrapper tt-'.$tone]) }} data-timed-tab-switcher data-tt-duration="{{ $duration }}">
    <div class="tt-bg" aria-hidden="true">
        <div class="tt-glow tt-glow-1"></div>
        <div class="tt-glow tt-glow-2"></div>
    </div>

    @if ($eyebrow || $heading || $subtext)
        <div class="tt-head">
            @if ($eyebrow)<p class="tt-eyebrow">{{ $eyebrow }}</p>@endif
            @if ($heading)<h2 class="tt-heading">{{ $heading }}</h2>@endif
            @if ($subtext)<p class="tt-subtext">{{ $subtext }}</p>@endif
        </div>
    @endif

    <div class="tt-container">
        <nav class="tt-tabs" role="tablist" aria-label="{{ $heading ?: 'Browse' }}">
            @foreach ($items as $i => $item)
                <button type="button" class="tt-tab {{ $i === 0 ? 'active' : '' }}" data-slide="{{ $i }}" role="tab" aria-selected="{{ $i === 0 ? 'true' : 'false' }}">
                    {{ $item['title'] }}
                </button>
            @endforeach
        </nav>

        <div class="tt-content">
            <div class="tt-progress-header">
                <div class="tt-progress tt-progress-h">
                    <div class="tt-progress-fill-h"></div>
                </div>
                <div class="tt-timer">Next in: {{ (int) ($duration / 1000) }}s</div>
            </div>

            <div class="tt-body">
                <div class="tt-slides">
                    @foreach ($items as $i => $item)
                        <div class="tt-slide {{ $i === 0 ? 'active' : '' }}" data-slide="{{ $i }}">
                            <div class="tt-slide-info">
                                <h3 class="tt-slide-title">{{ $item['title'] }}</h3>
                                <p class="tt-slide-desc">{{ $item['desc'] }}</p>
                            </div>
                            @if (! empty($item['image']))
                                <div class="tt-slide-image {{ ! empty($item['device']) ? 'tt-slide-image--device' : '' }}">
                                    <img src="{{ $item['image'] }}" alt="{{ $item['title'] }}" loading="lazy" decoding="async">
                                </div>
                            @elseif (! empty($item['icon']))
                                <div class="tt-slide-icon">
                                    <span class="tt-slide-icon-badge">
                                        <x-icon :name="$item['icon']" class="h-8 w-8" />
                                    </span>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
                <div class="tt-progress tt-progress-v">
                    <div class="tt-progress-fill-v"></div>
                </div>
            </div>
        </div>
    </div>
</div>
