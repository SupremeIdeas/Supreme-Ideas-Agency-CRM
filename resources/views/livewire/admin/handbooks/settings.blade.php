{{-- nx:converted --}}
{{-- Admin > Handbooks > Store and unlock settings (MASTER-ONLY). Skin tokens only. --}}
<div>
@php
    $card = 'ns-pg__card ns-ring';
    $in = 'ns-input';
    $lb = 'ns-pg__hint';
@endphp
<x-nx.page class="ns-pg ns-pg--mid">
    <div class="ns-pg__head">
        <div>
            <a href="{{ route('admin.handbooks') }}" class="ns-pg__back"><x-nx.icon name="left" /> Handbooks</a>
            <h1 class="ns-h1" style="margin-top:6px">Store and unlock settings</h1>
            <p class="ns-sub" style="margin:4px 0 0">Decide when a handbook unlocks, what a purchase does, and how the store looks. Per-book switches live on each book.</p>
        </div>
    </div>
    @if ($flash)<div class="{{ $card }}" role="status" style="margin-top:12px" data-saved>{{ $flash }}</div>@endif

    <form wire:submit="save" style="display:grid;gap:16px;margin-top:16px">
        <div class="{{ $card }}" data-esim-rules>
            <h2 class="ns-pg__h2">When someone buys an eSIM</h2>
            <label class="hb-switch"><input type="checkbox" wire:model.live="esimUnlockEnabled"><span><b>An eSIM unlocks a handbook</b><small>Master switch. Off means no eSIM purchase unlocks anything, and nothing is taken away from people who already have a handbook.</small></span></label>
            @if ($esimUnlockEnabled)
                <div style="margin-top:12px"><span class="{{ $lb }}"><b style="color:rgb(var(--nx-text))">Which eSIMs qualify</b></span>
                    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:6px">@foreach ($coverage as $k => $label)<label class="hb-chip" style="cursor:pointer"><input type="checkbox" wire:model="esimCoverage" value="{{ $k }}"> {{ $label }}</label>@endforeach</div>
                    <p class="{{ $lb }}" style="margin:6px 0 0">A single-country eSIM unlocks that country's handbook at once. A regional or global eSIM lets the buyer pick from the countries it covers.</p>
                </div>
                <div style="display:grid;gap:12px;grid-template-columns:repeat(auto-fit,minmax(14rem,1fr));margin-top:12px">
                    <div><label class="{{ $lb }}" for="hs-picks">Handbooks a regional or global eSIM lets them pick</label><input id="hs-picks" type="number" min="1" max="10" wire:model="esimRegionalPicks" class="{{ $in }}">@error('esimRegionalPicks')<p class="ns-pg__fielderr">{{ $message }}</p>@enderror</div>
                    <div><label class="{{ $lb }}" for="hs-min">Minimum eSIM price to qualify (USD, 0 = any)</label><input id="hs-min" type="number" step="0.01" min="0" wire:model="esimMinPriceUsd" class="{{ $in }}">@error('esimMinPriceUsd')<p class="ns-pg__fielderr">{{ $message }}</p>@enderror</div>
                </div>
                <label class="hb-switch" style="margin-top:12px"><input type="checkbox" wire:model="esimFlagshipOnly"><span><b>Flagship titles only</b><small>On: only a country's main handbook (series slot S1) can be unlocked by an eSIM. Off: any title that has its own eSIM switch on.</small></span></label>
            @endif
        </div>

        <div class="{{ $card }}" data-pro-rules>
            <h2 class="ns-pg__h2">Naara Pro</h2>
            <label class="hb-switch"><input type="checkbox" wire:model.live="proUnlockEnabled"><span><b>Pro members get free handbook unlocks</b><small>Off hides the Pro slot choice. Unlocks they already made stay while their Pro term is active.</small></span></label>
            @if ($proUnlockEnabled)<div style="max-width:16rem;margin-top:12px"><label class="{{ $lb }}" for="hs-slots">Free unlocks per Pro term</label><input id="hs-slots" type="number" min="0" max="50" wire:model="proSlots" class="{{ $in }}">@error('proSlots')<p class="ns-pg__fielderr">{{ $message }}</p>@enderror</div>@endif
        </div>

        <div class="{{ $card }}" data-purchase-rules>
            <h2 class="ns-pg__h2">Buying</h2>
            <div style="display:grid;gap:10px;grid-template-columns:repeat(auto-fit,minmax(16rem,1fr))">
                <label class="hb-switch"><input type="checkbox" wire:model="purchaseEnabled"><span><b>Direct purchase is open</b><small>Off hides every Buy button.</small></span></label>
                <label class="hb-switch"><input type="checkbox" wire:model="upgradeEnabled"><span><b>Unlocked books can be upgraded to download</b><small>Pays 50% of list, rounded up. EPUB and PDF with a buyer watermark.</small></span></label>
            </div>
        </div>

        <div class="{{ $card }}" data-storefront-rules>
            <h2 class="ns-pg__h2">Storefront</h2>
            <div style="display:grid;gap:10px;grid-template-columns:repeat(auto-fit,minmax(16rem,1fr))">
                <label class="hb-switch"><input type="checkbox" wire:model="storeEnabled"><span><b>The store is open</b><small>Off shows a friendly "coming soon" page instead of the landing page, directory and book pages. Readers keep their Library.</small></span></label>
                <label class="hb-switch"><input type="checkbox" wire:model="esimBannerEnabled"><span><b>Banner in the eSIM hero</b><small>An extra slide that sends shoppers to the handbooks.</small></span></label>
            </div>
            <div style="display:grid;gap:12px;grid-template-columns:repeat(auto-fit,minmax(16rem,1fr));margin-top:12px">
                <div><label class="{{ $lb }}" for="hs-iw">Manuscript images: widest picture (px)</label><input id="hs-iw" type="number" min="480" max="4000" wire:model="imageMaxWidth" class="{{ $in }}">@error('imageMaxWidth')<p class="ns-pg__fielderr">{{ $message }}</p>@enderror<p class="ns-pg__hint" style="margin:4px 0 0">The manuscript studio scales wider pictures down to this so they read sharply on a phone.</p></div>
                <div><label class="{{ $lb }}" for="hs-iq">Manuscript images: quality (50 to 95)</label><input id="hs-iq" type="number" min="50" max="95" wire:model="imageQuality" class="{{ $in }}">@error('imageQuality')<p class="ns-pg__fielderr">{{ $message }}</p>@enderror</div>
                <div><label class="{{ $lb }}" for="hs-ik">Manuscript images: flag when heavier than (KB)</label><input id="hs-ik" type="number" min="50" max="8000" wire:model="imageFlagKb" class="{{ $in }}">@error('imageFlagKb')<p class="ns-pg__fielderr">{{ $message }}</p>@enderror</div>
                <div><label class="{{ $lb }}" for="hs-pop">Titles on the Popular shelf</label><input id="hs-pop" type="number" min="3" max="24" wire:model="popularCount" class="{{ $in }}"></div>
                <div><label class="{{ $lb }}" for="hs-eb">Eyebrow</label><input id="hs-eb" type="text" wire:model="heroEyebrow" class="{{ $in }}">@error('heroEyebrow')<p class="ns-pg__fielderr">{{ $message }}</p>@enderror</div>
                <div><label class="{{ $lb }}" for="hs-h">Headline</label><input id="hs-h" type="text" wire:model="heroHeadline" class="{{ $in }}">@error('heroHeadline')<p class="ns-pg__fielderr">{{ $message }}</p>@enderror</div>
                <div><label class="{{ $lb }}" for="hs-a">Headline (second line)</label><input id="hs-a" type="text" wire:model="heroAccent" class="{{ $in }}"></div>
                <div style="grid-column:1/-1"><label class="{{ $lb }}" for="hs-s">Intro</label><textarea id="hs-s" rows="3" wire:model="heroSubtext" class="{{ $in }}"></textarea>@error('heroSubtext')<p class="ns-pg__fielderr">{{ $message }}</p>@enderror</div>
                <div><label class="{{ $lb }}" for="hs-c1">Main button</label><input id="hs-c1" type="text" wire:model="ctaPrimary" class="{{ $in }}"></div>
                <div><label class="{{ $lb }}" for="hs-c2">Second button</label><input id="hs-c2" type="text" wire:model="ctaSecondary" class="{{ $in }}"></div>
            </div>
        </div>

        <div><button type="submit" class="ns-cta ns-cta--pill" wire:loading.attr="disabled" wire:target="save"><span wire:loading.remove wire:target="save">Save settings</span><span wire:loading wire:target="save">Saving…</span></button></div>
    </form>
</x-nx.page>
</div>
