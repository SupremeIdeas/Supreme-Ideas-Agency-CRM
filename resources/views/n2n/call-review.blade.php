{{-- nx:converted --}}
{{-- After-call rating sheet (MASTER-ONLY). Filled by resources/js/n2n/call.js, only when the admin has switched it on and a Trustpilot profile is set. Step 1: stars. Step 2: an invitation to Trustpilot offered to everyone, whatever they rated. Skin tokens only. --}}
<div class="ncr" data-call-review hidden role="dialog" aria-modal="true" aria-labelledby="ncr-title">
    <div class="ncr__scrim" data-review-scrim></div>
    <div class="ncr__sheet">
        <div data-review-step="rate">
            <div class="ncr__head"><button type="button" class="ncr__x" data-review-close aria-label="Close"><x-nx.icon name="x" /></button><h2 id="ncr-title">How was your call?</h2></div>
            <p class="ncr__sub">Your rating helps us make Naara calls better.</p>
            <div class="ncr__stars" role="radiogroup" aria-label="Rate your call from 1 to 5 stars">
                @foreach ([1, 2, 3, 4, 5] as $n)
                    <button type="button" class="ncr__star" role="radio" aria-checked="false" aria-label="{{ $n }} {{ $n === 1 ? 'star' : 'stars' }}" data-star="{{ $n }}"><x-nx.icon name="star" /></button>
                @endforeach
            </div>
            <div class="ncr__row"><button type="button" class="ncr__quiet" data-review-close>Not now</button><button type="button" class="ncr__go" data-review-submit disabled>Submit</button></div>
        </div>
        <div data-review-step="thanks" hidden>
            <div class="ncr__head"><button type="button" class="ncr__x" data-review-close aria-label="Close"><x-nx.icon name="x" /></button><h2>Thank you</h2></div>
            <p class="ncr__sub">Would you share your experience on Trustpilot? It takes a minute, it is public, and it helps other travellers decide.</p>
            <a class="ncr__tp" data-review-link href="#" target="_blank" rel="noopener noreferrer"><x-nx.icon name="star" /> Write a review on Trustpilot</a>
            <a class="ncr__help" data-review-support href="#" hidden wire:navigate>Something not right? Tell our team</a>
            <div class="ncr__row"><button type="button" class="ncr__quiet" data-review-close>No thanks</button></div>
        </div>
    </div>
</div>
