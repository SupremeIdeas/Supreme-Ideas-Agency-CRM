{{-- Generic renderer for a brand-new page composed entirely from the Section
     Builder (owner request, 2026-09-21: "all sections in marketing site can
     be reused imported... reused in new page sections... repurposed with
     new context and arrangements... when we want to use Claude to just
     generate the page, it learned from our sections to build our page from
     templates"). An admin (or Claude, on the admin's behalf) types any new
     slug into the Page Builder, adds/arranges typed sections
     (hero/two-column/bento/carousel/storytelling/testimonial/quote/FAQ/
     video/code/logo-showcase/custom-HTML — see App\Support\SectionLibrary),
     publishes, and it goes live here with zero new blade files or routes —
     the same seam every "starter" page (home/about/how-it-works/contact)
     already renders through via partials.sections.render. --}}
<x-layouts.marketing :title="$title">
    @include('partials.sections.render', ['sections' => $sections])
</x-layouts.marketing>
