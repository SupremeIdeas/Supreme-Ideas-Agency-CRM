{{-- nx:converted (skin tokens; legacy palette classes removed 2026-10-05) --}}
<div class="mx-auto max-w-2xl px-4 py-16">
    @php($accent = {{-- nx:allow:start merchant brand default --}}$merchant->brand_color ?: '#0A6E6E'{{-- nx:allow:end --}})
    <div class="overflow-hidden rounded-3xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] shadow-xl">
        {{-- Merchant hero (co-brand accent) --}}
        <div class="relative px-8 py-12 text-center"
             style="background: linear-gradient(135deg, {{ $accent }}14, transparent);">
            @if ($merchant->logo_url)
                <img src="{{ $merchant->logo_url }}" alt="{{ $merchant->business_name }}"
                     class="mx-auto h-24 w-24 rounded-2xl object-contain shadow-sm ring-1 ring-black/5">
            @else
                <span class="mx-auto flex h-24 w-24 items-center justify-center rounded-2xl text-3xl font-bold uppercase text-white shadow-sm"
                      style="background-color: {{ $accent }};">
                    {{ \Illuminate\Support\Str::of($merchant->business_name)->trim()->substr(0, 2) }}
                </span>
            @endif
            <h1 class="mt-6 font-display text-3xl font-bold text-[rgb(var(--nx-text))]">{{ $merchant->business_name }}</h1>
            <p class="mt-2 text-sm text-[rgb(var(--nx-text-2))]">
                Global eSIM data & phone numbers — 190+ countries, no swaps, no borders.
            </p>
        </div>

        {{-- Value + CTA --}}
        <div class="border-t border-[rgb(var(--nx-line))] px-8 py-8">
            <ul class="mx-auto max-w-md space-y-3 text-sm text-[rgb(var(--nx-text-2))]">
                <li class="flex items-start gap-2"><x-icon name="globe" class="mt-0.5 h-4 w-4 shrink-0 text-[rgb(var(--nx-teal-ink))]" /> Instant eSIM data plans for 190+ countries.</li>
                <li class="flex items-start gap-2"><x-icon name="hash" class="mt-0.5 h-4 w-4 shrink-0 text-[rgb(var(--nx-teal-ink))]" /> Virtual & verification numbers in one app.</li>
                <li class="flex items-start gap-2"><x-icon name="shield-check" class="mt-0.5 h-4 w-4 shrink-0 text-[rgb(var(--nx-teal-ink))]" /> Secure wallet, transparent pricing, live support.</li>
            </ul>

            <a href="{{ route('register') }}" wire:navigate
               class="mt-8 flex w-full items-center justify-center gap-2 rounded-xl px-5 py-3.5 text-sm font-semibold text-white shadow-sm transition hover:opacity-90"
               style="background-color: {{ $accent }};">
                <x-icon name="zap" class="h-5 w-5" /> Create your account
            </a>
            <p class="mt-3 text-center text-xs text-[rgb(var(--nx-text-2))]">
                Already have an account? <a href="{{ route('login') }}" wire:navigate class="font-medium text-[rgb(var(--nx-teal-ink))] hover:underline">Sign in</a>
            </p>
        </div>

        {{-- Powered by NaaraSim — always present alongside the merchant brand. --}}
        <div class="flex items-center justify-center gap-1.5 border-t border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface-2))] py-4 text-xs text-[rgb(var(--nx-text-2))]">
            <x-icon name="signal" class="h-3.5 w-3.5" /> Powered by <span class="font-semibold text-[rgb(var(--nx-text-2))]">NaaraSim</span>
        </div>
    </div>
</div>
