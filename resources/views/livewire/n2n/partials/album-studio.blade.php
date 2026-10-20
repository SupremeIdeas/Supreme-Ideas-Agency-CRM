{{-- nx:converted --}}
{{-- Album studio for artists with a music profile (N2N, MASTER-ONLY): cover, title, distributor and its smart link, streaming links with their logos, publish. Skin tokens and the .alb-* classes only. --}}
@php
    $liveCount = $albums->whereNotNull('published_at')->count();
    $atCap = $albumCap !== PHP_INT_MAX && $albums->count() >= $albumCap;
    $coverPreview = null;
    if ($albumCover) { try { $coverPreview = $albumCover->temporaryUrl(); } catch (\Throwable) { $coverPreview = null; } }
    $coverPreview ??= $albumCoverUrl;
@endphp
<section class="ns-pg__card ns-ring alb" data-album-studio aria-labelledby="alb-h">
    <div class="alb__head">
        <div>
            <h2 class="ns-pg__h2" id="alb-h" style="margin:0">Albums</h2>
            <p class="ns-sub" style="margin:2px 0 0">Your releases, shown as a carousel on your profile. {{ $albumCap === PHP_INT_MAX ? 'Unlimited albums on your plan.' : $albums->count().' of '.$albumCap.' used.' }}</p>
        </div>
        <button type="button" wire:click="newAlbum" @disabled($atCap) class="ns-cta ns-cta--pill ns-cta--sm" data-album-new><x-nx.icon name="plus" /> New album</button>
    </div>
    @if ($atCap)<p class="ns-sub" style="margin:8px 0 0" role="status">You have reached your album limit. The highest plan has no limit.</p>@endif

    @if ($albums->isNotEmpty())
        <div class="alb__grid" role="list">
            @foreach ($albums as $a)
                <article class="alb__card" role="listitem" wire:key="alb-{{ $a->id }}" data-album-card>
                    <div class="alb__cover">
                        @if ($a->cover_path)<img src="{{ $a->cover_path }}" alt="" loading="lazy" decoding="async">@else<span class="alb__nocover"><x-nx.icon name="image" /></span>@endif
                        @if ($a->distributor)<x-music.logo kind="distributor" :slug="$a->distributor" size="sm" class="alb__corner" />@endif
                        <span class="alb__pill {{ $a->hidden_at ? 'is-hidden' : ($a->published_at ? 'is-live' : '') }}">{{ $a->hidden_at ? 'Hidden by admin' : ($a->published_at ? 'Live' : 'Draft') }}</span>
                    </div>
                    <b class="alb__title">{{ $a->title }}</b>
                    <small class="ns-sub">{{ $a->release_date?->format('M Y') ?? 'No date' }} · {{ $a->links->count() }} {{ \Illuminate\Support\Str::plural('link', $a->links->count()) }}@if ($a->smart_url) · smart link @endif</small>
                    <div class="alb__acts">
                        <button type="button" wire:click="editAlbum({{ $a->id }})" class="ns-pg__act ns-pg__act--link">Edit</button>
                        @if ($a->published_at)<a href="{{ route('n2n.album', ['brand' => $brand->id, 'slug' => $a->slug]) }}" target="_blank" rel="noopener" class="ns-pg__act ns-pg__act--link">View</a>
                            <button type="button" wire:click="unpublishAlbum({{ $a->id }})" class="ns-pg__act ns-pg__act--link">Unpublish</button>@endif
                        <button type="button" wire:click="moveAlbum({{ $a->id }}, -1)" class="ns-pg__act ns-pg__act--link" aria-label="Move earlier" @disabled($loop->first)>&larr;</button>
                        <button type="button" wire:click="moveAlbum({{ $a->id }}, 1)" class="ns-pg__act ns-pg__act--link" aria-label="Move later" @disabled($loop->last)>&rarr;</button>
                        <button type="button" wire:click="deleteAlbum({{ $a->id }})" wire:confirm="Delete this album and its links?" class="ns-pg__act ns-pg__act--link">Delete</button>
                    </div>
                </article>
            @endforeach
        </div>
    @elseif ($albumId === null)
        <div class="alb__empty"><x-nx.icon name="volume-2" /><p style="margin:6px 0 0">Add your first album: a cover, its distributor smart link, and where to stream it.</p></div>
    @endif

    @if ($albumId !== null)
        <form wire:submit.prevent="saveAlbum(false)" class="alb__editor" data-album-editor aria-label="{{ $albumId ? 'Edit album' : 'New album' }}">
            <div class="alb__left">
                <label class="alb__drop" for="alb-cover">
                    @if ($coverPreview)<img src="{{ $coverPreview }}" alt="Album cover preview">@else<span><x-nx.icon name="image" /><b>Add the cover</b><small>Square, 3000 x 3000 is ideal. JPG, PNG or WebP, up to 5 MB.</small></span>@endif
                    <span class="alb__dropbar" wire:loading wire:target="albumCover" role="status">Uploading...</span>
                </label>
                <input id="alb-cover" type="file" wire:model="albumCover" accept="image/jpeg,image/png,image/webp" class="sr-only" aria-label="Album cover">
                @error('albumCover')<small style="color:rgb(var(--nx-bad))">{{ $message }}</small>@enderror
                @if ($albumDistributor)<div class="alb__preview"><span class="ns-sub">On your profile</span><div class="alb__cover alb__cover--sm">@if ($coverPreview)<img src="{{ $coverPreview }}" alt="">@endif<x-music.logo kind="distributor" :slug="$albumDistributor" size="sm" class="alb__corner" /></div></div>@endif
            </div>
            <div class="alb__right">
                <label class="ns-sub">Album title<input type="text" wire:model="albumTitle" class="ns-input" maxlength="120" placeholder="Album or single title" style="width:100%"></label>
                <div class="alb__row2">
                    <label class="ns-sub">Release date<input type="date" wire:model="albumRelease" class="ns-input" style="width:100%"></label>
                    <label class="ns-sub">About this release (optional)<input type="text" wire:model="albumDescription" class="ns-input" maxlength="600" placeholder="One line about the project" style="width:100%"></label>
                </div>

                <div>
                    <span class="ns-sub">Your distributor</span>
                    <div class="alb__dist" role="radiogroup" aria-label="Distributor">
                        @foreach ($distributors as $slug => $label)
                            <button type="button" role="radio" aria-checked="{{ $albumDistributor === $slug ? 'true' : 'false' }}" wire:click="$set('albumDistributor', '{{ $albumDistributor === $slug ? '' : $slug }}')" wire:key="d-{{ $slug }}" class="alb__distbtn {{ $albumDistributor === $slug ? 'is-on' : '' }}">
                                <x-music.logo kind="distributor" :slug="$slug" size="sm" />
                                @if (config('n2n_music_distributors.distributors.'.$slug.'.logo'))<span>{{ $label }}</span>@endif
                            </button>
                        @endforeach
                    </div>
                </div>

                <label class="ns-sub">Distributor smart link
                    <input type="url" wire:model="albumSmartUrl" class="ns-input" placeholder="https://ditto.fm/your-release" style="width:100%" autocomplete="off">
                    <small>The one link that opens your release on every store. Ditto, DistroKid, TuneCore and smart-link services like Songwhip or Linkfire are accepted.</small>
                </label>

                <div>
                    <span class="ns-sub">Streaming links (optional, each shows its logo)</span>
                    @foreach ($albumLinks as $i => $row)
                        <div class="alb__link" wire:key="al-{{ $i }}">
                            <x-music.logo kind="platform" :slug="$row['platform'] ?: 'spotify'" size="md" />
                            <select wire:model.live="albumLinks.{{ $i }}.platform" class="ns-input" aria-label="Platform">@foreach ($platforms as $k => $p)<option value="{{ $k }}">{{ $p['label'] }}</option>@endforeach</select>
                            <input type="url" wire:model="albumLinks.{{ $i }}.url" class="ns-input" placeholder="https://" aria-label="Link" style="flex:1;min-width:150px">
                            <button type="button" wire:click="removeAlbumLinkRow({{ $i }})" class="ns-pg__act ns-pg__act--link" aria-label="Remove link">Remove</button>
                        </div>
                    @endforeach
                    <button type="button" wire:click="addAlbumLinkRow" class="ns-pg__act ns-pg__act--link">+ Add a link</button>
                </div>

                @if ($error)<p role="alert" style="color:rgb(var(--nx-bad));margin:0" data-album-error>{{ $error }}</p>@endif
                <div class="alb__bar">
                    <button type="button" wire:click="saveAlbum(true)" wire:loading.attr="disabled" wire:target="saveAlbum,albumCover" class="ns-cta ns-cta--pill"><span wire:loading.remove wire:target="saveAlbum">{{ $albumLive ? 'Save changes' : 'Publish album' }}</span><span wire:loading wire:target="saveAlbum">Saving...</span></button>
                    <button type="submit" wire:loading.attr="disabled" wire:target="saveAlbum,albumCover" class="ns-cta ns-cta--pill ns-cta--ghost">Save as draft</button>
                    <button type="button" wire:click="closeAlbum" class="ns-pg__act ns-pg__act--link">Cancel</button>
                </div>
            </div>
        </form>
    @endif
</section>
