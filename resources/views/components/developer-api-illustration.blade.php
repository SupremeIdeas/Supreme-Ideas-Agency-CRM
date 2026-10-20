{{-- "Your app -> the Naara API -> orders and answers" illustration for the Developer API page. Inline SVG that follows the active skin and light/dark through the
     --nx-* tokens (with light fallbacks). Two layouts of the same artwork: wide (tablet and up) and tall (phones), so nothing is cropped or hidden behind a sideways
     scroll. The four scopes shown are the real ones a key can hold (catalogue, quote, order, status). --}}
@php
    $uw = 'da'.substr(md5(uniqid('w', true)), 0, 6);
    $ut = 'da'.substr(md5(uniqid('t', true)), 0, 6);
@endphp
<div {{ $attributes->merge(['class' => 'nx-da-illus']) }}>
@php($u = $uw)
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 960 420" role="img" aria-labelledby="{{ $u }}-t {{ $u }}-d" class="{{ $u }} hidden w-full sm:block" style="height:auto">
  <title id="{{ $u }}-t">Your app calls the Naara API</title>
  <desc id="{{ $u }}-d">Your app sends a signed request with an API key. The Naara API checks the key, its scopes and your prepaid balance, then lets you read the catalogue, get a quote, place an order and follow its status. The answer comes back as JSON.</desc>
  @include('partials.developer-api-art.defs')
  <rect class="panel" x="1" y="1" width="958" height="418" rx="26"/>
  <g class="grid"><path d="M0 105H960M0 210H960M0 315H960M160 0V420M320 0V420M480 0V420M640 0V420M800 0V420"/></g>
  <path class="flow" d="M298 157C350 157 372 205 418 205"/>
  <path class="flow back" d="M542 205C588 205 610 157 662 157"/>
  @include('partials.developer-api-art.request')
  @include('partials.developer-api-art.gate')
  @include('partials.developer-api-art.scopes')
  @include('partials.developer-api-art.response')
  @include('partials.developer-api-art.balance')
</svg>
@php($u = $ut)
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 400 1140" role="img" aria-labelledby="{{ $u }}-t {{ $u }}-d" class="{{ $u }} block w-full sm:hidden" style="height:auto">
  <title id="{{ $u }}-t">Your app calls the Naara API</title>
  <desc id="{{ $u }}-d">Your app sends a signed request with an API key. The Naara API checks the key, its scopes and your prepaid balance, then lets you read the catalogue, get a quote, place an order and follow its status. The answer comes back as JSON.</desc>
  @include('partials.developer-api-art.defs')
  <rect class="panel" x="1" y="1" width="398" height="1138" rx="26"/>
  <g class="grid"><path d="M0 150H400M0 300H400M0 450H400M0 600H400M0 750H400M0 900H400M0 1050H400M100 0V1140M200 0V1140M300 0V1140"/></g>
  <path class="flow" d="M200 316V372"/>
  <path class="flow" d="M200 706V752"/>
  <g transform="translate(65 -14)">@include('partials.developer-api-art.request')</g>
  <g transform="translate(-280 245)">@include('partials.developer-api-art.gate')</g>
  @foreach ([[70, 618, 'list', 'catalogue'], [212, 618, 'quote', 'quote'], [70, 664, 'bag', 'order'], [212, 664, 'pulse', 'status']] as [$cx, $cy, $ic, $lb])
    <g transform="translate({{ $cx }} {{ $cy }})"><rect class="chip" width="118" height="34" rx="17"/><use href="#{{ $u }}-i-{{ $ic }}" class="ico" x="12" y="8" width="18" height="18"/><text class="t mono" x="36" y="22" font-size="12">{{ $lb }}</text></g>
  @endforeach
  <g transform="translate(-597 716)">@include('partials.developer-api-art.response')</g>
  <g transform="translate(-597 716)">@include('partials.developer-api-art.balance')</g>
</svg>
</div>
