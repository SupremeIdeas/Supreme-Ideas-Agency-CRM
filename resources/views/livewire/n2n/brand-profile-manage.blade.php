{{-- nx:converted --}}
{{-- Brand profile extras for the owner (N2N Batch 8, MASTER-ONLY). Skin tokens only; every limit shown here is enforced on the server. --}}
<div>
<x-nx.page class="ns-pg ns-pg--mid">
    <div class="ns-pg__head">
        <div>
            <a href="{{ route('brand.manage') }}" wire:navigate class="ns-pg__back"><x-nx.icon name="left" /> Manage listing</a>
            <h1 class="ns-h1" style="margin-top:6px">Profile extras</h1>
            <p class="ns-sub" style="margin:4px 0 0">{{ $tier === 'music' ? 'Music profile' : 'General profile' }}</p>
        </div>
    </div>

    @if ($message)<div class="ns-pg__card ns-ring" role="status" style="margin-top:12px">{{ $message }}</div>@endif
    @if ($error)<div class="ns-pg__card ns-ring" role="alert" style="margin-top:12px"><span class="ns-pg__fielderr">{{ $error }}</span></div>@endif

    @if ($tier !== 'music')
        <div class="ns-pg__card ns-ring">
            <h2 class="ns-pg__h2">Music profile</h2>
            <p class="ns-sub" style="margin:4px 0 10px">Artists and labels can unlock streaming links, embedded players, albums and higher video and image limits. We confirm each request before it takes effect.</p>
            @if ($brand->music_requested_at)
                <span class="ns-st ns-st--warn">Waiting for confirmation</span>
            @else
                <button type="button" wire:click="requestMusic" wire:loading.attr="disabled" wire:target="requestMusic" class="ns-cta ns-cta--pill">Request a music profile</button>
            @endif
        </div>
    @endif

    <div class="ns-pg__card ns-ring">
        <h2 class="ns-pg__h2">Updates</h2>
        <p class="ns-sub" style="margin:4px 0 8px">Your gallery images show as Updates to people you are connected with, while your listing has Naara Pro.</p>
        <label class="ns-pg__lbl" for="upd-vis">Who can see my Updates</label>
        <select id="upd-vis" wire:change="setUpdatesVisibility($event.target.value)" class="ns-input">
            <option value="connections" @selected($brand->n2n_updates_visibility !== 'nobody')>People I am connected with</option>
            <option value="nobody" @selected($brand->n2n_updates_visibility === 'nobody')>Nobody (hide my Updates)</option>
        </select>
    </div>

    <div class="ns-pg__card ns-ring">
        <h2 class="ns-pg__h2">Gallery ({{ $imageCount }} of {{ $limits['images'] }})</h2>
        <div style="display:flex;gap:8px;flex-wrap:wrap;margin:8px 0">
            @foreach ($gallery as $g)
                <div wire:key="g-{{ $g->id }}" style="position:relative">
                    <img src="{{ $g->image_path }}" alt="" width="72" height="72" loading="lazy" style="width:72px;height:72px;object-fit:cover;border-radius:12px">
                    <button type="button" wire:click="removeImage({{ $g->id }})" wire:confirm="Remove this image?" class="ns-pg__act ns-pg__act--link" aria-label="Remove image" style="display:block;margin:2px auto 0">Remove</button>
                </div>
            @endforeach
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap">
            <input type="file" wire:model="image" accept="image/jpeg,image/png,image/webp" class="ns-input" aria-label="Gallery image" style="max-width:260px">
            <button type="button" wire:click="addImage" wire:loading.attr="disabled" wire:target="addImage,image" class="ns-cta ns-cta--pill ns-cta--sm">Add image</button>
        </div>
        @error('image')<span class="ns-pg__fielderr">{{ $message }}</span>@enderror
    </div>

    <div class="ns-pg__card ns-ring">
        <h2 class="ns-pg__h2">Social links</h2>
        <ul style="margin:8px 0;padding:0;list-style:none">
            @foreach ($social as $s)
                <li wire:key="s-{{ $s->id }}" style="display:flex;justify-content:space-between;gap:8px;margin:4px 0"><span>{{ $platforms[$s->platform]['label'] ?? $s->platform }}: {{ $s->url }}@if ($s->hidden_at) (hidden by admin)@endif</span>
                    <button type="button" wire:click="removeSocial({{ $s->id }})" class="ns-pg__act ns-pg__act--link">Remove</button></li>
            @endforeach
        </ul>
        <form wire:submit="addSocial" style="display:flex;gap:8px;flex-wrap:wrap">
            <select wire:model="socialPlatform" class="ns-input" aria-label="Platform" style="max-width:160px">
                @foreach (['instagram', 'tiktok', 'youtube'] as $k)<option value="{{ $k }}">{{ $platforms[$k]['label'] }}</option>@endforeach
            </select>
            <input type="url" wire:model="socialUrl" class="ns-input" placeholder="https://" aria-label="Link" style="flex:1;min-width:180px">
            <button type="submit" wire:loading.attr="disabled" wire:target="addSocial" class="ns-cta ns-cta--pill ns-cta--sm">Add</button>
        </form>
        <p class="ns-sub" style="margin:6px 0 0">These are shown on your profile only. They do not change your Follow Hunt handles.</p>
    </div>

    @if ($tier === 'music')
        <div class="ns-pg__card ns-ring">
            <h2 class="ns-pg__h2">Streaming links ({{ $music->count() }} of {{ $limits['music_links'] }})</h2>
            <ul style="margin:8px 0;padding:0;list-style:none">
                @foreach ($music as $m)
                    <li wire:key="m-{{ $m->id }}" style="display:flex;justify-content:space-between;gap:8px;margin:4px 0"><span>{{ $platforms[$m->platform]['label'] ?? $m->platform }}: {{ $m->url }}@if ($m->hidden_at) (hidden by admin)@endif</span>
                        <button type="button" wire:click="removeMusic({{ $m->id }})" class="ns-pg__act ns-pg__act--link">Remove</button></li>
                @endforeach
            </ul>
            <form wire:submit="addMusic" style="display:flex;gap:8px;flex-wrap:wrap">
                <select wire:model="musicPlatform" class="ns-input" aria-label="Platform" style="max-width:180px">
                    @foreach ($platforms as $k => $p)<option value="{{ $k }}">{{ $p['label'] }}</option>@endforeach
                </select>
                <input type="url" wire:model="musicUrl" class="ns-input" placeholder="https://open.spotify.com/..." aria-label="Link" style="flex:1;min-width:180px">
                <button type="submit" wire:loading.attr="disabled" wire:target="addMusic" class="ns-cta ns-cta--pill ns-cta--sm">Add</button>
            </form>
        </div>

        <div class="ns-pg__card ns-ring">
            <h2 class="ns-pg__h2">Embedded players ({{ $embeds->count() }} of {{ $limits['embeds'] }})</h2>
            <p class="ns-sub" style="margin:4px 0 8px">Paste a YouTube, Vimeo, Spotify or SoundCloud link or embed code. We keep only the player id and build the player ourselves.</p>
            <ul style="margin:8px 0;padding:0;list-style:none">
                @foreach ($embeds as $e)
                    <li wire:key="e-{{ $e->id }}" style="display:flex;justify-content:space-between;gap:8px;margin:4px 0"><span>{{ \App\Services\N2N\Brands\EmbedRenderer::label($e->provider) }}: {{ $e->provider_id }}@if ($e->hidden_at) (hidden by admin)@endif</span>
                        <button type="button" wire:click="removeEmbed({{ $e->id }})" class="ns-pg__act ns-pg__act--link">Remove</button></li>
                @endforeach
            </ul>
            <form wire:submit="addEmbed" style="display:flex;gap:8px;flex-wrap:wrap">
                <input type="text" wire:model="embedCode" class="ns-input" placeholder="Link or embed code" aria-label="Player link or embed code" style="flex:1;min-width:200px" maxlength="2000">
                <button type="submit" wire:loading.attr="disabled" wire:target="addEmbed" class="ns-cta ns-cta--pill ns-cta--sm">Add</button>
            </form>
        </div>

        @include('livewire.n2n.partials.album-studio')
    @endif
</x-nx.page>
</div>
