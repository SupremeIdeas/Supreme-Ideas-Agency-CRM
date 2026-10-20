<?php

namespace Database\Seeders;

use App\Models\Brain\BrainPrompt;
use Illuminate\Database\Seeder;

/**
 * NaaraBrain content library (MASTER-ONLY): 52 rotating centre lines, 77 quick-question ideas across 15 areas, and the capability list, taken from the skills
 * catalogue (docs/naarabrain/03-SKILLS-CATALOG.md). Honest by design: a "live" idea is something NaaraBrain can do TODAY with no access to the member's account
 * (explain, plan, draft, translate, advise); anything that needs to read or change the account is stored as `live = false` and shown as "Soon" until the
 * Sprint 1+ tools exist. Re-running never overwrites an admin's edits (rows are matched by key and only created when missing).
 */
class BrainPromptSeeder extends Seeder
{
    public function run(): void
    {
        $n = 0;
        foreach ($this->taglines() as $text) {
            $this->put(BrainPrompt::TAGLINE, 'general', $text, null, true, $n++);
        }
        $n = 0;
        foreach ($this->suggestions() as [$pack, $text, $live]) {
            $this->put(BrainPrompt::SUGGESTION, $pack, $text, null, $live, $n++);
        }
        $n = 0;
        foreach ($this->capabilities() as [$pack, $text, $detail, $live]) {
            $this->put(BrainPrompt::CAPABILITY, $pack, $text, $detail, $live, $n++);
        }
    }

    private function put(string $kind, string $pack, string $text, ?string $detail, bool $live, int $sort): void
    {
        BrainPrompt::query()->firstOrCreate(
            ['key' => $kind.':'.substr(md5($kind.$text), 0, 12)],
            ['kind' => $kind, 'pack' => $pack, 'text' => $text, 'detail' => $detail, 'live' => $live, 'active' => true, 'sort' => $sort],
        );
    }

    /** @return list<string> */
    private function taglines(): array
    {
        return [
            'Ask anything about staying connected.',
            'Plan your data before you fly.',
            'Not sure which eSIM fits your trip? Ask.',
            'Turn a messy question into a clear answer.',
            'Draft the message. Skip the stress.',
            'Your travel-connectivity co-pilot.',
            'eSIMs, numbers, messages: ask away.',
            'Where are you headed next?',
            'Think out loud. NaaraBrain keeps up.',
            'Landing soon? Get connected before you do.',
            'One question can save you a roaming bill.',
            'Need a number that works across borders? Start here.',
            'Ask it like you would ask a friend who knows telecoms.',
            'From "how does this work?" to "done", in one chat.',
            'Make travel data less of a guessing game.',
            'Got a customer to answer? Let us draft it.',
            'What would you like to figure out today?',
            'Clear answers for confusing connectivity questions.',
            'Switch networks, not stress.',
            'Compare, decide and move on.',
            'Ask in your own language.',
            'Verification codes, explained simply.',
            'A second number without a second phone: ask how.',
            'Before you buy, ask.',
            'Short answers when you are rushing, deep dives when you are not.',
            'Your questions, your pace.',
            'Tell me the trip. I will help with the data.',
            'Roaming without the surprise bill.',
            'Make your next message sound exactly right.',
            'Stuck on an install step? Describe it.',
            'Ask about eSIM setup, QR codes and manual installs.',
            'From Lagos to Lisbon: stay online.',
            'Pan-African travel, one clear answer at a time.',
            'Need a plan for a team trip? Let us work it out.',
            'Bring the question. We will bring the options.',
            'Rewrite it, shorten it, make it kinder.',
            'Understand your options before you commit.',
            'What is your connectivity puzzle today?',
            'Numbers for calls, numbers for codes: know the difference.',
            'Good questions get great answers.',
            'Run a quick what-if on your data needs.',
            'Explain it like I am new to eSIMs.',
            'Planning a long stay abroad? Start with a question.',
            'Ask once, move on.',
            'Practical help for people on the move.',
            'Know before you go.',
            'Brainstorm bios, captions and taglines for your brand.',
            'Stay reachable wherever you land.',
            'A calmer way to sort out your connectivity.',
            'Type a question. Get unstuck.',
            'Let us make this simple.',
            'Ready when you are.',
        ];
    }

    /** @return list<array{0: string, 1: string, 2: bool}> pack, the question that is sent, works today */
    private function suggestions(): array
    {
        return [
            // Trips and data
            ['trips', 'Which eSIM plan fits a two-week trip?', true],
            ['trips', 'How much data do I need for a week of maps, WhatsApp and video calls?', true],
            ['trips', 'I am visiting three countries in ten days. How should I think about eSIM data?', true],
            ['trips', 'What should I check before I fly so my eSIM works when I land?', true],
            ['trips', 'Is it better to buy one regional eSIM or a plan per country?', true],
            ['trips', 'How do I avoid roaming charges on my normal SIM while using an eSIM for data?', true],
            ['trips', 'Give me a pre-flight connectivity checklist for a long trip.', true],
            ['trips', 'Which of my upcoming trips will need the most data?', false],
            // Choosing eSIMs and numbers
            ['buying', 'Explain the difference between a virtual number and an SMS verification number.', true],
            ['buying', 'What is an eSIM, and how is it different from a physical SIM?', true],
            ['buying', 'Which phones support eSIM, and how can I check mine?', true],
            ['buying', 'When do I need a permanent number instead of a one-time number?', true],
            ['buying', 'Why might a verification code not arrive, and what can I try first?', true],
            ['buying', 'What does it mean when an eSIM says "data only"?', true],
            ['buying', 'Which service gets verification codes fastest for WhatsApp?', false],
            ['buying', 'Recommend a plan for me based on how I used data last month.', false],
            // Wallet and credits
            ['account', 'Explain the difference between my wallet balance and NaaraCredits.', true],
            ['account', 'How do refunds work if a verification code never arrives?', true],
            ['account', 'What does auto-renew mean on a number, and how do I stay in control of it?', true],
            ['account', 'Where did my money go this month?', false],
            ['account', 'What renews next, and when?', false],
            ['account', 'Show me my pending gifts and credits that expire soon.', false],
            // Messages and inbox
            ['messaging', 'Draft a polite reply to a customer who is asking about a delayed order.', true],
            ['messaging', 'How does the 24-hour WhatsApp window work for business replies?', true],
            ['messaging', 'Write an out-of-office auto-reply that sounds friendly, not robotic.', true],
            ['messaging', 'Help me word a firm but kind message to a late-paying client.', true],
            ['messaging', 'Summarize everything I missed today across my channels.', false],
            ['messaging', 'Who keeps texting me spam, and how do I block them?', false],
            // Naara chat
            ['chat', 'Explain how Naara chat differs from SMS and WhatsApp.', true],
            ['chat', 'What does a Naara ID do, and how do people find me?', true],
            ['chat', 'Find the message where Ada sent me the address.', false],
            ['chat', 'Summarize my chat with Ada from last week.', false],
            ['chat', 'Draft a message to John and show me the cost before sending.', false],
            // Calls
            ['calls', 'Why does a call sometimes drop on mobile data, and what can I do about it?', true],
            ['calls', 'What is the difference between an internet call and a regular phone call?', true],
            ['calls', 'Who called me while I was away?', false],
            ['calls', 'How much did my calls cost this month?', false],
            // Planner and follow-ups
            ['planner', 'Help me plan a weekly routine to keep my contacts and follow-ups on track.', true],
            ['planner', 'Write a reminder message I can send to a client the day before a call.', true],
            ['planner', 'Remind me to follow up with Kemi on Friday.', false],
            ['planner', 'Set up a group call for Friday with a link I can share.', false],
            // Your brand
            ['brand', 'Write three bio options for my brand in a warm, professional tone.', true],
            ['brand', 'Suggest captions for a product launch post.', true],
            ['brand', 'Give me a tagline for a Pan-African travel brand.', true],
            ['brand', 'What makes a strong brand profile that people follow?', true],
            ['brand', 'Why is my brand not showing in Updates?', false],
            ['brand', 'Give me this week\'s Brand Pulse.', false],
            // Team workspace
            ['workspace', 'How should I set up roles for a small team: owner, admin, agent and viewer?', true],
            ['workspace', 'What spending limits make sense for a new team member?', true],
            ['workspace', 'Who spent what in my workspace this month?', false],
            ['workspace', 'Invite Ada as an agent with a spending cap.', false],
            // Business phone
            ['phone', 'Design a phone menu for a small shop: sales, support and after-hours voicemail.', true],
            ['phone', 'Should my ring group ring everyone at once or one after another?', true],
            ['phone', 'What business hours and holiday rules should a call flow cover?', true],
            ['phone', 'Why did that call go to voicemail?', false],
            ['phone', 'How many calls did we miss after 5pm?', false],
            // Call recordings
            ['recordings', 'What should I tell callers when I record a call?', true],
            ['recordings', 'What makes a good call summary and a good follow-up list?', true],
            ['recordings', 'Summarize my last call and list what I promised.', false],
            ['recordings', 'Which calls were negative this week?', false],
            // Developers
            ['developer', 'Explain webhooks and API keys in plain language.', true],
            ['developer', 'How would I connect my number to Zapier?', true],
            ['developer', 'Which events can I subscribe to, and how do I verify a signature?', true],
            // Privacy and safety
            ['trust', 'Who can see my phone number and email on Naara, and how do I control it?', true],
            ['trust', 'How do I recognise a verification-code scam?', true],
            ['trust', 'What should I do if I think someone has my account?', true],
            ['trust', 'What data does NaaraBrain use to answer me?', true],
            // Writing and translation
            ['writing', 'Translate this into French and keep the tone friendly.', true],
            ['writing', 'Make this message shorter and clearer.', true],
            ['writing', 'Rewrite this so it sounds more professional.', true],
            ['writing', 'Reply to this in Portuguese.', true],
            // Help and how-to
            ['help', 'How do I install an eSIM manually if the QR code will not scan?', true],
            ['help', 'My eSIM shows no service. What should I check?', true],
            ['help', 'Walk me through buying my first eSIM on Naara.', true],
            ['help', 'Where in the app do I manage my numbers and my wallet?', true],
        ];
    }

    /** @return list<array{0: string, 1: string, 2: string, 3: bool}> pack, title, what it does, works today */
    private function capabilities(): array
    {
        return [
            ['help', 'Answer questions', 'Plain-language answers about eSIMs, numbers, messages and calls, in your language.', true],
            ['trips', 'Plan your data', 'Work out how much data a trip needs and what to check before you fly.', true],
            ['writing', 'Draft and rewrite', 'Messages, replies, bios and captions: write it, shorten it, translate it.', true],
            ['help', 'Walk you through set-up', 'Step-by-step help with eSIM installs, verification codes and common problems.', true],
            ['brand', 'Brainstorm for your brand', 'Taglines, bios and post ideas, ready to copy.', true],
            ['phone', 'Design a phone menu', 'Talk through menus, ring groups and business hours before you build them.', true],
            ['account', 'Read your wallet and orders', 'Balances, charges, renewals and refunds explained from your own account.', false],
            ['buying', 'Recommend plans for you', 'Suggestions based on how you actually use data.', false],
            ['messaging', 'Summarize your inbox', 'Everything you missed, across channels, in one brief.', false],
            ['chat', 'Search your chats', 'Find a message or summarize a conversation.', false],
            ['calls', 'Explain your calls', 'Recents, missed calls, why a call ended and what it cost.', false],
            ['planner', 'Remind and follow up', 'Reminders and follow-ups that actually get sent.', false],
            ['workspace', 'Run your workspace', 'Who spent what, who is in the team, and invites with caps.', false],
            ['recordings', 'Learn from your calls', 'Summaries, action items and coaching notes from recordings.', false],
            ['trust', 'Manage your privacy', 'See and change who can reach you.', false],
            ['help', 'Brief you each morning', 'A short daily and weekly digest of what matters.', false],
            ['help', 'Watch things for you', 'Tell you when data runs low, a renewal is close or a code arrives.', false],
            ['help', 'Do things with your approval', 'Drafts that wait for your yes before anything is sent or changed.', false],
        ];
    }
}
