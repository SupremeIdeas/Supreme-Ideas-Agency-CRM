{{-- Merchant side pane (owner request, 2026-10-11). PHONE ONLY: desktop already has the universal side menu. Everything about running a business on Naara lives
     here so the More menu stays tidy for people who are not merchants. Opened by the merchant button in the header (or any `open-merchant-pane` event). --}}
@php
    $mpUser = auth()->user();
    $mpMerchant = $mpUser?->merchantAccount;
    $mpOn = \App\Support\Header\HeaderItems::isMerchant($mpUser);
@endphp
@if ($mpOn)
    @php
        $mpV2 = $mpMerchant->isV2();
        $mpName = $mpMerchant->business_name ?: \App\Support\BrandSettings::name();
        $mpBalance = (float) ($mpUser->wallet?->usd_balance ?? 0);
        $mpFav = \App\Support\BrandSettings::favicon();
        $mpActive = fn ($r) => request()->routeIs($r);
        $mpGroups = [
            __('header.merchant_run') => array_values(array_filter([
                ['merchant.dashboard', 'My Storefront', 'package', true],
                ['merchant.clients', 'Clients', 'users', $mpV2],
                ['merchant.invoices', 'Invoices', 'file-text', $mpV2],
            ], fn ($i) => $i[3] && \Illuminate\Support\Facades\Route::has($i[0]))),
            __('header.merchant_dev') => array_values(array_filter([
                ['developer', 'Developer portal', 'key', (bool) \App\Models\Setting::getValue('developer_api.enabled', false)],
                ['developers', 'API documentation', 'code', (bool) \App\Models\Setting::getValue('developer_api.enabled', false)],
                ['merchant.widgets', 'Widgets', 'globe', \App\Support\ComingSoon::isLive('merchant_widgets')],
            ], fn ($i) => $i[3] && \Illuminate\Support\Facades\Route::has($i[0]))),
            __('header.merchant_money') => array_values(array_filter([
                ['merchant.earnings', 'Earnings', 'wallet', true],
                ['merchant.white-label', 'White label', 'layers', $mpV2],
            ], fn ($i) => $i[3] && \Illuminate\Support\Facades\Route::has($i[0]))),
        ];
    @endphp
    <div x-data="{ open: false }" @open-merchant-pane.window="open = true" @keydown.escape.window="open = false" class="lg:hidden">
        <template x-teleport="body">
            <div x-show="open" x-cloak class="mpane lg:hidden" style="display:none" role="dialog" aria-modal="true" aria-label="{{ __('header.merchant_panel') }}">
                <div class="mpane__scrim" x-show="open" x-transition.opacity @click="open = false"></div>
                <aside class="mpane__panel" x-show="open" x-transition:enter="mpane-in" x-transition:enter-start="mpane-from" x-transition:enter-end="mpane-to" x-transition:leave="mpane-out" x-transition:leave-start="mpane-to" x-transition:leave-end="mpane-from">
                    <div class="mpane__head">
                        <div class="mpane__brand">
                            <span class="mpane__logo">@if ($mpFav)<img src="{{ $mpFav }}" alt="" width="28" height="28">@else<x-nx.icon name="grid" />@endif</span>
                            <span class="mpane__who"><b class="mpane__name">{{ $mpName }}</b><span class="mpane__role">{{ __('header.merchant_panel') }}</span></span>
                            <button type="button" class="mpane__x" @click="open = false" aria-label="{{ __('common.close') }}"><x-nx.icon name="x" /></button>
                        </div>
                        <a href="{{ route('wallet') }}" wire:navigate @click="open = false" class="mpane__balance"><span>{{ __('header.balance') }}</span><b>${{ number_format($mpBalance, 2) }}</b></a>
                    </div>
                    <nav class="mpane__body" aria-label="{{ __('header.merchant_panel') }}">
                        @foreach ($mpGroups as $title => $rows)
                            @continue($rows === [])
                            <span class="mpane__group">{{ $title }}</span>
                            @foreach ($rows as [$route, $label, $icon])
                                <a href="{{ route($route) }}" wire:navigate @click="open = false" @class(['mpane__row', 'is-on' => $mpActive($route)]) @if ($mpActive($route)) aria-current="page" @endif>
                                    <span class="mpane__tile"><x-nx.icon :name="$icon" /></span><span>{{ $label }}</span><x-nx.icon name="chevron-right" class="mpane__chev" />
                                </a>
                            @endforeach
                        @endforeach
                    </nav>
                </aside>
            </div>
        </template>
    </div>
    @once
        <style>
            .mpane { position: fixed; inset: 0; z-index: 72; }
            .mpane__scrim { position: absolute; inset: 0; background: rgb(0 0 0 / .5); }
            /* A floating, fully rounded drawer (curvy on every corner) with a rounded header card inside it, like the reference. */
            .mpane__panel { position: absolute; top: 10px; bottom: 10px; inset-inline-start: 10px; display: flex; flex-direction: column; width: min(86vw, 336px); border-radius: 30px; background: rgb(var(--nx-surface)); box-shadow: 0 24px 60px -14px rgb(0 0 0 / .6), inset 0 0 0 1px rgb(var(--nx-line)); overflow: hidden; }
            .mpane__head { position: relative; margin: 8px; padding: 16px 14px 14px; border-radius: 24px 24px 30px 30px; color: #fff; overflow: hidden; background: linear-gradient(150deg, rgb(var(--nx-cta-a)), rgb(var(--nx-cta-b)) 55%, rgb(var(--nx-acc-c, var(--nx-cta-b)))); }
            .mpane__head::after { content: ""; position: absolute; inset-inline-end: -40px; top: -50px; width: 170px; height: 170px; border-radius: 50%; background: radial-gradient(closest-side, rgb(255 255 255 / .2), transparent); pointer-events: none; }
            .mpane__brand { position: relative; display: flex; align-items: center; gap: 11px; }
            .mpane__logo { display: grid; place-items: center; flex: none; width: 44px; height: 44px; border-radius: 15px; background: rgb(255 255 255 / .16); box-shadow: inset 0 0 0 1px rgb(255 255 255 / .26); font-size: 20px; }
            .mpane__logo img { width: 32px; height: 32px; object-fit: contain; border-radius: 8px; }
            .mpane__who { min-width: 0; flex: 1; display: flex; flex-direction: column; gap: 1px; }
            .mpane__name { display: -webkit-box; -webkit-box-orient: vertical; -webkit-line-clamp: 2; overflow: hidden; font-size: 16px; line-height: 1.18; letter-spacing: .01em; }
            .mpane__role { font-size: 11px; font-weight: 600; letter-spacing: .12em; text-transform: uppercase; color: rgb(255 255 255 / .72); }
            .mpane__x { display: grid; place-items: center; flex: none; width: 32px; height: 32px; border: 0; border-radius: 50%; cursor: pointer; color: #fff; background: rgb(255 255 255 / .18); align-self: flex-start; }
            .mpane__balance { position: relative; display: flex; align-items: center; justify-content: space-between; margin-top: 14px; padding: 11px 16px; border-radius: 18px; background: rgb(0 0 0 / .24); box-shadow: inset 0 0 0 1px rgb(255 255 255 / .12); color: #fff; text-decoration: none; font-size: 11.5px; font-weight: 700; letter-spacing: .12em; text-transform: uppercase; }
            .mpane__balance b { font-size: 21px; letter-spacing: 0; color: rgb(var(--nx-gold)); }
            .mpane__body { flex: 1; overflow-y: auto; overscroll-behavior: contain; padding: 4px 12px calc(18px + env(safe-area-inset-bottom)); }
            .mpane__group { display: block; margin: 16px 6px 6px; font-size: 11px; font-weight: 700; letter-spacing: .13em; text-transform: uppercase; color: rgb(var(--nx-text-2)); }
            .mpane__row { display: flex; align-items: center; gap: 12px; padding: 9px 10px; margin-bottom: 4px; border-radius: 18px; color: rgb(var(--nx-text)); text-decoration: none; font-size: 15px; font-weight: 600; transition: background .15s ease; }
            .mpane__row:hover, .mpane__row:focus-visible { background: rgb(var(--nx-surface-3)); outline: none; }
            .mpane__row.is-on { background: rgb(var(--nx-cta-b) / .13); box-shadow: inset 0 0 0 1.5px rgb(var(--nx-cta-b) / .55); }
            .mpane__tile { display: grid; place-items: center; flex: none; width: 38px; height: 38px; border-radius: 13px; background: rgb(var(--nx-surface-2)); box-shadow: inset 0 0 0 1px rgb(var(--nx-line)); color: rgb(var(--nx-teal-ink)); font-size: 18px; }
            .mpane__row.is-on .mpane__tile { background: rgb(var(--nx-cta-b)); color: #fff; box-shadow: none; }
            .mpane__chev { margin-inline-start: auto; color: rgb(var(--nx-text-2)); }
            .mpane-in { transition: transform .26s cubic-bezier(.2, .8, .2, 1), opacity .2s ease; } .mpane-out { transition: transform .18s ease, opacity .15s ease; }
            .mpane-from { transform: translateX(-108%); opacity: .6; } .mpane-to { transform: none; opacity: 1; }
            [dir=rtl] .mpane-from { transform: translateX(108%); }
            @media (prefers-reduced-motion: reduce) { .mpane-in, .mpane-out { transition: none; } }
        </style>
    @endonce
@endif
