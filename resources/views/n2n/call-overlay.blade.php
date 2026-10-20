{{-- nx:converted --}}
{{-- N2N call screen (MASTER-ONLY): one overlay for outgoing, incoming and active calls, voice or video. Filled by resources/js/n2n/call.js. Skin tokens for the voice card; the video screen is media chrome (dark glass over the picture, fixed colours so it reads on any video). --}}
<div id="n2n-call" class="nc-call" hidden role="dialog" aria-modal="true" aria-label="Call" data-phase="idle">
    {{-- ============ Video screen ============ --}}
    <div class="ncv" data-call-stage hidden>
        <div class="ncv__tile ncv__tile--main" data-tile="remote" data-tile-remote>
            <video data-call-remote playsinline autoplay muted></video>
            <div class="ncv__away" data-call-away hidden><span class="nc-call__avatar" aria-hidden="true"><x-nx.icon name="user" /></span><p data-call-away-text>Camera is off</p></div>
        </div>
        <div class="ncv__tile ncv__tile--pip" data-tile="self" data-call-pip role="button" tabindex="0" aria-label="Swap the two pictures">
            <video data-call-local playsinline autoplay muted></video>
            <span class="ncv__pipoff" data-call-pipoff hidden><x-nx.icon name="video-off" /></span>
            {{-- Your picture's own tools: they sit on your tile, and move to the right edge when your picture is the big one. --}}
            <div class="ncv__tools">
                <button type="button" class="ncv__round" data-call-flip aria-label="Flip camera" hidden><x-nx.icon name="switch-camera" /></button>
                <button type="button" class="ncv__round" data-call-beauty aria-label="Beauty looks" aria-expanded="false" aria-controls="n2n-beauty" hidden><x-nx.icon name="wand" /></button>
            </div>
        </div>

        <div class="ncv__hero" data-call-hero aria-hidden="true"><x-nx.avatar name="" :size="200" data-call-avatar /></div>
        <div class="ncv__top">
            <button type="button" class="ncv__round" data-call-minimize aria-label="Minimise the call"><x-nx.icon name="minimize" /></button>
            <span class="ncv__q" data-call-qicon hidden title="Connection quality" aria-label="Connection quality"><i></i><i></i><i></i></span>
            <span class="ncv__title"><b data-call-vname></b><span data-call-vclock aria-hidden="true">0:00</span><small data-call-vquality></small></span>
            <span class="ncv__lock" data-call-lock hidden title="This call is encrypted in transit" aria-label="This call is encrypted in transit"><x-nx.icon name="lock" /></span>
            <span class="ncv__spacer" data-call-spacer aria-hidden="true"></span>
        </div>
        <p class="ncv__status" data-call-vstatus role="status" aria-live="polite"></p>
        <p class="ncv__warn" data-call-vwarn role="alert" hidden></p>
        <div class="ncv__float" data-call-float aria-live="polite"></div>
        <button type="button" class="ncv__mini-end" data-call-hangup-mini aria-label="End the call"><x-nx.icon name="phone-end" /></button>
    </div>

    {{-- ============ Voice card / ringing card (also the "calling" and "connecting" card on video) ============ --}}
    <div class="nc-call__card" data-call-card>
        <span class="nc-call__avatar" aria-hidden="true"><x-nx.icon name="phone" /></span>
        <h2 data-call-name class="nc-call__name"></h2>
        <p data-call-status class="nc-call__status" role="status" aria-live="polite"></p>
        <p data-call-clock class="nc-call__clock" aria-hidden="true"></p>
        <p data-call-quality class="nc-call__quality"></p>
        <p data-call-warn class="nc-call__warn" role="alert" hidden></p>
        <div data-call-actions class="nc-call__links" hidden>
            <a data-call-buy href="#" wire:navigate class="ns-cta ns-cta--pill ns-cta--sm">Buy credit</a>
            <a data-call-ask href="#" wire:navigate class="nn__iconbtn" style="width:auto;padding:0 14px;border-radius:99px">Ask a friend</a>
        </div>
    </div>

    {{-- ============ Controls: the pill bar on video, the button row on voice ============ --}}
    <div class="nc-call__row" data-call-controls>
        <button type="button" data-call-decline class="nc-call__btn nc-call__btn--bad" hidden><x-nx.icon name="x" /><span>Decline</span></button>
        <button type="button" data-call-answer-audio class="nc-call__btn" hidden><x-nx.icon name="phone" /><span>Audio only</span></button>
        <button type="button" data-call-answer class="nc-call__btn nc-call__btn--ok ns-cta" hidden><x-nx.icon name="phone" /><span>Answer</span></button>
        <button type="button" data-call-more class="nc-call__btn" aria-label="More" aria-haspopup="true" aria-expanded="false" hidden><x-nx.icon name="ellipsis" /><span>More</span></button>
        <button type="button" data-call-cam class="nc-call__btn ncv-only" aria-pressed="false" aria-label="Camera" hidden><x-nx.icon name="video" /><span>Camera</span></button>
        <button type="button" data-call-out class="nc-call__btn" aria-label="Speaker" hidden><x-nx.icon name="volume" /><span>Speaker</span></button>
        <button type="button" data-call-mute class="nc-call__btn" aria-pressed="false" aria-label="Microphone" hidden><x-nx.icon name="mic" /><span>Mute</span></button>
        <button type="button" data-call-hangup class="nc-call__btn nc-call__btn--bad" aria-label="End the call" hidden><x-nx.icon name="phone" /><span>End</span></button>
    </div>
    <p class="ncv__hint" data-call-hint hidden role="status"><span>Share your screen with others</span><button type="button" data-call-hint-x aria-label="Dismiss"><x-nx.icon name="x" /></button></p>

    {{-- More menu: reactions and the extras. --}}
    <div class="ncv__more" data-more-sheet hidden>
        {{-- The six reactions are drawn by call.js (they are the person's own expression, not interface icons). --}}
        <div class="ncv__pill" data-react-list role="group" aria-label="Send a reaction"></div>
        <div class="ncv__menu" role="menu">
            <button type="button" role="menuitem" data-call-share><span class="ncv__round" aria-hidden="true"><x-nx.icon name="monitor-up" /></span><b data-call-share-label>Share screen</b></button>
            <button type="button" role="menuitem" data-call-msg><span class="ncv__round" aria-hidden="true"><x-nx.icon name="message-square-text" /></span><b>Send message</b></button>
        </div>
    </div>

    {{-- Beauty looks. Hidden until the person asks for it; "No filter" is the default; the choice is remembered on this device only. --}}
    <div class="nc-beauty" id="n2n-beauty" data-beauty-sheet hidden role="group" aria-label="Beauty looks">
        <div class="nc-beauty__head"><b>Beauty</b><button type="button" class="nc-beauty__x" data-beauty-close aria-label="Close beauty looks"><x-nx.icon name="x" /></button></div>
        <div class="nc-beauty__list" data-beauty-list role="radiogroup" aria-label="Look"></div>
        <label class="nc-beauty__slider" data-beauty-slider hidden><span>Intensity</span><input type="range" min="10" max="100" value="70" data-beauty-intensity aria-label="Filter intensity"></label>
        <p class="nc-beauty__note">Runs on your phone only. Nothing is uploaded and nobody else can change it.</p>
    </div>
</div>
@include('n2n.call-review')
