{{-- NaaraBrain as a pop-up (owner request, 2026-10-11): the header's small NaaraBrain button opens the real chat in a modal instead of leaving the page.
     The chat is the same page as /brain, framed from this origin (the app's frame policy allows `'self'`), loaded only the first time it is opened. A phone gets
     a near-full-height sheet, a desktop a centred panel. Master only: renders nothing where NaaraBrain is not installed or switched on. --}}
@if (\App\Support\Header\HeaderItems::exists('brain'))
    <div x-data="{ open: false, loaded: false }" @open-brain-modal.window="open = true; loaded = true" @keydown.escape.window="open = false">
        <template x-teleport="body">
            <div x-show="open" x-cloak class="hbm" style="display:none" role="dialog" aria-modal="true" aria-label="NaaraBrain">
                <div class="hbm__scrim" x-show="open" x-transition.opacity @click="open = false"></div>
                <div class="hbm__panel" x-show="open" x-transition:enter="hbm-in" x-transition:enter-start="hbm-from" x-transition:enter-end="hbm-to" x-transition:leave="hbm-out" x-transition:leave-start="hbm-to" x-transition:leave-end="hbm-from">
                    <div class="hbm__bar">
                        <span class="hbm__title"><img src="{{ \App\Support\Brain\BrainSettings::logo('mark', true) }}" alt="" width="22" height="22" draggable="false"> NaaraBrain</span>
                        <a href="{{ route('brain.chat') }}" wire:navigate @click="open = false" class="hbm__full">{{ __('header.open_full') }}</a>
                        <button type="button" class="hbm__x" @click="open = false" aria-label="{{ __('common.close') }}"><x-nx.icon name="x" /></button>
                    </div>
                    <iframe class="hbm__frame" title="NaaraBrain" :src="loaded ? '{{ route('brain.chat') }}' : null" allow="microphone; clipboard-write"></iframe>
                </div>
            </div>
        </template>
    </div>
    @once
        <style>
            .hbm { position: fixed; inset: 0; z-index: 75; display: flex; align-items: flex-end; justify-content: center; }
            .hbm__scrim { position: absolute; inset: 0; background: rgb(0 0 0 / .55); }
            .hbm__panel { position: relative; display: flex; flex-direction: column; width: 100%; height: min(94dvh, 100%); border-radius: 22px 22px 0 0; overflow: hidden; background: rgb(var(--nx-surface)); box-shadow: 0 -18px 50px -12px rgb(0 0 0 / .55), inset 0 0 0 1px rgb(var(--nx-line)); }
            .hbm__bar { display: flex; align-items: center; gap: 10px; padding: 10px 12px 10px 16px; border-bottom: 1px solid rgb(var(--nx-line)); background: rgb(var(--nx-surface)); }
            .hbm__title { display: inline-flex; align-items: center; gap: 8px; font-weight: 700; font-size: 15px; color: rgb(var(--nx-text)); margin-inline-end: auto; }
            .hbm__title img { width: 22px; height: 22px; object-fit: contain; }
            .hbm__full { font-size: 12.5px; font-weight: 600; color: rgb(var(--nx-teal-ink)); text-decoration: none; padding: 6px 8px; border-radius: 8px; }
            .hbm__full:hover { background: rgb(var(--nx-surface-3)); }
            .hbm__x { display: grid; place-items: center; width: 34px; height: 34px; border: 0; border-radius: 50%; cursor: pointer; color: rgb(var(--nx-text-2)); background: rgb(var(--nx-surface-3)); }
            .hbm__frame { flex: 1; width: 100%; border: 0; background: rgb(var(--nx-surface)); }
            .hbm-in { transition: transform .26s cubic-bezier(.2, .8, .2, 1), opacity .2s ease; } .hbm-out { transition: transform .18s ease, opacity .16s ease; }
            .hbm-from { transform: translateY(28px); opacity: 0; } .hbm-to { transform: none; opacity: 1; }
            @media (min-width: 768px) {
                .hbm { align-items: center; padding: 24px; }
                .hbm__panel { width: min(480px, 100%); height: min(760px, 92dvh); border-radius: 22px; box-shadow: 0 30px 80px -20px rgb(0 0 0 / .6), inset 0 0 0 1px rgb(var(--nx-line)); }
            }
            @media (prefers-reduced-motion: reduce) { .hbm-in, .hbm-out { transition: none; } }
        </style>
    @endonce
@endif
