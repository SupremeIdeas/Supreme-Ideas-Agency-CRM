<?php

namespace Database\Seeders;

use App\Models\FaqCategory;
use App\Models\FaqItem;
use Illuminate\Database\Seeder;

/**
 * MASTER-ONLY extra FAQ topics (2026-10-19): everything built since the original 12 topics, written from the real features so no answer promises what is not live.
 * Idempotent on category slug and (category, question), like FaqSeeder. Lives under a "MasterMarketing" name so the white-label boundary guards keep it out of forks.
 */
class MasterMarketingFaqSeeder extends Seeder
{
    public function run(): void
    {
        // An upgrade passes seed.only_missing so admin edits to existing rows are never overwritten; a plain db:seed still refreshes everything.
        $op = config('seed.only_missing') ? 'firstOrCreate' : 'updateOrCreate';
        foreach (self::categories() as $catData) {
            $items = $catData['items'];
            unset($catData['items']);
            $category = FaqCategory::{$op}(['slug' => $catData['slug']], $catData);
            foreach ($items as $i => [$q, $a]) {
                FaqItem::{$op}(['faq_category_id' => $category->id, 'question' => $q], ['answer' => $a, 'sort_order' => $i, 'is_active' => true]);
            }
        }
    }

    private static function categories(): array
    {
        return [
            [
                'slug' => 'chat', 'title' => 'Naara Chat & People', 'eyebrow' => 'Section XIII',
                'blurb' => 'Message and call other members, find people and brands, and learn the gestures.',
                'tag_label' => 'Open Naara Chat', 'tag_route' => 'n2n.chat', 'sort_order' => 13, 'is_active' => true,
                'items' => [
                    ['What is Naara Chat?', 'Naara Chat lets members message and call each other by <strong>Naara ID</strong>, inside the same app as your wallet, eSIMs and numbers. You can send text, photos, videos, voice notes, files, polls, contacts and locations.'],
                    ['Do I have to share my phone number to chat?', 'No. Share your <strong>Naara ID</strong> or your invite link. People can also find you by username, phone or email if you allow it in your privacy settings.'],
                    ['How do I find or invite someone?', 'Open <strong>People</strong>, type a Naara ID, username, phone or email. If they are not on Naara yet you get an invite link to share by WhatsApp or copy.'],
                    ['Who pays for messages and calls?', 'Chat between members is free to read and write. Calls and some media use <strong>NaaraCredits or your wallet</strong>, and you choose which one funds them.'],
                    ['Can I undo or schedule a message?', 'Yes. You can undo a message for a few seconds after sending, and the send menu lets you send silently, send so it disappears, or schedule it for later.'],
                    ['How do I change the swipe and tap gestures?', 'Open <strong>Chat gestures</strong> to choose what a swipe or double-tap does, how long a press takes, or turn gestures off. A sample message lets you try them.'],
                    ['What are Updates?', 'Updates are status posts from people you are connected with. You can post a photo or short video from the in-app camera and mention people.'],
                    ['How do I block or report someone?', 'Open the chat, tap their name, and choose <strong>Block</strong> or <strong>Report</strong>. A blocked person cannot message or call you.'],
                    ['Why will my call not connect?', 'Check you have internet, allow microphone permission, and try switching between Wi-Fi and mobile data. Some networks block direct calls; Naara uses a relay server so most calls still connect.'],
                    ['Is there a guided tour?', 'Yes. Open the help hub and start <strong>Naara Chat</strong> or <strong>People and brands</strong>. The tour points at the real buttons and you can replay it any time.'],
                ],
            ],
            [
                'slug' => 'brands', 'title' => 'Brands, Follows & Naara Pro', 'eyebrow' => 'Section XIV',
                'blurb' => 'Connect with brands, earn credits for following, and what a brand gets with Naara Pro.',
                'tag_label' => 'Find brands', 'tag_route' => 'n2n.people', 'sort_order' => 14, 'is_active' => true,
                'items' => [
                    ['How do I connect with a brand?', 'Open <strong>People</strong>, then <strong>Brands</strong>. Tap <strong>Connect</strong> on a brand, or use <strong>Quick connect</strong> at the top. Some cards also offer <strong>Connect and message</strong>.'],
                    ['What does Connect and message do?', 'It connects you to the brand and opens a chat in one tap, when the brand accepts messages from you. If the brand keeps chat for connected people only, you still connect and the chat opens once you are connected with each other.'],
                    ['How do I earn NaaraCredits from brands?', 'Connect with a brand, then follow its social pages. Each verified follow earns credits. The <strong>Earn credits</strong> view shows what is left to follow.'],
                    ['Can I leave a brand?', 'Yes. Tap <strong>Connected</strong> on the brand card to disconnect. You can also block or report a chat.'],
                    ['What is Naara Pro?', 'Naara Pro is one package for brands: a verified listing with social handles, a business card with QR code and short link, Naara Chat features, included handbooks, the Business Suite and perks such as a monthly NaaraCredits refill. The plan card lists exactly what each plan includes.'],
                    ['What is the follower guarantee?', 'Each plan promises a number of real followers per handle each month. If a brand falls short, its listing is boosted until it catches up.'],
                    ['What happens if my Naara Pro plan ends?', 'Nothing is deleted. A lapsed workspace is paused and everything comes back when the plan returns.'],
                    ['How do I get my brand listed?', 'Open <strong>Get Listed</strong> and follow the brand wizard: links, connections, chat profile, plan and share.'],
                    ['Are brand links safe?', 'Brand website links are checked so visitors only meet safe pages.'],
                ],
            ],
            [
                'slug' => 'suite', 'title' => 'Business Suite', 'eyebrow' => 'Section XV',
                'blurb' => 'Team workspaces, shared inbox, call flows, recording and the setup guide.',
                'tag_label' => 'Open Business', 'tag_route' => 'business.workspace', 'sort_order' => 15, 'is_active' => true,
                'items' => [
                    ['What is the Business Suite?', 'A team workspace with a shared wallet, seats and roles, a shared inbox, call flows, call recording with caller notices, an AI voice assistant, an inbox assistant, and an API with webhooks. Which parts you see depends on your Naara Pro plan.'],
                    ['What roles are there?', '<strong>Owner</strong> holds the plan, <strong>admin</strong> connects channels and changes settings, <strong>agent</strong> answers customers, <strong>viewer</strong> watches without changing anything.'],
                    ['How do I set up quickly?', 'Open the Business overview and press <strong>Start setup</strong>. The Setup Guide walks you through profile, channels, your AI helper and team, and remembers where you stopped.'],
                    ['Which channels can I connect?', 'Telegram, website chat and email work today. WhatsApp and Instagram can be connected with your own Meta credentials. Each channel shows the exact steps and tests the connection before saving.'],
                    ['Are my channel secrets safe?', 'Secrets such as bot tokens are encrypted, wiped from the page after connecting and never shown again.'],
                    ['Is call recording on by default?', 'No. Recording is off by default, every recorded call is announced to the caller, and the strictest rule across both ends applies. You set retention and can delete recordings.'],
                    ['What happens when my plan ends?', 'The workspace is paused, nothing is deleted, and it returns when you renew.'],
                    ['Can an agent change settings?', 'No. Only owners and admins can connect channels or open automation settings.'],
                ],
            ],
            [
                'slug' => 'respond', 'title' => 'Naara Respond & AI Assistant', 'eyebrow' => 'Section XVI',
                'blurb' => 'One inbox for Telegram, web chat and email, with a safe AI assistant, flows and broadcasts.',
                'tag_label' => 'Open Respond', 'tag_route' => 'business.respond', 'sort_order' => 16, 'is_active' => true,
                'items' => [
                    ['What is Naara Respond?', 'One inbox for customer messages from Telegram, website chat and email, with a knowledge base, an AI assistant, automated flows, broadcasts, webhooks and insights.'],
                    ['Does it support WhatsApp, Instagram and Messenger?', 'Those channels follow once the required Meta approvals are in place. Until then Respond works with Telegram, web chat and email.'],
                    ['How does the AI assistant know what to say?', 'It answers <strong>only</strong> from the FAQs, notes and web pages you add to the knowledge base. If your knowledge does not cover a question, it hands over to your team instead of guessing.'],
                    ['What are Off, Suggest and Auto?', '<strong>Off</strong>: your team answers. <strong>Suggest</strong>: the assistant drafts a reply and you press Use this reply. <strong>Auto</strong>: it answers by itself.'],
                    ['When does the assistant hand over to a person?', 'When a customer asks for a person, makes a complaint, raises something sensitive such as fraud or a legal threat, tries to change its instructions, or when nothing in your knowledge answers the question.'],
                    ['Is there a limit on AI replies?', 'Yes: a daily limit you set, plus a monthly allowance that is part of your plan and shared with the inbox assistant.'],
                    ['What is the 24-hour window?', 'On channels such as WhatsApp you can reply freely within 24 hours of the customer\'s last message. After that only approved templates are allowed, and Respond tells you when the window has closed.'],
                    ['Who receives broadcasts?', 'Only people marked as having agreed to hear from you. Anyone who replies STOP is removed immediately.'],
                    ['What are flows?', 'A flow starts on a trigger (a first message, a keyword or the time of day) and runs steps such as sending a message, waiting, tagging or adding a private note. You can test a flow before turning it on.'],
                    ['Can I see what the assistant did?', 'Yes. The AI assistant tab lists recent decisions with the reason, and Insights shows how many were answered, suggested or handed over.'],
                    ['Can my website visitors chat with me?', 'Yes. Create a web chat in Channels, set your website address, greeting and colour, and paste one line of code into your site.'],
                ],
            ],
            [
                'slug' => 'handbooks', 'title' => 'Travel Handbooks', 'eyebrow' => 'Section XVII',
                'blurb' => 'Book-length guides with a reader that can read aloud.',
                'tag_label' => 'Browse handbooks', 'tag_route' => 'handbooks.store', 'sort_order' => 17, 'is_active' => true,
                'items' => [
                    ['What is a handbook?', 'A book-length guide for people whose journey starts in Africa, for example studying or renting in the UK, available as an EPUB or PDF and readable in the app.'],
                    ['Can the app read it aloud?', 'Yes. The reader has a read-aloud mode, adjustable text and a table of contents.'],
                    ['Are handbooks included with Naara Pro?', 'When your plan includes handbooks you can unlock a set number of titles while the plan is active. Titles can also be bought separately.'],
                    ['How do you keep the facts correct?', 'Every fee, date and rule is checked against official sources and marked with the book\'s date. Anything we cannot confirm is flagged for verification.'],
                    ['What if I bought the wrong book?', 'Submit a refund request from your library. Requests are reviewed by the team.'],
                ],
            ],
            [
                'slug' => 'partners', 'title' => 'White Label, Widgets & Developers', 'eyebrow' => 'Section XVIII',
                'blurb' => 'Run Naara under your own brand, embed it on your site, or build on the API.',
                'tag_label' => 'Developers', 'tag_route' => 'developers', 'sort_order' => 18, 'is_active' => true,
                'items' => [
                    ['What is Naara White Label?', 'A complete, installable version of the platform you run under your own name, colours and domain, on a VPS or shared cPanel hosting. You choose a licence plan, pay, receive a key and install.'],
                    ['Can I get a custom quote?', 'Yes. Send a custom order and the team replies with a quote you can accept and pay in the same flow.'],
                    ['What do I need to bring?', 'Your own provider accounts and payment gateways. The platform connects to them, but the relationships and balances are yours.'],
                    ['What are Naara Widgets?', 'An embed that shows a Naara catalogue on your own site, with a hosted checkout and a WordPress plugin. Orders are tracked per widget key.'],
                    ['Do visitors need an account to buy from a widget?', 'No. The hosted checkout supports guest purchases.'],
                    ['How do I trust a webhook from Naara?', 'Every delivery is signed. Recompute the HMAC of the timestamp and raw body with your secret, compare in constant time, and reject old timestamps.'],
                    ['Can I point a webhook at an internal address?', 'No. Webhook addresses must be public https addresses; private network addresses are refused.'],
                    ['Is there a sandbox?', 'Provider integrations support sandbox keys. Test with those before using real balances.'],
                ],
            ],
            [
                'slug' => 'trust', 'title' => 'Calls, Privacy & Trust', 'eyebrow' => 'Section XIX',
                'blurb' => 'What is private, what is recorded and how we handle your data.',
                'tag_label' => 'Privacy', 'tag_route' => 'support', 'sort_order' => 19, 'is_active' => true,
                'items' => [
                    ['Does Naara read my chats?', 'Naara does not use your private chats to advertise to you. Business conversations in the Business Suite belong to the workspace, and AI features only read the conversation you ask them to work on.'],
                    ['Is the AI assistant a real person?', 'No. It says it is an AI if asked, and it hands over to a person when it cannot help.'],
                    ['What does the AI do with my customers\' messages?', 'It reads the message and your knowledge base to write a reply. It cannot send money, change accounts or call anyone, and a daily limit and plan allowance cap its use.'],
                    ['Can I export or delete my data?', 'Yes. You can request a data export, deactivate your account, or request deletion. Deletion is approved by a super admin and reaches backups.'],
                    ['How are reviews requested?', 'After a real success, such as an eSIM that connected, we may ask once whether you would like to leave a review. You can dismiss it and we will not nag.'],
                    ['Who can see call recordings?', 'Only people in the workspace with the right role. Recording is off by default and announced to every caller.'],
                ],
            ],
        ];
    }
}
