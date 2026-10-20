{{-- nx:converted --}}
{{-- Manuscript studio (MASTER-ONLY): upload, preview on three screen sizes, fit pictures in one batch, save. Skin tokens only. --}}
<div data-manuscript-studio>
@php($card = 'ns-pg__card ns-ring')
@php($hint = 'ns-pg__hint')
@php($widths = ['phone' => 390, 'tablet' => 768, 'desktop' => 1100])
<x-nx.page class="ns-pg ns-pg--wide">
    <div class="ns-pg__head">
        <div>
            <a href="{{ route('admin.handbooks.edit', $book->id) }}" class="ns-pg__back"><x-nx.icon name="left" /> {{ $book->title }}</a>
            <h1 class="ns-h1" style="margin-top:6px">Manuscript studio</h1>
            <p class="ns-sub" style="margin:4px 0 0">Upload the manuscript, check how it reads on every screen, fit big pictures in one go, then save. Nothing goes live until you save.</p>
        </div>
        @if ($book->epub_path)<a href="{{ route('admin.handbooks.preview', $book->id) }}" target="_blank" class="ns-cta ns-cta--pill ns-cta--ghost">Open the saved book in the reader</a>@endif
    </div>

    @if ($flash)<div class="{{ $card }}" role="status" style="margin-top:12px">{{ $flash }}</div>@endif
    @foreach ($problems as $p)<div class="{{ $card }}" role="alert" style="margin-top:8px"><span class="ns-pg__fielderr">{{ $p }}</span></div>@endforeach
    @error('manuscript')<div class="{{ $card }}" role="alert" style="margin-top:8px"><span class="ns-pg__fielderr">{{ $message }}</span></div>@enderror

    {{-- Step 1: bring the manuscript in --}}
    <div class="{{ $card }}" style="margin-top:16px" data-step="upload">
        <h2 class="ns-pg__h2">1. Manuscript</h2>
        <p class="{{ $hint }}" style="margin:0 0 10px">An EPUB, or a Word (.docx) file with its pictures. A Word file is turned into an EPUB: a new chapter starts at every top-level heading.</p>
        <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">
            <input type="file" wire:model="manuscript" accept=".epub,.docx,application/epub+zip,application/vnd.openxmlformats-officedocument.wordprocessingml.document" aria-label="Manuscript file">
            @if ($book->epub_path && ! $token)<button type="button" wire:click="useCurrent" wire:loading.attr="disabled" class="ns-cta ns-cta--pill ns-cta--ghost ns-cta--sm">Work on the saved manuscript</button>@endif
            @if ($token)<button type="button" wire:click="discard" class="ns-cta ns-cta--pill ns-cta--ghost ns-cta--sm">Discard this upload</button>@endif
            <span wire:loading wire:target="manuscript,useCurrent" class="{{ $hint }}" style="margin:0">Reading the file...</span>
        </div>
    </div>

    @if ($token && $check)
        {{-- What the checklist found --}}
        <div class="{{ $card }}" style="margin-top:16px" data-step="report">
            <h2 class="ns-pg__h2">2. What we found</h2>
            <div style="display:flex;gap:18px;flex-wrap:wrap;margin:6px 0 10px">
                <span><b style="font-size:20px;color:rgb(var(--nx-text))">{{ count($chapters) }}</b> <span class="{{ $hint }}">sections</span></span>
                <span><b style="font-size:20px;color:rgb(var(--nx-text))">{{ number_format($words) }}</b> <span class="{{ $hint }}">words (about {{ $check['pages'] }} pages)</span></span>
                <span><b style="font-size:20px;color:rgb(var(--nx-text))">{{ count($images) }}</b> <span class="{{ $hint }}">pictures</span></span>
                <span><b style="font-size:20px;color:rgb(var(--nx-text))">{{ number_format($staged / 1048576, 2) }} MB</b> <span class="{{ $hint }}">file size</span></span>
            </div>
            @foreach ($check['errors'] as $e)<p class="ns-pg__fielderr" style="margin:0 0 4px">{{ $e }}</p>@endforeach
            @foreach ($check['warnings'] as $w)<p class="{{ $hint }}" style="margin:0 0 4px">Note: {{ $w }}</p>@endforeach
            @if ($check['ok'] && ! $check['warnings'])<p class="{{ $hint }}" style="margin:0;color:rgb(var(--nx-ok))">Passes the store's checklist.</p>@endif
        </div>

        {{-- Preview on three screens --}}
        <div class="{{ $card }}" style="margin-top:16px" data-step="preview">
            <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;justify-content:space-between">
                <h2 class="ns-pg__h2" style="margin:0">3. Preview</h2>
                <div class="ns-pg__chips" role="group" aria-label="Screen size">
                    @foreach ($widths as $d => $w)<button type="button" wire:click="$set('device', '{{ $d }}')" class="ns-pg__chip {{ $device === $d ? 'is-on' : '' }}" aria-pressed="{{ $device === $d ? 'true' : 'false' }}" data-device="{{ $d }}">{{ ucfirst($d) }} {{ $w }}px</button>@endforeach
                </div>
            </div>
            <div style="display:grid;gap:14px;grid-template-columns:minmax(0,14rem) minmax(0,1fr);margin-top:12px" class="ms-grid">
                <nav aria-label="Sections" style="max-height:34rem;overflow:auto;border-radius:12px;border:1px solid rgb(var(--nx-line));padding:6px" data-chapters>
                    @foreach ($chapters as $c)
                        <button type="button" wire:click="$set('chapter', {{ $c['index'] }})" wire:key="ch-{{ $c['index'] }}" aria-current="{{ $chapterIndex === $c['index'] ? 'true' : 'false' }}"
                                style="display:block;width:100%;text-align:left;padding:7px 9px;border-radius:9px;border:0;cursor:pointer;font-size:13px;background:{{ $chapterIndex === $c['index'] ? 'rgb(var(--nx-teal)/0.16)' : 'transparent' }};color:rgb(var(--nx-text))">
                            <span style="display:block;font-weight:600;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ $c['title'] }}</span><small style="color:rgb(var(--nx-text-2))">{{ number_format($c['words']) }} words</small>
                        </button>
                    @endforeach
                </nav>
                <div style="min-width:0;overflow:auto;display:flex;justify-content:center;background:rgb(var(--nx-surface-2));border-radius:14px;padding:14px">
                    <iframe title="Preview of the selected section" src="{{ route('admin.handbooks.manuscript.chapter', ['token' => $token, 'n' => $chapterIndex]) }}?v={{ md5($staged.$chapterIndex) }}" sandbox="" referrerpolicy="no-referrer" data-preview-frame
                            style="width:{{ $widths[$device] }}px;max-width:100%;height:34rem;border:0;border-radius:12px;background:#fff;box-shadow:0 8px 24px -12px rgb(0 0 0/.5)"></iframe>
                </div>
            </div>
        </div>

        {{-- Pictures --}}
        <div class="{{ $card }}" style="margin-top:16px" data-step="images">
            <h2 class="ns-pg__h2">4. Pictures</h2>
            @if ($images === [])
                <p class="{{ $hint }}">This manuscript has no pictures to fit.</p>
            @else
                <div style="display:grid;gap:10px;grid-template-columns:repeat(auto-fit,minmax(10rem,1fr));max-width:42rem">
                    <div><label class="{{ $hint }}" for="ms-w">Widest picture (px)</label><input id="ms-w" type="number" min="480" max="4000" wire:model.live.debounce.500ms="maxWidth" class="ns-input"></div>
                    <div><label class="{{ $hint }}" for="ms-q">Quality (50 to 95)</label><input id="ms-q" type="number" min="50" max="95" wire:model="quality" class="ns-input"></div>
                    <div><label class="{{ $hint }}" for="ms-k">Flag when heavier than (KB)</label><input id="ms-k" type="number" min="50" max="8000" wire:model.live.debounce.500ms="flagKb" class="ns-input"></div>
                </div>
                <p class="{{ $hint }}" style="margin:10px 0 6px"><b data-flagged-count style="color:{{ $flagged ? 'rgb(var(--nx-warn))' : 'rgb(var(--nx-ok))' }}">{{ $flagged }}</b> of {{ count($images) }} pictures are too big for these limits.</p>
                <div style="overflow-x:auto"><table style="width:100%;border-collapse:collapse;font-size:13px">
                    <thead><tr style="text-align:left;color:rgb(var(--nx-text-2))"><th style="padding:6px">Fit</th><th>Picture</th><th>Size</th><th>Weight</th><th>Status</th></tr></thead>
                    <tbody>@foreach ($images as $img)
                        @php($big = $img['width'] > $maxWidth || $img['bytes'] > $flagKb * 1024)
                        <tr style="border-top:1px solid rgb(var(--nx-line))" wire:key="img-{{ md5($img['href']) }}" data-image-row>
                            <td style="padding:6px"><input type="checkbox" wire:model="pick.{{ $img['href'] }}" aria-label="Fit {{ $img['href'] }}"></td>
                            <td style="max-width:16rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="{{ $img['href'] }}">{{ basename($img['href']) }}</td>
                            <td>{{ $img['width'] }} x {{ $img['height'] }}</td>
                            <td>{{ $img['bytes'] >= 1048576 ? number_format($img['bytes'] / 1048576, 2).' MB' : number_format($img['bytes'] / 1024).' KB' }}</td>
                            <td><span style="color:{{ $big ? 'rgb(var(--nx-warn))' : 'rgb(var(--nx-ok))' }};font-weight:600">{{ $big ? 'Too big' : 'Fits' }}</span></td>
                        </tr>@endforeach</tbody>
                </table></div>
                <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:12px">
                    <button type="button" wire:click="selectFlagged" class="ns-cta ns-cta--pill ns-cta--ghost ns-cta--sm">Tick only the too-big ones</button>
                    <button type="button" wire:click="fitImages" wire:loading.attr="disabled" wire:target="fitImages" class="ns-cta ns-cta--pill"><span wire:loading.remove wire:target="fitImages">Fit the ticked pictures</span><span wire:loading wire:target="fitImages">Fitting...</span></button>
                </div>
                @if ($fitted)<p class="{{ $hint }}" style="margin:10px 0 0;color:rgb(var(--nx-ok))" data-fit-result>Fitted {{ $fitted['changed'] }} of {{ $fitted['total'] }}, saved {{ number_format($fitted['saved'] / 1048576, 2) }} MB.</p>@endif
            @endif
        </div>

        {{-- Save --}}
        <div class="{{ $card }}" style="margin-top:16px" data-step="save">
            <h2 class="ns-pg__h2">5. Save</h2>
            <p class="{{ $hint }}" style="margin:0 0 10px">{{ $book->status === 'published' && $book->epub_path ? 'This handbook is published: saving creates a new edition that you publish when ready.' : 'Saving puts this manuscript on the handbook, ready for the checklist and publishing.' }}</p>
            <button type="button" wire:click="saveManuscript" wire:loading.attr="disabled" wire:target="saveManuscript" @disabled(! $check['ok']) class="ns-cta ns-cta--pill"><span wire:loading.remove wire:target="saveManuscript">Save as the manuscript</span><span wire:loading wire:target="saveManuscript">Saving...</span></button>
        </div>
    @endif

    {{-- Offline PDF --}}
    <div class="{{ $card }}" style="margin-top:16px" data-step="pdf">
        <h2 class="ns-pg__h2">Offline PDF</h2>
        <p class="{{ $hint }}" style="margin:0 0 10px">The print-ready PDF readers download. {{ $book->pdf_path ? 'A PDF is on record. Uploading a new one replaces it.' : 'None yet.' }}</p>
        <input type="file" wire:model="pdfUpload" accept="application/pdf" aria-label="PDF file"><span wire:loading wire:target="pdfUpload" class="{{ $hint }}" style="margin-inline-start:8px">Uploading...</span>
        @error('pdfUpload')<p class="ns-pg__fielderr">{{ $message }}</p>@enderror
    </div>
</x-nx.page>
<style>@media (max-width: 720px) { .ms-grid { grid-template-columns: minmax(0, 1fr) !important; } }</style>
</div>
