{{-- nx:converted (skin tokens + x-nx components only) --}}
{{-- The friendly "Coming soon" sheet shown when a feature's switch is off (App\Support\ComingSoon). Skin-aware: it uses the skin canvas and the shared sheet. --}}
@php($home = auth()->check() ? route('dashboard') : url('/'))
<x-layouts.app :title="$feature['name'].' is coming soon'" page-type="marketing" :skin="auth()->check()">
    <x-nx.page :bleed="true" class="min-h-screen"
        x-data="{ open: true, leave() { if (window.history.length > 1) { window.history.back(); } else { window.location = '{{ $home }}'; } } }"
        x-effect="if (!open) { leave(); }">
        <noscript>
            <div class="ns-card" style="margin:24px 16px;text-align:center">
                <h1 class="ns-h1">{{ $feature['name'] }} is coming soon</h1>
                <p class="ns-sub">{{ $feature['blurb'] }}</p>
                <a href="{{ $home }}" class="ns-cta ns-cta--pill" style="margin:16px auto 0">Back to home</a>
            </div>
        </noscript>
        <x-nx.alpine-sheet show="open" :title="$feature['name'].' is coming soon'" :icon="$feature['icon']">
            <div style="text-align:center;padding:8px 4px 4px">
                <span class="ns-tile" style="width:64px;height:64px;font-size:32px;margin:0 auto 14px"><x-nx.icon :name="$feature['icon']" /></span>
                <p class="ns-sub" style="font-size:15px">{{ $feature['blurb'] }}</p>
                <p class="ns-sub" style="margin-top:10px">We will switch it on very soon. Thank you for your patience.</p>
            </div>
            <x-slot:foot>
                <button type="button" class="ns-cta ns-cta--pill" style="width:100%" @click="open = false">Got it</button>
            </x-slot:foot>
        </x-nx.alpine-sheet>
    </x-nx.page>
</x-layouts.app>
