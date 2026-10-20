{{-- "One engine, your brand on top" (MASTER-ONLY, White Label sales page). One piece of artwork in two layouts: a wide scene for tablets and desktops and a tall
     one for phones, so nothing is ever cropped or hidden behind a sideways scroll. Every label comes from the Site Editor (page `white-label`, section `engine`,
     fields art_*). Colours follow light/dark through the variables in _engine-defs; ids are unique per <svg>. Expects $l (the section's fields). --}}
@php
    $t = fn (string $k) => $l[$k] ?? '';
    $uw = 'wl'.substr(md5(uniqid('w', true)), 0, 6);
    $ut = 'wl'.substr(md5(uniqid('t', true)), 0, 6);
@endphp

{{-- Wide --}}
@php($u = $uw)
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1000 536" role="img" aria-labelledby="{{ $u }}-t {{ $u }}-d" class="{{ $u }} hidden w-full sm:block" style="height:auto">
  <title id="{{ $u }}-t">{{ $t('art_title') }}</title>
  <desc id="{{ $u }}-d">{{ $t('art_desc') }}</desc>
  @include('marketing.master-marketing._engine-defs')
  <rect class="panel" x="1" y="1" width="998" height="534" rx="28"/>
  <g class="grid"><path d="M0 140H1000M0 280H1000M0 420H1000M200 0V536M400 0V536M600 0V536M800 0V536"/></g>
  <circle cx="190" cy="230" r="230" fill="url(#{{ $u }}-glow1)"/><circle cx="830" cy="230" r="230" fill="url(#{{ $u }}-glow2)"/>
  <text class="tag" x="30" y="40" font-size="12.5">{{ $t('art_src') }}</text>
  <path class="flow" d="M320 196C392 196 410 268 456 276"/>
  <path class="flow" d="M320 380C392 380 412 292 458 286"/>
  <path class="flow" d="M546 281C596 281 600 374 640 374"/>
  @include('marketing.master-marketing._engine-left')
  @include('marketing.master-marketing._engine-gate')
  @include('marketing.master-marketing._engine-right')
</svg>

{{-- Tall (phones): the same pieces stacked top to bottom --}}
@php($u = $ut)
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 400 1150" role="img" aria-labelledby="{{ $u }}-t {{ $u }}-d" class="{{ $u }} block w-full sm:hidden" style="height:auto">
  <title id="{{ $u }}-t">{{ $t('art_title') }}</title>
  <desc id="{{ $u }}-d">{{ $t('art_desc') }}</desc>
  @include('marketing.master-marketing._engine-defs')
  <rect class="panel" x="1" y="1" width="398" height="1148" rx="26"/>
  <g class="grid"><path d="M0 150H400M0 300H400M0 450H400M0 600H400M0 750H400M0 900H400M0 1050H400M100 0V1150M200 0V1150M300 0V1150"/></g>
  <circle cx="200" cy="200" r="220" fill="url(#{{ $u }}-glow1)"/><circle cx="200" cy="920" r="230" fill="url(#{{ $u }}-glow2)"/>
  <text class="tag" x="24" y="32" font-size="12">{{ $t('art_src') }}</text>
  <path class="flow" d="M200 326V410"/>
  <path class="flow" d="M200 626V664"/>

  <!-- the engine, laid out for a narrow screen -->
  <g filter="url(#{{ $u }}-sh)"><rect class="card" x="20" y="46" width="360" height="280" rx="24"/></g>
  <circle class="halo" cx="104" cy="126" r="58"/>
  <circle class="orbit" cx="104" cy="126" r="68"/><circle class="orbit r" cx="104" cy="126" r="82"/>
  <circle cx="104" cy="126" r="44" fill="url(#{{ $u }}-core)"/><circle cx="104" cy="126" r="44" fill="none" stroke="#fff" stroke-opacity=".2" stroke-width="1.5"/>
  <circle cx="104" cy="126" r="40" fill="#fff" fill-opacity=".96"/>
  <image href="{{ asset('images/brand/naara-core-mark.webp') }}" x="82.16" y="95.19" width="58" height="50.02" preserveAspectRatio="xMidYMid meet"/>
  <text class="t" x="196" y="120" font-size="19">{{ $t('art_core_title') }}</text>
  <text class="s" x="196" y="142" font-size="14">{{ $t('art_core_sub') }}</text>
  @foreach ([[34, 208, 'signal', 'art_n1'], [204, 208, 'hash', 'art_n2'], [34, 262, 'card', 'art_n3'], [204, 262, 'headset', 'art_n4']] as [$x, $y, $ic, $k])
    <g transform="translate({{ $x }} {{ $y }})">
      <rect class="chip" width="162" height="46" rx="14"/>
      <use href="#{{ $u }}-i-{{ $ic }}" class="ico" x="14" y="13" width="20" height="20"/>
      <text class="t" x="44" y="28.500" font-size="14">{{ $t($k) }}</text>
    </g>
  @endforeach

  <g transform="translate(-300 171)">@include('marketing.master-marketing._engine-gate')</g>
  <g transform="translate(-608 628)">@include('marketing.master-marketing._engine-right')</g>
</svg>
