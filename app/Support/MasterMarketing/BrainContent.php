<?php

namespace App\Support\MasterMarketing;

/**
 * MASTER-ONLY. The shipped copy of the NaaraBrain landing page (`/naara-brain`), as Site Editor page `naara-brain`. The page ships SWITCHED OFF
 * (PageSwitch::DEFAULT_OFF) until NaaraBrain itself is live. The copy only promises what the product does today (it answers questions); everything
 * else is labelled "on the way". No price, quota or model name is published: none has been measured yet.
 */
final class BrainContent
{
    /** Icons are code, not copy (they must be names in the icon sprite). */
    public const DEPTH_ICONS = [1 => 'zap', 2 => 'sparkles', 3 => 'bulb'];

    /** @return array<string, array<string, string>> */
    public static function defaults(): array
    {
        $how = ['eyebrow' => 'How it works', 'headline' => 'Three moves, and you are done', 'subtext' => 'NaaraBrain sits inside your Naara account, in the same chat style you already know.'];
        $steps = [
            1 => ['Ask', 'Type it the way you would say it', 'Write a question in plain language. The composer is the same one you use in Naara Chat, so text styles and emoji are right there.', 'ask'],
            2 => ['Tune', 'Choose how deep and how long', 'Set each conversation to Quick, Balanced or Deep, and pick a short, balanced or detailed answer. Your history waits in the sidebar, one drag away.', 'tune'],
            3 => ['Pick up', 'Come back to it any time', 'Pin the chats you use most, search the rest, archive what you are finished with and delete anything you do not want to keep.', 'answers'],
        ];
        foreach ($steps as $n => [$label, $title, $text, $img]) {
            $how["s{$n}_label"] = $label;
            $how["s{$n}_title"] = $title;
            $how["s{$n}_text"] = $text;
            $how["s{$n}_image"] = "/images/marketing/mockups/brain/{$img}.webp";
            $how["s{$n}_image_alt"] = $title;
        }

        $depth = ['eyebrow' => 'Depth', 'headline' => 'Quick when you are in a hurry. Deep when it matters.', 'subtext' => 'Every conversation has its own depth and answer length, so a simple question gets a simple answer.'];
        $levels = [
            1 => ['Quick', 'For the everyday question', 'Fast, to the point answers for things like how to install an eSIM or what a number type is for.'],
            2 => ['Balanced', 'For most questions', 'A good mix of speed and thought. This is where most conversations sit.'],
            3 => ['Deep', 'For the harder ones', 'More thought for questions that need weighing up, like which plan fits a long, multi-country trip.'],
        ];
        foreach ($levels as $n => [$label, $title, $text]) {
            $depth["d{$n}_label"] = $label;
            $depth["d{$n}_title"] = $title;
            $depth["d{$n}_text"] = $text;
        }
        $depth['note'] = 'Higher depths are part of higher Naara Pro plans. Free members get a small number of questions each day at the lightest depth.';

        $today = [
            'eyebrow' => 'Honest about today',
            'headline' => 'What it does now, and what is on the way',
            'subtext' => 'We would rather tell you exactly where NaaraBrain is than promise what is not built yet.',
            'now_title' => 'Ready today',
            'now_1' => 'Answers questions about eSIMs, numbers and messages',
            'now_2' => 'Plain-language replies you can shape: depth and answer length per chat',
            'now_3' => 'Text, emoji and formatting in the same composer as Naara Chat',
            'now_4' => 'History with pinned, grouped, searchable conversations',
            'soon_title' => 'On the way',
            'soon_1' => 'Reading your orders, balances and numbers to answer about your own account',
            'soon_2' => 'Daily and weekly briefs',
            'soon_3' => 'Watching things for you and telling you when they change',
            'soon_4' => 'Doing things for you, only with your approval',
            'soon_label' => 'Soon',
            'now_label' => 'On',
            'shot_image' => '/images/marketing/mockups/brain/desktop.webp',
            'image_alt' => 'NaaraBrain open on a desktop, history on the left and a conversation on the right',
        ];

        $trust = ['eyebrow' => 'Trust', 'headline' => 'Built to be clear about what it is', 'subtext' => 'A good assistant tells you what it is, and leaves the control with you.'];
        $items = [
            1 => ['It tells you it is an AI', 'Every screen says NaaraBrain is an AI, that your messages are processed by an AI model provider, and that it can make mistakes, so you can check what matters.'],
            2 => ['Your conversations are yours', 'Pin, archive or delete any conversation. Deleting is permanent, and you are asked before it happens.'],
            3 => ['No surprises on cost', 'NaaraBrain is part of Naara Pro, with a small daily allowance for free members. Your remaining questions are shown in the sidebar.'],
            4 => ['It looks like your Naara', 'It follows the skin, accent and light or dark mode you chose, so it feels like part of your dashboard and not a bolt-on.'],
        ];
        foreach ($items as $n => [$title, $text]) {
            $trust["t{$n}_title"] = $title;
            $trust["t{$n}_text"] = $text;
        }

        $faq = ['eyebrow' => 'Questions', 'headline' => 'Before you ask'];
        $qa = [
            1 => ['Is NaaraBrain free?', 'Free members get a small number of questions each day at the lightest depth. Naara Pro gives you more questions and the deeper levels.'],
            2 => ['Can it see my account?', 'Not yet. Today it answers general questions about eSIMs, numbers and messages. Reading your own orders and balances is on the way, and anything it does for you will wait for your approval.'],
            3 => ['Is it always right?', 'No. It is an AI and it can make mistakes, so please check anything important, such as refund rules or what a plan covers, before you act on it.'],
            4 => ['Does it keep my chats?', 'Your conversations are kept in your history so you can pick them up later. You can delete any of them whenever you like.'],
            5 => ['Where do I find it?', 'Open NaaraBrain from the button on your dashboard, or from the floating NaaraBrain button where you have it switched on.'],
        ];
        foreach ($qa as $n => [$q, $a]) {
            $faq["q{$n}"] = $q;
            $faq["a{$n}"] = $a;
        }

        return [
            'hero' => [
                'eyebrow' => 'NaaraBrain',
                'headline' => 'Ask once,',
                'headline_accent' => 'move on.',
                'subtext' => 'The assistant inside your Naara account. Ask a question in plain language and get a clear answer about eSIMs, numbers and messages, without digging through help pages.',
                'cta_primary' => 'Try NaaraBrain',
                'cta_primary_href' => '/register',
                'cta_secondary' => 'See how it works',
                'chip_1' => 'Plain-language answers',
                'chip_2' => 'Quick, Balanced or Deep',
                'chip_3' => 'Your history, your control',
                'chip_4' => 'Matches your skin',
                'hero_image' => '/images/marketing/mockups/brain/hero-desktop.webp',
                'hero_image_alt' => 'NaaraBrain open on a desktop',
                'hero_phone_image' => '/images/marketing/mockups/brain/hero-phone.webp',
                'hero_phone_alt' => 'A NaaraBrain conversation on a phone',
            ],
            'how' => $how,
            'depth' => $depth,
            'today' => $today,
            'trust' => $trust,
            'faq' => $faq,
            'cta' => [
                'headline' => 'Your next question is already answered',
                'subtext' => 'Open your account and ask NaaraBrain.',
                'primary' => 'Create your account',
                'primary_href' => '/register',
                'secondary' => 'Talk to us',
                'secondary_href' => '/contact',
            ],
        ];
    }
}
