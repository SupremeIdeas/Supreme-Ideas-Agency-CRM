{{-- Language switcher (owner request, 2026-10-03). A compact header chip that shows the active language code ("EN" by default); tapping it opens
     the platform's ONE modal engine with every available language. Works for guests (marketing nav, login) and members (header): the choice is
     a plain POST to /locale (session + cookie + profile), so it needs no Livewire and survives full-page navigation.
     Members' chip + list follow the skin tokens (see `.nx-lang*` in nx-layout.css); guests get the neutral Tailwind look. --}}
@props(['onDark' => false])
@php
    $cur = app()->getLocale();
    $opts = \App\Support\Locale::available();
    $curName = $opts[$cur]['native'] ?? 'English';
@endphp
<button type="button" x-data @click="$dispatch('open-modal', { name: 'language' })"
        aria-haspopup="dialog" aria-label="{{ __('common.language_current', ['name' => $curName]) }}" title="{{ $curName }}"
        {{ $attributes->merge(['class' => 'nx-lang-chip inline-flex h-9 items-center gap-1.5 rounded-full border px-2.5 text-xs font-bold tracking-wide transition '.($onDark ? 'border-white/60 bg-[rgb(13_27_42)] text-white hover:bg-[rgb(22_44_66)]' : 'border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface-3))] text-[rgb(var(--nx-text))] hover:border-[rgb(var(--nx-teal-ink))] hover:bg-[rgb(var(--nx-surface))]')]) }}>
    <x-icon name="globe" class="h-4 w-4" />
    <span>{{ \App\Support\Locale::code($cur) }}</span>
</button>

@include('partials.lang-modal')
