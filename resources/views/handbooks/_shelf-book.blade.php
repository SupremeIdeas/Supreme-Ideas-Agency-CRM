{{-- One book on a shelf (MASTER-ONLY). --}}
<a href="{{ route('handbooks.show', $b->slug) }}" class="hb-book" data-handbook-card wire:key="shelf-{{ $b->id }}">
    <x-handbook-cover :book="$b" />
    <span class="hb-book__t">{{ $b->title }}</span>
    <span class="hb-book__m">
        <b>${{ number_format((float) $b->list_price_usd, 2) }}</b>
        @if ($b->country_code && $b->unlock_via_esim)<span class="hb-book__tag"><x-icon name="sim" class="h-3 w-3" />Free with {{ $b->country_code }} eSIM</span>@endif
    </span>
</a>
