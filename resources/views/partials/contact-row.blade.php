{{-- Contact row: tap the body to edit, swipe left to reveal Delete, favourite / call / message / spam actions. --}}
<div wire:key="row-{{ $c->id }}" class="ns-ct__row {{ ($last ?? false) ? 'is-last' : '' }}"
     x-data="{ x: 0, sx: 0, dragging: false }"
     @pointerdown="sx = $event.clientX; dragging = true"
     @pointermove="if (dragging) x = Math.max(-88, Math.min(0, $event.clientX - sx))"
     @pointerup="dragging = false; x = x < -44 ? -88 : 0"
     @pointercancel="dragging = false; x = 0"
     @pointerleave="if (dragging) { dragging = false; x = x < -44 ? -88 : 0 }">

    <button type="button" wire:click="delete({{ $c->id }})" wire:confirm="Remove {{ $c->name }} from your contacts?" class="ns-ct__del">
        <x-nx.icon name="trash" /> Delete
    </button>

    <div class="ns-ct__front" :style="{ transform: 'translateX(' + x + 'px)' }">
        <button type="button" wire:click="edit({{ $c->id }})" @click="$dispatch('contact-edit')" class="ns-ct__who">
            @include('partials.contact-avatar', ['contact' => $c, 'size' => 'h-10 w-10 text-sm'])
            <span class="ns-ct__name"><b>{{ $c->name }}</b><small>{{ $c->phone_number }}</small></span>
        </button>

        <span class="ns-ct__acts">
            <button type="button" wire:click="toggleFavorite({{ $c->id }})" aria-label="Favourite {{ $c->name }}" aria-pressed="{{ $c->is_favorite ? 'true' : 'false' }}" class="ns-ct__act ns-ct__act--star {{ $c->is_favorite ? 'is-on' : '' }}"><x-nx.icon name="star" /></button>
            <a href="{{ route('numbers.dialer', ['to' => $c->phone_number]) }}" wire:navigate aria-label="Call {{ $c->name }}" class="ns-ct__act ns-ct__act--call"><x-nx.icon name="phone" /></a>
            @if ($ownsLine)
                <button type="button" wire:click="$dispatch('open-send-message', { to: '{{ $c->phone_number }}', name: @js($c->name) })" aria-label="Message {{ $c->name }}" class="ns-ct__act ns-ct__act--msg"><x-nx.icon name="message-circle" /></button>
            @endif
            {{-- Spam-report + auto-block (Prompt 11). --}}
            <button type="button" wire:click="reportSpam({{ $c->id }})" wire:confirm="Report {{ $c->name }}'s number ({{ $c->phone_number }}) as spam?" aria-label="Report {{ $c->name }} as spam" class="ns-ct__act ns-ct__act--spam"><x-nx.icon name="alert-triangle" /></button>
        </span>
    </div>
</div>
