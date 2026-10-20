{{-- Add / edit contact + bulk import: the skin sheet chrome (bottom sheet on a phone, centred dialog on desktop), driven by Alpine `sheet`. --}}
<div x-show="sheet" x-cloak class="ns-modal" x-trap.noscroll="sheet" @keydown.escape.window="sheet = false" role="dialog" aria-modal="true" aria-label="Contact">
    <div class="ns-scrim" @click="sheet = false"></div>
    <div class="ns-sheet">
        <div class="ns-handle"></div>
        <div class="ns-sheet__head">
            <span class="ns-tile"><x-nx.icon name="user" /></span>
            <div class="ns-text"><b>{{ $editingId ? 'Edit contact' : 'New contact' }}</b></div>
            <button type="button" class="ns-sheet__close" @click="sheet = false" aria-label="Close"><x-nx.icon name="x" /></button>
        </div>
        <div class="ns-sheet__body">
            <div class="ns-ct__form">
                <label class="ns-ct__lbl" for="ct-name">Name</label>
                <input id="ct-name" type="text" wire:model="name" placeholder="Ada Obi" class="ns-ct__input">
                @error('name') <span class="ns-ct__err">{{ $message }}</span> @enderror

                <label class="ns-ct__lbl" for="ct-phone">Number</label>
                <input id="ct-phone" type="tel" wire:model="phone" placeholder="+2348012345678" class="ns-ct__input">
                @error('phone') <span class="ns-ct__err">{{ $message }}</span> @enderror
            </div>

            <button type="button" wire:click="save" wire:loading.attr="disabled" wire:target="save" class="ns-cta" style="margin-top:16px">
                <span wire:loading.remove wire:target="save" class="ns-cta__label"><x-nx.icon name="check" /> {{ $editingId ? 'Save changes' : 'Add contact' }}</span>
                <span wire:loading wire:target="save" class="ns-cta__label"><x-ui.spinner class="h-4 w-4" /> Saving…</span>
            </button>

            @if ($editingId)
                <button type="button" wire:click="delete({{ $editingId }})" wire:confirm="Remove this contact?" @click="sheet = false" class="ns-cta ns-cta--ghost ns-ct__danger" style="margin-top:10px">Delete contact</button>
            @endif

            {{-- Bulk import --}}
            <div class="ns-ct__import">
                <b class="ns-ct__importhead"><x-nx.icon name="upload" /> Import a batch</b>
                <p class="ns-small">Upload a <strong>.csv</strong> (name, number) or a <strong>.vcf</strong> (vCard).</p>
                <div class="ns-ct__importrow">
                    <input type="file" wire:model="upload" accept=".csv,.txt,.vcf" class="ns-ct__file" aria-label="Contacts file">
                    <button type="button" wire:click="import" wire:loading.attr="disabled" wire:target="import,upload" @disabled(! $upload) class="ns-cta ns-cta--pill ns-cta--sm">
                        <span wire:loading.remove wire:target="import" class="ns-cta__label"><x-nx.icon name="upload" /> Import</span>
                        <span wire:loading wire:target="import" class="ns-cta__label"><x-ui.spinner class="h-4 w-4" /> Importing…</span>
                    </button>
                </div>
                @error('upload') <p class="ns-ct__err">{{ $message }}</p> @enderror

                {{-- Android-Chrome OS contact picker (progressive enhancement). --}}
                <div x-data="{ supported: (typeof navigator !== 'undefined' && 'contacts' in navigator && 'ContactsManager' in window),
                        async pick() { try { const sel = await navigator.contacts.select(['name','tel'], { multiple: true });
                            const rows = sel.map(c => ({ name: (c.name && c.name[0]) || '', phone: (c.tel && c.tel[0]) || '' })).filter(r => r.phone);
                            if (rows.length) { $wire.importPicked(rows); } } catch (e) {} } }"
                     x-show="supported" x-cloak class="ns-ct__pick">
                    <button type="button" @click="pick()" class="ns-cta ns-cta--pill ns-cta--ghost ns-cta--sm"><x-icon name="inbox" class="h-4 w-4" /> Import from phone</button>
                </div>
            </div>
        </div>
    </div>
</div>
