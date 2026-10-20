{{-- The tile every setup wizard wears: Nia's own face when the admin has set the assistant avatar, else the plain icon. One place, so every wizard stays personal together. --}}
@props(['icon' => 'sparkles'])
@php($niaFace = \App\Support\SupportSettings::avatar())
@if ($niaFace !== '')
    <span {{ $attributes->merge(['class' => 'ns-tile']) }} style="padding:0;overflow:hidden" data-nia-tile><img src="{{ $niaFace }}" alt="{{ \App\Support\SupportSettings::name() }}" width="44" height="44" class="h-full w-full object-cover"></span>
@else
    <span {{ $attributes->merge(['class' => 'ns-tile']) }} aria-hidden="true"><x-nx.icon :name="$icon" /></span>
@endif
