{{-- A handbook cover (MASTER-ONLY). The real cover when there is one; otherwise a typeset cover in the Naara palette (never an empty box). 3:4, rounded, with a spine highlight. --}}
@props(['book', 'eager' => false, 'class' => ''])
@php
    $palettes = [['#0A6E6E', '#0D1B2A'], ['#0D1B2A', '#1D4E89'], ['#7A4E00', '#D4A017'], ['#12433F', '#13A39B'], ['#3B1F5C', '#0A6E6E'], ['#1F3A2E', '#4C9F70']];
    $pal = $palettes[crc32((string) $book->slug) % count($palettes)];
@endphp
<span {{ $attributes->merge(['class' => 'hbk '.$class]) }} style="--hbk-a: {{ $pal[0] }}; --hbk-b: {{ $pal[1] }}">
    {{-- The typeset cover is always underneath: it is the cover when there is no image, and what shows if the image is missing or fails to load. --}}
    <span class="hbk__type" aria-label="{{ $book->title }}">
        <small>{{ $book->country_code ?: 'Naara' }}</small>
        <b>{{ $book->title }}</b>
        <em>Travel Handbook{{ $book->catalogue_id && ! str_starts_with((string) $book->catalogue_id, 'DEMO') ? ' · '.$book->catalogue_id : '' }}</em>
    </span>
    @if ($book->cover_path)
        <img src="{{ route('handbooks.cover', $book->slug) }}" alt="Cover of {{ $book->title }}" width="450" height="600" loading="{{ $eager ? 'eager' : 'lazy' }}" decoding="async" onerror="this.remove()">
    @endif
    <i class="hbk__spine" aria-hidden="true"></i>
</span>
