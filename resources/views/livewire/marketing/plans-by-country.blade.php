{{-- nx:converted (skin tokens; legacy palette classes removed 2026-10-05) --}}
{{-- Homepage plans carousel (CMS copy comes from home.pricing). Light band; cards are the premium rounded sheets, snap carousel from x-marketing. --}}
<section class="overflow-hidden py-20 sm:py-24" data-bg="light">
    <div class="mx-auto max-w-6xl px-4">
        <div class="flex flex-col gap-6 sm:flex-row sm:items-end sm:justify-between">
            <div class="max-w-2xl">
                <p data-reveal class="text-xs font-semibold uppercase tracking-[0.25em] text-[rgb(var(--nx-gold-ink))]">{{ $copy['eyebrow'] ?? '' }}</p>
                <h2 data-reveal class="mt-3 text-3xl font-bold leading-tight text-[rgb(var(--nx-text))] sm:text-5xl">
                    @if ($live && $countryName)
                        Plans for <span class="inline-flex items-center gap-2 align-middle"><x-country-flag :country="$country" class="h-6 w-9" /> {{ $countryName }}</span>
                    @else
                        {{ $copy['headline'] ?? 'Plans That Make Sense' }}
                    @endif
                </h2>
                <p data-reveal class="mt-4 leading-relaxed text-[rgb(var(--nx-text-2))]">{{ $copy['text'] ?? '' }}</p>
            </div>
            @if ($live && $countries->isNotEmpty())
                <label data-reveal class="block shrink-0 text-xs font-semibold uppercase tracking-wide text-[rgb(var(--nx-text-2))]">
                    Where are you going?
                    <select wire:model.live="country" wire:loading.attr="disabled" aria-label="Choose a country to see its plans"
                            class="mt-1.5 block w-full min-w-[14rem] rounded-full border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] px-4 py-2.5 text-sm font-medium normal-case tracking-normal text-[rgb(var(--nx-text))] shadow-sm focus:border-[rgb(var(--nx-teal))] focus:ring-[rgb(var(--nx-teal))]">
                        <option value="">Popular plans</option>
                        @foreach ($countries as $iso => $name)<option value="{{ $iso }}">{{ $name }}</option>@endforeach
                    </select>
                </label>
            @endif
        </div>
    </div>

    <x-marketing.snap-carousel class="mt-10 text-[rgb(var(--nx-text))]" label="eSIM plans" wire:key="plans-{{ $country ?: 'popular' }}">
        @if ($live)
            @foreach ($plans as $plan)
                @php($cc = is_array($plan->countries) ? $plan->countries : [])
                <article class="relative flex min-h-[20rem] w-[84%] max-w-[22rem] flex-col rounded-[28px] border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-7 shadow-[0_24px_60px_-34px_rgba(13,27,42,0.35)] sm:w-[20rem]">
                    <div class="flex items-center justify-between">
                        <span class="inline-flex -space-x-1.5">
                            @foreach (array_slice($cc, 0, 3) as $c)<x-country-flag :country="$c" class="h-5 w-7" />@endforeach
                            @if (count($cc) > 3)<span class="ml-2.5 self-center text-xs font-semibold text-[rgb(var(--nx-text-2))]">+{{ count($cc) - 3 }}</span>@endif
                        </span>
                        <span class="rounded-full bg-[rgb(var(--nx-cta-b)/0.1)] px-2.5 py-1 text-[11px] font-semibold uppercase tracking-wide text-[rgb(var(--nx-teal-ink))]">{{ ucfirst($plan->coverage_type ?? 'local') }}</span>
                    </div>
                    <h3 class="mt-5 text-sm font-medium text-[rgb(var(--nx-text-2))]">{{ $plan->name }}</h3>
                    <p class="mt-1 text-5xl font-bold tracking-tight text-[rgb(var(--nx-text))]">{{ \App\Livewire\Marketing\PlansByCountry::dataLabel($plan->data_mb) }}</p>
                    <p class="mt-1 text-sm text-[rgb(var(--nx-text-2))]">{{ $plan->validity_days }} days &middot; instant activation</p>
                    <div class="mt-auto pt-8">
                        <p class="text-2xl font-bold text-[rgb(var(--nx-text))]">{{ $plan->display_price['usd'] }}</p>
                        <a href="{{ route('checkout', $plan->id) }}" wire:navigate class="nx-btn nx-btn--primary mt-4 !w-full !justify-center !py-3">Get this plan</a>
                    </div>
                </article>
            @endforeach
        @else
            @foreach ($tiers as $tier)
                <article class="relative flex min-h-[20rem] w-[84%] max-w-[22rem] flex-col rounded-[28px] border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-7 shadow-[0_24px_60px_-34px_rgba(13,27,42,0.35)] sm:w-[20rem]">
                    <span class="w-fit rounded-full bg-[rgb(var(--nx-cta-b)/0.1)] px-2.5 py-1 text-[11px] font-semibold uppercase tracking-wide text-[rgb(var(--nx-teal-ink))]">{{ $tier['name'] }}</span>
                    <p class="mt-5 text-5xl font-bold tracking-tight text-[rgb(var(--nx-text))]">{{ $tier['data'] }}</p>
                    <p class="mt-1 text-sm text-[rgb(var(--nx-text-2))]">{{ $tier['validity'] }} &middot; 190+ countries</p>
                    <p class="mt-4 text-sm leading-relaxed text-[rgb(var(--nx-text-2))]">{{ $tier['blurb'] }}</p>
                    <div class="mt-auto pt-6">
                        <p class="text-2xl font-bold text-[rgb(var(--nx-text))]"><span class="text-sm font-medium text-[rgb(var(--nx-text-2))]">from</span> ${{ number_format((float) $tier['from_usd'], 0) }}</p>
                        <a href="{{ route('pricing') }}" wire:navigate class="nx-btn nx-btn--primary mt-4 !w-full !justify-center !py-3">See plans</a>
                    </div>
                </article>
            @endforeach
        @endif

        {{-- Last card: the way to everything else --}}
        <article class="relative flex min-h-[20rem] w-[84%] max-w-[22rem] flex-col justify-between rounded-[28px] bg-gradient-to-br from-[rgb(var(--nx-cta-b))] via-[rgb(var(--nx-cta-a))] to-black/60 p-7 text-white shadow-[0_24px_60px_-34px_rgba(13,27,42,0.5)] sm:w-[20rem]">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-white/80">{{ $copy['callout'] ?? 'No hidden fees' }}</p>
                <p class="mt-4 text-3xl font-bold leading-tight">190+ countries, one eSIM.</p>
                <p class="mt-3 text-sm text-white/85">{{ $copy['trust'] ?? '' }}</p>
            </div>
            <a href="{{ $live && $country ? route('catalogue', ['country' => $country]) : route('pricing') }}" wire:navigate
               class="mt-8 inline-flex items-center justify-center gap-2 rounded-full bg-[rgb(var(--nx-surface))] px-5 py-3 text-sm font-bold text-[rgb(var(--nx-teal-ink))] transition hover:bg-[rgb(var(--nx-teal)/0.1)]">
                {{ $copy['cta'] ?? 'See All Plans' }} <x-icon name="chevron-right" class="h-4 w-4" />
            </a>
        </article>
    </x-marketing.snap-carousel>

    <div class="mx-auto mt-6 max-w-6xl px-4 text-center">
        <a href="{{ route('pricing') }}" wire:navigate class="nx-btn nx-btn--ghost !px-7 !py-2.5">{{ $copy['cta'] ?? 'See All Plans' }}</a>
        <p class="mt-3 text-xs text-[rgb(var(--nx-text-2))]">{{ $copy['trust'] ?? '' }}</p>
    </div>
</section>
