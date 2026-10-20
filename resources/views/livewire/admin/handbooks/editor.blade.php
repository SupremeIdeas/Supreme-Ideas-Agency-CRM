{{-- nx:converted --}}
{{-- Admin handbook editor (MASTER-ONLY). Skin tokens only. --}}
<div>
@php
    $card = 'ns-pg__card ns-ring';
    $in = 'ns-input';
    $lb = 'ns-pg__hint';
    $err = fn ($k) => $errors->first($k);
@endphp
<x-nx.page class="ns-pg ns-pg--mid">
    <div class="ns-pg__head">
        <div>
            <a href="{{ route('admin.handbooks') }}" class="ns-pg__back"><x-nx.icon name="left" /> Handbooks</a>
            <h1 class="ns-h1" style="margin-top:6px">{{ $b ? $b->title : 'New handbook' }}</h1>
            @if ($b)<p class="ns-sub" style="margin:4px 0 0">Status: <b>{{ \App\Models\Handbook::STATUSES[$b->status] ?? $b->status }}</b> · Edition {{ $b->edition }}</p>@endif
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap">
            @if ($b)<a href="{{ route('admin.handbooks.manuscript', $b->id) }}" class="ns-cta ns-cta--pill" data-open-studio>Manuscript studio</a>@endif
            @if ($b && $b->epub_path)<a href="{{ route('admin.handbooks.preview', $b->id) }}" target="_blank" class="ns-cta ns-cta--pill ns-cta--ghost">Preview as reader</a>@endif
        </div>
    </div>

    @if ($flash)<div class="{{ $card }}" role="status" style="margin-top:12px">{{ $flash }}</div>@endif
    @if ($report && ($report['errors'] || $report['warnings']))
        <div class="{{ $card }}" role="alert" style="margin-top:12px" data-upload-report>
            @foreach ($report['errors'] as $e)<p class="ns-pg__fielderr" style="margin:0 0 4px">{{ $e }}</p>@endforeach
            @foreach ($report['warnings'] as $w)<p class="ns-pg__hint" style="margin:0 0 4px">Note: {{ $w }}</p>@endforeach
        </div>
    @endif

    <form wire:submit="save" style="display:grid;gap:16px;margin-top:16px">
        <div class="{{ $card }}">
            <h2 class="ns-pg__h2">Details</h2>
            <div style="display:grid;gap:10px;grid-template-columns:repeat(auto-fit,minmax(14rem,1fr))">
                <div style="grid-column:1/-1"><label class="{{ $lb }}" for="hb-title">Title</label><input id="hb-title" type="text" wire:model="title" class="{{ $in }}">@error('title')<p class="ns-pg__fielderr">{{ $message }}</p>@enderror</div>
                <div style="grid-column:1/-1"><label class="{{ $lb }}" for="hb-sub">Subtitle</label><input id="hb-sub" type="text" wire:model="subtitle" class="{{ $in }}"></div>
                <div style="grid-column:1/-1"><label class="{{ $lb }}" for="hb-desc">Description</label><textarea id="hb-desc" rows="5" wire:model="description" class="{{ $in }}"></textarea></div>
                <div><label class="{{ $lb }}" for="hb-cc">Country (2 letters)</label><input id="hb-cc" type="text" maxlength="2" wire:model="countryCode" class="{{ $in }}" placeholder="GH">@error('countryCode')<p class="ns-pg__fielderr">{{ $message }}</p>@enderror</div>
                <div><label class="{{ $lb }}" for="hb-slot">Series slot (S1 is the flagship)</label><input id="hb-slot" type="text" maxlength="2" wire:model="seriesSlot" class="{{ $in }}" placeholder="S1">@error('seriesSlot')<p class="ns-pg__fielderr">{{ $message }}</p>@enderror</div>
                <div><label class="{{ $lb }}" for="hb-series">Series</label><select id="hb-series" wire:model="seriesId" class="{{ $in }}"><option value="">None</option>@foreach ($seriesList as $s)<option value="{{ $s->id }}">{{ $s->name }}</option>@endforeach</select></div>
                <div><label class="{{ $lb }}" for="hb-nseries">Or a new series</label><input id="hb-nseries" type="text" wire:model="newSeries" class="{{ $in }}" placeholder="The Ghana Series"></div>
                <div><label class="{{ $lb }}" for="hb-price">List price (USD)</label><input id="hb-price" type="number" step="0.01" min="0" wire:model="price" class="{{ $in }}">@error('price')<p class="ns-pg__fielderr">{{ $message }}</p>@enderror</div>
                <div><label class="{{ $lb }}" for="hb-ver">Last verified on</label><input id="hb-ver" type="date" wire:model="lastVerified" class="{{ $in }}">@error('lastVerified')<p class="ns-pg__fielderr">{{ $message }}</p>@enderror</div>
                <div><label class="{{ $lb }}" for="hb-cat">Catalogue ID</label><input id="hb-cat" type="text" wire:model="catalogueId" class="{{ $in }}" placeholder="GHA-01">@error('catalogueId')<p class="ns-pg__fielderr">{{ $message }}</p>@enderror</div>
                <div><label class="{{ $lb }}" for="hb-slug">Address (slug)</label><input id="hb-slug" type="text" wire:model="slug" class="{{ $in }}" placeholder="made from the title">@error('slug')<p class="ns-pg__fielderr">{{ $message }}</p>@enderror</div>
                <div><label class="{{ $lb }}" for="hb-lang">Language</label><input id="hb-lang" type="text" wire:model="language" class="{{ $in }}"></div>
                <div><label class="{{ $lb }}" for="hb-i1">ISBN (ebook)</label><input id="hb-i1" type="text" wire:model="isbnEbook" class="{{ $in }}"></div>
                <div><label class="{{ $lb }}" for="hb-i2">ISBN (paperback)</label><input id="hb-i2" type="text" wire:model="isbnPaperback" class="{{ $in }}"></div>
                <div><label class="{{ $lb }}" for="hb-i3">ISBN (hardcover)</label><input id="hb-i3" type="text" wire:model="isbnHardcover" class="{{ $in }}"></div>
            </div>
        </div>

        <div class="{{ $card }}" data-unlock-controls>
            <h2 class="ns-pg__h2">Store and unlock controls</h2>
            <p class="ns-pg__hint" style="margin:0 0 10px">These apply to this book only. The store-wide rules (which eSIMs unlock a handbook, Pro slots, purchases) are in <a href="{{ route('admin.handbooks.settings') }}">Handbook settings</a>.</p>
            <div style="display:grid;gap:10px;grid-template-columns:repeat(auto-fit,minmax(16rem,1fr))">
                <label class="hb-switch"><input type="checkbox" wire:model="isFeatured"><span><b>Feature on the landing page</b><small>Shown first on the Popular shelf.</small></span></label>
                <label class="hb-switch"><input type="checkbox" wire:model="allowPurchase"><span><b>Can be bought</b><small>Off hides the Buy button; unlocks still work.</small></span></label>
                <label class="hb-switch"><input type="checkbox" wire:model="unlockViaEsim"><span><b>Unlocked by a matching eSIM</b><small>Buying an eSIM for its country (or a regional one that covers it) unlocks it.</small></span></label>
                <label class="hb-switch"><input type="checkbox" wire:model="unlockViaPro"><span><b>Can be unlocked with a Pro slot</b><small>Naara Pro members may spend a free slot on it.</small></span></label>
            </div>
            <div style="margin-top:10px;max-width:26rem"><label class="{{ $lb }}" for="hb-extra">Also unlock for eSIMs covering these countries</label><input id="hb-extra" type="text" wire:model="extraCountries" class="{{ $in }}" placeholder="NG, KE (optional, 2-letter codes)">@error('extraCountries')<p class="ns-pg__fielderr">Use 2-letter country codes separated by commas.</p>@enderror</div>
        </div>

        <div class="{{ $card }}">
            <h2 class="ns-pg__h2">Categories and style</h2>
            <div style="display:flex;gap:8px;flex-wrap:wrap">@foreach ($categories as $c)<label class="hb-chip" style="cursor:pointer"><input type="checkbox" wire:model="categoryIds" value="{{ $c->id }}"> {{ $c->name }}</label>@endforeach</div>
            <div style="margin-top:8px;max-width:20rem"><label class="{{ $lb }}" for="hb-ncat">Add a category</label><input id="hb-ncat" type="text" wire:model="newCategory" class="{{ $in }}" placeholder="Student life"></div>
            <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:12px">@foreach ($moods as $m)<label class="hb-chip hb-chip--alt" style="cursor:pointer"><input type="checkbox" wire:model="moodIds" value="{{ $m->id }}"> {{ $m->name }}</label>@endforeach</div>
        </div>

        <div class="{{ $card }}">
            <h2 class="ns-pg__h2">Also sold on (optional)</h2>
            <p class="ns-pg__hint" style="margin:0 0 8px">A button only shows on the book page while it is switched on, set to Live, and has an https link.</p>
            @foreach (\App\Models\HandbookExternalLink::MARKETPLACES as $k => $label)
                <div style="display:grid;gap:8px;grid-template-columns:9rem 1fr auto auto;align-items:center;margin-bottom:8px">
                    <span class="{{ $lb }}" style="margin:0">{{ $label }}</span>
                    <input type="url" wire:model="links.{{ $k }}.url" class="{{ $in }}" placeholder="https://" aria-label="{{ $label }} link">
                    <select wire:model="links.{{ $k }}.availability" class="{{ $in }}" style="width:auto" aria-label="{{ $label }} availability"><option value="coming_soon">Coming soon</option><option value="live">Live</option></select>
                    <label style="display:flex;gap:6px;align-items:center;color:rgb(var(--nx-text))"><input type="checkbox" wire:model="links.{{ $k }}.enabled"> On</label>
                </div>
                @error('links.'.$k.'.url')<p class="ns-pg__fielderr">{{ $message }}</p>@enderror
            @endforeach
        </div>

        <div class="{{ $card }}">
            <h2 class="ns-pg__h2">Audiobook (sold separately)</h2>
            <div style="display:grid;gap:10px;grid-template-columns:repeat(auto-fit,minmax(12rem,1fr));align-items:end">
                <div><label class="{{ $lb }}" for="hb-ap">Price (USD)</label><input id="hb-ap" type="number" step="0.01" min="0" wire:model="audioPrice" class="{{ $in }}">@error('audioPrice')<p class="ns-pg__fielderr">{{ $message }}</p>@enderror</div>
                <label style="display:flex;gap:6px;align-items:center;color:rgb(var(--nx-text))"><input type="checkbox" wire:model="audioEnabled"> On sale</label>
                <label style="display:flex;gap:6px;align-items:center;color:rgb(var(--nx-text))"><input type="checkbox" wire:model="audioStream"> Streaming</label>
                <label style="display:flex;gap:6px;align-items:center;color:rgb(var(--nx-text))"><input type="checkbox" wire:model="audioDownload"> Allow download</label>
            </div>
        </div>

        <div style="display:flex;gap:8px;flex-wrap:wrap"><button type="submit" class="ns-cta ns-cta--pill" wire:loading.attr="disabled" wire:target="save">Save details</button></div>
    </form>

    @if ($b)
        <div class="{{ $card }}" style="margin-top:16px" data-handbook-files>
            <h2 class="ns-pg__h2">Files</h2>
            <p class="ns-pg__hint" style="margin:0 0 10px">Everything is stored privately. Covers must be a 2:3 or 3:4 portrait of at least {{ config('handbooks.cover_min_width') }}x{{ config('handbooks.cover_min_height') }} px.</p>
            <div style="display:grid;gap:14px">
                <div><label class="{{ $lb }}" for="hb-cover">Cover</label><div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">@if ($b->cover_path)<img src="{{ route('admin.handbooks.cover', $b->id) }}" alt="" width="48" height="72" style="border-radius:6px;object-fit:cover">@endif<input id="hb-cover" type="file" wire:model="coverUpload" accept="image/jpeg,image/png,image/webp"><button type="button" wire:click="uploadCover" wire:loading.attr="disabled" wire:target="uploadCover,coverUpload" class="ns-cta ns-cta--pill ns-cta--ghost ns-cta--sm">Upload cover</button></div></div>
                <div><label class="{{ $lb }}" for="hb-back">Back cover</label><div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">@if ($b->back_cover_path)<img src="{{ route('admin.handbooks.backcover', $b->id) }}" alt="" width="48" height="72" style="border-radius:6px;object-fit:cover">@endif<input id="hb-back" type="file" wire:model="backCoverUpload" accept="image/jpeg,image/png,image/webp"><button type="button" wire:click="uploadBackCover" wire:loading.attr="disabled" wire:target="uploadBackCover,backCoverUpload" class="ns-cta ns-cta--pill ns-cta--ghost ns-cta--sm">Upload back cover</button></div></div>
                <div><label class="{{ $lb }}" for="hb-epub">EPUB {{ $b->status === 'published' && $b->epub_path ? '(uploading a new file creates a new edition)' : '' }}</label><div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center"><span class="ns-pg__hint" style="margin:0">{{ $b->epub_path ? 'File on record, about '.($b->page_count ?: '?').' pages' : 'None yet' }}</span>@if ($b->epub_path)<a href="{{ route('admin.handbooks.download', [$b->id, 'epub']) }}" class="ns-cta ns-cta--pill ns-cta--ghost ns-cta--sm" data-hb-download="epub">Download EPUB</a>@endif<input id="hb-epub" type="file" wire:model="epubUpload" accept=".epub,application/epub+zip"><button type="button" wire:click="uploadEpub" wire:loading.attr="disabled" wire:target="uploadEpub,epubUpload" class="ns-cta ns-cta--pill ns-cta--ghost ns-cta--sm">Check and upload EPUB</button></div></div>
                <div><label class="{{ $lb }}" for="hb-pdf">PDF</label><div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center"><span class="ns-pg__hint" style="margin:0">{{ $b->pdf_path ? 'File on record' : 'None yet' }}</span>@if ($b->pdf_path)<a href="{{ route('admin.handbooks.download', [$b->id, 'pdf']) }}" class="ns-cta ns-cta--pill ns-cta--ghost ns-cta--sm" data-hb-download="pdf">Download PDF</a>@endif<input id="hb-pdf" type="file" wire:model="pdfUpload" accept="application/pdf"><button type="button" wire:click="uploadPdf" wire:loading.attr="disabled" wire:target="uploadPdf,pdfUpload" class="ns-cta ns-cta--pill ns-cta--ghost ns-cta--sm">Upload PDF</button></div></div>
            </div>
            @if (! empty($b->toc))
                <details style="margin-top:12px"><summary class="ns-pg__hint" style="cursor:pointer;margin:0"><b style="color:rgb(var(--nx-text))">Table of contents read from the EPUB</b> ({{ count($b->toc) }} entries)</summary><ol class="hb-toc" style="margin-top:8px">@foreach (array_slice($b->toc, 0, 80) as $t)<li style="margin-inline-start:{{ min(3, (int) $t['depth']) * 18 }}px">{{ $t['title'] }}</li>@endforeach</ol></details>
            @endif
        </div>

        @if ($pending->isNotEmpty())
            <div class="{{ $card }}" style="margin-top:16px" data-pending-edition>
                <h2 class="ns-pg__h2">New edition ready to publish</h2>
                <label class="{{ $lb }}" for="hb-notes">What changed (optional)</label><input id="hb-notes" type="text" wire:model="editionNotes" class="{{ $in }}">
                @foreach ($pending as $ed)<button type="button" wire:click="publishEdition({{ $ed->edition }})" wire:loading.attr="disabled" wire:target="publishEdition" wire:confirm="Publish edition {{ $ed->edition }}? Everyone who holds this book gets it free and is told." class="ns-cta ns-cta--pill" style="margin-top:10px">Publish edition {{ $ed->edition }}</button>@endforeach
            </div>
        @endif

        <div class="{{ $card }}" style="margin-top:16px">
            <h2 class="ns-pg__h2">Audiobook chapters</h2>
            <ul style="list-style:none;margin:0 0 10px;padding:0;display:grid;gap:6px">@foreach (($b->audiobook?->tracks ?? []) as $t)<li style="display:flex;justify-content:space-between;gap:8px;color:rgb(var(--nx-text))"><span>{{ $t->position }}. {{ $t->chapter_title }} <span class="ns-pg__hint">{{ $t->seconds ? floor($t->seconds / 60).' min' : '' }}</span></span><button type="button" wire:click="removeTrack({{ $t->id }})" class="ns-cta ns-cta--pill ns-cta--ghost ns-cta--sm">Remove</button></li>@endforeach</ul>
            <div style="display:grid;gap:8px;grid-template-columns:repeat(auto-fit,minmax(11rem,1fr));align-items:end">
                <div><label class="{{ $lb }}" for="hb-tt">Chapter title</label><input id="hb-tt" type="text" wire:model="trackTitle" class="{{ $in }}"></div>
                <div><label class="{{ $lb }}" for="hb-ts">Length (seconds)</label><input id="hb-ts" type="number" min="0" wire:model="trackSeconds" class="{{ $in }}"></div>
                <div><input type="file" wire:model="trackUpload" accept="audio/*" aria-label="Chapter audio file"></div>
                <button type="button" wire:click="addTrack" wire:loading.attr="disabled" wire:target="addTrack,trackUpload" class="ns-cta ns-cta--pill ns-cta--ghost">Add chapter</button>
            </div>
        </div>

        <div class="{{ $card }}" style="margin-top:16px" data-publish-checklist>
            <h2 class="ns-pg__h2">Publish</h2>
            @if ($checklist)<ul class="ns-pg__hint" style="margin:0 0 10px;padding-inline-start:18px">@foreach ($checklist as $p)<li>{{ $p }}</li>@endforeach</ul>@else<p class="ns-pg__hint" style="margin:0 0 10px">Everything needed is in place.</p>@endif
            @if ($problems && ! $checklist)<p class="ns-pg__fielderr">{{ implode(' ', $problems) }}</p>@endif
            <div style="display:flex;gap:8px;flex-wrap:wrap">
                @if ($b->status !== 'published')<button type="button" wire:click="publish" wire:loading.attr="disabled" wire:target="publish" class="ns-cta ns-cta--pill" @disabled($checklist)>Publish</button>@endif
                @if ($b->status !== 'archived')<button type="button" wire:click="archive" wire:confirm="Hide this handbook from the store? People who own it keep it." class="ns-cta ns-cta--pill ns-cta--ghost">Archive</button>@endif
            </div>
        </div>
    @endif
</x-nx.page>
</div>
