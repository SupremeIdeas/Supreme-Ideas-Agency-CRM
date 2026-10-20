<?php

namespace App\Support\MasterMarketing;

/**
 * MASTER-ONLY. The shipped copy of the extra sections on the "What Naara Gives You" page (`/products`), as Site Editor page `products`.
 * The first section of that page (the product carousel) is still the homepage's `products` section and the admin's Product lines; the
 * sections below follow it. Every claim maps to something the platform really does today. Edit the words and swap every image from
 * Admin > Marketing site > Products page; the structure lives in resources/views/marketing/master-marketing/products-extra.blade.php.
 */
final class ProductsContent
{
    /** The eight skins shown in the personalisation showcase: key => [name, line]. Screens: images/marketing/skins/{key}/home.webp */
    public const SKINS = [
        'surface' => ['Naara Surface', 'Layered depth with one bold action card.'],
        'noir' => ['Noir', 'True black, hairlines, accent only where it counts.'],
        'glass' => ['Glass', 'Frosted layers over a soft colour mesh.'],
        'paper' => ['Paper', 'Warm editorial paper with serif headings.'],
        'neo' => ['Neo', 'Bold outlines and hard offset shadows.'],
        'aurora' => ['Aurora', 'Glow, orbit rings and a motif on every card.'],
        'ankara' => ['Ankara', 'A bold woven-print band across every card.'],
        'vault' => ['Vault', 'Black and champagne foil with engraved lines.'],
    ];

    /** Icons of the "more" cards are code, not copy (they must be names in the icon sprite). */
    public const MORE_ICONS = [1 => 'plane', 2 => 'target', 3 => 'signal', 4 => 'phone-forwarded', 5 => 'hash', 6 => 'arrow-right-left', 7 => 'gift', 8 => 'wallet', 9 => 'globe', 10 => 'bell', 11 => 'shield-check', 12 => 'headset'];

    /** @return array<string, array<string, string>> */
    public static function defaults(): array
    {
        $earn = [
            'eyebrow' => 'More benefits',
            'headline' => 'However you earn, Naara pays you for showing up',
            'subtext' => 'Naara is not only something you buy from. It is something you can build on. Pick the way that fits you, and use the same account for all three.',
            'tab1_label' => 'As a merchant',
            'tab1_title' => 'Sell connectivity under your own name',
            'tab1_text' => 'Open a merchant account and resell eSIM data and numbers to your own customers. You choose the margin, Naara delivers the product, and your earnings land in a ledger you can see line by line.',
            'tab1_b1' => 'Your own storefront, clients and invoices in one dashboard',
            'tab1_b2' => 'Earnings by product line and by customer, always visible',
            'tab1_b3' => 'A developer API and keys when you want to plug Naara into your own app',
            'tab1_b4' => 'Withdraw what you earn through the payout guide',
            'tab1_cta' => 'Become a merchant',
            'tab1_href' => '/merchant/apply',
            'tab1_image' => '/images/marketing/mockups/products/earn-merchant.webp',
            'tab2_label' => 'As a member',
            'tab2_title' => 'Get rewarded for simply using Naara',
            'tab2_text' => 'Every member has a rewards wallet. Check in, invite friends, follow brands you actually like and watch short offers, and NaaraCredit builds up toward the things you already pay for.',
            'tab2_b1' => 'Daily check-in and a referral link that pays when friends join',
            'tab2_b2' => 'Follow brands in the directory and earn credit for each real follow',
            'tab2_b3' => 'Spend NaaraCredit on data, numbers and Naara Chat',
            'tab2_b4' => 'Cash out eligible earnings when you are ready',
            'tab2_cta' => 'Start earning',
            'tab2_href' => '/register',
            'tab2_image' => '/images/marketing/mockups/products/earn-member.webp',
            'tab3_label' => 'As a brand',
            'tab3_title' => 'Be found by people who want to follow you',
            'tab3_text' => 'List your brand in the Naara directory and meet members who are already motivated to follow. Your profile is yours to shape, and a message from a follower lands in your Naara Chat.',
            'tab3_b1' => 'A listing in the brand directory, with your own profile page',
            'tab3_b2' => 'Links, albums and music on your profile, all editable by you',
            'tab3_b3' => 'Real members following your handles, not bought numbers',
            'tab3_b4' => 'A direct Message button so interest turns into a conversation',
            'tab3_cta' => 'Get listed',
            'tab3_href' => '/brand/get-listed',
            'tab3_image' => '/images/marketing/mockups/products/earn-brand.webp',
        ];

        $network = [
            'eyebrow' => 'Business meets business',
            'headline' => 'Meet the businesses that grow with you',
            'subtext' => 'As a business owner on Naara you are never selling into a void. The people and companies around you are on the same network, today and for the long run, and every step from the first look to the long relationship happens inside Naara.',
            'closing' => 'Good business relationships are built in small, repeated moments. Naara gives every one of them a home.',
        ];
        $steps = [
            1 => ['Discover', 'Find who is worth knowing', 'Browse the brand directory and see which businesses are on Naara, with their story, links and what they offer.', 'directory'],
            2 => ['Follow', 'Start the relationship', 'Follow a brand to hear from it, and let it see that you are a real person behind a real Naara account.', 'profile'],
            3 => ['Chat', 'Say hello on Naara Chat', 'Message them with your Naara ID. No phone numbers swapped, no new app to install, and the thread stays tidy.', 'chat'],
            4 => ['Call', 'Talk when talking is faster', 'Start a voice call from the same screen. The person who picks up pays nothing, and the caller is billed by the second.', 'calls'],
            5 => ['Plan', 'Keep it going', 'Put the next meeting in the chat, answer with a tap, and set a private reminder to follow up so no lead goes quiet.', 'planner'],
        ];
        foreach ($steps as $n => [$label, $title, $text, $img]) {
            $network["st{$n}_label"] = $label;
            $network["st{$n}_title"] = $title;
            $network["st{$n}_text"] = $text;
            $network["st{$n}_image"] = "/images/marketing/mockups/products/network-{$img}.webp";
        }

        $personal = [
            'eyebrow' => 'Personalisation on demand',
            'headline' => 'Your dashboard, in your mood',
            'text_1' => 'We did not bolt a dark mode onto a finished product and call it personal. We spent many months building a personalisation layer of its own, so that the screen you open ten times a day feels like it was made for you.',
            'text_2' => 'Pick a skin and your whole dashboard changes with it: cards, buttons, navigation, even the way the chat bubbles look. Then fine-tune the dials until it is exactly right. Members on a Pro plan get the full collection and the freedom to mix it all.',
            'dials_label' => 'Then tune it your way',
            'd1' => 'Corner roundness',
            'd2' => 'Density',
            'd3' => 'Text size',
            'd4' => 'Card depth',
            'd5' => 'Typeface',
            'd6' => 'Motion',
            'accents_label' => 'Six accent colours, light and dark mode, and a custom colour of your own.',
            'pro_note' => 'Some skins are free for everyone. The rest unlock with a Naara Pro plan, so the best of Naara is a plan away.',
            'cta' => 'Open Appearance',
            'cta_href' => '/account/appearance',
        ];
        foreach (self::SKINS as $key => [$name, $line]) {
            $personal["sk_{$key}_name"] = $name;
            $personal["sk_{$key}_text"] = $line;
            $personal["sk_{$key}_image"] = "/images/marketing/skins/{$key}/home.webp";
        }

        $more = [
            'eyebrow' => 'And there is a lot more',
            'headline' => 'So much is built in, you may not find it all in a week',
            'subtext' => 'These are the things people tell us they did not expect to find in one app.',
        ];
        $cards = [
            1 => ['plane', 'Check your phone before you pay', 'A device check tells you whether your phone supports eSIM before you spend a cent.'],
            2 => ['target', 'My Journey', 'Plan a trip and let Naara line up the data, the numbers and the reminders around it.'],
            3 => ['signal', 'Data estimator', 'Tell it how you use your phone and it recommends how much data to buy, so you never over-pay.'],
            4 => ['phone-forwarded', 'Call abroad and forward calls', 'Dial out from the app, forward your number, and pick up voicemail with a transcript.'],
            5 => ['hash', 'Numbers that fit the job', 'One-time verification numbers, rentals and permanent lines, matched to the country you need.'],
            6 => ['arrow-right-left', 'Bring your number with you', 'Port a US or Canada number in, or port out any time. Your number is yours.'],
            7 => ['gift', 'Gift cards for the brands people love', 'Send a gift in seconds and let it arrive instantly, from the same wallet.'],
            8 => ['wallet', 'One wallet for everything', 'Top up once and spend on data, numbers, gifts and chat. Share a plan with people you trust.'],
            9 => ['globe', 'Your language, your currency', 'The app speaks your language and shows prices in the currency you think in.'],
            10 => ['bell', 'Sounds that are yours', 'Pick the tone for messages, calls and alerts from a library, one by one.'],
            11 => ['shield-check', 'Privacy you can act on', 'Export your data, deactivate or ask to be erased, all from your account.'],
            12 => ['headset', 'Support that answers at 3 a.m.', 'NaaraCare answers around the clock, with a person behind it when it matters.'],
        ];
        foreach ($cards as $n => [$icon, $title, $text]) {
            $more["m{$n}_title"] = $title;
            $more["m{$n}_text"] = $text;
        }

        return [
            'quote' => [
                'eyebrow' => 'The infrastructure',
                'quote_before' => 'Naara is the largest connectivity infrastructure in the world, with the ',
                'quote_accent' => 'orchestration that only you and your business need',
                'quote_after' => ' to stay ahead of the curve.',
                'attribution' => 'The Naara promise',
                'subtext' => 'Behind one simple app sits a network of data and number providers, routed by country and by need, with a backup always waiting. You never see the switching. You only see a connection that works.',
                's1_value' => '190+', 's1_label' => 'Countries covered',
                's2_value' => '24/7', 's2_label' => 'NaaraCare support',
                's3_value' => '1', 's3_label' => 'Wallet for every product',
                's4_value' => '35', 's4_label' => 'Dashboard skins',
                'quote_image' => '/images/marketing/mockups/products/quote-desktop.webp',
                'quote_image_alt' => 'The Naara dashboard open on a desktop screen',
            ],
            'earn' => $earn,
            'network' => $network,
            'personal' => $personal,
            'more' => $more,
            'cta' => [
                'headline' => 'Everything Naara gives you starts with one account',
                'subtext' => 'Create it in a minute, and the rest of Naara is already waiting.',
                'primary' => 'Create your account',
                'primary_href' => '/register',
                'secondary' => 'See how it works',
                'secondary_href' => '/how-it-works',
            ],
        ];
    }
}
