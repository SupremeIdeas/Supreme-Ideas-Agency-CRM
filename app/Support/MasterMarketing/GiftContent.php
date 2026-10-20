<?php

namespace App\Support\MasterMarketing;

/**
 * MASTER-ONLY. The shipped copy of the Naara Gift landing page (`/naara-gift`), as Site Editor page `naara-gift`. The page can be switched off by the admin
 * (PageSwitch). The screens are real captures of the Naara Gift store running against a SANDBOX catalogue of well-known brands (scripts/mockups/seed-gift-demo.php),
 * so they show a believable store before the live provider keys go in; the footnote says so, and the admin can replace every image from the Site Editor.
 * No discount, fee or delivery time is promised beyond what the store itself says ("delivered instantly", "final once delivered").
 */
final class GiftContent
{
    /** The twelve cards of the brand wall: brand key (committed art under images/marketing/gift/cards/{key}.webp). */
    public const CARDS = [1 => 'amazon', 2 => 'netflix', 3 => 'spotify', 4 => 'apple', 5 => 'google-play', 6 => 'steam', 7 => 'playstation', 8 => 'uber', 9 => 'starbucks', 10 => 'airbnb', 11 => 'nike', 12 => 'ikea'];

    /** Icons are code, not copy (they must be names in the icon sprite). */
    public const WHY_ICONS = [1 => 'zap', 2 => 'wallet', 3 => 'tag', 4 => 'globe', 5 => 'info', 6 => 'shield-check'];

    /** @return array<string, array<string, string>> */
    public static function defaults(): array
    {
        $cards = ['alt' => 'Gift cards from popular brands'];
        foreach (self::CARDS as $n => $key) {
            $cards["card_{$n}_image"] = "/images/marketing/gift/cards/{$key}.webp";
        }

        $how = ['eyebrow' => 'How it works', 'headline' => 'From browsing to a code in your hand, in three steps', 'subtext' => 'Naara Gift lives inside your Naara account, next to your eSIMs, numbers and wallet.'];
        $steps = [
            1 => ['Browse', 'Find the brand', 'Scroll the store or search for a brand. Every card shows its country and category, so you pick the right one for where you or your recipient live.', 'store'],
            2 => ['Choose', 'Pick an amount, see the price', 'Choose a value and the price you pay appears before you commit. What you see is what you are charged.', 'sheet'],
            3 => ['Get it', 'Pay from your wallet', 'Pay with your Naara wallet and the gift card is delivered to your account, with simple instructions for redeeming it.', 'search'],
        ];
        foreach ($steps as $n => [$label, $title, $text, $img]) {
            $how["s{$n}_label"] = $label;
            $how["s{$n}_title"] = $title;
            $how["s{$n}_text"] = $text;
            $how["s{$n}_image"] = "/images/marketing/mockups/gift/{$img}-phone.webp";
            $how["s{$n}_image_alt"] = $title;
        }

        $why = ['eyebrow' => 'Why Naara Gift', 'headline' => 'A gift store that behaves like the rest of Naara', 'subtext' => 'Clear, quick and in the same place you already top up and travel.'];
        $items = [
            1 => ['Delivered instantly', 'Gift cards are delivered to your account as soon as your payment goes through.'],
            2 => ['One wallet', 'Use the same Naara wallet you already use for eSIMs and numbers. No new account, no card details to type again.'],
            3 => ['Prices up front', 'Pick an amount and you see exactly what you will pay, before you press the button.'],
            4 => ['Brands by country', 'Cards are listed by country and category, so you can find the right ones for you or the person you are gifting.'],
            5 => ['Redeem with confidence', 'Each card comes with plain instructions for redeeming it on the brand\'s own site or app.'],
            6 => ['Built on safe payments', 'Payments are handled like every other Naara purchase: atomic, checked and recorded.'],
        ];
        foreach ($items as $n => [$title, $text]) {
            $why["w{$n}_title"] = $title;
            $why["w{$n}_text"] = $text;
        }

        $cats = ['eyebrow' => 'Categories', 'headline' => 'Something for everyone on your list', 'subtext' => 'The store is organised by what people actually spend on.'];
        foreach ([1 => 'Shopping', 2 => 'Streaming', 3 => 'Gaming', 4 => 'Music', 5 => 'Food and drink', 6 => 'Travel', 7 => 'Fashion', 8 => 'Home'] as $n => $label) {
            $cats["c{$n}"] = $label;
        }

        return [
            'hero' => [
                'eyebrow' => 'Naara Gift',
                'headline' => 'Gift cards for the brands',
                'headline_accent' => 'people actually use.',
                'subtext' => 'Shopping, streaming, gaming, travel and more, in one store inside your Naara account. Pick a brand, pick an amount, pay from your wallet.',
                'cta_primary' => 'Open the gift store',
                'cta_primary_href' => '/gift-cards',
                'cta_secondary' => 'See how it works',
                'chip_1' => 'Delivered instantly',
                'chip_2' => 'Pay from your wallet',
                'chip_3' => 'Prices shown up front',
                'chip_4' => 'Brands by country',
                'hero_phone_image' => '/images/marketing/mockups/gift/screen-store.webp',
                'hero_phone_alt' => 'The Naara Gift store on a phone',
            ] + $cards,
            'how' => $how,
            'why' => $why,
            'categories' => $cats,
            'desktop' => [
                'eyebrow' => 'On every screen',
                'headline' => 'The same store on your phone, tablet and desktop',
                'text_1' => 'Naara Gift follows the skin and the light or dark mode you chose for your account, so it feels like part of your dashboard.',
                'text_2' => 'Search by brand, filter by country and switch between a grid and a list, whatever suits the screen in front of you.',
                'shot_image' => '/images/marketing/mockups/gift/store-desktop.webp',
                'image_alt' => 'The Naara Gift store open on a desktop',
                'dark_image' => '/images/marketing/mockups/gift/screen-store-dark.webp',
                'dark_alt' => 'The Naara Gift store in dark mode on a phone',
                'footnote' => 'Screens show the Naara Gift store with a sample catalogue of popular brands. The live catalogue, countries and prices come from our gift-card partner and can differ.',
            ],
            'faq' => [
                'eyebrow' => 'Questions',
                'headline' => 'Before you buy',
                'q1' => 'How do I pay for a gift card?',
                'a1' => 'With your Naara wallet. Top it up once and use it for gift cards, eSIMs and numbers.',
                'q2' => 'How fast is delivery?',
                'a2' => 'Gift cards are delivered to your account as soon as the payment goes through. If a brand needs a moment longer, the order page tells you.',
                'q3' => 'Can I get a refund on a gift card?',
                'a3' => 'Gift cards are final once they are delivered, because the code is revealed to you. Please check the brand, country and amount before you buy.',
                'q4' => 'Why do I only see some countries?',
                'a4' => 'Gift cards are issued for specific countries. The store lists the cards that are available, and you can filter by country to find the right one.',
                'q5' => 'Where do I find Naara Gift?',
                'a5' => 'Open Gifts in your Naara account, from the bottom menu or the main menu.',
            ],
            'cta' => [
                'headline' => 'Find the right card in a minute',
                'subtext' => 'Open the store, pick a brand and pay from your wallet.',
                'primary' => 'Open the gift store',
                'primary_href' => '/gift-cards',
                'secondary' => 'Create your account',
                'secondary_href' => '/register',
            ],
        ];
    }
}
