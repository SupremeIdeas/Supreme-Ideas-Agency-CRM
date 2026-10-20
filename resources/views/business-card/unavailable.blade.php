{{-- nx:converted (skin tokens only; see docs/appearance/SKIN-CONTRACT.md) --}}
<x-layouts.marketing :bare="true" title="This card is not available" description="This Naara Business Card is not available right now.">
    @push('head')
        <meta name="robots" content="noindex,nofollow">
    @endpush
    <div class="mx-auto flex min-h-[70vh] max-w-md flex-col items-center justify-center px-6 text-center" data-business-card-unavailable>
        <h1 class="text-2xl font-bold text-[rgb(var(--nx-text))]">This card is not available</h1>
        <p class="mt-2 text-sm text-[rgb(var(--nx-text-2))]">The business behind this link may have paused its page, or the link may have a typo. Ask them for a fresh link or QR code.</p>
        <a href="{{ url('/') }}" class="ns-cta ns-cta--pill mt-6">Go to Naara</a>
    </div>
</x-layouts.marketing>
