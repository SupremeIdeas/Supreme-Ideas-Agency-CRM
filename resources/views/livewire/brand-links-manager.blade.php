{{-- Business links card (Brand-Growth Phase 2, MASTER-ONLY). One plain root; skin tokens only. --}}
<div class="ns-pg__card ns-ring" data-tour="brand.links">
    <h2 class="ns-bh__h" style="margin-bottom:4px"><x-nx.icon name="globe" /> Business links</h2>
    <p class="ns-pg__hint" style="margin:0 0 12px">{{ $links->count() }} / {{ $limit }} used. Visitors see the link name with the real address next to it. Links must open your own website.</p>

    @if ($flash)<div class="ns-pg__card ns-ring" role="status" style="margin-bottom:10px">{{ $flash }}</div>@endif
    @if ($problems)
        <div class="ns-pg__card ns-ring" role="alert" style="margin-bottom:10px">
            <ul style="margin:0;padding-left:18px">@foreach ($problems as $p)<li class="ns-pg__fielderr">{{ $p }}</li>@endforeach</ul>
            @if ($canReview && $reviewFor)<button type="button" wire:click="review({{ $reviewFor }})" class="ns-cta ns-cta--pill ns-cta--ghost ns-cta--sm" style="margin-top:8px">It is my domain: ask for a review</button>@endif
        </div>
    @endif

    <div class="ns-pg__stack ns-pg__stack--sm">
        @foreach ($links as $l)
            <div wire:key="bl-{{ $l->id }}" class="ns-pg__kv" style="gap:10px">
                <span style="flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:rgb(var(--nx-text))">{{ $l->label }} <span class="ns-small">· {{ $l->host }}</span></span>
                <span class="ns-small" style="flex:none">{{ ['approved' => 'Live', 'pending' => 'Waiting for review', 'rejected' => 'Not accepted', 'suspended' => 'Paused'][$l->status] ?? $l->status }}</span>
                <button type="button" wire:click="remove({{ $l->id }})" wire:confirm="Remove this link?" aria-label="Remove {{ $l->label }}" class="ns-ct__act ns-ct__act--spam" style="width:32px;height:32px"><x-nx.icon name="x" /></button>
            </div>
            @if (in_array($l->status, ['rejected', 'suspended'], true) && $l->reject_reason)<p class="ns-pg__fielderr" style="margin:-4px 0 4px">{{ $l->reject_reason }}</p>@endif
            @if ($l->status === 'rejected' && ! $l->review_requested)<button type="button" wire:click="review({{ $l->id }})" class="ns-cta ns-cta--pill ns-cta--ghost ns-cta--sm" style="align-self:flex-start">Ask for a review</button>@endif
        @endforeach
    </div>

    <div class="ns-pg__form" style="margin-top:12px">
        <div class="ns-pg__two">
            <input wire:model="label" maxlength="40" placeholder="Link name, e.g. Our portfolio" aria-label="Link name" class="ns-input">
            <input wire:model="url" placeholder="https://yourbrand.com" aria-label="Link address" class="ns-input">
        </div>
        @error('label')<span class="ns-pg__fielderr">{{ $message }}</span>@enderror
        @error('url')<span class="ns-pg__fielderr">{{ $message }}</span>@enderror
        <button type="button" wire:click="add" wire:loading.attr="disabled" wire:target="add" class="ns-cta ns-cta--pill ns-cta--ghost ns-cta--sm" style="align-self:flex-start;margin-top:10px">
            <span wire:loading.remove wire:target="add">Check and add link</span><span wire:loading wire:target="add">Checking the website...</span>
        </button>
    </div>
</div>
