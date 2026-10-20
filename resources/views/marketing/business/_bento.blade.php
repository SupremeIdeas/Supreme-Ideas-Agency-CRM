{{-- Problem / solution bento for the NaaraBusiness Suite page. Same structure, styles (.nx-bento-*) and script (bento-diagnosis.js, hooked by data-bento-diagnosis) as the homepage Diagnosis block, with Suite copy. $s = the `problem` section. --}}
@php
    $modals = [];
    foreach (['p1', 'p2', 'p3', 'p4', 's1', 's2', 's3', 's4'] as $id) {
        $modals[$id] = [
            'eyebrow' => (string) ($s["m_{$id}_eyebrow"] ?? ''), 'headline' => (string) ($s["m_{$id}_headline"] ?? ''),
            'body' => strip_tags((string) ($s["m_{$id}_body"] ?? ''), '<p><strong><em><br><ul><ol><li>'), 'quote' => (string) ($s["m_{$id}_quote"] ?? ''),
            'bullets' => array_values(array_filter(array_map('trim', preg_split('/\R/', (string) ($s["m_{$id}_bullets"] ?? '')) ?: []))),
        ];
    }
    $blocks = [['p', 'eyebrow', 'headline', 'headline_accent', 'intro_1', 'intro_2'], ['s', 'sol_eyebrow', 'sol_headline', 'sol_headline_accent', 'sol_intro_1', 'sol_intro_2']];
@endphp
<div data-bento-diagnosis>
@foreach ($blocks as [$p, $eb, $hl, $ac, $i1, $i2])
<section class="nx-bento-wrap">
    <div class="nx-bento-inner">
        <p class="nx-bento-eyebrow">{{ $s[$eb] ?? '' }}</p>
        <h2 class="nx-bento-headline">{{ $s[$hl] ?? '' }}<br><span class="grad">{{ $s[$ac] ?? '' }}</span></h2>
        <div class="nx-bento-intro"><p>{{ $s[$i1] ?? '' }}</p><p>{{ $s[$i2] ?? '' }}</p></div>
        <div class="nx-bento-grid">
            @foreach ([1, 2, 3, 4] as $n)
                @php $id = $p.$n @endphp
                <div class="nx-bento-cell is-span-{{ (int) ($s[$id.'_span'] ?? 6) }}" data-cell="{{ $id }}" role="button" tabindex="0">
                    <div class="nx-bento-cell-content">
                        <span class="nx-bento-eyebrow-sm">{{ $s[$id.'_label'] ?? '' }}</span>
                        <h3 class="nx-bento-cell-title">{{ $s[$id.'_title'] ?? '' }}</h3>
                        <p class="nx-bento-cell-text">{{ $s[$id.'_text'] ?? '' }}</p>
                    </div>
                    <div class="nx-bento-art" aria-hidden="true">@include('marketing.business._art', ['id' => $id])</div>
                </div>
            @endforeach
        </div>
    </div>
</section>
@endforeach

<div class="nx-bento-modal" data-bento-modal aria-hidden="true">
    <div class="nx-bento-modal__panel" role="dialog" aria-modal="true" aria-labelledby="nx-bento-modal-headline">
        <div class="nx-bento-modal__actions">
            <button type="button" class="nx-bento-modal__btn" data-bento-theme aria-label="Toggle light or dark" aria-pressed="false"><x-icon name="moon" class="h-5 w-5" /></button>
            <button type="button" class="nx-bento-modal__btn" data-bento-close aria-label="Close"><x-icon name="x" class="h-5 w-5" /></button>
        </div>
        <div class="nx-bento-modal__interior">
            <div class="nx-bento-modal__copy">
                <p class="nx-bento-modal__eyebrow" data-m-eyebrow></p>
                <h3 class="nx-bento-modal__headline" id="nx-bento-modal-headline" data-m-headline></h3>
                <div class="nx-bento-modal__body" data-m-body></div>
                <p class="nx-bento-modal__quote" data-m-quote hidden></p>
                <ul class="nx-bento-modal__bullets" data-m-bullets></ul>
            </div>
            <div class="nx-bento-modal__art" data-m-art aria-hidden="true"></div>
        </div>
    </div>
    <script type="application/json" data-bento-modal-data>@json($modals)</script>
</div>
</div>
