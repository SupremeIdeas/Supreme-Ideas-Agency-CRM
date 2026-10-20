{{-- Widget Dock (MASTER-ONLY): the one positioning system for the floating widgets. Emits the member's saved positions and loads the dock script;
     the widgets themselves opt in with data-dock attributes. Skin tokens only. Callers guard it with class_exists, so a fork never renders it. --}}
@php
    $dockUser = auth()->user();
    $dockRow = $dockUser && class_exists(\App\Models\MasterMarketingDockPosition::class)
        ? \App\Models\MasterMarketingDockPosition::where('user_id', $dockUser->id)->first() : null;
    $dockCfg = $dockUser ? [
        'uid' => $dockUser->id,
        'positions' => $dockRow ? $dockRow->positions : new stdClass,
        'stamp' => (int) ($dockRow ? $dockRow->stamp : 0),
        'saveUrl' => route('widget-dock.store'),
        'resetUrl' => route('widget-dock.reset'),
        'csrf' => csrf_token(),
    ] : [];
@endphp
@if ($dockUser && file_exists(resource_path('js/master-marketing/widget-dock.js')))
    <script type="application/json" data-dock-config>{!! json_encode($dockCfg, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES) !!}</script>
    @vite('resources/js/master-marketing/widget-dock.js')
    <style>
        .wd-layer { position: fixed; inset: 0; z-index: 70; pointer-events: none; }
        .wd-live { position: absolute; width: 1px; height: 1px; overflow: hidden; clip-path: inset(50%); }
        .wd-grip { position: fixed; z-index: 71; pointer-events: auto; display: grid; place-items: center; width: 22px; height: 16px; padding: 0; border: 0; border-radius: 999px; cursor: grab;
                   transform: translate(-50%, -55%); color: rgb(var(--nx-text-2)); background: rgb(var(--nx-surface-3)); box-shadow: inset 0 0 0 1px rgb(var(--nx-line)), var(--nx-e1, 0 1px 2px rgb(0 0 0 / .15));
                   opacity: .72; touch-action: none; transition: opacity .15s ease, transform .15s ease; }
        .wd-grip::after { content: ""; position: absolute; inset: -14px -11px; }                       /* 44px touch target */
        .wd-grip:hover, .wd-grip:focus-visible, .wd-grip.is-moving { opacity: 1; }
        .wd-grip:focus-visible { outline: 2px solid rgb(var(--nx-cta-b)); outline-offset: 3px; }
        .wd-grip.is-moving { color: rgb(var(--nx-cta-ink, 255 255 255)); background: rgb(var(--nx-cta-b)); cursor: grabbing; }
        .wd-grip[hidden] { display: none; }
        .wd-dragging, .wd-dragging * { cursor: grabbing !important; user-select: none !important; -webkit-user-select: none !important; }
        .wd-lifted { filter: drop-shadow(0 10px 18px rgb(0 0 0 / .28)); z-index: 69 !important; }
        .wd-guide { position: absolute; inset: 0; opacity: 0; transition: opacity .15s ease; }
        .wd-guide.is-on { opacity: 1; }
        .wd-guide i { position: absolute; top: var(--wd-top, 64px); bottom: var(--wd-bottom, 12px); width: 72px; border-radius: 22px; background: rgb(var(--nx-cta-b) / .10); box-shadow: inset 0 0 0 1.5px rgb(var(--nx-cta-b) / .45); }
        .wd-guide__l { left: 4px; } .wd-guide__r { right: 4px; }
        @media (prefers-reduced-motion: reduce) { .wd-grip, .wd-guide { transition: none; } }
        @media print { .wd-layer { display: none; } }
    </style>
@endif
