{{-- nx:converted --}}
{{-- Business shell (MASTER-ONLY): the Business Suite's own app inside the app, built on the Naara chat shell so it carries the same skin signatures and
     contrast guarantees (.nn / .nc classes, tokens only). Desktop: a rail with the workspace destinations. Phone: a docked bottom bar with a raised
     "New message" centre. Header: the workspace chip (switch workspace or go back to Personal), the bell, language and theme, More. In an open
     conversation on a phone the header and bar step aside so the composer owns the bottom edge (CSS :has on [data-bz-thread]). --}}
@props(['title' => null, 'preloaderType' => 'dashboard'])
@php
    $u = auth()->user();
    $group = \App\Support\Workspace::active() ?? ($u ? \App\Support\Business\WorkspaceAdminAccess::groupFor($u) : null);
    $items = \App\Support\Business\BusinessNav::items($u, $group);
    $bar = \App\Support\Business\BusinessNav::bar($items);
    $railOnly = array_values(array_filter($items, fn ($i) => ! $i['bar']));
    $resolved = \App\Support\BottomNav::resolveMain($u);
    $more = array_merge($resolved['primary'], $resolved['more']);
    $half = (int) ceil(count($bar) / 2);
    $workspaces = $u ? \App\Support\Workspace::switchableFor($u) : collect();
    $initial = $group ? mb_strtoupper(mb_substr($group->name, 0, 1)) : 'B';
@endphp
<x-layouts.app :title="$title ?? 'Business'" :page-type="$preloaderType" :body-class="\App\Support\PlatformTheme::bodyClass()">
<div class="nn nb" x-data="{ moreOpen: false, wsOpen: false, moreLayout: localStorage.getItem('nx_more_layout') === 'list' ? 'list' : 'grid' }"
     x-effect="localStorage.setItem('nx_more_layout', moreLayout)" @keydown.escape.window="wsOpen = false">

    {{-- ===== Desktop rail ===== --}}
    <aside class="nn__rail nb__rail" aria-label="Business navigation">
        <a href="{{ route('business.workspace') }}" wire:navigate class="nn__brand" aria-label="Business overview"><span class="nb__mark" aria-hidden="true"><x-nx.icon name="briefcase" /></span></a>
        @if (\Illuminate\Support\Facades\Route::has('numbers.messages'))
            <a href="{{ route('numbers.messages', ['compose' => 1]) }}" wire:navigate class="ns-cta nn__compose" aria-label="New message"><x-nx.icon name="edit" /><span>Compose</span></a>
        @endif
        <nav class="nn__railnav">
            @foreach ($items as $i)
                <a href="{{ route($i['route']) }}" wire:navigate title="{{ $i['label'] }}" aria-label="{{ $i['label'] }}" @class(['nn__railitem', 'is-on' => \App\Support\Business\BusinessNav::active($i)]) @if (\App\Support\Business\BusinessNav::active($i)) aria-current="page" @endif>
                    <x-nx.icon :name="$i['icon']" /><span>{{ $i['label'] }}</span>
                    @if ($i['badge'] > 0)<b class="nn__badge">{{ $i['badge'] > 99 ? '99+' : $i['badge'] }}</b>@endif
                </a>
            @endforeach
        </nav>
        <button type="button" class="nn__railitem" title="More" aria-label="More" @click="moreOpen = true"><x-nx.icon name="grid" /><span>More</span></button>
    </aside>

    <div class="nn__col">
        {{-- ===== Header: the workspace chip on the left, balance-free actions on the right ===== --}}
        <header class="nn__head nb__head">
            <div class="nb__ws" @click.outside="wsOpen = false">
                <button type="button" class="nb__wschip" @click="wsOpen = !wsOpen" :aria-expanded="wsOpen" aria-haspopup="menu" aria-label="Switch workspace">
                    <span class="nb__wsmark" aria-hidden="true">{{ $initial }}</span>
                    <span class="nb__wsname">{{ $group?->name ?? 'Business' }}</span>
                    <x-nx.icon name="chevron-right" class="nb__wscaret" />
                </button>
                <div class="nb__wsmenu" x-show="wsOpen" x-cloak style="display:none" role="menu" aria-label="Workspaces">
                    <form method="POST" action="{{ route('workspace.switch') }}">@csrf
                        <button type="submit" role="menuitem" class="nb__wsitem"><x-nx.icon name="user" /><span>Personal</span></button>
                    </form>
                    @foreach ($workspaces as $ws)
                        <form method="POST" action="{{ route('workspace.switch', $ws) }}">@csrf
                            <button type="submit" role="menuitem" @class(['nb__wsitem', 'is-on' => $group?->id === $ws->id])><x-nx.icon :name="$ws->isBusiness() ? 'briefcase' : 'users'" /><span>{{ $ws->name }}</span>@if ($group?->id === $ws->id)<x-nx.icon name="check" class="nb__wscheck" />@endif</button>
                        </form>
                    @endforeach
                </div>
            </div>
            <div class="nn__actions">
                <livewire:notification-center :key="'nc-business'" />
                <span class="nn__wide"><x-lang-switcher /></span>
                <span class="nn__wide"><x-theme-toggle /></span>
                <a href="{{ route('dashboard') }}" wire:navigate class="nn__me" aria-label="Back to my dashboard"><x-avatar :src="$u->avatar" :name="$u->name" size="h-8 w-8" :on-dark="true" /></a>
                <button type="button" @click="moreOpen = true" aria-label="More" class="nn__iconbtn"><x-nx.icon name="grid" /></button>
            </div>
        </header>

        <main class="nn__main nb__main">{{ $slot }}</main>

        {{-- ===== Bottom bar: the workspace destinations + a raised "New message" centre ===== --}}
        <nav class="nn__bar nb__bar nx-bottomnav ns-ring" aria-label="Business" style="padding-bottom: env(safe-area-inset-bottom);">
            <div class="nn__barrow" style="--nn-cols: {{ count($bar) + 1 }}">
                @foreach (array_slice($bar, 0, $half) as $i)
                    @include('business.nav-item', ['item' => $i])
                @endforeach
                <a href="{{ route('numbers.messages', ['compose' => 1]) }}" wire:navigate aria-label="New message" class="nn__new ns-cta nx-bn-more"><x-nx.icon name="edit" /></a>
                @foreach (array_slice($bar, $half) as $i)
                    @include('business.nav-item', ['item' => $i])
                @endforeach
            </div>
        </nav>
    </div>

    <x-more-sheet :more="$more" :promo="false" :always="true" />
</div>
</x-layouts.app>
