{{-- More Apps carousel (master-only). Cross-promotes companion Supreme
     Ideas apps in the mobile "More" sheet as a horizontally-scrollable row
     of icon + name shortcuts. Gated by FeatureEntitlements::isMaster() so a
     white-label instance simply never sees it — no code changes needed if
     that instance later upgrades to master entitlement. --}}
@if (\App\Support\FeatureEntitlements::isMaster())
    @php($apps = \App\Support\MoreApps::active()->filter(fn ($a) => $a->visibleTo(auth()->user())))
    @if ($apps->isNotEmpty())
        <div {{ $attributes->merge(['class' => 'nx-more-apps mb-4']) }}>
            <h3 class="mb-2 text-[11px] font-semibold uppercase tracking-wide text-slate-400 dark:text-slate-500">More from Supreme Ideas</h3>
            <div class="flex gap-3 overflow-x-auto pb-1" style="-webkit-overflow-scrolling: touch;">
                @foreach ($apps as $app)
                    @php($external = $app->isExternalLink())
                    <a href="{{ $app->href() }}" @if ($external) target="_blank" rel="noopener noreferrer" @else wire:navigate @endif
                       wire:key="more-app-{{ $app->id }}"
                       class="flex w-20 shrink-0 flex-col items-center gap-1.5 rounded-2xl border border-slate-200 bg-slate-50 p-3 text-center transition hover:bg-slate-100 dark:border-[var(--brand-card-border-dark)] dark:bg-[var(--brand-card-inner-dark)] dark:hover:bg-[var(--brand-card-hover-dark)]">
                        <img src="{{ $app->icon_url }}" alt="{{ $app->name }}" loading="lazy" class="h-10 w-10 rounded-xl object-cover" onerror="this.replaceWith(Object.assign(document.createElement('span'),{className:'flex h-10 w-10 items-center justify-center rounded-xl bg-primary/15 text-sm font-bold text-primary',textContent:this.alt.slice(0,1)}))">
                        <span class="line-clamp-2 text-[11px] font-medium leading-tight text-slate-600 dark:text-slate-300">{{ $app->name }}</span>
                    </a>
                @endforeach
            </div>
        </div>
    @endif
@endif
