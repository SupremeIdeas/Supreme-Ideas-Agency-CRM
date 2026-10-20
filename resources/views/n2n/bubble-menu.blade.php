{{-- Per-message actions (N2N Batch 12, MASTER-ONLY). Everything here is private to the viewer except "delete for everyone". --}}
<span class="ncb__menu" x-data="{ o: false }" @keydown.escape.window="o = false" @click.outside="o = false">
    <button type="button" class="ncb__dots" @click="o = !o" :aria-expanded="o" aria-label="Message actions"><x-nx.icon name="menu" style="font-size:14px" /></button>
    <span class="ncb__pop {{ $m['mine'] ? 'ncb__pop--end' : '' }}" x-show="o" x-cloak style="display:none" role="menu">
        <button type="button" role="menuitem" wire:click="setReply({{ $m['id'] }})" @click="o = false"><x-nx.icon name="reply" /> Reply</button>
        <button type="button" role="menuitem" wire:click="toggleStar({{ $m['id'] }})" @click="o = false"><x-nx.icon name="star" /> {{ $m['starred'] ? 'Remove star' : 'Star' }}</button>
        <button type="button" role="menuitem" wire:click="togglePinMessage({{ $m['id'] }})" @click="o = false"><x-nx.icon name="pin" /> {{ ! empty($m['pinned']) ? 'Unpin' : 'Pin for me' }}</button>
        @if (trim($m['body']) !== '')
            <button type="button" role="menuitem" x-on:click="navigator.clipboard && navigator.clipboard.writeText(@js($m['body'])); o = false"><x-nx.icon name="copy" /> Copy text</button>
        @endif
        @if ($m['canDelete'])
            <button type="button" role="menuitem" wire:click="deleteMessage({{ $m['id'] }})" wire:confirm="Delete this message for everyone?" @click="o = false" class="ncb__danger"><x-nx.icon name="trash" /> Delete for everyone</button>
        @endif
    </span>
</span>
