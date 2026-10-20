{{-- The artwork of the master-only Travel Handbooks slide: a deep-teal field with the real covers fanned on the right. Decorative; the copy and link live in esim-hero-copy. --}}
<div class="relative h-full w-full overflow-hidden bg-gradient-to-br from-[#0A6E6E] via-[#0B5560] to-[#0D1B2A]" aria-hidden="true">
    <span class="absolute -right-10 -top-12 h-56 w-56 rounded-full bg-[#F2C94C]/15 blur-2xl"></span>
    <span class="absolute right-[6%] top-1/2 flex -translate-y-1/2 items-end gap-0">
        @foreach ($hb['covers'] as $i => $b)
            <span class="relative block {{ $compact ? 'w-14' : 'w-24 lg:w-28' }} shadow-2xl" style="margin-left: {{ $i === 0 ? 0 : ($compact ? '-0.9rem' : '-1.6rem') }}; transform: rotate({{ [-9, 0, 9][$i] ?? 0 }}deg) translateY({{ [6, 0, 6][$i] ?? 0 }}px); z-index: {{ $i }}"><x-handbook-cover :book="$b" /></span>
        @endforeach
    </span>
</div>
