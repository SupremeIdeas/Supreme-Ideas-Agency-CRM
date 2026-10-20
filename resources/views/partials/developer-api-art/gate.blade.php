  <!-- 2. The Naara API: key, scopes, balance -->
  <circle class="halo" cx="480" cy="205" r="70"/><circle class="ring" cx="480" cy="205" r="84"/><circle class="ring" cx="480" cy="205" r="104" style="animation-direction:reverse;animation-duration:54s"/>
  <g filter="url(#{{ $u }}-sh)"><circle cx="480" cy="205" r="60" fill="url(#{{ $u }}-core)"/></g>
  <circle cx="480" cy="205" r="60" fill="none" stroke="#fff" stroke-opacity=".2" stroke-width="1.500"/>
  <circle cx="480" cy="205" r="52" fill="#fff" fill-opacity=".96"/>
  <image href="{{ asset('images/brand/naara-core-mark.webp') }}" x="450.63" y="163.57" width="78" height="67.28" preserveAspectRatio="xMidYMid meet"/>
  @foreach ([[480, 84, 'shield', 'Key checked'], [596, 205, 'key', 'Scoped'], [364, 205, 'pulse', 'Rate limited']] as [$bx, $by, $ic, $lb])
    <g transform="translate({{ $bx }} {{ $by }})"><circle class="card" r="18"/><use href="#{{ $u }}-i-{{ $ic }}" class="ico" x="-10" y="-10" width="20" height="20"/></g>
  @endforeach
  <text class="t" x="480" y="323" text-anchor="middle" font-size="17">Naara API</text>
  <text class="s" x="480" y="343" text-anchor="middle" font-size="12.500">api/v1 · catalogue, quote, orders</text>

