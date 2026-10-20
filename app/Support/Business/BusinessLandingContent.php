<?php

namespace App\Support\Business;

/**
 * The shipped copy of the NaaraBusiness Suite marketing page (MASTER-ONLY), as Site Editor sections: page `business-suite` in SiteContent.
 * Every line here is a promise about the product, so every claim maps to something the platform really does today (see docs/business-suite). Edit
 * the words in Admin > Marketing site; the structure (and the illustrations) live in resources/views/marketing/business/.
 */
class BusinessLandingContent
{
    /** The eight problem/solution cards: id => [span, label, title, text, modal headline, modal body, bullets]. */
    public const CELLS = [
        'p1' => ['7', 'Scattered', 'Five apps, one customer', 'Messages land in WhatsApp, Telegram, Instagram and plain texts. Whoever sees one first answers it, and nobody sees all of them.',
            'The same customer, in five places', 'A customer asks on Instagram, follows up on WhatsApp and texts your number. To your team that looks like three strangers.', ['Replies go out twice, or not at all', 'New teammates start blind', 'Nothing tells you who is on it']],
        'p2' => ['5', 'Missed', 'Calls on a personal phone', 'Customers ring one person\'s own number. Off hours, on holiday or in a meeting, the call is lost, and so is the sale.',
            'A business line is not a person', 'When the number belongs to a person, the business goes with them: to dinner, on leave, offline.', ['No menu, no hours, no voicemail', 'Nobody to hand over to', 'Private numbers on public flyers']],
        'p3' => ['5', 'Blind', 'Who said what?', 'There is no shared history. A customer repeats themselves, and you cannot tell what was promised last week.',
            'Context lives in heads', 'Without one shared record, service depends on who happens to be around and what they remember.', ['Handover means a long chat', 'Quality is a guess', 'Growth means more confusion']],
        'p4' => ['7', 'Invisible', 'Found only if you pay for ads', 'People who would love your brand never find it, and the ones who do cannot tell whether you are real.',
            'Trust is the first sale', 'A new customer decides in seconds whether you are legitimate. A verified listing answers that before they ask.', ['Paid reach stops when the budget does', 'No proof you are who you say', 'Followers who never engage']],
        's1' => ['5', 'Inbox', 'One inbox for every channel', 'WhatsApp, Telegram, Instagram and SMS in one shared inbox, answered with one composer.',
            'Every channel, one conversation', 'Connect your own accounts and see every thread beside SMS, with the channel marked so you always know where a reply goes.', ['Files, voice notes, GIFs and saved snippets', 'Schedule a reply for later', 'Per-channel rules respected for you']],
        's2' => ['7', 'Calls', 'Calls answered, always', 'Menus, ring groups, business hours, voicemail and an optional AI receptionist answer your number so a call is never just lost.',
            'Your number, answered on your terms', 'Build the call journey once: greet, route, ring the team in turn or together, take a voicemail, or hand to the assistant.', ['Business hours and after-hours messages', 'Recording only with a notice to every caller', 'The assistant hands over to a person']],
        's3' => ['7', 'Team', 'A team with clear roles', 'Owners, admins, agents and viewers each get exactly the access they need, on one shared wallet with spend caps per seat.',
            'Invite anyone, even before they have an account', 'Add teammates by email. They get a seat with a role, and you decide what each person can spend.', ['Four roles, no guesswork', 'Spend caps per seat', 'One wallet for the whole team']],
        's4' => ['5', 'Listed', 'Listed, verified, found', 'Get into the Naara brand directory with a gold verified seal and a guaranteed number of real follows every month.',
            'Be where members already look', 'Naara members follow brands to earn credit. Your listing puts you in front of people motivated to follow, with the Naara Pro seal beside your name.', ['A guaranteed monthly follower target', 'Priority placement if a month runs short', 'The verified Naara Pro seal']],
    ];

    /** The A to Z of what the Suite and Naara Pro give: letter => [title, text]. */
    public const ATOZ = [
        'A' => ['Automation', 'Call menus, business hours, voicemail and scheduled replies work while you sleep.'],
        'B' => ['Brand directory', 'A listing where real members follow brands to earn credit.'],
        'C' => ['Call flows', 'Menus, ring groups and hours on your business numbers.'],
        'D' => ['Dashboard skins', 'Premium looks for your whole Naara dashboard on plans that include them.'],
        'E' => ['Export your data', 'Take your information with you, any time, through the account and privacy tools.'],
        'F' => ['Follower guarantee', 'A real follower target per handle every month, or boosted placement until you catch up.'],
        'G' => ['Guides', 'Step-by-step guides for WhatsApp, Telegram, Instagram and every Suite tool.'],
        'H' => ['Hand-over', 'The AI assistant passes a caller to a person whenever it should.'],
        'I' => ['Inbox', 'One shared inbox for WhatsApp, Telegram, Instagram and SMS.'],
        'J' => ['Join in seconds', 'Invite teammates by email, even before they have a Naara account.'],
        'K' => ['Keep answers consistent', 'Saved snippets for the replies you give every day.'],
        'L' => ['Local numbers', 'Give each market its own line so customers call someone local.'],
        'M' => ['Money control', 'One shared wallet, with a spend cap on every seat.'],
        'N' => ['NaaraCredit refills', 'Plans can add NaaraCredits to your account every month.'],
        'O' => ['Opening hours', 'Say when you are open, and what callers hear when you are not.'],
        'P' => ['Priority placement', 'Fall short of your follower target and your listing gets boosted next cycle.'],
        'Q' => ['Quality reviews', 'Record calls with a notice to every caller, then review them.'],
        'R' => ['Roles', 'Owner, admin, agent and viewer: the right access for each person.'],
        'S' => ['Secure by design', 'Scoped roles, signed webhooks and consent-first recording.'],
        'T' => ['Transcripts', 'Summaries and notes on recorded calls, with recording and analysis consented separately.'],
        'U' => ['Usage you control', 'Recording, transcripts and AI minutes are paid per use from your wallet, never beyond it.'],
        'V' => ['Verified seal', 'The gold Naara Pro badge on your brand profile while your plan is active.'],
        'W' => ['WhatsApp, Telegram, Instagram', 'Connect your own accounts and reply on each from one place.'],
        'X' => ['X-border reach', 'Numbers and customers across borders, one USD wallet to run it.'],
        'Y' => ['Your brand, your voice', 'Write your own greetings, menus and voicemail prompts.'],
        'Z' => ['Zero lock-in', 'Cancel any time. Your workspace pauses, nothing is deleted, and it all returns when you do.'],
    ];

    public const PILLS = ['Shared inbox', 'WhatsApp', 'Telegram', 'Instagram', 'SMS', 'Call menus', 'Ring groups', 'Business hours', 'Voicemail', 'AI receptionist', 'Call recording', 'Transcripts', 'Team seats', 'Spend caps', 'One wallet', 'API key', 'Webhooks', 'Zapier', 'Verified seal', 'NaaraCredits', 'Premium skins', 'Brand listing', 'Guides'];

    /** @return array<string, array<string, string>> */
    public static function defaults(): array
    {
        $problem = [
            'eyebrow' => 'The problem',
            'headline' => 'Your customers are everywhere.',
            'headline_accent' => 'Your business is scattered.',
            'intro_1' => 'A small team answers from personal phones and a dozen apps. Good people, working hard, with no shared view of a customer or a call.',
            'intro_2' => 'The result is slow replies, lost calls and a brand nobody can verify. It is not a people problem. It is a set-up problem.',
            'sol_eyebrow' => 'The solution',
            'sol_headline' => 'One suite.',
            'sol_headline_accent' => 'Everything in its place.',
            'sol_intro_1' => 'NaaraBusiness Suite puts the conversation, the call, the team and the listing in one workspace, on one wallet.',
            'sol_intro_2' => 'Start with the pieces you need. Your Naara Pro plan decides which tools you have today, and the rest are a plan away.',
        ];
        foreach (self::CELLS as $id => [$span, $label, $title, $text, $mh, $mb, $bullets]) {
            $problem["{$id}_span"] = $span;
            $problem["{$id}_label"] = $label;
            $problem["{$id}_title"] = $title;
            $problem["{$id}_text"] = $text;
            $problem["m_{$id}_eyebrow"] = $label;
            $problem["m_{$id}_headline"] = $mh;
            $problem["m_{$id}_body"] = '<p>'.$mb.'</p>';
            $problem["m_{$id}_quote"] = '';
            $problem["m_{$id}_bullets"] = implode("\n", $bullets);
        }

        $atoz = ['eyebrow' => 'Everything, A to Z', 'headline' => 'What NaaraBusiness Suite gives you', 'subtext' => 'Twenty-six things your business gets with a Naara Pro plan. Which tools sit on which plan is shown on the plan itself.'];
        foreach (self::ATOZ as $letter => [$t, $x]) {
            $atoz[strtolower($letter).'_title'] = $t;
            $atoz[strtolower($letter).'_text'] = $x;
        }

        $pills = ['eyebrow' => 'Pick one up', 'headline' => 'Everything you would otherwise stitch together yourself', 'subtext' => 'Grab a pill and throw it around. Each one is a tool you get in one place, on one bill.'];
        foreach (self::PILLS as $i => $label) {
            $pills['pill_'.($i + 1)] = $label;
        }

        return [
            'hero' => [
                'eyebrow' => 'NaaraBusiness Suite',
                'headline' => 'Get found. Stay reachable.',
                'subtext' => 'Get listed in the Naara brand directory, then run your customer calls and conversations from one team workspace: WhatsApp, Telegram, Instagram and SMS in one inbox, call menus that answer for you, and an AI receptionist that never sleeps. One Naara Pro plan, one wallet.',
                'cta_primary' => 'See plans',
                'cta_secondary' => 'See how it works',
                'stat_1' => '1 inbox|for every channel', 'stat_2' => '1 wallet|for the whole team', 'stat_3' => '0 lock-in|cancel any time',
                'trust' => 'Built on the same platform that connects members in 190+ countries.',
            ],
            'channels' => ['eyebrow' => 'Works where your customers already are', 'label_1' => 'WhatsApp', 'label_2' => 'Telegram', 'label_3' => 'Instagram', 'label_4' => 'SMS', 'label_5' => 'Voice calls'],
            'problem' => $problem,
            'listing' => [
                'eyebrow' => 'Get listed', 'headline' => 'Be where members already look for brands',
                'subtext' => 'Naara members follow brands to earn NaaraCredits. Your listing puts you in front of people who are motivated to follow, and the verified seal shows you are the real thing.',
                'c1_title' => 'Real people, already motivated', 'c1_text' => 'Members follow your handles to earn NaaraCredits: genuine accounts with a reason to follow.',
                'c2_title' => 'A guaranteed monthly target', 'c2_text' => 'Every plan guarantees a real follower count per handle per month. A number you can hold us to.',
                'c3_title' => 'Short a target? You get boosted', 'c3_text' => 'Miss the guarantee and your listing gets priority placement next cycle, automatically, until it catches up.',
                'guarantee_title' => 'Our follower guarantee', 'guarantee_text' => 'If a handle does not hit its guaranteed follows in a month, your listing gets boosted priority placement the next cycle until it catches up. The plan renews monthly from your wallet, and you can pause or cancel any time.',
            ],
            // The team and calls showcase (owner-supplied artwork, trimmed and re-encoded under public/images/marketing/business-suite). Only describes what the Suite does today.
            'team' => [
                'eyebrow' => 'Your team, on the line',
                'headline' => 'Every call and message lands with the right person',
                'text' => 'Ring groups, business hours, call menus and voicemail send each customer to the teammate who should answer, and everyone works from one shared inbox on one wallet.',
                'b1' => 'Ring groups and call menus that route by team and by hours',
                'b2' => 'A shared inbox with roles for owners, admins, agents and viewers',
                'b3' => 'Voicemail, recordings and transcripts, on the plans that include them',
                'main_image' => 'images/marketing/business-suite/team-call-routing.webp',
                'image_alt' => 'A support agent on a headset with callers routed to him from other people',
                'w' => 720, 'h' => 580,
            ],
            // Showcases for what Naara Respond adds on top of the Suite. They carry a badge until the feature is live (owner rule: never describe a switched-off feature as live).
            'assistant' => [
                'eyebrow' => 'From ad to booked customer', 'badge' => 'Coming with Naara Respond',
                'headline' => 'Turn an ad click into a conversation that closes',
                'text' => 'Send people from your Instagram or Facebook ads straight to a chat on WhatsApp. The Naara assistant answers the first questions from your own knowledge, and hands over to a person when it should.',
                'b1' => 'The assistant only uses the answers you give it, never invented ones',
                'b2' => 'A person can take over any conversation at any moment',
                'b3' => 'You set the hours, the tone and what it may promise',
                'main_image' => 'images/marketing/business-suite/ad-to-chat-ai.webp',
                'image_alt' => 'A sponsored ad with a customer chat and an AI assistant reply',
                'w' => 800, 'h' => 680,
            ],
            'insights' => [
                'eyebrow' => 'Know what is working', 'badge' => 'Coming with Naara Respond',
                'headline' => 'See what your team and assistant handled',
                'text' => 'Conversations by channel, what ended in a sale, what the assistant handled alone and where a person stepped in. One simple view, so you can improve the next reply.',
                'b1' => 'Conversations closed, by channel and by outcome',
                'b2' => 'What the assistant handled and what it handed over',
                'b3' => 'The same numbers for every teammate, in one place',
                'main_image' => 'images/marketing/business-suite/conversation-analytics.webp',
                'image_alt' => 'A conversations chart with support, sales and assistant totals (sample data)',
                'w' => 740, 'h' => 730,
            ],
            'connect' => [
                'eyebrow' => 'Where your customers already are',
                'headline' => 'Your channels and your payments in one workspace',
                'text' => 'WhatsApp, Telegram, Instagram, SMS and voice land in one inbox today. Email, calendar booking and payment links through Paystack or Flutterwave are on the way with Naara Respond.',
                'b1' => 'One shared inbox across every channel you connect',
                'b2' => 'Email and calendar booking are coming with Naara Respond',
                'b3' => 'Payment links through Paystack and Flutterwave, paid to your own account, are coming too',
                'main_image' => 'images/marketing/business-suite/integrations-grid.webp',
                'image_alt' => 'Tiles for email, Instagram, Telegram, SMS, WhatsApp, voice, calendar, Paystack and Flutterwave',
                'w' => 660, 'h' => 660,
            ],
            'atoz' => $atoz,
            'how' => [
                'eyebrow' => 'How it works', 'headline' => 'From sign-up to your first answered call',
                's1_title' => 'Choose a Naara Pro plan', 's1_text' => 'Pick the plan that includes the tools you need. It bills monthly from your Naara wallet.',
                's2_title' => 'Complete your listing', 's2_text' => 'Add your brand, category, image and handles. Your listing goes live with the verified seal.',
                's3_title' => 'Open your workspace', 's3_text' => 'Name it, invite your team by email and give everyone a role.',
                's4_title' => 'Connect and go live', 's4_text' => 'Connect your channels, bring in a number, build a call flow. The guides inside the Suite hold your hand.',
            ],
            'perks' => [
                'eyebrow' => 'Naara Pro perks', 'headline' => 'More than tools',
                'subtext' => 'A Naara Pro plan carries benefits that make the rest of Naara better for you and your team.',
                'p1_title' => 'The verified seal', 'p1_text' => 'A gold Naara Pro badge on your brand profile, so customers know you are legitimate.',
                'p2_title' => 'NaaraCredit refills', 'p2_text' => 'Plans can add NaaraCredits to your account every month, shown on the plan.',
                'p3_title' => 'Premium dashboard skins', 'p3_text' => 'Restyle your whole Naara dashboard with premium skins, on plans that include them.',
                'p4_title' => 'Priority placement', 'p4_text' => 'Your listing is boosted automatically whenever a month runs short of its follower target.',
                'skins_title' => 'A few of the skins',
            ],
            'plans' => [
                'eyebrow' => 'Plans', 'headline' => 'Pick the plan that fits',
                'subtext' => 'Every plan includes your directory listing and the verified seal. What differs is the number of handles, the follower guarantee and which Suite tools come with it.',
                'popular' => 'Most popular', 'cta' => 'Choose', 'empty' => 'Plans are being prepared. Check back very soon.',
                'foot' => 'Plans renew monthly from your Naara wallet. Cancel any time: your workspace pauses and nothing is deleted.',
            ],
            'pills' => $pills,
            'faq' => self::faq(),
            'cta' => [
                'headline' => 'Ready to be found and reachable?',
                'subtext' => 'Choose a plan, complete your listing and open your workspace in minutes.',
                'cta_primary' => 'See plans', 'cta_secondary' => 'Read the FAQ',
                'trust' => 'Monthly billing. Cancel any time. Nothing is ever deleted.',
            ],
        ];
    }

    /** @return array<string, string> flat g{n}_label / g{n}_q{m} / g{n}_a{m} keys, the shape the homepage FAQ partial already reads */
    private static function faq(): array
    {
        $groups = [
            ['Getting started', [
                ['What is NaaraBusiness Suite?', 'It is the business side of Naara Pro: a listing in the Naara brand directory plus a team workspace for your customer conversations and calls. You choose a Naara Pro plan, and the plan decides which Suite tools you have.'],
                ['Do I need a Naara account first?', 'Yes, and it takes a minute. It is the same login you would use as a customer. Then pick a plan and open your workspace. You can switch between Personal and Business whenever you like.'],
                ['How fast can I be live?', 'Your listing goes live once your brand profile is complete. A workspace takes minutes. Channels depend on each platform\'s own set-up, and the guides inside the Suite walk through WhatsApp, Telegram and Instagram step by step.'],
                ['Can I try it before committing?', 'Plans are monthly with no contract, so the most you commit is one month. If you cancel, your workspace pauses and nothing is deleted.'],
            ]],
            ['Plans and billing', [
                ['How does billing work?', 'Your plan renews monthly from your Naara wallet. Usage-based extras, such as call recording, transcripts and AI voice minutes, are charged from the same wallet at the price shown before you switch them on.'],
                ['What if my wallet is short at renewal?', 'We remind you first. If the renewal cannot be taken, your plan lapses and the Suite pauses. Nothing is deleted, and everything returns the moment you renew.'],
                ['Do plans include different tools?', 'Yes. Each plan shows exactly which Suite tools it includes, how many seats, numbers and channels it allows, and any monthly NaaraCredit refill. What the plan shows is what you get.'],
                ['What are the NaaraCredit refills?', 'Some plans add NaaraCredits to your account each month. They work like your other NaaraCredits: use them toward eligible purchases as the loyalty programme allows.'],
            ]],
            ['Channels and inbox', [
                ['Which channels can I connect?', 'Your own WhatsApp Business number, your own Telegram bot and your own Instagram account, next to SMS on your Naara numbers. All of them arrive in one shared inbox.'],
                ['Do I need accounts with Meta or Telegram?', 'Yes. Your channels belong to you, so you connect your own. The guides inside the Suite explain each step. Each platform keeps its own rules, such as the 24-hour reply window on WhatsApp and Instagram.'],
                ['Can my whole team reply from the same place?', 'Yes. Agents answer from the shared inbox with the Naara composer: files, voice notes, GIFs, saved snippets and scheduled replies, within each channel\'s own limits.'],
                ['What happens to messages if my plan lapses?', 'Incoming messages are still kept. The inbox is hidden until you renew, so your conversations are waiting for you when you are back.'],
            ]],
            ['Calls and recording', [
                ['What are call flows?', 'A call flow decides what happens when someone rings your business number: a menu, a ring group, business hours, a voicemail box or the AI assistant.'],
                ['Is call recording legal?', 'Rules differ by country. Recording is off by default, every recorded call is announced, and in strict countries the caller must agree by pressing a key. Check your own obligations; this is not legal advice.'],
                ['Can an AI assistant take calls?', 'It can answer a number, take callback requests and hand over to a person. Callers are told they are speaking to an automated assistant. It is available where the platform has it switched on.'],
                ['Who pays for call minutes?', 'Usage is billed from the workspace owner\'s wallet. We do not record a call or run the assistant on a call the wallet cannot cover.'],
            ]],
            ['Your data and privacy', [
                ['Who can see our conversations?', 'Only people in your workspace, according to their role. Owners and admins manage the workspace, agents work the conversations, and viewers can look but not change anything.'],
                ['Can I export or delete my data?', 'Yes, through Naara\'s account and privacy tools. Recordings are also deleted automatically when their retention period ends.'],
                ['Are our conversations used for advertising?', 'No. Your conversations are yours: they are used to run your workspace, not to advertise to you or anyone else.'],
                ['Can I connect my own tools?', 'Yes. A workspace API key (read-only contacts, calls and messages) and signed webhooks let tools like Zapier connect.'],
            ]],
            ['Naara Pro and the directory', [
                ['What does the verified seal mean?', 'The gold seal shows your brand holds an active Naara Pro plan. It appears on your brand profile and goes when the plan ends.'],
                ['What is the follower guarantee?', 'Each plan guarantees a number of real follows per handle per month from Naara members who follow brands to earn credit. If a handle falls short, your listing gets boosted placement the next cycle until it catches up.'],
                ['Are the followers real?', 'They are real Naara members who followed your handle to earn NaaraCredits, and each follow is confirmed when they claim it. We promise genuine, motivated follows. We cannot promise that every follower buys.'],
                ['What are premium skins?', 'Premium skins restyle your whole Naara dashboard. Plans that include them unlock the skins marked Pro.'],
            ]],
        ];
        $out = ['headline' => 'Questions, answered properly', 'cta' => 'Still unsure?', 'cta_contact' => 'Talk to us', 'cta_all' => 'Read the full FAQ'];
        foreach ($groups as $g => [$label, $items]) {
            $out['g'.($g + 1).'_label'] = $label;
            foreach ($items as $n => [$q, $a]) {
                $out['g'.($g + 1).'_q'.($n + 1)] = $q;
                $out['g'.($g + 1).'_a'.($n + 1)] = $a;
            }
        }

        return $out;
    }
}
