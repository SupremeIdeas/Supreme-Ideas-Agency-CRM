{{-- A menu icon from the Naara custom set (MASTER-ONLY). Resolves the item's own slot (an admin override, then the bundled default) and swaps the light and dark
     files with the app's html.dark class; when no slot icon exists it renders the old generic icon, so a missing file never leaves a hole. Callers in shared views
     wrap this in class_exists(MenuIcons::class), so a white-label install that does not ship the set keeps its generic icons. --}}
@props(['route' => null, 'slotKey' => null, 'surface' => null, 'fallback' => 'grid', 'nx' => false, 'class' => '', 'size' => null])
@php
    use App\Support\MasterMarketing\MenuIcons;
    $slotId = $slotKey ?: ($route ? MenuIcons::slotForRoute($route, $surface ?: MenuIcons::surface()) : null);
    $mi = MenuIcons::resolve($slotId);
@endphp
@if ($mi)
    <span {{ $attributes->merge(['class' => 'mi '.($mi['custom'] ? '' : 'mi--duo ').$class]) }} @if ($size) style="--mi-size:{{ (int) $size }}px" @endif aria-hidden="true">
        <img class="mi__l" src="{{ $mi['light'] }}" alt="" width="64" height="64" decoding="async" draggable="false">
        @unless ($mi['custom'])<img class="mi__d" src="{{ $mi['dark'] }}" alt="" width="64" height="64" decoding="async" draggable="false">@endunless
    </span>
@elseif ($nx)
    <x-nx.icon :name="$fallback" {{ $attributes->only('class') }} />
@else
    <x-icon :name="$fallback" :class="$class ?: 'h-5 w-5'" />
@endif
