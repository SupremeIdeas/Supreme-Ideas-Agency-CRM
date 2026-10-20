{{-- "Who Naara Is For" — auto time-based persona tab/slide switcher (owner
     request, 2026-09-21, repeated twice for emphasis). Now built on the
     reusable <x-timed-tab-switcher> (owner request, 2026-09-21: "reuse that
     our time based tabs switcher container") — see that component and
     resources/js/timed-tab-switcher.js for the preserved technique
     (progress-fill timer, "Premium Glass" 30px image card, floating glow
     blobs). Sits inside the hero (owner: "just put it in our hero"), so it
     renders on the same WebGL/no-override branch as the rest of the hero's
     device-group visuals — always with real, on-brand persona photography,
     never a placeholder. --}}
@php
    // Persona copy + photos live in the homepage hero's Site Editor fields
    // (who_{n}_title / _desc / _image) — seeded defaults in SiteContent.
    $whoForPersonas = [];
    foreach (range(1, 6) as $n) {
        $t = trim((string) ($s["who_{$n}_title"] ?? ''));
        if ($t === '') {
            continue; // an admin who blanks a title... gets the default back (page() semantics), so this only skips unseeded slots
        }
        $whoForPersonas[] = [
            'title' => $t,
            'desc' => (string) ($s["who_{$n}_desc"] ?? ''),
            'image' => \App\Support\SiteContent::imageUrl($s["who_{$n}_image"] ?? ''),
        ];
    }
@endphp
<div data-reveal class="relative z-10 mx-auto mt-8 max-w-6xl px-4 pb-4">
    <x-timed-tab-switcher
        :items="$whoForPersonas"
        tone="dark"
        id="who-for"
        :eyebrow="$s['who_eyebrow'] ?? ''"
        :heading="$s['who_heading'] ?? ''"
        :subtext="$s['who_subtext'] ?? ''"
    />
</div>
