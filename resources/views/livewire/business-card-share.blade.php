{{-- nx:converted --}}
{{-- "Send in Naara Chat" on a Business Card page (MASTER-ONLY). Skin tokens only. --}}
<div data-card-share>
    @if ($listed)
        <label for="card-share-to" class="ns-pg__hint" style="display:block;margin:0 0 4px"><b style="color:rgb(var(--nx-text))">Send in Naara Chat</b></label>
        <div style="display:flex;gap:8px;flex-wrap:wrap">
            <input id="card-share-to" type="text" wire:model.live.debounce.500ms="to" autocomplete="off" placeholder="Name, @username, Naara ID or phone" class="ns-input" style="flex:1;min-width:10rem">
            <button type="button" wire:click="send" wire:loading.attr="disabled" wire:target="send" class="ns-cta ns-cta--pill" @disabled(empty($hit['naara_id']))>Send card</button>
        </div>
        @if ($hit)<p class="ns-pg__hint" style="margin:6px 0 0" role="status">@if ($hit['name'])Sending to <b style="color:rgb(var(--nx-text))">{{ $hit['name'] }}</b>. Costs the normal chat price.@else Not on Naara.@endif</p>@endif
        @if ($notice)<p class="ns-pg__hint" style="margin:6px 0 0" role="status">{{ $notice }}</p>@endif
        @if ($error)<p class="ns-pg__fielderr" style="margin:6px 0 0" role="alert">{{ $error }}</p>@endif
    @endif
</div>
