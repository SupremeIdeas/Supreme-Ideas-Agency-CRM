{{-- nx:converted --}}
{{-- Handbook reader (MASTER-ONLY): distraction-free, chapter drawer, bookmarks, text size and themes. Skin tokens plus the reader's own paper colours (set by script). --}}
<x-layouts.app :title="$b->title.' — Reader'" page-type="app">
<div class="hbr" data-reader data-cfg='@json($cfg)' data-theme="light">
    <header class="hbr__bar">
        <a href="{{ route('handbooks.library') }}" class="hbr__btn" aria-label="Back to Library"><x-nx.icon name="left" /></a>
        <span class="hbr__title">{{ $b->title }}</span>
        <span class="hbr__pct" data-pct aria-live="polite"></span>
        <button type="button" class="hbr__btn" data-act="listen" aria-label="Read aloud" data-tour-listen><x-nx.icon name="volume" /></button>
        <button type="button" class="hbr__btn" data-act="bookmark" aria-label="Add bookmark"><x-nx.icon name="star" /></button>
        <button type="button" class="hbr__btn" data-act="marks" aria-label="Bookmarks"><x-nx.icon name="list" /></button>
        <button type="button" class="hbr__btn" data-act="toc" aria-label="Chapters"><x-nx.icon name="menu" /></button>
        <button type="button" class="hbr__btn" data-act="settings" aria-label="Reading settings"><x-nx.icon name="settings" /></button>
    </header>
    <p class="hbr__status" data-status role="status" aria-live="polite"></p>
    <div class="hbr__stage" data-stage></div>
    <section class="hbr__listen" data-listen hidden aria-label="Read aloud">
        <p class="hbr__now" data-tts-now aria-live="polite">Press play to hear this page.</p>
        <div class="hbr__lrow">
            <button type="button" class="hbr__lbtn" data-tts-prev aria-label="Previous sentence"><svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor" aria-hidden="true"><path d="M6 5h2v14H6zM20 5.5v13a1 1 0 0 1-1.5.86L9 13.2a1 1 0 0 1 0-1.72l9.5-6.16A1 1 0 0 1 20 5.5z"/></svg></button>
            <button type="button" class="hbr__lbtn hbr__lbtn--main" data-tts-play aria-label="Read aloud"></button>
            <button type="button" class="hbr__lbtn" data-tts-next aria-label="Next sentence"><svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor" aria-hidden="true"><path d="M16 5h2v14h-2zM4 5.5v13a1 1 0 0 0 1.5.86l9.5-6.16a1 1 0 0 0 0-1.72L5.5 4.64A1 1 0 0 0 4 5.5z"/></svg></button>
            <button type="button" class="hbr__lchip" data-tts-rate aria-label="Reading speed">1x</button>
            <button type="button" class="hbr__lchip" data-tts-follow aria-pressed="true" aria-label="Follow along">Follow</button>
            <button type="button" class="hbr__lchip" data-tts-sleep data-minutes="0">Sleep timer off</button>
            <button type="button" class="hbr__lchip" data-tts-close aria-label="Close the player">Close</button>
        </div>
        <label class="hbr__lvoice"><span>Voice</span><select data-tts-voice aria-label="Voice"></select></label>
        <p class="hbr__note" data-tts-novoice hidden>No voice for this book's language is installed on this device. Add one in your device's text-to-speech settings.</p>
    </section>
    <footer class="hbr__foot" data-foot>
        <span class="hbr__chapter" data-chapter></span>
        <input type="range" min="0" max="100" value="0" step="1" disabled class="hbr__scrub" data-scrub aria-label="Jump to a place in the book">
    </footer>
    <button type="button" class="hbr__nav hbr__nav--prev" data-act="prev" aria-label="Previous page"><x-nx.icon name="chevron-left" /></button>
    <button type="button" class="hbr__nav hbr__nav--next" data-act="next" aria-label="Next page"><x-nx.icon name="chevron-right" /></button>

    <aside class="hbr__panel" data-panel="toc" hidden aria-label="Jump to a section">
        <h2>Jump to a section</h2>
        <label class="sr-only" for="hbr-tf">Search sections</label>
        <input id="hbr-tf" type="search" class="hbr__search" data-toc-filter placeholder="Search sections" autocomplete="off">
        <ul data-toc></ul>
    </aside>
    <aside class="hbr__panel" data-panel="marks" hidden aria-label="Bookmarks"><h2>Bookmarks</h2><ul data-bookmarks></ul></aside>
    <aside class="hbr__panel" data-panel="settings" hidden aria-label="Reading settings">
        <h2>Reading</h2>
        <div class="hbr__row"><span>Text size</span><span><button type="button" data-act="smaller" aria-label="Smaller text">A-</button><button type="button" data-act="bigger" aria-label="Bigger text">A+</button></span></div>
        <div class="hbr__row"><span>Layout</span><span><button type="button" data-act="pref" data-pref="flow" data-value="paginated">Pages</button><button type="button" data-act="pref" data-pref="flow" data-value="scroll">Scroll</button></span></div>
        <div class="hbr__row"><span>Font</span><span><button type="button" data-act="pref" data-pref="font" data-value="serif">Serif</button><button type="button" data-act="pref" data-pref="font" data-value="sans">Sans</button></span></div>
        <div class="hbr__row"><span>Line spacing</span><span><button type="button" data-act="pref" data-pref="spacing" data-value="compact">Tight</button><button type="button" data-act="pref" data-pref="spacing" data-value="normal">Normal</button><button type="button" data-act="pref" data-pref="spacing" data-value="relaxed">Airy</button></span></div>
        <div class="hbr__row"><span>Margins</span><span><button type="button" data-act="pref" data-pref="margin" data-value="narrow">Narrow</button><button type="button" data-act="pref" data-pref="margin" data-value="normal">Normal</button><button type="button" data-act="pref" data-pref="margin" data-value="wide">Wide</button></span></div>
        <div class="hbr__row"><span>Theme</span><span>@foreach (['auto' => 'Auto', 'light' => 'Light', 'dark' => 'Dark', 'sepia' => 'Sepia'] as $v => $l)<button type="button" data-act="theme" data-value="{{ $v }}">{{ $l }}</button>@endforeach</span></div>
        <div class="hbr__row"><span>Offline copy</span><button type="button" data-act="forget">Remove from this device</button></div>
        <p class="hbr__note">Your place is saved and follows you to your other devices. @unless ($canDownload)This handbook is unlocked for reading; upgrade from its page to download it.@endunless</p>
    </aside>
    <div class="hbr__lightbox" data-lightbox hidden tabindex="-1" role="dialog" aria-label="Picture"><img alt=""><button type="button" class="hbr__lbx" aria-label="Close picture" onclick="this.parentNode.hidden=true"><x-nx.icon name="x" /></button></div>
</div>
@vite('resources/js/handbooks/reader.js')
</x-layouts.app>
