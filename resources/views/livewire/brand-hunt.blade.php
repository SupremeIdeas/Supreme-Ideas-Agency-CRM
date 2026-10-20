{{-- The Hunt (brand partner directory) on the skin system (S3 Batch 6). Same sections: platform handles, category filter, featured band, directory, get-listed pitch. --}}
<div>
<x-nx.page class="ns-pg ns-pg--wide">
    <a href="{{ route('rewards') }}" wire:navigate class="ns-pg__back" style="position:relative;z-index:2"><x-nx.icon name="left" /> Back to Rewards</a>
    <x-nx.hero style="margin-top:14px" kicker="Brand Partner Directory" :first="\App\Support\HuntHeroBackground::titleFirstWord()" :rest="\App\Support\HuntHeroBackground::titleRestWords()"
               :desc="\App\Support\HuntHeroBackground::description()" :size="\App\Support\HuntHeroBackground::titleSize()"
               :light="\App\Support\HuntHeroBackground::showsImage() ? \App\Support\HuntHeroBackground::light() : null" :dark="\App\Support\HuntHeroBackground::showsImage() ? \App\Support\HuntHeroBackground::dark() : null">
        <div class="ns-pg__stat ns-ring" style="display:inline-flex;align-items:center;gap:12px;margin-top:18px;padding:12px 18px">
            <b style="margin:0;font-size:26px">${{ number_format($dailyRemaining, 0) }}</b>
            <small style="max-width:6rem;line-height:1.2">left to earn today</small>
        </div>
    </x-nx.hero>

    @if ($flash)<div class="ns-bh__flash" role="status">{{ $flash }}</div>@endif

    {{-- Discover / Followed: a brand whose every handle is claimed and that you have connected with moves to Followed, so Discover stays fresh. --}}
    <div class="ns-pg__filters" role="tablist" aria-label="Brands" style="margin-top:22px">
        <button type="button" role="tab" wire:click="setTab('discover')" class="ns-nt__chip {{ $tab === 'discover' ? 'is-on' : '' }}" aria-selected="{{ $tab === 'discover' ? 'true' : 'false' }}">Discover</button>
        <button type="button" role="tab" wire:click="setTab('followed')" class="ns-nt__chip {{ $tab === 'followed' ? 'is-on' : '' }}" aria-selected="{{ $tab === 'followed' ? 'true' : 'false' }}">Followed ({{ $followedBrands->count() }})</button>
    </div>

    @if ($tab === 'followed')
        @if ($followedBrands->isEmpty())
            <x-nx.empty art="es-brands" style="margin-top:24px" text="Brands you have fully followed and connected with will live here. Finish a brand in Discover and it moves over." />
        @else
            <section class="ns-bh__section" style="margin-top:24px"><div class="ns-bh__cards">@foreach ($followedBrands as $brand)@include('livewire.partials.hunt-brand-card', ['brand' => $brand])@endforeach</div></section>
        @endif
    @else
    {{-- Platform: follow NaaraSim's own handles, the "official" band. --}}
    @if ($platformHandles->isNotEmpty())
        <section class="ns-pg__card ns-ring" style="margin-top:24px">
            <h2 class="ns-bh__h"><x-nx.icon name="star" /> Follow {{ \App\Support\BrandSettings::name() }}</h2>
            <div class="ns-bh__grid">
                @foreach ($platformHandles as $h)
                    @include('livewire.partials.hunt-handle-card', ['handle' => $h, 'claimed' => isset($claimedHandles[$h->id]), 'action' => 'followHandle'])
                @endforeach
            </div>
        </section>
    @endif

    {{-- Category filter --}}
    @if ($categories->isNotEmpty())
        <div class="ns-pg__filters" role="group" aria-label="Category" style="margin-top:22px">
            <button type="button" wire:click="setCategory(null)" class="ns-nt__chip {{ ! $category ? 'is-on' : '' }}" aria-pressed="{{ ! $category ? 'true' : 'false' }}">All ({{ $totalBrandCount }})</button>
            @foreach ($categories as $cat)
                <button type="button" wire:click="setCategory('{{ $cat }}')" class="ns-nt__chip {{ $category === $cat ? 'is-on' : '' }}" aria-pressed="{{ $category === $cat ? 'true' : 'false' }}">{{ $cat }}</button>
            @endforeach
        </div>
    @endif

    {{-- Featured band: admin-placed brands get a sponsored-style row up top. A snap-scroll strip at every breakpoint, so one, two or ten brands lay out the same way. --}}
    @if (! $category && $featuredBrands->isNotEmpty())
        <section class="ns-bh__section" style="margin-top:24px">
            <h2 class="ns-bh__h"><x-nx.icon name="check" /> Featured brands</h2>
            <div class="ns-bh__strip">
                @foreach ($featuredBrands as $brand)
                    <div>@include('livewire.partials.hunt-brand-card', ['brand' => $brand])</div>
                @endforeach
            </div>
        </section>
    @endif

    {{-- Everything else, grouped by category: a real directory, not a flat scroll (BUILD-9 §8.1). With a filter active, the matching brands (featured included) collapse into one section. --}}
    @if ($category)
        @php($filtered = $brandsByCategory->get($category, collect())->concat($featuredBrands))
        @if ($filtered->isNotEmpty())
            <section class="ns-bh__section"><div class="ns-bh__cards">@foreach ($filtered as $brand)@include('livewire.partials.hunt-brand-card', ['brand' => $brand])@endforeach</div></section>
        @endif
    @else
        @forelse ($brandsByCategory as $cat => $catBrands)
            <section class="ns-bh__section" wire:key="cat-{{ $cat }}">
                <div class="ns-bh__catrow"><h2 class="ns-pg__h2">{{ $cat }}</h2><small>{{ $catBrands->count() }} {{ Str::plural('brand', $catBrands->count()) }}</small></div>
                <div class="ns-bh__cards">@foreach ($catBrands as $brand)@include('livewire.partials.hunt-brand-card', ['brand' => $brand])@endforeach</div>
            </section>
        @empty
            @if ($featuredBrands->isEmpty())
                <x-nx.empty style="margin-top:24px" :text="$platformHandles->isEmpty() ? 'No brands to follow yet. Check back soon.' : 'More brands coming soon.'" />
            @endif
        @endforelse
    @endif

    @endif

    {{-- Get-listed CTA (business pitch, distinct from the credit-hunter content). --}}
    <a href="{{ route('brand.get-listed') }}" wire:navigate class="ns-bh__cta" style="margin-top:32px">
        <span><b>Grow your following. List your brand on {{ \App\Support\BrandSettings::name() }}.</b><small>Real follows from people motivated to follow. See plans →</small></span>
        <x-nx.icon name="chev" />
    </a>
</x-nx.page>
</div>
