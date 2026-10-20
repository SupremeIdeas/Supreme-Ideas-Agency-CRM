{{-- nx:converted --}}
{{-- Public brand profile extras (N2N Batch 8, MASTER-ONLY). Thumbnails only until the visitor taps; the iframe is built from provider + id. --}}
@php($x = \App\Services\N2N\Brands\BrandPublicExtras::for($brand))
@if ($x)
    @if ($x['albums']->isNotEmpty())
        <section class="ns-bh__section" aria-label="Albums" data-album-carousel>
            <h2 class="ns-bh__h"><x-nx.icon name="volume-2" /> Albums</h2>
            <div class="albc"><div class="albc__track" tabindex="0" role="list">
                @foreach ($x['albums'] as $a)
                    <a role="listitem" class="albc__item" wire:key="alc-{{ $a->id }}" href="{{ route('n2n.album', ['brand' => $brand->id, 'slug' => $a->slug]) }}">
                        <span class="alb__cover">
                            @if ($a->cover_path)<img src="{{ $a->cover_path }}" alt="" loading="lazy" decoding="async">@else<span class="alb__nocover"><x-nx.icon name="image" /></span>@endif
                            @if ($a->distributor)<x-music.logo kind="distributor" :slug="$a->distributor" size="sm" class="alb__corner" />@endif
                        </span>
                        <b>{{ $a->title }}</b><small>{{ $a->release_date?->format('M Y') ?? $brand->brand_name }}</small>
                    </a>
                @endforeach
            </div></div>
        </section>
    @endif

    @php($cards = $x['videos']->concat($x['embeds']))
    @if ($cards->isNotEmpty())
        <section class="ns-bh__section" aria-label="Videos and players">
            <h2 class="ns-bh__h"><x-nx.icon name="play" /> Watch and listen</h2>
            <div style="display:flex;gap:12px;overflow-x:auto;scroll-snap-type:x mandatory;padding-bottom:6px" tabindex="0">
                @foreach ($cards as $c)
                    <div x-data="{ on: false }" wire:key="px-{{ $c['provider'] }}-{{ $c['id'] }}" style="flex:0 0 min(86%,340px);scroll-snap-align:start;border-radius:16px;overflow:hidden;background:rgb(var(--nx-surface-2))">
                        <template x-if="!on">
                            <button type="button" x-on:click="on = true" aria-label="Play {{ \App\Services\N2N\Brands\EmbedRenderer::label($c['provider']) }} {{ $c['kind'] }}" style="position:relative;display:block;width:100%;{{ $c['kind'] === 'video' ? 'aspect-ratio:16/9' : 'height:112px' }};border:0;padding:0;background:rgb(var(--nx-surface-2));cursor:pointer">
                                @if ($c['thumb'])<img src="{{ $c['thumb'] }}" alt="" loading="lazy" width="320" height="180" style="width:100%;height:100%;object-fit:cover">@endif
                                <span style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;gap:8px;color:rgb(var(--nx-text));font-weight:600"><x-nx.icon name="play" /> {{ \App\Services\N2N\Brands\EmbedRenderer::label($c['provider']) }}</span>
                            </button>
                        </template>
                        <template x-if="on">
                            <iframe src="{{ $c['src'] }}" sandbox="{{ $c['sandbox'] }}" allow="{{ $c['allow'] }}" referrerpolicy="strict-origin-when-cross-origin" loading="lazy" title="{{ \App\Services\N2N\Brands\EmbedRenderer::label($c['provider']) }} player"
                                    style="display:block;width:100%;border:0;{{ $c['kind'] === 'video' ? 'aspect-ratio:16/9' : 'height:'.$c['height'].'px' }}"></iframe>
                        </template>
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    @if ($x['music']->isNotEmpty())
        <section class="ns-bh__section">
            <h2 class="ns-bh__h"><x-nx.icon name="volume-2" /> Listen on</h2>
            <div style="display:flex;gap:8px;flex-wrap:wrap">
                @foreach ($x['music'] as $m)
                    <a href="{{ $m->url }}" target="_blank" rel="noopener noreferrer nofollow" class="ns-cta ns-cta--pill ns-cta--sm" style="gap:8px"><x-music.logo kind="platform" :slug="$m->platform" size="sm" />{{ $x['platforms'][$m->platform]['label'] ?? $m->platform }}</a>
                @endforeach
            </div>
        </section>
    @endif


    @if ($x['social']->isNotEmpty())
        <section class="ns-bh__section">
            <h2 class="ns-bh__h"><x-nx.icon name="link" /> Find them elsewhere</h2>
            <div style="display:flex;gap:8px;flex-wrap:wrap">
                @foreach ($x['social'] as $s)
                    <a href="{{ $s->url }}" target="_blank" rel="noopener noreferrer nofollow" class="ns-pg__act ns-pg__act--link">{{ $x['platforms'][$s->platform]['label'] ?? $s->platform }}</a>
                @endforeach
            </div>
        </section>
    @endif

    @if ($x['canMessage'] && $x['ownerHandle'] && auth()->id() !== $brand->owner_user_id)
        <section class="ns-bh__section">
            <a href="{{ route('n2n.chat', ['to' => $x['ownerHandle']]) }}" wire:navigate class="ns-cta ns-cta--pill"><x-nx.icon name="chat" /> Message {{ $brand->brand_name }}</a>
        </section>
    @endif
@endif
