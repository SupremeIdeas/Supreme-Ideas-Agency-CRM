{{-- MASTER ONLY. Videos of one slot as a deck: blog posts and guides (any number, a carousel), app intro cards ($variant 'intro'), or a titled section ($variant 'section'). Nothing when empty. --}}
@php($deck = \App\Support\MasterMarketing\VideoSlots::deck($slot))
@if ($deck)@include('marketing.master-marketing._deck', ['slot' => $slot, 'variant' => $variant ?? 'section', 'items' => $deck, 'heading' => $heading ?? null])@endif
