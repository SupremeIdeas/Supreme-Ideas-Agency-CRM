{{-- MASTER ONLY. The hero video card of a page ($slot = '<page>.hero'): the player (see _deck) under the page's hero. Nothing at all when the slot is empty. --}}
@php($deck = \App\Support\MasterMarketing\VideoSlots::deck($slot))
@if ($deck)@include('marketing.master-marketing._deck', ['slot' => $slot, 'variant' => 'hero', 'items' => $deck])@endif
