{{-- nx:converted (skin tokens; legacy palette classes removed 2026-10-05) --}}
{{-- Trust/feature strip (footer row): four honest platform promises at the foot of the eSIM front. Static, brand-safe copy — no invented
     provider claims. One shared partial, identical across every theme. --}}
<div class="ns-card ns-ring ns-features">
    @foreach ([
        ['zap', 'Instant Activation', 'Online in seconds'],
        ['shield-check', 'Secure & Private', 'Your data, protected'],
        ['globe', '190+ Countries', 'Global coverage'],
        ['message-circle', '24/7 Support', "We're here anytime"],
    ] as [$icon, $title, $sub])
        <div class="ns-feature">
            <span class="ns-tile"><x-nx.icon :name="$icon" /></span>
            <span><b>{{ $title }}</b><small>{{ $sub }}</small></span>
        </div>
    @endforeach
</div>
