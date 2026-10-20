{{-- nx:converted --}}
{{-- The member's handbook Library (MASTER-ONLY). Skin tokens only. --}}
<div>
<x-nx.page class="ns-pg">
    <div class="ns-pg__head">
        <div>
            <h1 class="ns-h1">My Library</h1>
            <p class="ns-sub" style="margin:4px 0 0">Your travel handbooks. Open one to read it, even without a connection.</p>
        </div>
        <a href="{{ route('handbooks.store') }}" class="ns-cta ns-cta--pill ns-cta--ghost">Browse handbooks</a>
    </div>

    @if ($notice)<div class="ns-pg__card ns-ring" style="margin-top:16px" role="status" data-hb-notice>{{ $notice }}</div>@endif
    @if ($error)<div class="ns-pg__card ns-ring" style="margin-top:16px;border-color:rgb(var(--nx-bad))" role="alert">{{ $error }}</div>@endif

    @foreach ($picks as $pk)
        <div class="ns-pg__card ns-ring" style="margin-top:16px" data-esim-pick wire:key="pick-{{ $pk['id'] }}">
            <b style="color:rgb(var(--nx-text))">Your eSIM includes {{ $pk['left'] > 1 ? $pk['left'].' free travel handbooks' : 'a free travel handbook' }}</b>
            <p class="ns-pg__hint" style="margin:4px 0 10px">Pick the destination you need most. It unlocks to read in the app and offline.</p>
            <div style="display:flex;gap:8px;flex-wrap:wrap">
                @foreach ($pk['options'] as $o)
                    <button type="button" wire:click="choosePick({{ $pk['id'] }}, {{ $o->id }})" wire:loading.attr="disabled" class="ns-cta ns-cta--pill ns-cta--ghost ns-cta--sm">{{ $o->country_code }} · {{ $o->title }}</button>
                @endforeach
            </div>
        </div>
    @endforeach

    @if ($isPro && $slotsLeft > 0 && $slotOptions->isNotEmpty())
        <div class="ns-pg__card ns-ring" style="margin-top:16px" data-pro-slots>
            <b style="color:rgb(var(--nx-text))">{{ $slotsLeft }} of {{ $slotsTotal }} free handbook unlocks left</b>
            <p class="ns-pg__hint" style="margin:4px 0 10px">A Naara Pro benefit. Choose a destination to unlock it.</p>
            <div style="display:flex;gap:8px;flex-wrap:wrap">
                @foreach ($slotOptions as $o)
                    <button type="button" wire:click="useSlot({{ $o->id }})" wire:loading.attr="disabled" class="ns-cta ns-cta--pill ns-cta--ghost ns-cta--sm">{{ $o->country_code }} · {{ $o->title }}</button>
                @endforeach
            </div>
        </div>
    @endif

    @if ($continue)
        @php($cb = $continue->handbook)
        <a href="{{ route('handbooks.read', $cb->slug) }}" class="ns-pg__card ns-ring hb-continue" style="margin-top:16px" data-continue-reading>
            <span class="hb-continue__cover"><x-handbook-cover :book="$cb" /></span>
            <span><b style="color:rgb(var(--nx-text))">Continue reading</b><span class="ns-pg__hint" style="display:block;margin:2px 0 0">{{ $cb->title }} · {{ (int) $progress[$cb->id]->percent }}% read</span></span>
            <x-nx.icon name="right" />
        </a>
    @endif

    @if ($countries->count() > 1)
        <div class="ns-seg ns-small" role="group" aria-label="Filter by country" style="margin-top:16px">
            <button type="button" wire:click="$set('country', '')" class="{{ $country === '' ? 'is-on' : '' }}">All</button>
            @foreach ($countries as $cc)<button type="button" wire:click="$set('country', '{{ $cc }}')" class="{{ strtoupper($country) === $cc ? 'is-on' : '' }}">{{ $cc }}</button>@endforeach
        </div>
    @endif

    @if ($rows->isEmpty())
        <div class="ns-pg__card ns-ring" style="margin-top:18px"><b style="color:rgb(var(--nx-text))">Nothing here yet</b><p class="ns-pg__hint" style="margin:4px 0 10px">Buy a Naara eSIM for a country and its handbook appears here, or pick a handbook from the store.</p><a href="{{ route('handbooks.store') }}" class="ns-cta ns-cta--pill">Browse handbooks</a></div>
    @else
        <div class="hb-grid" data-library-grid>
            @foreach ($rows as $e)
                @php($b = $e->handbook)
                @php($locked = $e->locked_at !== null)
                @php($pct = (int) ($progress[$b->id]->percent ?? 0))
                <div class="hb-lib" wire:key="lib-{{ $e->id }}">
                    <span class="hb-card__cover">
                        <x-handbook-cover :book="$b" :class="$locked ? 'hb-lib__locked' : ''" />
                        @if ($e->level === 'owned')<span class="hb-badge" title="Download included">Download</span>@endif
                    </span>
                    <span class="hb-card__t">{{ $b->title }}</span>
                    @if ($locked)
                        <span class="ns-pg__hint" style="margin:0">Locked: renew Naara Pro to read it again.</span>
                    @else
                        <span class="hb-bar" aria-label="{{ $pct }}% read"><i style="width:{{ $pct }}%"></i></span>
                        <span style="display:flex;gap:6px;flex-wrap:wrap">
                            <a href="{{ route('handbooks.read', $b->slug) }}" class="ns-cta ns-cta--pill ns-cta--sm">{{ $pct > 0 ? 'Continue' : 'Read' }}</a>
                            @if ($e->level === 'owned')
                                <a href="{{ route('handbooks.download', [$b->slug, 'epub']) }}" class="ns-cta ns-cta--pill ns-cta--ghost ns-cta--sm">EPUB</a>
                                @if ($b->pdf_path)<a href="{{ route('handbooks.download', [$b->slug, 'pdf']) }}" class="ns-cta ns-cta--pill ns-cta--ghost ns-cta--sm">PDF</a>@endif
                            @else
                                <a href="{{ route('handbooks.show', $b->slug) }}" class="ns-cta ns-cta--pill ns-cta--ghost ns-cta--sm">Upgrade to download</a>
                            @endif
                        </span>
                        @if (in_array($b->id, $refunds, true))
                            <span class="ns-pg__hint" style="margin:0" data-refund-pending>Refund request with our team.</span>
                        @elseif (in_array($e->id, $refundable, true))
                            @if ($refundFor === $e->id)
                                <form wire:submit="requestRefund" style="display:grid;gap:6px" data-refund-form>
                                    <label class="ns-pg__hint" for="rf-{{ $e->id }}" style="margin:0">Tell us what went wrong (optional)</label>
                                    <textarea id="rf-{{ $e->id }}" wire:model="refundReason" rows="3" maxlength="1000" class="ns-input"></textarea>
                                    <span style="display:flex;gap:6px;flex-wrap:wrap">
                                        <button type="submit" wire:loading.attr="disabled" wire:target="requestRefund" class="ns-cta ns-cta--pill ns-cta--sm">Send request</button>
                                        <button type="button" wire:click="cancelRefund" class="ns-cta ns-cta--pill ns-cta--ghost ns-cta--sm">Cancel</button>
                                    </span>
                                </form>
                            @else
                                <button type="button" wire:click="openRefund({{ $e->id }})" class="ns-pg__hint" style="margin:0;text-decoration:underline;background:none;border:0;padding:0;cursor:pointer;text-align:left" data-refund-open>Request a refund</button>
                            @endif
                        @endif
                    @endif
                </div>
            @endforeach
        </div>
    @endif

    @if ($audio->isNotEmpty())
        <h2 class="ns-pg__h2" style="margin-top:28px">Audiobooks</h2>
        <ul style="list-style:none;margin:8px 0 0;padding:0;display:grid;gap:10px" data-audio-shelf>
            @foreach ($audio as $a)
                <li class="ns-pg__card ns-ring" style="display:flex;justify-content:space-between;gap:10px;align-items:center;flex-wrap:wrap"><span><b style="color:rgb(var(--nx-text))">{{ $a->audiobook->handbook->title }}</b><span class="ns-pg__hint" style="display:block;margin:2px 0 0">Audiobook</span></span><a href="{{ route('handbooks.listen', $a->audiobook->handbook->slug) }}" class="ns-cta ns-cta--pill ns-cta--sm">Listen</a></li>
            @endforeach
        </ul>
    @endif
</x-nx.page>
</div>
