{{-- nx:converted --}}
{{-- Naara profile settings (N2N Batch 8, MASTER-ONLY). Skin tokens only. --}}
<div>
<x-nx.page class="ns-pg ns-pg--mid">
    <div class="ns-pg__head">
        <div>
            <a href="{{ route('n2n.identity') }}" wire:navigate class="ns-pg__back"><x-nx.icon name="left" /> Naara ID</a>
            <h1 class="ns-h1" style="margin-top:6px">Your Naara profile</h1>
            <p class="ns-sub" style="margin:4px 0 0">Choose who sees what. Your email and phone are only ever shown to people you are connected with, and only if you tick them.</p>
        </div>
        <img src="{{ asset('images/illustrations/nc-profile.svg') }}" alt="" width="240" height="180" class="hidden h-28 w-auto sm:block" data-n2n-art>
    </div>
    <x-n2n.section-tabs />

    @if ($message)<div class="ns-pg__card ns-ring" role="status" style="margin-top:12px">{{ $message }}</div>@endif
    @if ($error)<div class="ns-pg__card ns-ring" role="alert" style="margin-top:12px"><span class="ns-pg__fielderr">{{ $error }}</span></div>@endif
    @if ($profileUrl)<div class="ns-pg__card ns-ring"><a href="{{ $profileUrl }}" wire:navigate class="ns-pg__act ns-pg__act--link">See how your profile looks</a></div>@endif

    <form wire:submit="save" class="ns-pg__card ns-ring">
        <label class="ns-pg__lbl" for="pf-vis">Who can see your profile</label>
        <select id="pf-vis" wire:model.live="visibility" class="ns-input">
            <option value="public">Everyone on Naara</option>
            <option value="connections">Only people I am connected with</option>
            <option value="private">Private: only what I tick below</option>
        </select>

        <fieldset style="margin:12px 0 0;border:0;padding:0">
            <legend class="ns-pg__lbl">Show on my profile</legend>
            <label style="display:flex;gap:8px;margin:6px 0"><input type="checkbox" wire:model="reveal" value="naara_id"> <span>My Naara ID</span></label>
            <label style="display:flex;gap:8px;margin:6px 0"><input type="checkbox" wire:model="reveal" value="images"> <span>My pictures</span></label>
            <label style="display:flex;gap:8px;margin:6px 0"><input type="checkbox" wire:model="reveal" value="email"> <span>My email (connections only)</span></label>
            <label style="display:flex;gap:8px;margin:6px 0"><input type="checkbox" wire:model="reveal" value="phone"> <span>My verified phone (connections only)</span></label>
        </fieldset>

        <label style="display:flex;gap:8px;margin:12px 0 6px"><input type="checkbox" wire:model="showYear"> <span>Show my birth year (otherwise only day and month)</span></label>

        <label class="ns-pg__lbl" for="pf-bio" style="margin-top:8px;display:block">Bio</label>
        <textarea id="pf-bio" wire:model="bio" maxlength="280" rows="3" class="ns-input"></textarea>
        <button type="submit" wire:loading.attr="disabled" wire:target="save" class="ns-cta ns-cta--pill" style="margin-top:10px">Save</button>
    </form>

    <div class="ns-pg__card ns-ring">
        <h2 class="ns-pg__h2" style="margin-bottom:6px">Pictures</h2>
        <p class="ns-sub" style="margin:0 0 10px">Up to two small pictures. Location data in photos is removed automatically.</p>
        @foreach ([1, 2] as $slot)
            <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin:8px 0">
                @if (in_array($slot, $slots, true))
                    <img src="{{ route('n2n.profile.picture', ['userId' => auth()->id(), 'slot' => $slot]) }}" alt="Picture {{ $slot }}" width="56" height="56" style="width:56px;height:56px;border-radius:9999px;object-fit:cover">
                    <button type="button" wire:click="removePicture({{ $slot }})" wire:confirm="Remove this picture?" class="ns-pg__act ns-pg__act--link">Remove</button>
                @endif
                <input type="file" wire:model="pic{{ $slot }}" accept="image/jpeg,image/png,image/webp" class="ns-input" aria-label="Picture {{ $slot }}" style="max-width:260px">
                <button type="button" wire:click="savePicture({{ $slot }})" wire:loading.attr="disabled" wire:target="savePicture,pic{{ $slot }}" class="ns-cta ns-cta--pill ns-cta--sm">Upload</button>
            </div>
        @endforeach
    </div>
</x-nx.page>
</div>
