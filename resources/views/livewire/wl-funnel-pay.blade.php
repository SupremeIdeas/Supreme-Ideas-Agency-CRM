{{-- nx:converted --}}
{{-- Pay for a White Label by bank transfer or stablecoin (Funnel Phases 4 and 5.4, MASTER-ONLY). Skin tokens only. Every number and account comes from the server. --}}
<div>
<x-nx.page class="ns-pg ns-pg--mid">
    <a href="{{ route('wl-funnel.purchase') }}" wire:navigate class="ns-pg__back"><x-nx.icon name="left" /> Back to your purchase</a>
    <div class="ns-pg__head">
        <div>
            <h1 class="ns-h1">Pay for your White Label</h1>
            <p class="ns-sub" style="margin:4px 0 0">Reference {{ $inst->public_code }}@if ($plan) · {{ $plan->name }} · ${{ number_format((float) $plan->price_usd, 2) }}@endif</p>
        </div>
    </div>

    @if ($error)<div class="ns-pg__callout" role="alert" style="color:rgb(var(--nx-bad))"><x-nx.icon name="alert" /><div><p style="margin:0">{{ $error }}</p></div></div>@endif
    @if ($notice)<div class="ns-pg__callout ns-pg__callout--ok" role="status"><x-nx.icon name="check" /><div><p style="margin:0">{{ $notice }}</p></div></div>@endif

    @if ($pay && $pay->status === 'approved')
        <div class="ns-pg__card ns-ring" data-wl-paid><b>Payment approved.</b><p class="ns-sub" style="margin:4px 0 0">Your licence is being set up. We will contact you with the next steps.</p>
            <a href="{{ route('wl-funnel.setup') }}" wire:navigate class="ns-cta ns-cta--pill" style="margin-top:10px;display:inline-flex">Tell us how to set it up</a></div>
        @if ($balance && \App\Services\WhiteLabel\Funnel\WlFunnelManualPayments::enabled())
            <div class="ns-pg__card ns-ring" data-wl-balance-link><b>Upgrade to Extended</b><p class="ns-sub" style="margin:4px 0 8px">Pay the balance by transfer to unlock the Extended tier. Your invoice is in your documents.</p>
                <a href="{{ route('wl-funnel.balance') }}" wire:navigate class="ns-cta ns-cta--pill" style="display:inline-flex">Pay the balance</a></div>
        @endif
        @if ($docs->isNotEmpty())
            <div class="ns-pg__card ns-ring" data-wl-docs><b>Your documents</b>
                @foreach ($docs as $d)<p style="margin:6px 0 0"><a href="{{ route('wl-invoice.own', $d->id) }}" target="_blank" rel="noopener" style="color:rgb(var(--nx-teal-ink));text-decoration:underline">{{ ucfirst(str_replace('_', ' ', $d->doc_type)) }} {{ $d->number }}</a> <span class="ns-sub">{{ number_format((float) $d->total, 2) }} USD · {{ $d->status }}@if ($d->balance_due > 0) · balance {{ number_format((float) $d->balance_due, 2) }}@endif</span></p>@endforeach
            </div>
        @endif
    @else
        <div class="ns-pg__card ns-ring" data-wl-wallet>
            <b>Pay from your wallet</b>
            @if ($walletOk)
                <p class="ns-sub" style="margin:4px 0 8px">Instant. Your Naara wallet covers this purchase.</p>
                <button type="button" wire:click="payFromWallet" wire:confirm="Pay ${{ number_format((float) $plan->price_usd, 2) }} from your wallet?" wire:loading.attr="disabled" wire:target="payFromWallet" class="ns-cta ns-cta--pill"><span wire:loading.remove wire:target="payFromWallet">Pay from wallet</span><span wire:loading wire:target="payFromWallet">Paying...</span></button>
            @else
                <p class="ns-sub" style="margin:4px 0 0">Your wallet needs ${{ number_format($short, 2) }} more to cover this.@if ($topGateways !== []) Top up exactly that here and you come straight back.@else <a href="{{ route('wallet') }}" wire:navigate style="text-decoration:underline">Top up your wallet</a> or pay by transfer below.@endif</p>
                @if ($pendingTopUp)
                    <div style="margin-top:10px">@include('livewire.wl-balance-topup-pending')</div>
                @elseif ($topGateways !== [])
                    <div role="group" aria-label="Top up the wallet" style="display:flex;gap:8px;flex-wrap:wrap;margin-top:10px" data-wl-topup>
                        @foreach ($topGateways as $slug => $label)
                            <button type="button" wire:click="topUp('{{ $slug }}')" wire:loading.attr="disabled" wire:target="topUp" class="ns-cta ns-cta--pill ns-cta--ghost ns-cta--sm" wire:key="tu-{{ $slug }}"><span wire:loading.remove wire:target="topUp">{{ $label }} (${{ number_format(max(1, $short), 2) }})</span><span wire:loading wire:target="topUp">Opening...</span></button>
                        @endforeach
                    </div>
                @endif
            @endif
        </div>
        @if ($manualOn)
        <div class="ns-pg__card ns-ring">
            <b>1. Or pay by bank transfer or crypto</b>
            <div role="group" aria-label="Currency" style="display:flex;gap:8px;flex-wrap:wrap;margin-top:10px">
                @foreach ($available as $c => $why)
                    <button type="button" wire:click="choose('{{ $c }}')" wire:loading.attr="disabled" @disabled($why) class="ns-nt__chip {{ $currency === $c && $pay ? 'is-on' : '' }}" aria-pressed="{{ $currency === $c && $pay ? 'true' : 'false' }}" @if ($why) title="{{ $why }}" @endif>{{ $c }}</button>
                @endforeach
            </div>
            @if (count($networks) > 0)
                <p class="ns-sub" style="margin:12px 0 4px">Network (you must send on the same one):</p>
                <div role="group" aria-label="Network" style="display:flex;gap:8px;flex-wrap:wrap">
                    @foreach ($networks as $n)<button type="button" wire:click="choose('{{ $currency }}', '{{ $n }}')" class="ns-nt__chip {{ $network === $n && $pay ? 'is-on' : '' }}" aria-pressed="{{ $network === $n && $pay ? 'true' : 'false' }}">{{ $n }}</button>@endforeach
                </div>
            @endif
        </div>

        @if ($pay)
            <div class="ns-pg__card ns-ring" data-wl-quote x-data="{ left: {{ max(0, $pay->locked_until->timestamp - now()->timestamp) }}, t: null, fmt() { const m = Math.floor(this.left / 60), s = this.left % 60; return m + ':' + String(s).padStart(2, '0'); } }" x-init="t = setInterval(() => { if (left > 0) left--; }, 1000)" x-on:remove="clearInterval(t)">
                <b>2. You pay</b>
                <p style="font-size:28px;font-weight:700;margin:6px 0;color:rgb(var(--nx-text))">{{ number_format((float) $pay->expected_amount, 2) }} {{ $pay->currency }}</p>
                @if ($pay->currency !== 'USD')<p class="ns-sub" style="margin:0">Rate 1 {{ $pay->currency }} = {{ rtrim(rtrim(number_format((float) $pay->rate, 6, '.', ''), '0'), '.') }} USD</p>@endif
                <p class="ns-sub" style="margin:6px 0 0"><span x-show="left > 0">Locked for <b x-text="fmt()"></b></span><span x-show="left <= 0" x-cloak style="color:rgb(var(--nx-bad))">This quote has expired.</span>
                    <button type="button" wire:click="requote" wire:loading.attr="disabled" wire:target="requote" class="ns-cta ns-cta--pill ns-cta--ghost ns-cta--sm" style="margin-left:8px">Refresh quote</button></p>
            </div>

            <div class="ns-pg__card ns-ring" data-wl-instructions>
                <b>3. Send it here</b>
                @if ($pay->account?->payee_note)<p class="ns-sub" style="margin:6px 0 0" data-wl-payee-note>{{ $pay->account->payee_note }}</p>@endif
                @if (in_array($pay->currency, ['USDT', 'USDC'], true))
                    <div class="ns-pg__callout" role="alert" style="margin-top:10px"><x-nx.icon name="alert" /><div><b>Send only {{ $pay->currency }} on {{ $pay->network }} to this address.</b><p style="margin:2px 0 0">Any other network or token is lost and cannot be recovered. Check the network and the address before you send.</p></div></div>
                    @if ($qr)<div class="[&>svg]:h-full [&>svg]:w-full" style="margin:10px 0;width:180px;background:white;padding:6px;border-radius:12px" role="img" aria-label="QR code of the receiving address">{!! $qr !!}</div>@endif
                @else
                    <p class="ns-sub" style="margin:6px 0 0">Your bank may charge a fee. If you can, choose to cover it so the full amount arrives.</p>
                @endif
                @if (in_array($pay->status, ['awaiting_transfer', 'underpaid']))<p style="margin:10px 0 0"><a href="{{ route('wl-funnel.pay.proforma') }}" class="ns-cta ns-cta--pill ns-cta--ghost ns-cta--sm" data-wl-proforma download>Download proforma invoice (PDF)</a></p>@endif
                <dl style="margin:10px 0 0;display:grid;gap:8px">
                    @foreach ($rows as $r)
                        <div style="display:flex;gap:8px;align-items:center;justify-content:space-between;flex-wrap:wrap"><span><dt class="ns-sub" style="font-size:12px">{{ $r['label'] }}</dt><dd style="margin:0;word-break:break-all;color:rgb(var(--nx-text))">{{ $r['value'] }}</dd></span>
                            <button type="button" x-data="{ done: false }" @click="navigator.clipboard.writeText(@js($r['value'])).then(() => { done = true; setTimeout(() => done = false, 1500) })" class="ns-cta ns-cta--pill ns-cta--ghost ns-cta--sm"><span x-show="!done">Copy</span><span x-show="done" x-cloak>Copied</span></button></div>
                    @endforeach
                    <div style="display:flex;gap:8px;align-items:center;justify-content:space-between;flex-wrap:wrap"><span><dt class="ns-sub" style="font-size:12px">Payment reference (put this in the transfer description)</dt><dd style="margin:0;color:rgb(var(--nx-text));font-weight:700">{{ $pay->reference_code }}</dd></span>
                        <button type="button" x-data="{ done: false }" @click="navigator.clipboard.writeText(@js($pay->reference_code)).then(() => { done = true; setTimeout(() => done = false, 1500) })" class="ns-cta ns-cta--pill ns-cta--ghost ns-cta--sm"><span x-show="!done">Copy</span><span x-show="done" x-cloak>Copied</span></button></div>
                </dl>
            </div>

            <div class="ns-pg__card ns-ring" data-wl-proof>
                <b>4. I have paid</b>
                @if ($pay->status === 'under_review')
                    <p class="ns-sub" style="margin:6px 0 0">Under review. We will notify you as soon as it is approved.</p>
                @endif
                @if ($pay->status === 'underpaid')<p style="margin:6px 0 0;color:rgb(var(--nx-bad))" role="alert">We received {{ number_format((float) $pay->approved_amount, 2) }} {{ $pay->currency }} of {{ number_format((float) $pay->expected_amount, 2) }}. Please send the difference ({{ number_format((float) $pay->expected_amount - (float) $pay->approved_amount, 2) }} {{ $pay->currency }}) and upload the new proof.</p>@endif
                @if ($pay->reject_reason)<p style="margin:6px 0 0;color:rgb(var(--nx-bad))" role="alert">Last proof was not accepted: {{ $pay->reject_reason }}</p>@endif
                @if (in_array($pay->status, ['awaiting_transfer', 'underpaid']))
                    <form wire:submit="submit" style="display:grid;gap:10px;margin-top:10px">
                        <label class="ns-sub">Name on the paying account<input type="text" wire:model="payer_name" maxlength="120" class="ns-input" style="width:100%"></label>
                        @error('payer_name')<small style="color:rgb(var(--nx-bad))">{{ $message }}</small>@enderror
                        <label class="ns-sub">Bank or wallet you used (optional)<input type="text" wire:model="payer_bank" maxlength="120" class="ns-input" style="width:100%"></label>
                        <label class="ns-sub">When you paid (optional)<input type="datetime-local" wire:model="paid_at" class="ns-input" style="width:100%"></label>
                        @if (in_array($pay->currency, ['USDT', 'USDC'], true))
                            <label class="ns-sub">Transaction hash<input type="text" wire:model="tx_hash" maxlength="120" class="ns-input" style="width:100%" autocomplete="off"></label>
                        @endif
                        <label class="ns-sub">Screenshot or PDF of the payment (up to 3 files, 8 MB each)<input type="file" wire:model="files" multiple accept="image/jpeg,image/png,image/webp,application/pdf" style="width:100%"></label>
                        <div wire:loading wire:target="files" class="ns-sub" role="status">Uploading...</div>
                        @error('files.*')<small style="color:rgb(var(--nx-bad))">{{ $message }}</small>@enderror
                        <button type="submit" wire:loading.attr="disabled" wire:target="submit,files" class="ns-cta ns-cta--pill"><span wire:loading.remove wire:target="submit">Send proof</span><span wire:loading wire:target="submit">Sending...</span></button>
                    </form>
                @endif
            </div>
        @endif
        @endif
    @endif
</x-nx.page>
</div>
