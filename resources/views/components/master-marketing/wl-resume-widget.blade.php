{{-- Master-only side widget: resume an unfinished White Label purchase from anywhere in the app. Skin tokens only; minimised state is remembered per reference. --}}
@php($wl = class_exists(\App\Support\MasterMarketing\WlResumeWidget::class) ? \App\Support\MasterMarketing\WlResumeWidget::for(auth()->user(), request()->route()?->getName()) : null)
@if ($wl)
    <div x-data="{ open: true, key: 'wl-widget-min-{{ $wl['code'] }}',
                   init() { try { this.open = localStorage.getItem(this.key) !== '1'; } catch (e) {} },
                   set(v) { this.open = v; try { localStorage.setItem(this.key, v ? '0' : '1'); } catch (e) {} } }"
         class="pointer-events-none fixed right-0 z-[60] bottom-28 sm:bottom-auto sm:top-1/2 sm:-translate-y-1/2" data-wl-resume-widget data-dock="wl-resume" data-dock-label="White Label purchase" data-dock-sides="right" data-dock-flush data-dock-layer="50" data-dock-anchor="right:260">
        <div class="pointer-events-auto flex items-stretch justify-end">
            {{-- Minimised: a slim tab on the screen edge. --}}
            <button type="button" x-show="!open" x-cloak @click="set(true)" aria-label="Open your White Label purchase"
                    class="ns-cta flex items-center gap-2 !rounded-l-2xl !rounded-r-none px-3 py-3 shadow-lg">
                <x-nx.icon name="rocket" class="h-5 w-5" />
            </button>
            {{-- Open card. --}}
            <div x-show="open" x-cloak x-transition.opacity class="w-64 max-w-[78vw] rounded-l-2xl border border-r-0 border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-4 shadow-xl">
                <div class="flex items-start justify-between gap-2">
                    <p class="text-sm font-semibold leading-tight text-[rgb(var(--nx-text))]">{{ $wl['label'] }}</p>
                    <button type="button" @click="set(false)" aria-label="Minimise" class="rounded-full p-1 text-[rgb(var(--nx-text-2))] hover:bg-[rgb(var(--nx-surface-2))]"><x-nx.icon name="chevron-right" class="h-4 w-4" /></button>
                </div>
                <img src="{{ asset('images/illustrations/wl-launch.svg') }}" alt="" width="320" height="240" loading="lazy" decoding="async" class="mx-auto -mb-1 mt-1 h-28 w-auto" data-wl-widget-art>
                <p class="mt-2 text-xs text-[rgb(var(--nx-text-2))]">{{ $wl['plan'] }} &middot; ref {{ $wl['code'] }}. Look around, then pick up exactly where you left off.</p>
                <a href="{{ $wl['url'] }}" wire:navigate class="ns-cta mt-3 w-full">
                    {{ $wl['cta'] }} <x-nx.icon name="arrow-right" class="h-4 w-4" />
                </a>
            </div>
        </div>
    </div>
@endif
