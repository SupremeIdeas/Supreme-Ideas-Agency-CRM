{{-- The Naara Family — dedicated page (owner request, 2026-09-21: "this
     section in home please move it to another page"). Reuses the homepage's
     own "What Naara Gives You" section verbatim via @include — same
     SiteContent key (home.products), same admin-managed ProductLineSettings
     carousel — proving marketing sections can be composed onto a new page
     with a new arrangement instead of duplicated. --}}
<x-layouts.marketing :title="\App\Support\BrandSettings::name().' — Our Products'">
    @include('marketing.home.products', ['s' => \App\Support\SiteContent::page('home')['products']])
    {{-- Master only: the infrastructure statement, how members, merchants and brands earn, the business-to-business journey, personalisation and the
         "a lot more" grid. `$extra` is empty on a white-label install (its copy class is never shipped there), so nothing renders. --}}
    @if (! empty($extra ?? []))
        @include('marketing.master-marketing.products-extra', ['extra' => $extra])
    @endif
</x-layouts.marketing>
