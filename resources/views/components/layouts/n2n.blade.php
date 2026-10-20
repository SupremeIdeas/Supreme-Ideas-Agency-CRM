{{-- nx:converted --}}
{{-- N2N shell (MASTER-ONLY): Naara chat is its own app inside the app. Full-bleed (no page gutters, no card in a card), its own sticky header
     with the same "More" button the Numbers section uses, its own skin-aware bottom bar built from App\Support\N2N\N2NNav, a desktop rail,
     and a docked composer. In an open thread the bar and header step aside (CSS :has on [data-n2n-thread]) so the composer sits at the very
     bottom, like a real chat app. Skin tokens only. --}}
@props(['title' => null, 'preloaderType' => 'n2n'])
@php
    $u = auth()->user();
    $items = \App\Support\N2N\N2NNav::items($u);
    $bar = \App\Support\N2N\N2NNav::bar($items);
    $railOnly = array_values(array_filter($items, fn ($i) => ! $i['bar']));
    // "More" opens the SAME global sheet the Numbers header opens: the full app, never a dead end.
    $resolved = \App\Support\BottomNav::resolveMain($u);
    $more = array_merge($resolved['primary'], $resolved['more']);
    $credits = \App\Services\N2N\Credit\Money::credits(app(\App\Services\N2N\Credit\UsageMeter::class)->available($u));
    $half = (int) ceil(count($bar) / 2);
    $logo = \App\Support\N2N\N2NSettings::logoUrl();
@endphp
<x-layouts.app :title="$title ?? 'Naara chat'" :page-type="$preloaderType" :body-class="\App\Support\PlatformTheme::bodyClass()">
<div class="nn" x-data="{ moreOpen: false, moreLayout: localStorage.getItem('nx_more_layout') === 'list' ? 'list' : 'grid' }"
     x-effect="localStorage.setItem('nx_more_layout', moreLayout)">

    {{-- ===== Desktop rail ===== --}}
    <aside class="nn__rail" aria-label="Naara chat navigation">
        <a href="{{ route('dashboard') }}" wire:navigate class="nn__brand" aria-label="Back to the dashboard"><img src="{{ $logo }}" alt="Naara chat" width="52" height="52" class="nn__brandimg"></a>
        <a href="{{ route('n2n.chat', ['new' => 1]) }}" wire:navigate class="ns-cta nn__compose" aria-label="New chat"><x-nx.icon name="edit" /><span>New chat</span></a>
        <nav class="nn__railnav">
            @foreach ($items as $i)
                <a href="{{ route($i['route']) }}" wire:navigate title="{{ $i['label'] }}" aria-label="{{ $i['label'] }}" @class(['nn__railitem', 'is-on' => \App\Support\N2N\N2NNav::active($i)]) @if (\App\Support\N2N\N2NNav::active($i)) aria-current="page" @endif>
                    <x-nx.icon :name="$i['icon']" /><span>{{ $i['label'] }}</span>
                    @if ($i['badge'] > 0)<b class="nn__badge">{{ $i['badge'] > 99 ? '99+' : $i['badge'] }}</b>@endif
                </a>
            @endforeach
        </nav>
        <button type="button" class="nn__railitem" title="More" aria-label="More" @click="moreOpen = true"><x-nx.icon name="grid" /><span>More</span></button>
        <div class="nn__grip nn__grip--rail" data-nn-grip="rail" role="separator" aria-orientation="vertical" aria-label="Resize the navigation rail (double-click to reset)" aria-valuemin="72" aria-valuemax="200" aria-valuenow="92" tabindex="0"><i></i></div>
    </aside>

    <div class="nn__col">
        {{-- ===== Header (Numbers-style: section name left, balance + More right) ===== --}}
        <header class="nn__head">
            <a href="{{ route('n2n.chat') }}" wire:navigate class="nn__title"><img src="{{ $logo }}" alt="" width="34" height="34" class="nn__logo"> <span>Naara chat</span></a>
            <div class="nn__actions">
                <a href="{{ route('n2n.credits') }}" wire:navigate class="nn__credits" aria-label="Your NaaraCredit"><x-nx.icon name="wallet" /> {{ $credits }}<span class="nn__plus"><x-nx.icon name="plus" /></span></a>
                <livewire:notification-center :key="'nc-n2n'" />
                <span class="nn__wide"><x-lang-switcher /></span>
                <span class="nn__wide"><x-theme-toggle /></span>
                <a href="{{ route('n2n.profile.settings') }}" wire:navigate class="nn__me" aria-label="My profile"><x-avatar :src="$u->avatar" :name="$u->name" size="h-8 w-8" :on-dark="true" /></a>
                <button type="button" @click="moreOpen = true" aria-label="More" class="nn__iconbtn"><x-nx.icon name="grid" /></button>
            </div>
        </header>

        <main class="nn__main">{{ $slot }}</main>

        {{-- ===== Bottom bar: its own destinations + a raised "New chat" centre ===== --}}
        <nav class="nn__bar nx-bottomnav ns-ring" aria-label="Naara chat" style="padding-bottom: env(safe-area-inset-bottom);">
            <div class="nn__barrow" style="--nn-cols: {{ count($bar) + 1 }}">
                @foreach (array_slice($bar, 0, $half) as $i)
                    @include('n2n.nav-item', ['item' => $i])
                @endforeach
                <a href="{{ route('n2n.chat', ['new' => 1]) }}" wire:navigate aria-label="New chat" class="nn__new ns-cta nx-bn-more"><x-nx.icon name="edit" /></a>
                @foreach (array_slice($bar, $half) as $i)
                    @include('n2n.nav-item', ['item' => $i])
                @endforeach
            </div>
        </nav>
    </div>

    <x-more-sheet :more="$more" :promo="false" :always="true" />
    @vite('resources/js/n2n/shell.js')
</div>
</x-layouts.app>
