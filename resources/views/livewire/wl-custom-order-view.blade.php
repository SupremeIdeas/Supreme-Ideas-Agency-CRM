{{-- nx:converted --}}
{{-- One White Label custom order for its buyer (Funnel Phase 8, MASTER-ONLY). Skin tokens only. Every number comes from the server. --}}
<div>
<x-nx.page class="ns-pg ns-pg--mid">
    <a href="{{ route('wl-custom-orders') }}" wire:navigate class="ns-pg__back"><x-nx.icon name="left" /> All custom requests</a>
    <div class="ns-pg__head">
        <div>
            <h1 class="ns-h1">{{ $o->brand_name }}</h1>
            <p class="ns-sub" style="margin:4px 0 0">{{ $o->reference }} · <span data-wlco-status>{{ ucfirst($expired ? 'expired' : $o->status) }}</span></p>
        </div>
    </div>

    @if ($error)<div class="ns-pg__callout" role="alert" style="color:rgb(var(--nx-bad))"><x-nx.icon name="alert" /><div><p style="margin:0">{{ $error }}</p></div></div>@endif
    @if ($notice)<div class="ns-pg__callout ns-pg__callout--ok" role="status"><x-nx.icon name="check" /><div><p style="margin:0">{{ $notice }}</p></div></div>@endif

    <div class="ns-pg__card ns-ring">
        <b>Your request</b>
        <p class="ns-sub" style="margin:6px 0 0">{{ collect($o->modules)->map(fn ($k) => $moduleOptions[$k] ?? $k)->implode(', ') }}</p>
        <p style="margin:8px 0 0;white-space:pre-line">{{ $o->brief }}</p>
    </div>

    @if (in_array($o->status, ['quoted', 'accepted', 'paid'], true))
        <div class="ns-pg__card ns-ring" data-wlco-quote>
            <b>Your quote</b>
            <div style="margin-top:8px;display:grid;gap:4px">
                @foreach ((array) $o->quote_items as $i)
                    <div wire:key="qi-{{ $loop->index }}" style="display:flex;justify-content:space-between;gap:12px"><span>{{ $i['label'] }}</span><span style="white-space:nowrap">${{ number_format((float) $i['amount'], 2) }}</span></div>
                @endforeach
                <div style="display:flex;justify-content:space-between;gap:12px;margin-top:6px;padding-top:8px;border-top:1px solid rgb(var(--nx-line));font-weight:700"><span>Total</span><span style="font-size:20px" data-wlco-total>${{ number_format((float) $o->quote_total_usd, 2) }}</span></div>
            </div>
            @if ($o->quote_note)<p class="ns-sub" style="margin:8px 0 0;white-space:pre-line">{{ $o->quote_note }}</p>@endif
            @if ($o->status === 'quoted')<p class="ns-sub" style="margin:8px 0 0">@if ($expired) This quote has expired. Message us and we will re-issue it. @else Valid until {{ $o->quote_valid_until->format('M j, Y') }}. @endif</p>@endif

            @if ($o->status === 'quoted' && ! $expired)
                <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:10px">
                    <button type="button" wire:click="accept" wire:loading.attr="disabled" wire:target="accept" class="ns-cta ns-cta--pill"><span wire:loading.remove wire:target="accept">Accept this quote</span><span wire:loading wire:target="accept">Accepting...</span></button>
                    <button type="button" wire:click="decline" wire:confirm="Cancel this request?" wire:loading.attr="disabled" wire:target="decline" class="ns-cta ns-cta--pill ns-cta--ghost">Not for me</button>
                </div>
            @endif
            @if ($o->status === 'accepted')
                <div style="margin-top:10px" data-wlco-pay>
                    @if ($spendable + 0.00001 >= (float) $o->quote_total_usd)
                        <p class="ns-sub" style="margin:0 0 8px">Pay instantly from your Naara wallet.</p>
                        <button type="button" wire:click="pay" wire:confirm="Pay ${{ number_format((float) $o->quote_total_usd, 2) }} from your wallet?" wire:loading.attr="disabled" wire:target="pay" class="ns-cta ns-cta--pill"><span wire:loading.remove wire:target="pay">Pay ${{ number_format((float) $o->quote_total_usd, 2) }} from wallet</span><span wire:loading wire:target="pay">Paying...</span></button>
                    @else
                        <p class="ns-sub" style="margin:0 0 8px">Your wallet needs ${{ number_format((float) $o->quote_total_usd - $spendable, 2) }} more. <a href="{{ route('wallet') }}" wire:navigate>Top up your wallet</a>, or message us below to pay by bank transfer or crypto.</p>
                    @endif
                </div>
            @endif
            @if ($o->status === 'paid')<p style="margin:10px 0 0"><b>Paid</b> · {{ $o->paid_at?->format('M j, Y') }}. We are working on your build and will update you here.</p>@endif
        </div>
    @elseif ($o->status === 'requested')
        <div class="ns-pg__card ns-ring"><b>We are pricing your request</b><p class="ns-sub" style="margin:4px 0 0">You will get a notification as soon as the quote is ready.</p></div>
    @elseif (in_array($o->status, ['declined', 'cancelled'], true))
        <div class="ns-pg__card ns-ring"><b>{{ $o->status === 'declined' ? 'We could not take this on' : 'Cancelled' }}</b>@if ($o->decline_reason)<p class="ns-sub" style="margin:4px 0 0">{{ $o->decline_reason }}</p>@endif</div>
    @endif

    <div class="ns-pg__card ns-ring" data-wlco-thread>
        <b>Conversation</b>
        <div style="display:grid;gap:8px;margin-top:8px">
            @forelse ($messages as $m)
                <div wire:key="m-{{ $m->id }}" style="padding:8px 10px;border-radius:12px;background:rgb(var(--nx-surface-3));{{ $m->from_admin ? '' : 'justify-self:end' }}"><span class="ns-sub" style="display:block">{{ $m->from_admin ? 'Naara team' : 'You' }} · {{ $m->created_at->diffForHumans() }}</span><span style="white-space:pre-line">{{ $m->body }}</span></div>
            @empty
                <p class="ns-sub" style="margin:0">No messages yet.</p>
            @endforelse
        </div>
        @if (in_array($o->status, ['requested', 'quoted', 'accepted', 'paid', 'expired'], true))
            <form wire:submit="send" style="display:flex;gap:8px;margin-top:10px">
                <label class="sr-only" for="wlco-msg">Message</label>
                <input id="wlco-msg" type="text" wire:model="message" maxlength="2000" class="ns-input" style="flex:1" placeholder="Ask a question or tell us what to change">
                <button type="submit" wire:loading.attr="disabled" wire:target="send" class="ns-cta ns-cta--pill ns-cta--sm">Send</button>
            </form>
        @endif
    </div>
</x-nx.page>
</div>
