  <!-- Your white label: three branded phones -->
  <text class="tag" x="642" y="40" font-size="12.5">{{ $t('art_dst') }}</text>
  @foreach ([['a', 656, 104, -7, 'pa', 'art_d1', 702], ['c', 856, 104, 7, 'pc', 'art_d3', 906]] as [$cls, $x, $y, $rot, $g, $k, $cx])
    <g class="float {{ $cls === 'c' ? 'c' : '' }}"><g transform="translate({{ $x }} {{ $y }}) rotate({{ $rot }} 52 106)" filter="url(#{{ $u }}-sh)">
      <rect class="card" width="104" height="212" rx="18"/>
      <path d="M0 18A18 18 0 0 1 18 0H86A18 18 0 0 1 104 18V74H0Z" fill="url(#{{ $u }}-{{ $g }})"/>
      <rect x="40" y="7" width="24" height="5" rx="2.5" fill="#fff" fill-opacity=".45"/>
      <rect x="14" y="30" width="48" height="7" rx="3.5" fill="#fff" fill-opacity=".85"/><rect x="14" y="44" width="70" height="14" rx="5" fill="#fff" fill-opacity=".25"/>
      <rect class="chip" x="12" y="86" width="80" height="26" rx="8"/><rect class="chip" x="12" y="118" width="80" height="26" rx="8"/><rect class="chip" x="12" y="150" width="80" height="26" rx="8"/>
      <circle cx="26" cy="99" r="6" fill="url(#{{ $u }}-{{ $g }})"/><circle cx="26" cy="131" r="6" fill="url(#{{ $u }}-{{ $g }})"/><circle cx="26" cy="163" r="6" fill="url(#{{ $u }}-{{ $g }})"/>
    </g></g>
  @endforeach
  <g class="float b"><g transform="translate(752 72)" filter="url(#{{ $u }}-sh)">
    <rect class="card" width="116" height="236" rx="20"/>
    <path d="M0 20A20 20 0 0 1 20 0H96A20 20 0 0 1 116 20V82H0Z" fill="url(#{{ $u }}-pb)"/>
    <rect x="46" y="8" width="24" height="5" rx="2.5" fill="#fff" fill-opacity=".45"/>
    <rect x="16" y="34" width="52" height="7" rx="3.5" fill="#fff" fill-opacity=".85"/><rect x="16" y="49" width="84" height="16" rx="6" fill="#fff" fill-opacity=".25"/>
    <rect class="chip" x="13" y="94" width="90" height="29" rx="9"/><rect class="chip" x="13" y="130" width="90" height="29" rx="9"/><rect class="chip" x="13" y="166" width="90" height="29" rx="9"/>
    <circle cx="29" cy="108.5" r="7" fill="url(#{{ $u }}-pb)"/><circle cx="29" cy="144.5" r="7" fill="url(#{{ $u }}-pb)"/><circle cx="29" cy="180.5" r="7" fill="url(#{{ $u }}-pb)"/>
    <rect x="38" y="104" width="44" height="6" rx="3" fill="var(--c-line)" fill-opacity=".7"/><rect x="38" y="140" width="52" height="6" rx="3" fill="var(--c-line)" fill-opacity=".7"/><rect x="38" y="176" width="36" height="6" rx="3" fill="var(--c-line)" fill-opacity=".7"/>
    <rect x="22" y="208" width="72" height="14" rx="7" fill="#D4A017"/>
  </g></g>
  <text class="t" x="704" y="338" text-anchor="middle" font-size="13">{{ $t('art_d1') }}</text>
  <text class="t" x="810" y="338" text-anchor="middle" font-size="13">{{ $t('art_d2') }}</text>
  <text class="t" x="908" y="338" text-anchor="middle" font-size="13">{{ $t('art_d3') }}</text>

  <!-- Your platform bar + earnings -->
  <g filter="url(#{{ $u }}-sh)"><rect x="640" y="354" width="332" height="42" rx="21" fill="url(#{{ $u }}-core)"/></g>
  <circle cx="663" cy="375" r="13" fill="#fff" fill-opacity=".96"/><image href="{{ asset('images/brand/naara-core-mark.webp') }}" x="656.6" y="365.97" width="17" height="14.66" preserveAspectRatio="xMidYMid meet"/>
  <text x="682" y="380" fill="#fff" font-size="14.5" font-weight="700">{{ $t('art_out') }}</text>
  <g filter="url(#{{ $u }}-sh)"><rect class="card" x="640" y="416" width="332" height="88" rx="20"/></g>
  <text class="t" x="662" y="451" font-size="17">{{ $t('art_earn') }}</text>
  <text class="s" x="662" y="474" font-size="14">{{ $t('art_earn_sub') }}</text>
  <path d="M826 486 850 474 872 480 896 458 920 450 948 428V492H826Z" fill="url(#{{ $u }}-area)"/>
  <path d="M826 486 850 474 872 480 896 458 920 450 948 428" fill="none" stroke="#D4A017" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
  <circle class="halo" cx="948" cy="428" r="10" style="fill:#D4A017"/><circle cx="948" cy="428" r="5" fill="#D4A017"/>
