{{-- Shown on the White Label purchase pages while a card top-up is waiting for the gateway's webhook (WatchesWlTopUp). --}}
<div wire:poll.3s="checkPendingTopUp" class="ns-pg__card ns-ring" data-wl-pending role="status" aria-live="polite">
    <b style="display:flex;align-items:center;gap:8px"><x-ui.spinner class="h-3.5 w-3.5" /> Confirming your payment...</b>
    <p class="ns-sub" style="margin:4px 0 8px">{{ ucfirst($pendingTopUp['gateway']) }} is confirming ${{ number_format((float) $pendingTopUp['amount'], 2) }}. This usually takes under a minute. Please do not pay again; this page updates by itself and your wallet is credited once it is confirmed.</p>
    <button type="button" wire:click="cancelPendingTopUp" wire:loading.attr="disabled" wire:target="cancelPendingTopUp" class="ns-cta ns-cta--pill ns-cta--ghost ns-cta--sm" data-wl-pending-cancel>I did not complete the payment</button>
</div>
