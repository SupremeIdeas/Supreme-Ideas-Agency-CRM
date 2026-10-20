{{-- Naara Pro verified badge — a gold scalloped seal with a check. Earned by an active paid brand listing or an admin-granted Pro
     (BrandPartner::isNaaraPro). `label` adds the "Naara Pro" wording; `tone="light"` is for use over a photo/dark scrim. --}}
@props(['size' => 'md', 'label' => false, 'tone' => 'auto'])
@php
    $dim = ['sm' => 'h-4 w-4', 'md' => 'h-5 w-5', 'lg' => 'h-7 w-7'][$size] ?? 'h-5 w-5';
    $text = $tone === 'light' ? 'text-white' : 'text-accent-dark dark:text-accent';
@endphp
<span {{ $attributes->merge(['class' => 'inline-flex shrink-0 items-center gap-1 align-middle', 'title' => 'Naara Pro verified']) }}>
    <svg class="{{ $dim }} text-accent drop-shadow-sm" viewBox="0 0 24 24" role="img" aria-label="Naara Pro verified" fill="none">
        <path d="M3.85 8.62a4 4 0 0 1 4.78-4.77 4 4 0 0 1 6.74 0 4 4 0 0 1 4.78 4.78 4 4 0 0 1 0 6.74 4 4 0 0 1-4.77 4.78 4 4 0 0 1-6.75 0 4 4 0 0 1-4.78-4.77 4 4 0 0 1 0-6.76z" fill="currentColor"/>
        <path d="m8.5 12.2 2.4 2.4 4.6-4.9" stroke="#fff" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>
    </svg>
    @if ($label)
        <span class="text-[11px] font-bold uppercase tracking-wide {{ $text }}">Naara Pro</span>
    @endif
</span>
