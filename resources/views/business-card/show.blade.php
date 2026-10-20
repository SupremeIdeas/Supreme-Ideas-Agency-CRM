{{-- nx:converted (skin tokens only; see docs/appearance/SKIN-CONTRACT.md) --}}
<x-layouts.marketing :bare="true" :title="$f['name'].' on Naara'" :description="$f['blurb'] ?: ('Find '.$f['name'].' on Naara.')" :og-image="$f['logo'] ?: $f['hero']">
    @push('head')
        <link rel="canonical" href="{{ $url }}">
        <meta property="og:type" content="profile">
        <meta property="og:url" content="{{ $url }}">
        <meta name="twitter:card" content="summary">
    @endpush
    <div class="pb-28" data-business-card>
        <article class="mx-auto max-w-md overflow-hidden border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] shadow-xl sm:mt-0 sm:rounded-b-3xl sm:border-x sm:border-b">
            {{-- nx:allow:start the card band wears the brand's own colour, identical in every skin and mode --}}
            <div class="relative h-44 w-full sm:h-52" style="background: linear-gradient(135deg, {{ $f['color'] }}, {{ $f['color'] }}aa)" aria-hidden="true">
                @if ($f['hero'])<img src="{{ $f['hero'] }}" alt="" class="absolute inset-0 h-full w-full object-cover">@endif
            </div>
            {{-- nx:allow:end --}}
            <div class="-mt-12 px-6 pb-6">
                @if ($f['logo'])
                    <img src="{{ $f['logo'] }}" alt="" class="h-24 w-24 rounded-full border-4 border-[rgb(var(--nx-surface))] bg-[rgb(var(--nx-surface))] object-cover shadow">
                @else
                    <span class="flex h-24 w-24 items-center justify-center rounded-full border-4 border-[rgb(var(--nx-surface))] bg-[rgb(var(--nx-surface-3))] text-3xl font-bold text-[rgb(var(--nx-text-2))] shadow" aria-hidden="true">{{ mb_strtoupper(mb_substr($f['name'], 0, 1)) }}</span>
                @endif
                <h1 class="mt-3 flex flex-wrap items-center gap-2 text-2xl font-bold text-[rgb(var(--nx-text))]">
                    {{ $f['name'] }}
                    @if ($f['pro'])<x-naara-pro-badge size="md" />@endif
                </h1>
                @if ($f['category'])<p class="mt-0.5 text-sm font-medium text-[rgb(var(--nx-text-2))]">{{ $f['category'] }}</p>@endif
                @if ($f['blurb'])<p class="mt-3 text-sm leading-relaxed text-[rgb(var(--nx-text-2))]">{{ $f['blurb'] }}</p>@endif
                @if ($f['followers'] > 0)<p class="mt-3 text-xs text-[rgb(var(--nx-text-2))]">{{ number_format($f['followers']) }} {{ \Illuminate\Support\Str::plural('person', $f['followers']) }} connected on Naara</p>@endif

                @if ($f['links'] !== [])
                    <ul class="mt-5 space-y-2">
                        @foreach ($f['links'] as $l)
                            <li>
                                <a href="{{ $l['url'] }}" target="_blank" rel="noopener noreferrer nofollow ugc" class="flex items-center justify-between gap-3 rounded-xl border border-[rgb(var(--nx-line))] px-4 py-3 text-sm font-medium text-[rgb(var(--nx-text))] transition hover:bg-[rgb(var(--nx-surface-3))] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[rgb(var(--nx-teal))]">
                                    <span class="min-w-0"><span class="block truncate">{{ $l['label'] }}</span><span class="block truncate text-xs font-normal text-[rgb(var(--nx-text-2))]">{{ $l['host'] }}</span></span>
                                    <x-icon name="chevron-right" class="h-4 w-4 shrink-0 text-[rgb(var(--nx-text-3))]" />
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endif

                <div class="mt-6 grid gap-2">
                    @auth
                        <a href="{{ route('rewards.hunt.profile', $brand) }}" class="inline-flex h-12 items-center justify-center rounded-xl bg-[rgb(var(--nx-cta-b))] px-4 text-sm font-semibold text-white transition hover:opacity-90 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[rgb(var(--nx-teal))]">Connect on Naara</a>
                    @else
                        <a href="{{ route('register', array_filter(['ref' => $ref, 'brand' => $brand->id])) }}" class="inline-flex h-12 items-center justify-center rounded-xl bg-[rgb(var(--nx-cta-b))] px-4 text-sm font-semibold text-white transition hover:opacity-90 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[rgb(var(--nx-teal))]">Let&rsquo;s Connect</a>
                        <a href="{{ route('login') }}" class="inline-flex h-11 items-center justify-center rounded-xl border border-[rgb(var(--nx-line-strong))] px-4 text-sm font-medium text-[rgb(var(--nx-text))]">I already have an account</a>
                    @endauth
                    <a href="{{ route('business-card.vcard', $code) }}" class="inline-flex h-11 items-center justify-center gap-2 rounded-xl border border-[rgb(var(--nx-line-strong))] px-4 text-sm font-medium text-[rgb(var(--nx-text))]"><x-icon name="download" class="h-4 w-4" /> Save to contacts</a>
                </div>
                @auth
                    @if (class_exists(\App\Livewire\BusinessCardShare::class) && (int) $brand->owner_user_id !== (int) auth()->id())
                        <div class="mt-4 rounded-xl border border-[rgb(var(--nx-line))] p-3"><livewire:business-card-share :brand-id="$brand->id" /></div>
                    @endif
                @endauth
            </div>

            <div class="border-t border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface-2))] px-6 py-5">
                <div class="flex items-center gap-4">
                    {{-- nx:allow:start a QR code must be dark on white to scan, in every mode --}}
                    <div class="h-28 w-28 shrink-0 rounded-lg bg-white p-1 [&>svg]:h-full [&>svg]:w-full" role="img" aria-label="QR code for this card">{!! $qr !!}</div>
                    {{-- nx:allow:end --}}
                    <div class="min-w-0 text-sm">
                        <p class="font-semibold text-[rgb(var(--nx-text))]">Scan to open this card</p>
                        <p class="mt-1 break-all text-xs text-[rgb(var(--nx-text-2))]">{{ $url }}</p>
                        <p class="mt-2 flex gap-3 text-xs font-medium">
                            <a href="{{ route('business-card.qr', [$code, 'png']) }}" class="text-[rgb(var(--nx-teal-ink))] hover:underline">PNG</a>
                            <a href="{{ route('business-card.qr', [$code, 'svg']) }}" class="text-[rgb(var(--nx-teal-ink))] hover:underline">SVG</a>
                        </p>
                    </div>
                </div>
            </div>
        </article>
    </div>
</x-layouts.marketing>
