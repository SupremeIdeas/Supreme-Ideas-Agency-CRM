{{-- A handbook in a grid (MASTER-ONLY). Cover is 2:3. Skin tokens only. --}}
<a href="{{ route('handbooks.show', $b->slug) }}" class="hb-card" data-handbook-card>
    <x-handbook-cover :book="$b" />
    <span class="hb-card__t">{{ $b->title }}</span>
    @if ($b->subtitle)<span class="hb-card__s">{{ $b->subtitle }}</span>@endif
    <span class="hb-card__m"><b>${{ number_format((float) $b->list_price_usd, 2) }}</b>@if ($b->last_verified_on)<span>Verified {{ $b->last_verified_on->format('M Y') }}</span>@endif</span>
</a>
