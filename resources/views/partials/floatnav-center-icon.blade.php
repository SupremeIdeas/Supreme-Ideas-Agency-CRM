{{-- The glowing centre button's icon. Master: the custom icon, drawn white on the teal button (the set is two-tone for light and dark pages, so here it is flattened to white). --}}
@php $ck = class_exists(\App\Support\MasterMarketing\MenuIcons::class) ? \App\Support\MasterMarketing\MenuIcons::navSlotKey((string) $center->target, (string) $center->icon, false) : null; @endphp
@if ($ck)<x-master-marketing.menu-icon :slot-key="$ck" :fallback="$center->icon" :size="24" class="shrink-0 [&_img]:brightness-0 [&_img]:invert" />@else<x-icon name="{{ $center->icon }}" class="h-5 w-5" />@endif
