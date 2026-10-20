{{-- nx:converted --}}
{{-- Admin > Handbooks > Refund requests and download watch (MASTER-ONLY). Skin tokens only. --}}
<div>
@php
    $in = 'ns-input';
@endphp
<x-nx.page class="ns-pg">
    <div class="ns-pg__head">
        <div>
            <a href="{{ route('admin.handbooks') }}" class="ns-pg__back"><x-nx.icon name="left" /> Handbooks</a>
            <h1 class="ns-h1" style="margin-top:6px">Refund requests</h1>
            <p class="ns-sub" style="margin:4px 0 0">Buyers can ask within {{ (int) config('handbooks.refund_window_days', 7) }} days of paying. Nothing is refunded until you approve it.</p>
        </div>
    </div>

    @if ($flash)<div class="ns-pg__card ns-ring" role="status" style="margin-top:12px">{{ $flash }}</div>@endif
    @if ($error)<div class="ns-pg__card ns-ring" role="alert" style="margin-top:12px;border-color:rgb(var(--nx-bad))">{{ $error }}</div>@endif

    <section style="margin-top:18px" data-refund-queue>
        <h2 class="ns-pg__h2">Waiting for a decision ({{ $pending->count() }})</h2>
        @forelse ($pending as $r)
            <div class="ns-pg__card ns-ring" style="margin-top:10px" wire:key="rr-{{ $r->id }}">
                <b style="color:rgb(var(--nx-text))">{{ $r->handbook?->title }}</b> @if ($r->handbook?->catalogue_id)<span class="ns-pg__hint">{{ $r->handbook->catalogue_id }}</span>@endif
                <p class="ns-pg__hint" style="margin:4px 0">{{ $r->user?->email }} · {{ $r->source === 'upgrade' ? 'download upgrade' : 'full purchase' }} · <b style="color:rgb(var(--nx-text))">${{ number_format((float) $r->amount_usd, 2) }}</b> · asked {{ $r->created_at->diffForHumans() }}</p>
                @if ($r->reason)<p class="ns-sub" style="margin:6px 0;white-space:pre-line">{{ $r->reason }}</p>@endif
                <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-top:8px">
                    <input type="text" wire:model="notes.{{ $r->id }}" maxlength="300" placeholder="Note to the buyer (optional)" class="{{ $in }}" style="flex:1 1 14rem" aria-label="Note to the buyer">
                    <button type="button" wire:click="approve({{ $r->id }})" wire:loading.attr="disabled" wire:target="approve,decline" wire:confirm="Refund ${{ number_format((float) $r->amount_usd, 2) }} to the buyer's wallet and end their access?" class="ns-cta ns-cta--pill ns-cta--sm">Approve and refund</button>
                    <button type="button" wire:click="decline({{ $r->id }})" wire:loading.attr="disabled" wire:target="approve,decline" class="ns-cta ns-cta--pill ns-cta--ghost ns-cta--sm">Decline</button>
                </div>
            </div>
        @empty
            <div class="ns-pg__card ns-ring" style="margin-top:10px"><p class="ns-pg__hint" style="margin:0">No refund requests waiting.</p></div>
        @endforelse
    </section>

    <section style="margin-top:26px" data-download-watch>
        <h2 class="ns-pg__h2">Download watch (last 24 hours)</h2>
        <p class="ns-pg__hint" style="margin:0 0 8px">Buyers whose downloads look like a shared copy: many different networks, or an unusual number of downloads. A prompt to look, never an automatic block.</p>
        @forelse ($flags as $f)
            <div class="ns-pg__card ns-ring" style="margin-top:8px">{{ $f['email'] }} · <b style="color:rgb(var(--nx-text))">{{ $f['downloads'] }}</b> downloads of {{ $f['books'] }} book(s) from <b style="color:rgb(var(--nx-text))">{{ $f['networks'] }}</b> networks</div>
        @empty
            <div class="ns-pg__card ns-ring"><p class="ns-pg__hint" style="margin:0">Nothing unusual.</p></div>
        @endforelse
    </section>

    @if ($decided->isNotEmpty())
        <section style="margin-top:26px">
            <h2 class="ns-pg__h2">Recently decided</h2>
            @foreach ($decided as $r)
                <div class="ns-pg__card ns-ring" style="margin-top:8px" wire:key="rd-{{ $r->id }}">{{ $r->handbook?->title }} · {{ $r->user?->email }} · ${{ number_format((float) $r->amount_usd, 2) }} · <b style="color:rgb(var(--nx-text))">{{ $r->status }}</b> {{ $r->decided_at?->diffForHumans() }}</div>
            @endforeach
        </section>
    @endif
</x-nx.page>
</div>
