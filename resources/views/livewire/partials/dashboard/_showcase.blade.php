{{-- First-visit value showcase (Theme Batch 2 §2): one spaced card per product, only for a not-yet-active account. Same routes as before.
     Each card's image + opacity + size come from Admin → Bento icons (BentoIcons), keyed by the product's key. --}}
@unless ($hasAny)
    {{-- DISCONNECTED (CRM Phase 1 fork-and-strip): every row here markets
         eSIM/Numbers/Naara Gift — none of them Supreme Ideas CRM products.
         Emptied rather than left pointing at unrouted/off-catalog pages.
         See SupremeIdeasCRM_Phase1_ForkAndStrip.md. --}}
    @php($rows = [])

    <div class="ns-showcase-grid">
        @foreach ($rows as [$bkey, $icon, $title, $badge, $text, $url, $cta])
            <x-nx.showcase :bkey="$bkey" :href="$url" :icon="$icon" :title="$title" :badge="$badge" :text="$text" :cta="$cta" />
        @endforeach
    </div>
@endunless
