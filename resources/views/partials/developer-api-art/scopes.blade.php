  <!-- the four scopes a key can hold -->
  @foreach ([[28, 'list', 'catalogue'], [156, 'quote', 'quote'], [284, 'bag', 'order'], [412, 'pulse', 'status']] as [$cx, $ic, $lb])
    <g transform="translate({{ $cx }} 366)"><rect class="chip" width="118" height="34" rx="17"/><use href="#{{ $u }}-i-{{ $ic }}" class="ico" x="12" y="8" width="18" height="18"/><text class="t mono" x="36" y="22" font-size="12">{{ $lb }}</text></g>
  @endforeach
