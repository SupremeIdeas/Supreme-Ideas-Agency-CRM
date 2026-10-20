{{-- Naara ID card (N2N, MASTER-ONLY). One read path (NaaraId::for), identical to Naara chat settings and, later, the Business Card. Skin tokens only. --}}
@php($naaraId = \App\Services\N2N\Identity\NaaraId::for(auth()->user()))
<div class="ns-card ns-ring" style="padding:14px;margin-top:12px" data-tour="account.naara-id">
    <span class="ns-lbl">{{ __('n2n.identity.naara_id') }}</span>
    <div x-data="{ copied: false }" style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-top:4px">
        <b style="font-size:1.25rem;letter-spacing:.04em;word-break:break-all">{{ $naaraId }}</b>
        <button type="button" class="ns-cta ns-cta--pill ns-cta--sm" x-on:click="navigator.clipboard.writeText(@js($naaraId)).then(() => { copied = true; setTimeout(() => copied = false, 1600) })">
            <span x-show="!copied">Copy</span><span x-show="copied" x-cloak>Copied</span>
        </button>
        <a href="{{ route('n2n.identity') }}" wire:navigate class="ns-linkink">Chat and discovery settings</a>
    </div>
    <p class="ns-small" style="margin:6px 0 0">{{ __('n2n.identity.naara_id_help') }}</p>
</div>
