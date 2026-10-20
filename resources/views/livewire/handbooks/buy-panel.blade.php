{{-- nx:converted --}}
{{-- Access options on a book page (MASTER-ONLY). The tiles adapt to the viewer. Money buttons disable while in flight. --}}
<div class="ns-pg__card ns-ring" data-buy-panel>
    @if ($notice)<p class="ns-pg__hint" role="status" style="margin:0 0 10px;color:rgb(var(--nx-text))">{{ $notice }}</p>@endif
    @if ($error)<p class="ns-pg__fielderr" role="alert" style="margin:0 0 10px">{{ $error }}@if ($shortBy) You need ${{ number_format($shortBy, 2) }} more. <a href="{{ route('wallet') }}" style="text-decoration:underline">Add funds</a>@endif</p>@endif

    @if ($offers['level'] === 'owned')
        <b style="color:rgb(var(--nx-text))">You own this handbook</b>
        <p class="ns-pg__hint" style="margin:4px 0 10px">Read it in the app, offline, or download the EPUB and PDF.</p>
        <a href="{{ route('handbooks.library') }}" class="ns-cta ns-cta--pill">Open my Library</a>
    @elseif ($offers['level'] === 'unlocked')
        <b style="color:rgb(var(--nx-text))">Unlocked: read it in the app, offline</b>
        <p class="ns-pg__hint" style="margin:4px 0 10px">Want the EPUB and PDF to keep on your device?</p>
        <div style="display:flex;gap:8px;flex-wrap:wrap">
            <a href="{{ route('handbooks.library') }}" class="ns-cta ns-cta--pill">Read now</a>
            <button type="button" wire:click="buy" wire:loading.attr="disabled" wire:target="buy" class="ns-cta ns-cta--pill ns-cta--ghost">Upgrade to download, ${{ number_format($offers['upgrade'], 2) }} (50%)</button>
        </div>
    @elseif (! $offers['signed_in'])
        <b style="color:rgb(var(--nx-text))">${{ number_format((float) $b->list_price_usd, 2) }}</b>
        <p class="ns-pg__hint" style="margin:4px 0 10px">Sign in to buy, or get a Naara eSIM for the country and read it free.</p>
        <div style="display:flex;gap:8px;flex-wrap:wrap"><a href="{{ route('login') }}" class="ns-cta ns-cta--pill">Sign in</a><a href="{{ route('register') }}" class="ns-cta ns-cta--pill ns-cta--ghost">Create an account</a></div>
    @else
        <b style="color:rgb(var(--nx-text))">Get this handbook</b>
        <p class="ns-pg__hint" style="margin:4px 0 10px">Buy it to read and download, from your wallet (${{ number_format($balance, 2) }} available).</p>
        <div style="display:flex;gap:8px;flex-wrap:wrap">
            <button type="button" wire:click="buy" wire:loading.attr="disabled" wire:target="buy" class="ns-cta ns-cta--pill">Buy for ${{ number_format($offers['buy'], 2) }}</button>
            @if ($offers['slot'])<button type="button" wire:click="useSlot" wire:loading.attr="disabled" wire:target="useSlot" class="ns-cta ns-cta--pill ns-cta--ghost">Use a Pro unlock ({{ $offers['slots_left'] }} left)</button>@endif
        </div>
    @endif

    @if ($audio)
        <div style="margin-top:14px;padding-top:12px;border-top:1px solid rgb(var(--nx-line))" data-audio-tile>
            <b style="color:rgb(var(--nx-text))">Audiobook</b>
            @if ($ownsAudio)
                <p class="ns-pg__hint" style="margin:4px 0 8px">You own the audiobook.</p><a href="{{ route('handbooks.library') }}" class="ns-cta ns-cta--pill ns-cta--ghost">Listen in your Library</a>
            @elseif ($offers['signed_in'])
                <p class="ns-pg__hint" style="margin:4px 0 8px">Listen on the go{{ $audio->total_seconds ? ' ('.floor($audio->total_seconds / 3600).'h '.floor(($audio->total_seconds % 3600) / 60).'m)' : '' }}.</p>
                <button type="button" wire:click="buyAudio" wire:loading.attr="disabled" wire:target="buyAudio" class="ns-cta ns-cta--pill ns-cta--ghost">Buy the audiobook, ${{ number_format((float) $audio->price_usd, 2) }}</button>
            @else
                <p class="ns-pg__hint" style="margin:4px 0 0">${{ number_format((float) $audio->price_usd, 2) }}. Sign in to buy.</p>
            @endif
        </div>
    @endif
</div>
