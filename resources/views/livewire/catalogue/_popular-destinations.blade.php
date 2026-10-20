{{-- nx:converted (skin tokens; legacy palette classes removed 2026-10-05) --}}
{{-- "Popular Destinations" photo-card row. One shared layout — not a per-theme variant — so every theme's eSIM Popular tab shows it
     identically. A destination is "popular" purely because it has an admin-featured plan (the same is_featured flag that already drives the
     Popular tab's plan list — no new curation surface). Its photo is the SAME per-country image an admin already sets in Admin → eSIM
     Control Center (EsimCountryImage.detail_image_path); nothing new to upload here. A country with no photo still shows a clean flag +
     price card — the photo is decoration, never a requirement to appear. Expects: $popularDestinations (EsimCatalogue::popularDestinations()), $fmt. --}}
@if (count($popularDestinations))
    <div class="ns-nrow"><span class="ns-lbl">Popular Destinations</span><span class="ns-sub" style="margin:0;font-size:13px">{{ count($popularDestinations) }} {{ \Illuminate\Support\Str::plural('country', count($popularDestinations)) }}</span></div>
    <div class="ns-dests">
        @foreach ($popularDestinations as $d)
            @php($teaser = $d['from_usd'] !== null ? $fmt((float) $d['from_usd']) : null)
            @php($dataLabel = $d['data_mb'] ? (($d['data_mb'] >= 1024) ? round($d['data_mb'] / 1024, 1).'GB' : $d['data_mb'].'MB') : null)
            <button type="button" wire:click="openCountry('{{ $d['code'] }}')" wire:key="popular-dest-{{ $d['code'] }}" class="ns-dest ns-ring {{ $d['photo'] ? 'has-photo' : '' }}">
                @if ($d['photo'])
                    <img src="{{ $d['photo'] }}" alt="{{ $d['name'] }}" loading="lazy" class="ns-dest__photo">
                @endif
                <span class="ns-dest__flag"><x-country-flag :country="$d['code']" class="h-4 w-6 rounded-sm shadow" /></span>
                <span class="ns-dest__text">
                    <b>{{ $d['name'] }}</b>
                    <small>@if ($teaser)from {{ $teaser['usd'] }}@endif @if ($dataLabel) · {{ $dataLabel }} @endif</small>
                    <span class="ns-dest__tag"><x-nx.icon name="zap" /> Instant eSIM</span>
                </span>
            </button>
        @endforeach
    </div>
@endif
