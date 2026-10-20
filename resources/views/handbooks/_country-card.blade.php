{{-- nx:converted --}}
{{-- "Travel handbook for {country}" card on an eSIM country page (MASTER-ONLY; the catalogue includes it with @includeIf so a fork without the store renders nothing). Skin tokens only. --}}
@php
    $hbBook = $cc ? app(\App\Services\Handbooks\HandbookUnlockService::class)->flagshipFor((string) $cc) : null;
@endphp
@if ($hbBook)
    <a href="{{ route('handbooks.show', $hbBook->slug) }}" wire:navigate class="ns-pg__card ns-ring hb-continue" style="margin-top:16px" data-country-handbook>
        <span class="hb-continue__cover"><x-handbook-cover :book="$hbBook" /></span>
        <span><b style="color:rgb(var(--nx-text))">Travel handbook for {{ $hbBook->title }}</b><span class="ns-pg__hint" style="display:block;margin:2px 0 0">Free with your {{ $hbBook->country_code }} eSIM. Visas, housing, money and more.</span></span>
        <x-nx.icon name="right" />
    </a>
@endif
