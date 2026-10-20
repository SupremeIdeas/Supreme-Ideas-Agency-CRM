  <!-- The official engine -->
  <g filter="url(#{{ $u }}-sh)"><rect class="card" x="28" y="56" width="292" height="448" rx="24"/></g>
  <circle class="halo" cx="174" cy="196" r="64"/>
  <circle class="orbit" cx="174" cy="196" r="78"/><circle class="orbit r" cx="174" cy="196" r="94"/>
  <circle cx="174" cy="196" r="50" fill="url(#{{ $u }}-core)"/><circle cx="174" cy="196" r="50" fill="none" stroke="#fff" stroke-opacity=".2" stroke-width="1.5"/>
  <circle cx="174" cy="196" r="46" fill="#fff" fill-opacity=".96"/>
  <image href="{{ asset('images/brand/naara-core-mark.webp') }}" x="148.39" y="159.88" width="68" height="58.65" preserveAspectRatio="xMidYMid meet"/>
  <text class="t" x="174" y="306" text-anchor="middle" font-size="19">{{ $t('art_core_title') }}</text>
  <text class="s" x="174" y="328" text-anchor="middle" font-size="14">{{ $t('art_core_sub') }}</text>
  @foreach ([[40, 356, 'signal', 'art_n1'], [174, 356, 'hash', 'art_n2'], [40, 418, 'card', 'art_n3'], [174, 418, 'headset', 'art_n4']] as [$x, $y, $ic, $k])
    <g transform="translate({{ $x }} {{ $y }})">
      <rect class="chip" width="126" height="48" rx="14"/>
      <use href="#{{ $u }}-i-{{ $ic }}" class="ico" x="11" y="14" width="20" height="20"/>
      <text class="t" x="38" y="29.5" font-size="13">{{ $t($k) }}</text>
    </g>
  @endforeach

