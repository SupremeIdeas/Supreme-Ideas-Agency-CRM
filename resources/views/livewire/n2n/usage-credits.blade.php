{{-- nx:converted --}}
{{-- N2N NaaraCredit (MASTER-ONLY). Skin tokens only. Money buttons disable while in flight and carry idempotency keys. --}}
<div>
<x-nx.page class="ns-pg ns-pg--mid">
    <div class="ns-pg__head">
        <div>
            <a href="{{ route('n2n.identity') }}" wire:navigate class="ns-pg__back"><x-nx.icon name="left" /> Naara ID</a>
            <h1 class="ns-h1" style="margin-top:6px">NaaraCredit for chat and calls</h1>
            <p class="ns-sub" style="margin:4px 0 0">Used only inside Naara. It cannot be withdrawn or turned back into wallet money.</p>
        </div>
        <img src="{{ asset('images/illustrations/nc-credit.svg') }}" alt="" width="240" height="180" class="hidden h-28 w-auto sm:block" data-n2n-art>
    </div>
    <x-n2n.section-tabs />

    @if ($message)<div class="ns-pg__card ns-ring" role="status" style="margin-top:12px">{{ $message }}</div>@endif
    @if ($error)<div class="ns-pg__card ns-ring" role="alert" style="margin-top:12px"><span class="ns-pg__fielderr">{{ $error }}</span></div>@endif

    <div class="ns-pg__stats">
        <div class="ns-pg__stat ns-ring"><small>Total you can spend</small><b>{{ $bal['total'] }}</b></div>
        <div class="ns-pg__stat ns-ring"><small>Bought (shareable)</small><b>{{ $bal['shareable'] }}</b></div>
        <div class="ns-pg__stat ns-ring"><small>Received</small><b>{{ $bal['nonshareable'] }}</b></div>
        <div class="ns-pg__stat ns-ring"><small>Rewards credit</small><b>{{ $bal['loyalty'] }}</b></div>
    </div>

    <form wire:submit="buy" class="ns-pg__card ns-ring">
        <h2 class="ns-pg__h2" style="margin-bottom:6px">Buy credit</h2>
        <p class="ns-sub" style="margin:0 0 10px">Paid from your wallet (${{ $walletUsd }} available).</p>
        @if ($canBuy)
            <div class="ns-seg ns-small" role="group" aria-label="Amount">
                @foreach (['5', '10', '25', '50'] as $amt)
                    <button type="button" wire:click="$set('buyUsd', '{{ $amt }}')" class="{{ $buyUsd === $amt ? 'is-on' : '' }}" aria-pressed="{{ $buyUsd === $amt ? 'true' : 'false' }}">${{ $amt }}</button>
                @endforeach
            </div>
            <button type="submit" wire:loading.attr="disabled" wire:target="buy" class="ns-cta ns-cta--pill" style="margin-top:12px"><span wire:loading.remove wire:target="buy">Buy ${{ $buyUsd }} of credit</span><span wire:loading wire:target="buy">Working...</span></button>
        @else
            <p class="ns-sub" style="margin:0">Buying credit is not available in the app yet. Please use the website.</p>
        @endif
    </form>

    <form wire:submit="share" class="ns-pg__card ns-ring">
        <h2 class="ns-pg__h2" style="margin-bottom:6px">Share credit</h2>
        <p class="ns-sub" style="margin:0 0 10px">Only credit you bought can be shared. Find them by Naara ID, username, phone or email.</p>
        <label class="ns-pg__lbl" for="cr-to">Who</label>
        <input id="cr-to" type="text" wire:model.live.debounce.500ms="shareTo" class="ns-input" placeholder="@username or Naara ID" autocomplete="off">
        @if ($recipient)
            <p class="ns-sub" style="margin:6px 0 0" role="status">{{ $recipient['name'] ? 'Sending to '.$recipient['name'].($recipient['username'] ? ' (@'.$recipient['username'].')' : '') : 'No match yet.' }}</p>
        @endif
        <label class="ns-pg__lbl" for="cr-amt" style="margin-top:10px">Credits</label>
        <input id="cr-amt" type="number" step="0.01" min="0" wire:model="shareAmount" class="ns-input" placeholder="50">
        <button type="submit" wire:loading.attr="disabled" wire:target="share" class="ns-cta ns-cta--pill" style="margin-top:12px"><span wire:loading.remove wire:target="share">Share credit</span><span wire:loading wire:target="share">Working...</span></button>
    </form>

    <form wire:submit="gift" class="ns-pg__card ns-ring">
        <h2 class="ns-pg__h2" style="margin-bottom:6px">Gift to someone not on Naara yet</h2>
        <p class="ns-sub" style="margin:0 0 10px">We hold it for 24 hours. They join with your invite link and confirm the same email or number. If they do not, it comes back to you.</p>
        <label class="ns-pg__lbl" for="cr-gto">Email or phone</label>
        <input id="cr-gto" type="text" wire:model="giftTo" class="ns-input" placeholder="friend@example.com" autocomplete="off">
        <label class="ns-pg__lbl" for="cr-gamt" style="margin-top:10px">Credits</label>
        <input id="cr-gamt" type="number" step="0.01" min="0" wire:model="giftAmount" class="ns-input" placeholder="50">
        <button type="submit" wire:loading.attr="disabled" wire:target="gift" class="ns-cta ns-cta--pill" style="margin-top:12px"><span wire:loading.remove wire:target="gift">Send gift</span><span wire:loading wire:target="gift">Working...</span></button>
    </form>

    @if ($pending->isNotEmpty())
        <div class="ns-pg__card ns-ring">
            <h2 class="ns-pg__h2" style="margin-bottom:8px">Pending gifts</h2>
            @foreach ($pending as $g)
                <div class="ns-pg__lrow" style="padding-inline:0" wire:key="gift-{{ $g->id }}" x-data="{ left: {{ max(0, (int) now()->diffInSeconds($g->expires_at, false)) }} }" x-init="setInterval(() => { if (left > 0) left-- }, 1000)">
                    <b style="font-weight:500">{{ $g->recipient_masked }} · {{ number_format((float) $g->amount, 2) }}</b>
                    <span class="ns-sub" x-text="left > 0 ? Math.floor(left/3600) + 'h ' + Math.floor((left%3600)/60) + 'm left' : 'expiring'"></span>
                </div>
            @endforeach
        </div>
    @endif

    <div class="ns-pg__card ns-ring">
        <h2 class="ns-pg__h2" style="margin-bottom:8px">History</h2>
        @forelse ($history as $row)
            <div class="ns-pg__lrow" style="padding-inline:0" wire:key="led-{{ $row->id }}">
                <span style="font-weight:500">{{ ucfirst(str_replace('_', ' ', $row->type)) }}</span>
                <span class="{{ (float) $row->amount >= 0 ? 'is-pos' : '' }}">{{ (float) $row->amount >= 0 ? '+' : '' }}{{ number_format((float) $row->amount, 2) }}</span>
            </div>
        @empty
            <x-n2n.empty art="nc-credit" title="Nothing yet" text="Top-ups and what you spend on chat and calls will be listed here." />
        @endforelse
    </div>
</x-nx.page>
</div>
