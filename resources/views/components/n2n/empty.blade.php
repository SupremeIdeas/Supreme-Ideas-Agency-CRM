{{-- Naara chat empty state with a drawn illustration (MASTER-ONLY). Skin tokens only. --}}
@props(['art', 'title' => null, 'text' => null])
<div {{ $attributes->merge(['class' => 'px-4 py-8 text-center']) }} data-n2n-empty>
    <img src="{{ asset('images/illustrations/'.$art.'.svg') }}" alt="" width="240" height="180" loading="lazy" decoding="async" class="mx-auto h-36 w-auto sm:h-40">
    @if ($title)<h2 class="mt-2 text-base font-semibold text-[rgb(var(--nx-text))]">{{ $title }}</h2>@endif
    @if ($text)<p class="mx-auto mt-1 max-w-sm text-sm text-[rgb(var(--nx-text-2))]">{{ $text }}</p>@endif
    {{ $slot }}
</div>
