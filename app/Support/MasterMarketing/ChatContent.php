<?php

namespace App\Support\MasterMarketing;

/**
 * MASTER-ONLY. The shipped copy of the Naara Chat landing page (`/naara-chat`), as Site Editor page `naara-chat`.
 * Every line is a promise about the product, so each maps to something Naara Chat really does today (docs/N2N-*.md, PROGRESS.md).
 * Where the product has a limit (not end-to-end encrypted, group calls only on installs that run a media server) the copy says so.
 * Edit the words and swap every image from Admin > Marketing site > Naara Chat page; the structure lives in
 * resources/views/marketing/master-marketing/chat.blade.php.
 */
final class ChatContent
{
    /** Six skins shown in the "looks like you" strip: key => name. Screens: images/marketing/skins/{key}/chat.webp */
    public const SKINS = [
        'surface' => 'Naara Surface',
        'noir' => 'Noir',
        'paper' => 'Paper',
        'neo' => 'Neo',
        'aurora' => 'Aurora',
        'ankara' => 'Ankara',
    ];

    /** Icons are code, not copy (they must be names in the icon sprite). */
    public const FEATURE_ICONS = [1 => 'edit', 2 => 'smile', 3 => 'reply', 4 => 'mic', 5 => 'list', 6 => 'clock', 7 => 'map-pin', 8 => 'file-text', 9 => 'pin', 10 => 'search', 11 => 'user', 12 => 'image'];

    public const PRIVACY_ICONS = [1 => 'clock', 2 => 'lock', 3 => 'shield-alert', 4 => 'eye-off', 5 => 'trash', 6 => 'download'];

    /** @return array<string, array<string, string>> */
    public static function defaults(): array
    {
        $pillars = [
            1 => ['Messages', 'Say it the way you mean it', 'Write with bold, italic, lists and coloured banners. React with one tap, reply to a single line, edit a typo, and send a voice note that shows its waveform.', ['Rich text, reactions and replies', 'Voice notes with a real waveform player', 'Photos, documents, GIFs and polls'], 'chat-thread'],
            2 => ['Calls', 'Talk the moment typing is slow', 'Start a voice call from any chat. The person who answers pays nothing, and the caller is billed by the second from their own credit, so there are no surprises.', ['One-to-one voice calls inside Naara', 'Calls and missed calls kept in one tab', 'Group calls where your install runs a media server'], 'calls'],
            3 => ['Planner', 'Turn a chat into a plan', 'Create an event right in the thread and let people answer Going, Maybe or Cannot go. Add it to your calendar, get a reminder before it starts, and keep private follow-ups nobody else sees.', ['Events with replies and calendar export', 'Private follow-up reminders per chat', 'An agenda of everything coming up'], 'planner'],
        ];
        $hero = [
            'eyebrow' => 'Naara Chat',
            'headline' => 'Chat and call anyone on Naara.',
            'headline_accent' => 'With your Naara ID, not your phone number.',
            'subtext' => 'Naara Chat is the in-network messenger built into your Naara account. Find people and brands by their Naara ID, message them, call them and plan with them, all without handing over a phone number.',
            'cta_primary' => 'Start chatting',
            'cta_primary_href' => '/register',
            'cta_secondary' => 'See what it can do',
            'chip_1' => 'Naara ID, no phone number',
            'chip_2' => 'Voice calls inside Naara',
            'chip_3' => 'Planner and reminders',
            'chip_4' => 'Skins for every mood',
            'hero_image' => '/images/marketing/mockups/chat/hero-desktop.webp',
            'hero_image_alt' => 'Naara Chat open on a desktop, a conversation beside the chat list',
            'hero_phone_image' => '/images/marketing/mockups/chat/hero-phone.webp',
            'hero_phone_alt' => 'A Naara Chat conversation on a phone',
        ];
        $p = [
            'eyebrow' => 'The essentials',
            'headline' => 'Messages, calls and plans in one place',
            'subtext' => 'Three things people use a chat app for, done properly and kept together.',
        ];
        foreach ($pillars as $n => [$label, $title, $text, $bullets, $img]) {
            $p["p{$n}_label"] = $label;
            $p["p{$n}_title"] = $title;
            $p["p{$n}_text"] = $text;
            foreach ($bullets as $i => $b) {
                $p["p{$n}_b".($i + 1)] = $b;
            }
            $p["p{$n}_image"] = "/images/marketing/mockups/chat/{$img}.webp";
            $p["p{$n}_image_alt"] = $title;
        }

        $power = [
            'eyebrow' => 'Power features',
            'headline' => 'Everything you would ask a chat app for',
            'subtext' => 'The small things are where a messenger earns its place on your phone. These are built in.',
        ];
        $features = [
            1 => ['edit', 'Text styles', 'Bold, italic, strikethrough, code, lists, quotes and eight banner colours, with the shortcuts you already know.'],
            2 => ['smile', 'Reactions', 'A closed set of 36 reactions, with your own quick bar that you can reorder and reset.'],
            3 => ['reply', 'Reply, forward and edit', 'Quote one message, forward text to chats you choose, and fix your own message for a short while after sending.'],
            4 => ['mic', 'Voice notes', 'Record, preview, send. Listeners get a waveform, speed control and automatic play of the next unheard note.'],
            5 => ['list', 'Polls', 'Ask a question with one or many answers and watch the results move live.'],
            6 => ['clock', 'Scheduled sending', 'Write now and choose when it goes, from a minute to thirty days ahead.'],
            7 => ['map-pin', 'Live location', 'Share where you are for fifteen minutes, an hour or eight. It stops when time is up, and nothing is kept.'],
            8 => ['file-text', 'Documents and media', 'Send photos, video, audio and documents, and find them again in the chat library.'],
            9 => ['pin', 'Pin, star and mute', 'Pin the chats that matter, star the messages you need, and mute the noisy ones for hours, a week or for good.'],
            10 => ['search', 'Search that finds it', 'Search your chats and find the message you meant, without scrolling for it.'],
            11 => ['user', 'Mentions', 'Type an at sign to bring someone into the line, and see your own name light up.'],
            12 => ['image', 'GIFs', 'Send a GIF, or make your own from a short video in the GIF studio.'],
        ];
        foreach ($features as $n => [$icon, $title, $text]) {
            $power["f{$n}_title"] = $title;
            $power["f{$n}_text"] = $text;
        }

        $privacy = [
            'eyebrow' => 'Privacy and control',
            'headline' => 'You decide who reaches you, and for how long',
            'subtext' => 'A chat app should give you control, not ask you to hand it over.',
            'note_title' => 'What we are honest about',
            'note_text' => 'Naara Chat is encrypted in transit, but it is not end-to-end encrypted, and forwarding a message hands it to someone else\'s chat. We say so inside the app, and we say so here, so you can decide what to put in it.',
            'shot_image' => '/images/marketing/mockups/chat/privacy-lock.webp',
            'image_alt' => 'Naara Chat settings for disappearing messages and chat lock',
        ];
        $items = [
            1 => ['clock', 'Disappearing messages', 'Set a chat to clear after 24 hours, 7 days or 90 days. Either person can turn it on, and both see the notice.'],
            2 => ['lock', 'Chat lock', 'Protect a chat with your own PIN. Locked chats leave the main list, the previews and the search until you unlock them.'],
            3 => ['shield-alert', 'Block and report', 'Block someone in a tap, or report them with the message that matters. A real person reviews reports.'],
            4 => ['eye-off', 'Receipts on your terms', 'Choose whether people can see when you have read their message.'],
            5 => ['trash', 'Delete for me, or for everyone', 'Clear a chat, remove a message from your side, or take it back for both of you.'],
            6 => ['download', 'Your data, your export', 'Download what you have written, and ask for it to be erased from your account at any time.'],
        ];
        foreach ($items as $n => [$icon, $title, $text]) {
            $privacy["pv{$n}_title"] = $title;
            $privacy["pv{$n}_text"] = $text;
        }

        $brands = [
            'eyebrow' => 'For brands',
            'headline' => 'From the brand directory to a conversation',
            'text_1' => 'A brand on Naara is more than a logo in a list. A Naara Pro brand has its own profile with links, albums and music, and a Message button that opens a chat with the team behind it.',
            'text_2' => 'Followers become customers because the next step is one tap away. And because both sides are on Naara, the chat, the call and the follow-up all stay in the same place.',
            'cta' => 'Browse the brand directory',
            'cta_href' => '/rewards/hunt',
            'shot_image' => '/images/marketing/mockups/chat/brand-profile.webp',
            'image_alt' => 'A brand profile on Naara with a Message button',
        ];

        $skins = [
            'eyebrow' => 'Looks like you',
            'headline' => 'Every Naara skin gives chat its own signature',
            'subtext' => 'Bubbles, the composer, event cards and the thread background all follow the skin you choose, so the place where you spend the most time feels like yours.',
        ];
        foreach (self::SKINS as $key => $name) {
            $skins["sk_{$key}_name"] = $name;
            $skins["sk_{$key}_image"] = "/images/marketing/skins/{$key}/chat.webp";
        }

        $credits = [
            'eyebrow' => 'Simple to fund',
            'headline' => 'Pay with NaaraCredit or your wallet',
            'subtext' => 'Naara Chat has no subscription. You top up the credit you want and it is used only when you send or call.',
            'k1_title' => 'The sender pays',
            'k1_text' => 'A message costs the person sending it a small amount of credit. Receiving never costs you anything.',
            'k2_title' => 'Calls billed by the second',
            'k2_text' => 'The caller pays for the time they use, from their own credit. The person who picks up pays nothing.',
            'k3_title' => 'Pro for the rich stuff',
            'k3_text' => 'Voice notes, photos, video and GIFs are part of a Naara Pro plan. The basics stay open to everyone.',
            'k4_title' => 'Your wallet, your choice',
            'k4_text' => 'Fund chat from NaaraCredit, or from your wallet balance when you choose to.',
        ];

        $faq = [
            'eyebrow' => 'Questions',
            'headline' => 'Before you start',
        ];
        $qa = [
            1 => ['Do I need to share my phone number?', 'No. People reach you by your Naara ID. You can also choose to be found by username, and you decide who can message you.'],
            2 => ['Is Naara Chat end-to-end encrypted?', 'No. Messages and calls are encrypted in transit, but they are not end-to-end encrypted. Please do not use it for secrets you would not put in an ordinary email.'],
            3 => ['What does it cost?', 'There is no subscription. A message costs the sender a small amount of NaaraCredit, calls are billed to the caller by the second, and the person receiving pays nothing.'],
            4 => ['Can I chat in a group?', 'Naara Chat is built around one-to-one conversations. Group voice calls are available where your install runs a media server, with a host and up to five speakers.'],
            5 => ['What if a stranger messages me?', 'Someone you have not talked to before arrives as a message request. You can accept it, decline it or block them, so a stranger never lands straight in your inbox.'],
            6 => ['Does it work on my phone?', 'Yes. Naara Chat is part of the Naara app and works in the browser on any phone or computer, with the same skins you chose for your dashboard.'],
        ];
        foreach ($qa as $n => [$q, $a]) {
            $faq["q{$n}"] = $q;
            $faq["a{$n}"] = $a;
        }

        return [
            'hero' => $hero,
            'pillars' => $p,
            'power' => $power,
            'privacy' => $privacy,
            'brands' => $brands,
            'skins' => $skins,
            'credits' => $credits,
            'faq' => $faq,
            'cta' => [
                'headline' => 'Your next conversation is one Naara ID away',
                'subtext' => 'Open your account, claim your Naara ID and say hello.',
                'primary' => 'Create your account',
                'primary_href' => '/register',
                'secondary' => 'Talk to us',
                'secondary_href' => '/contact',
            ],
        ];
    }
}
