{{-- nx:converted (skin tokens; legacy palette classes removed 2026-10-05) --}}
<div>
    {{-- Premium hero band (owner request, 2026-09-22: the plain text header
         here was "too poor" next to the rest of the site's hero treatment) —
         same paired teal+gold glow-blob + glass-panel language as the FAQ
         and CTA sections, full-bleed so it fills the space right under the
         floating nav instead of a flat text block. No photo needed here
         (matches the FAQ CTA section's own gradient-only precedent), so
         this stays lightweight Tailwind utilities rather than a new
         stylesheet. Curved rounded-top seam into the plans grid below,
         per the "no flat section divider" rule. --}}
{{-- nx:allow:start (marketing hero: brand-owned artwork) --}}
    <section class="relative overflow-hidden bg-gradient-to-br from-[#0D1B2A] via-[#085555] to-[#0A6E6E] px-4 pb-16 pt-24 text-center text-white sm:pt-28">
{{-- nx:allow:end --}}
{{-- nx:allow:start (marketing hero: brand-owned artwork) --}}
        <div class="pointer-events-none absolute bottom-[-10%] left-1/2 h-2/3 w-[90%] -translate-x-1/2 rounded-full bg-teal-400/20 blur-3xl"></div>
{{-- nx:allow:end --}}
{{-- nx:allow:start (marketing hero: brand-owned artwork) --}}
        <div class="pointer-events-none absolute -right-16 -top-16 h-2/5 w-2/5 rounded-full bg-amber-400/20 blur-3xl"></div>
{{-- nx:allow:end --}}
        <div class="relative z-10 mx-auto max-w-2xl rounded-[28px] bg-white/10 px-6 py-8 shadow-2xl shadow-black/20 ring-1 ring-white/15 backdrop-blur-xl sm:px-10 sm:py-10">
{{-- nx:allow:start (marketing hero: brand-owned artwork) --}}
            <p class="text-xs font-semibold uppercase tracking-[0.25em] text-teal-200">{{ $hero['eyebrow'] ?? '' }}</p>
{{-- nx:allow:end --}}
            <h1 class="mt-3 font-display text-3xl font-bold sm:text-4xl">{{ $hero['headline'] ?? '' }}</h1>
            <p class="mx-auto mt-4 leading-relaxed text-white/75">{{ $hero['subtext'] ?? '' }}</p>
            @if ($mode === 'estimate')
                <p class="mx-auto mt-5 inline-flex items-center gap-2 rounded-full bg-white/15 px-4 py-1.5 text-xs font-medium text-white">
                    <x-icon name="signal" class="h-3.5 w-3.5" /> {{ $hero['estimate_note'] ?? '' }}
                </p>
            @endif
        </div>
    </section>

    <div class="relative -mt-8 rounded-t-[30px] bg-[rgb(var(--nx-surface))]">
    <div class="mx-auto max-w-6xl px-4 pb-16 pt-10">
    {{-- LIVE plans --}}
    @if ($mode === 'live')
        <div class="mt-12 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($plans as $plan)
                @php($ref = (string) $plan->id)
                <div wire:key="plan-{{ $plan->id }}" @class([
                    'relative flex flex-col rounded-3xl border bg-[rgb(var(--nx-surface))] p-6 transition',
                    'border-[rgb(var(--nx-teal))] shadow-xl shadow-[rgb(var(--nx-teal)/0.1)]' => $plan->is_featured,
                    'border-[rgb(var(--nx-line))]' => ! $plan->is_featured,
                ])>
                    @if ($plan->is_featured)
                        <span class="absolute -top-3 left-6 rounded-full bg-[rgb(var(--nx-cta-b))] px-3 py-1 text-[11px] font-bold uppercase tracking-wide text-white">Most popular</span>
                    @endif
                    <h3 class="font-display text-lg font-bold text-[rgb(var(--nx-text))]">{{ $plan->name }}</h3>
                    <div class="mt-3 flex items-baseline gap-1">
                        <span class="font-display text-3xl font-bold text-[rgb(var(--nx-text))]">{{ $plan->display_price['usd'] }}</span>
                    </div>
                    <p class="text-xs text-[rgb(var(--nx-text-2))]">{{ $plan->display_price['ngn'] }}</p>

                    <ul class="mt-5 space-y-2 text-sm text-[rgb(var(--nx-text-2))]">
                        <li class="flex items-center gap-2"><x-icon name="check" class="h-4 w-4 text-[rgb(var(--nx-teal-ink))]" /> {{ $plan->data_mb ? number_format($plan->data_mb / 1024, 1).' GB data' : 'Data included' }}</li>
                        <li class="flex items-center gap-2"><x-icon name="check" class="h-4 w-4 text-[rgb(var(--nx-teal-ink))]" /> {{ $plan->validity_days ? $plan->validity_days.' days validity' : 'Flexible validity' }}</li>
                        <li class="flex items-center gap-2"><x-icon name="check" class="h-4 w-4 text-[rgb(var(--nx-teal-ink))]" /> {{ is_array($plan->countries) ? count($plan->countries).' '.\Illuminate\Support\Str::plural('country', count($plan->countries)) : 'Wide coverage' }}</li>
                        <li class="flex items-center gap-2"><x-icon name="check" class="h-4 w-4 text-[rgb(var(--nx-teal-ink))]" /> Instant activation</li>
                    </ul>

                    <button type="button" wire:click="explain('{{ $ref }}')" wire:loading.attr="disabled" wire:target="explain('{{ $ref }}')"
                            class="mt-4 inline-flex items-center gap-1.5 text-sm font-medium text-[rgb(var(--nx-teal-ink))] hover:underline">
                        <x-icon name="message-circle" class="h-4 w-4" />
                        <span wire:loading.remove wire:target="explain('{{ $ref }}')">What does this mean for me?</span>
                        <span wire:loading wire:target="explain('{{ $ref }}')">Thinking…</span>
                    </button>

                    @include('livewire.partials.pricing-explainer', ['ref' => $ref])

                    <a href="{{ auth()->check() ? route('checkout', $plan) : route('register') }}"
                       class="nx-btn {{ $plan->is_featured ? 'nx-btn--primary' : 'nx-btn--ghost' }} mt-5 w-full justify-center">
                        {{ auth()->check() ? 'Choose plan' : 'Get started' }}
                    </a>
                </div>
            @endforeach
        </div>

        <p class="mt-8 text-center text-sm text-[rgb(var(--nx-text-2))]">
            Looking for something specific? <a href="{{ auth()->check() ? route('catalogue') : route('register') }}" class="font-semibold text-[rgb(var(--nx-teal-ink))] hover:underline">Browse the full catalogue</a>.
        </p>
    @else
        {{-- ESTIMATE tiers --}}
        <div class="mt-12 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($tiers as $i => $tier)
                @php($ref = 'e'.$i)
                <div wire:key="tier-{{ $i }}" @class([
                    'relative flex flex-col rounded-3xl border bg-[rgb(var(--nx-surface))] p-6',
                    'border-[rgb(var(--nx-teal))] shadow-xl shadow-[rgb(var(--nx-teal)/0.1)]' => $i === 1,
                    'border-[rgb(var(--nx-line))]' => $i !== 1,
                ])>
                    @if ($i === 1)
                        <span class="absolute -top-3 left-6 rounded-full bg-[rgb(var(--nx-cta-b))] px-3 py-1 text-[11px] font-bold uppercase tracking-wide text-white">Most popular</span>
                    @endif
                    <h3 class="font-display text-lg font-bold text-[rgb(var(--nx-text))]">{{ $tier['name'] }}</h3>
                    <div class="mt-3 flex items-baseline gap-1">
                        <span class="text-xs font-medium text-[rgb(var(--nx-text-2))]">from</span>
                        <span class="font-display text-3xl font-bold text-[rgb(var(--nx-text))]">${{ number_format((float) $tier['from_usd'], 2) }}</span>
                    </div>
                    <p class="mt-4 text-sm leading-relaxed text-[rgb(var(--nx-text-2))]">{{ $tier['blurb'] }}</p>

                    <ul class="mt-5 space-y-2 text-sm text-[rgb(var(--nx-text-2))]">
                        <li class="flex items-center gap-2"><x-icon name="check" class="h-4 w-4 text-[rgb(var(--nx-teal-ink))]" /> {{ $tier['data'] }} data</li>
                        <li class="flex items-center gap-2"><x-icon name="check" class="h-4 w-4 text-[rgb(var(--nx-teal-ink))]" /> {{ $tier['validity'] }} validity</li>
                        <li class="flex items-center gap-2"><x-icon name="check" class="h-4 w-4 text-[rgb(var(--nx-teal-ink))]" /> 190+ countries</li>
                        <li class="flex items-center gap-2"><x-icon name="check" class="h-4 w-4 text-[rgb(var(--nx-teal-ink))]" /> Instant activation</li>
                    </ul>

                    <button type="button" wire:click="explain('{{ $ref }}')" wire:loading.attr="disabled" wire:target="explain('{{ $ref }}')"
                            class="mt-4 inline-flex items-center gap-1.5 text-sm font-medium text-[rgb(var(--nx-teal-ink))] hover:underline">
                        <x-icon name="message-circle" class="h-4 w-4" />
                        <span wire:loading.remove wire:target="explain('{{ $ref }}')">What does this mean for me?</span>
                        <span wire:loading wire:target="explain('{{ $ref }}')">Thinking…</span>
                    </button>

                    @include('livewire.partials.pricing-explainer', ['ref' => $ref])

                    <a href="{{ route('register') }}" class="nx-btn {{ $i === 1 ? 'nx-btn--primary' : 'nx-btn--ghost' }} mt-5 w-full justify-center">Get started</a>
                </div>
            @endforeach
        </div>

        <p class="mt-8 text-center text-xs text-[rgb(var(--nx-text-2))]">
            Estimates only, for guidance. Final prices are set live at checkout the moment our catalogue is connected — you always pay the price shown before you confirm.
        </p>
    @endif

    {{-- Trust line (Prompt 10 §1): the numbers/OTP refund guarantee, stated
         truthfully — this page is eSIM-plan-focused above, but the platform
         sells numbers too, so the promise belongs here as a real, standing
         line item, not buried only in the purchase modal. Timeout pulled
         from PollSmsOtpJob's own constant, never hardcoded. --}}
    <div class="mt-10 flex items-center justify-center gap-2 text-center text-sm text-[rgb(var(--nx-text-2))]">
        <x-icon name="shield-check" class="h-4 w-4 shrink-0 text-[rgb(var(--nx-teal-ink))]" />
        Verification numbers are covered too: no code within {{ \App\Jobs\PollSmsOtpJob::TIMEOUT_MINUTES }} minutes
        → automatic refund to your wallet, no ticket required.
        <a href="{{ route('refund-policy') }}" class="font-semibold text-[rgb(var(--nx-teal-ink))] hover:underline">Refund &amp; reliability policy</a>
    </div>

    {{-- Not sure? -> data estimator + device compatibility, side by side —
         both pre-purchase questions belong together, before checkout, not
         only inside the app (owner request, 2026-09-21: "let it work in
         real time in marketing page so users can check their devices even
         outside"). --}}
    <div class="mt-14 grid gap-6 sm:grid-cols-2">
        <div class="rounded-3xl bg-gradient-to-br from-[rgb(var(--nx-cta-b))] via-[rgb(var(--nx-cta-a))] to-black/60 p-8 text-white sm:col-span-2">
            <div class="grid items-center gap-8 md:grid-cols-[1fr_auto]">
                <div class="text-center md:text-left">
                    <h2 class="font-display text-xl font-bold">Not sure how much data you need?</h2>
{{-- nx:allow:start (marketing hero: brand-owned artwork) --}}
                    <p class="mx-auto mt-2 max-w-xl text-sm text-teal-100/90 md:mx-0">Tell us your trip and how you use your phone — we’ll estimate the right plan size so you never over- or under-buy.</p>
{{-- nx:allow:end --}}
                    <a href="{{ auth()->check() ? route('data-estimator') : route('register') }}" class="nx-btn nx-btn--gold mt-6 !px-8 !py-3">Try the data estimator</a>
                </div>
                {{-- The real estimator screen in a phone (scripts/mockups). --}}
                <figure class="mx-auto w-52 overflow-hidden rounded-3xl border border-[rgb(var(--nx-line))] shadow-xl sm:w-56">
                    <img src="{{ asset('images/marketing/mockups/pricing/data-estimator.webp') }}" alt="The Naara data estimator on a phone, suggesting about 5.5 GB for 7 days of medium use"
                         loading="lazy" decoding="async" width="720" height="950" class="aspect-[3/4] h-auto w-full object-cover">
                </figure>
            </div>
        </div>
        <div class="rounded-3xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-8 text-center sm:col-span-2">
            <h2 class="font-display text-xl font-bold text-[rgb(var(--nx-text))]">Not sure your phone supports eSIM?</h2>
            <p class="mx-auto mt-2 max-w-xl text-sm text-[rgb(var(--nx-text-2))]">Search your exact device — no account needed. We'll tell you straight away.</p>
            <button type="button" @click="$dispatch('open-compatibility')" class="nx-btn nx-btn--primary mt-6 !px-8 !py-3">Check my device</button>
        </div>
    </div>
    <livewire:esim-compatibility />
    </div>
    </div>
</div>
