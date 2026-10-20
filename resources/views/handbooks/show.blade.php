{{-- nx:converted --}}
{{-- A handbook's page (MASTER-ONLY). Public and indexable, with schema.org Book markup; buying needs sign-in. Skin tokens only. --}}
@php
    $cover = $b->cover_path ? route('handbooks.cover', $b->slug) : null;
    $ld = array_filter([
        '@context' => 'https://schema.org', '@type' => 'Book', 'name' => $b->title, 'description' => \Illuminate\Support\Str::limit(strip_tags((string) $b->description), 300), 'inLanguage' => $b->language,
        'image' => $cover, 'isbn' => $b->isbn_ebook ?: null, 'datePublished' => $b->published_at?->toDateString(), 'dateModified' => $b->last_verified_on?->toDateString(),
        'author' => ['@type' => 'Organization', 'name' => 'Naara'], 'publisher' => ['@type' => 'Organization', 'name' => 'Naara Connect Technologies Limited'],
        'offers' => ['@type' => 'Offer', 'price' => number_format((float) $b->list_price_usd, 2, '.', ''), 'priceCurrency' => 'USD', 'availability' => 'https://schema.org/InStock', 'url' => route('handbooks.show', $b->slug)],
    ]);
@endphp
<x-layouts.marketing :title="$b->title.' — Naara Travel Handbooks'" :description="\Illuminate\Support\Str::limit(strip_tags((string) $b->description), 160)" :og-image="$cover">
<x-nx.page class="ns-pg">
    <script type="application/ld+json">{!! json_encode($ld, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
    <a href="{{ route('handbooks.directory') }}" class="ns-pg__back"><x-nx.icon name="left" /> All handbooks</a>

    <div class="hb-show">
        <div class="hb-show__cover" @if ($b->back_cover_path) x-data="{ side: 'front' }" @endif>
            @if ($b->back_cover_path)
                <div x-show="side === 'front'"><x-handbook-cover :book="$b" :eager="true" /></div>
                <div x-show="side === 'back'" x-cloak data-back-cover><span class="hbk"><img src="{{ route('handbooks.backcover', $b->slug) }}" alt="Back cover of {{ $b->title }}: about the book and the Naara Family" width="450" height="600" loading="lazy" decoding="async"><i class="hbk__spine" aria-hidden="true"></i></span></div>
                <div role="group" aria-label="Which side of the cover" style="display:flex;gap:6px;justify-content:center;margin-top:12px">
                    <button type="button" class="hb-chip" :class="side === 'front' ? 'hb-chip--alt' : ''" :aria-pressed="side === 'front'" x-on:click="side = 'front'">Front</button>
                    <button type="button" class="hb-chip" :class="side === 'back' ? 'hb-chip--alt' : ''" :aria-pressed="side === 'back'" x-on:click="side = 'back'">Back cover</button>
                </div>
            @else
                <x-handbook-cover :book="$b" :eager="true" />
            @endif
        </div>
        <div class="hb-show__main">
            @php
                $eyebrow = collect([$b->series?->name, $b->categories->first()?->name])->filter()->implode(' · ');
                $topSections = collect($b->toc ?? [])->filter(fn ($t) => (int) ($t['depth'] ?? 0) === 0)->count();
                $paras = collect(preg_split('/\n{2,}/', trim((string) $b->description)))->map(fn ($x) => trim($x))->filter()->values();
                $notice = $paras->last() && preg_match('/^(general information|coverage in progress)/i', $paras->last()) ? $paras->pop() : null;
                $glance = array_values(array_filter([
                    ['Pages', $b->page_count ? '~'.number_format($b->page_count) : null],
                    ['Sections', $topSections ?: null],
                    ['Language', $b->language ? strtoupper($b->language) : null],
                    ['Edition', $b->edition ? 'Edition '.$b->edition : null],
                ], fn ($g) => $g[1] !== null));
            @endphp
            @if ($eyebrow !== '')<p class="ns-pg__hint" style="margin:0 0 6px;text-transform:uppercase;letter-spacing:.14em;font-weight:700;color:rgb(var(--nx-teal-ink))" data-book-eyebrow>{{ $eyebrow }}</p>@endif
            <h1 class="ns-h1" style="margin:0;text-wrap:balance">{{ $b->title }}</h1>
            @if ($b->subtitle)<p class="ns-sub" style="margin:8px 0 0;font-size:1.05em;text-wrap:pretty">{{ $b->subtitle }}</p>@endif
            <p class="ns-pg__hint" style="margin:10px 0 0" data-last-verified><b style="color:rgb(var(--nx-text))">Last verified {{ $b->last_verified_on?->format('F j, Y') ?? 'recently' }}</b>@if ($b->last_verified_on) <span>· every figure carries the date it was checked</span>@endif</p>
            @if ($glance)
                <dl class="hb-glance" data-book-glance style="display:flex;gap:10px;flex-wrap:wrap;margin:14px 0 0;padding:0">
                    @foreach ($glance as [$k, $v])<div style="flex:1 1 6.5rem;min-width:6.5rem;padding:10px 12px;border-radius:14px;border:1px solid rgb(var(--nx-line));background:rgb(var(--nx-surface-2))"><dt class="ns-pg__hint" style="margin:0;font-size:11px;text-transform:uppercase;letter-spacing:.1em">{{ $k }}</dt><dd style="margin:2px 0 0;font-weight:700;color:rgb(var(--nx-text))">{{ $v }}</dd></div>@endforeach
                </dl>
            @endif
            <div class="hb-show__chips" style="margin-top:12px">@foreach ($b->categories as $c)<span class="hb-chip">{{ $c->name }}</span>@endforeach @foreach ($b->moods as $m)<span class="hb-chip hb-chip--alt">{{ $m->name }}</span>@endforeach</div>
            @if ($paras->isNotEmpty())
                <div style="margin-top:16px;max-width:42rem" data-book-description>
                    <p style="margin:0;font-size:1.06em;line-height:1.65;color:rgb(var(--nx-text))">{{ $paras->first() }}</p>
                    @foreach ($paras->slice(1) as $para)<p class="ns-sub" style="margin:10px 0 0;line-height:1.65">{{ $para }}</p>@endforeach
                </div>
            @endif
            <ul class="hb-feats" data-book-features style="list-style:none;margin:16px 0 0;padding:0;display:grid;gap:7px;max-width:42rem">
                <li style="display:flex;gap:9px;align-items:flex-start"><x-nx.icon name="check" style="color:rgb(var(--nx-ok));margin-top:3px" /><span><b style="color:rgb(var(--nx-text))">Read anywhere, even offline.</b> <span class="ns-sub">Open it in the Naara reader and keep a copy on your device.</span></span></li>
                <li style="display:flex;gap:9px;align-items:flex-start"><x-nx.icon name="check" style="color:rgb(var(--nx-ok));margin-top:3px" /><span><b style="color:rgb(var(--nx-text))">Listen instead of reading.</b> <span class="ns-sub">Read aloud with a natural voice, following along on the page.</span></span></li>
                @if ($b->pdf_path)<li style="display:flex;gap:9px;align-items:flex-start"><x-nx.icon name="check" style="color:rgb(var(--nx-ok));margin-top:3px" /><span><b style="color:rgb(var(--nx-text))">A PDF you can keep.</b> <span class="ns-sub">Download it once you own the book.</span></span></li>@endif
            </ul>
            @if ($notice)<p class="ns-pg__hint" style="margin:14px 0 0;max-width:42rem" data-book-notice>{{ $notice }}</p>@endif

            <div style="margin-top:18px"><livewire:handbooks.buy-panel :handbook-id="$b->id" /></div>

            @php($shown = $b->links->filter->isShown())
            @if ($shown->isNotEmpty())
                <div style="margin-top:18px" data-external-links>
                    <b class="ns-pg__hint" style="color:rgb(var(--nx-text))">Also available as</b>
                    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:6px">@foreach ($shown as $l)<a href="{{ $l->url }}" class="ns-cta ns-cta--pill ns-cta--ghost" target="_blank" rel="noopener noreferrer nofollow sponsored">{{ \App\Models\HandbookExternalLink::MARKETPLACES[$l->marketplace] ?? 'Other store' }}</a>@endforeach</div>
                </div>
            @endif

            @if ($b->country_code)
                <div class="ns-pg__card ns-ring" style="margin-top:18px"><b style="color:rgb(var(--nx-text))">Travelling to {{ $b->country_code }}?</b><p class="ns-pg__hint" style="margin:4px 0 8px">A Naara eSIM for the country unlocks this handbook for you and gets you online the moment you land.</p><a href="{{ route('pricing') }}" class="ns-cta ns-cta--pill ns-cta--ghost">Get an eSIM</a></div>
            @endif
        </div>
    </div>

    @if (! empty($b->toc))
        <section class="ns-pg__card ns-ring" style="margin-top:22px" data-inside-book>
            <h2 class="ns-pg__h2" style="margin-bottom:8px">Inside this book</h2>
            <ol class="hb-toc">@foreach (array_slice($b->toc, 0, 60) as $t)<li style="margin-inline-start:{{ min(3, (int) $t['depth']) * 18 }}px">{{ $t['title'] }}</li>@endforeach</ol>
        </section>
    @endif

    @if ($siblings->isNotEmpty())
        <section style="margin-top:26px">
            <h2 class="ns-pg__h2">More in {{ $b->series?->name ?? 'this series' }}</h2>
            <div class="hb-shelf" style="color:rgb(var(--nx-text))" data-handbook-grid>@foreach ($siblings as $s)@include('handbooks._shelf-book', ['b' => $s])@endforeach</div>
        </section>
    @endif
    <p style="margin-top:20px"><a href="{{ route('handbooks.directory') }}" class="ns-cta ns-cta--pill ns-cta--ghost">Browse all handbooks</a></p>

    <p class="ns-pg__hint" style="margin-top:26px" data-disclaimer>{{ $disclaimer }}</p>
</x-nx.page>
</x-layouts.marketing>
