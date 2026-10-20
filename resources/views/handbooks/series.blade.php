{{-- nx:converted --}}
<x-layouts.marketing :title="'The '.$series->name.' — Naara Travel Handbooks'">
<x-nx.page class="ns-pg">
    <a href="{{ route('handbooks.directory') }}" class="ns-pg__back"><x-nx.icon name="left" /> All handbooks</a>
    <h1 class="ns-h1" style="margin-top:6px">{{ $series->name }}</h1>
    @if ($series->description)<p class="ns-sub" style="margin:6px 0 0">{{ $series->description }}</p>@endif
    <div class="hb-shelf" style="color:rgb(var(--nx-text));margin-top:20px" data-handbook-grid>@foreach ($books as $b)@include('handbooks._shelf-book', ['b' => $b])@endforeach</div>
</x-nx.page>
</x-layouts.marketing>
