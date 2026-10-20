<?php

namespace Database\Seeders;

use App\Models\FaqCategory;
use App\Models\FaqItem;
use App\Support\SiteContent;
use Illuminate\Database\Seeder;

/**
 * The dedicated /faq page's 12 topics and their Q&A content (owner-supplied
 * design handoff, 2026-09-21 — see the original `naara-faq.html` for the
 * full page design this seeds). Content was corrected against this repo's
 * actual routes/features before seeding, not copied verbatim:
 *
 *   - `/security` -> `/account/security` and `/privacy`/`/terms`/`/cookies`/
 *     `/data-deletion` -> `/legal/{slug}` (the source file's links didn't
 *     match this app's real route paths).
 *   - "Can I move my Naara Line to another provider?" now points to the
 *     REAL self-service `requestPortOut()` action on My Lines, not a
 *     "contact Support" flow that isn't how the feature is actually built.
 *   - "Can I run Naara under my own brand?" rewritten to reference the
 *     real self-service License Plans (Prompt 21-EXT), not a "wizard
 *     setup fee" concept that predates that rebuild and no longer exists.
 *   - Dynamic facts (OTP refund timeout, NaaraCredits earn rate, the
 *     cheapest live white-label plan price, the support WhatsApp link) are
 *     seeded as `{{token}}` placeholders — see `App\Support\FaqTokens`,
 *     which resolves them at render time so they can never drift stale.
 *
 * Idempotent on category slug / (category, question) so re-running never
 * duplicates rows; safe to run on every deploy like every other seeder here.
 */
class FaqSeeder extends Seeder
{
    public function run(): void
    {
        // An upgrade passes seed.only_missing so admin edits to existing rows are never overwritten; a plain db:seed still refreshes everything.
        $op = config('seed.only_missing') ? 'firstOrCreate' : 'updateOrCreate';
        $this->seedHeroImageOnce();

        foreach (self::categories() as $catData) {
            $items = $catData['items'];
            unset($catData['items']);

            $category = FaqCategory::{$op}(['slug' => $catData['slug']], $catData);

            foreach ($items as $i => [$q, $a]) {
                FaqItem::{$op}(
                    ['faq_category_id' => $category->id, 'question' => $q],
                    ['answer' => $a, 'sort_order' => $i, 'is_active' => true]
                );
            }
        }
    }

    /** @return array<int, array{slug:string, title:string, eyebrow:string, blurb:string, tag_label:string, tag_route:string, sort_order:int, is_active:bool, items:array<int,array{0:string,1:string}>}> */
    /**
     * Ships the real default hero background (owner-supplied, 2026-09-21)
     * as a SiteContent OVERRIDE, not a coded default — `SiteContent::page()`
     * force-blanks the generic 'image' key back to '' unless an override
     * exists (the same "no image until admin uploads one" rule About/
     * Contact/How-it-works all follow), so shipping a real default has to
     * go through the same path a real upload would. Only seeds once: if an
     * admin has already set (or explicitly cleared) the hero image, this
     * never overwrites their choice on a later seeder re-run.
     */
    private function seedHeroImageOnce(): void
    {
        $overrides = SiteContent::overrides('faq');
        if (array_key_exists('hero', $overrides)) {
            return;
        }

        $overrides['hero'] = array_merge($overrides['hero'] ?? [], ['image' => '/images/faq/faq-hero-bg.webp']);
        SiteContent::saveOverrides('faq', $overrides);
    }

    private static function categories(): array
    {
        return [
            [
                'slug' => 'start', 'title' => 'Getting Started', 'eyebrow' => 'Section I',
                'blurb' => 'What Naara is, how the pieces fit together, and how to get connected fast.',
                'tag_label' => 'Explore Pricing', 'tag_route' => 'pricing', 'sort_order' => 1, 'is_active' => true,
                'items' => [
                    ['What is Naara?', 'Naara is a Pan-African connectivity platform. In one app and one wallet you can buy <strong>eSIM data plans for 190+ countries</strong> and <strong>phone numbers</strong> (verification numbers and a permanent line). Our promise: <em>Stay Connected. No Borders. No Swaps.</em>'],
                    ['What is the difference between Naara, NaaraSim and Naara Gift?', 'Naara is the brand and platform. <strong>NaaraSim</strong> is the eSIM and phone-number side. <strong>Naara Gift</strong> is the gift-card side, available where enabled for your account. They all draw from the same wallet.'],
                    ['Do I need an account to buy?', 'Yes. Creating an account is free, and one login covers eSIMs, numbers, gift cards, rewards and your wallet.'],
                    ['How does the wallet work, in one line?', 'You fund one wallet, and you spend from it on anything in Naara. There is nothing to re-enter for each purchase.'],
                    ['How fast can I get connected?', 'Purchase to connected usually takes <strong>under three minutes</strong>: buy a plan, scan the QR code, and your data starts when you first connect in your destination.'],
                    ['Which countries are covered?', 'eSIM plans cover <strong>190+ countries</strong>, plus regional bundles. Search your destination on the <a href="/pricing">Pricing page</a> to see the exact plans and network coverage before you pay.'],
                    ['Is there a mobile app?', 'Naara works in any modern browser and can be added to your home screen. A native app is offered where it has been published; look for the download links on the site.'],
                    ['Something looks wrong. Is Naara down?', 'Check the live <a href="/status">status page</a> first. If everything is green and the problem persists, message <a href="/support">Support</a> with your order reference.'],
                ],
            ],
            [
                'slug' => 'esim', 'title' => 'eSIM Data & Naara Connect', 'eyebrow' => 'Section II',
                'blurb' => 'Compatibility, installation, activation, coverage and what to do when data does not flow.',
                'tag_label' => 'Browse eSIM Plans', 'tag_route' => 'pricing', 'sort_order' => 2, 'is_active' => true,
                'items' => [
                    ['What is an eSIM?', 'An eSIM is a digital SIM built into your phone. Instead of swapping a plastic card, you install a profile by scanning a QR code, and your physical SIM stays in place.'],
                    ['Will my phone work with an eSIM?', 'Most phones released from around 2018 do, but it must also be <strong>carrier-unlocked</strong>. Not sure? Use the <a href="/how-it-works">device compatibility checker</a> to confirm before you buy — on many phones, dialing <strong>*#06#</strong> also shows an EID number when eSIM is supported.'],
                    ['How do I install my eSIM?', 'After purchase, open the order and scan the <strong>QR code</strong> from another screen, or use the manual details (SM-DP+ address and activation code) in your phone\'s eSIM settings. Installing takes about a minute and needs internet.'],
                    ['Can I install it before I travel?', 'Yes, and we recommend it. Install at home on Wi-Fi, then switch the eSIM on when you land. Your plan\'s validity normally starts when you first connect to a network in the covered destination.'],
                    ['Will I keep my regular number and WhatsApp?', 'Yes. Naara eSIMs are for mobile data, so your main SIM number stays active for WhatsApp and SMS codes. Set the Naara eSIM as your data line.'],
                    ['Can I make normal phone calls with a data eSIM?', 'Data eSIMs carry data only. Use WhatsApp, FaceTime or another app for calls. <strong>Naara Connect</strong> (calls plus data) and the <a href="/numbers/lines">Naara Line</a> number are available where enabled.'],
                    ['I installed it but have no data. What now?', 'Try these in order: turn <strong>Data Roaming</strong> on for the eSIM line, select the eSIM as your mobile data line, restart the phone, and toggle airplane mode. If it still fails, message <a href="/support">Support</a> with your order reference.'],
                    ['What does "unlimited" mean on unlimited plans?', 'Unlimited plans are subject to a <strong>fair-usage policy</strong>: after a daily or total high-speed allowance, speeds may be reduced. The exact allowance is shown on each plan before you buy.'],
                    ['Can I use my phone as a hotspot?', 'It depends on the plan and network. Tethering rules are shown in the plan details, so check them before you buy if you need a hotspot.'],
                    ['I am running out of data. Can I top up the same eSIM?', 'At the moment you add data by <strong>buying another plan</strong>. You can keep both eSIM profiles on your phone and switch between them.'],
                    ['How much data will I need?', 'Use the <strong>data estimator</strong> on the Pricing page. Maps and messaging use little, while video calls and streaming use a lot. If in doubt, pick one size up.'],
                    ['When does my plan expire?', 'Each plan has a validity period (for example 7 or 30 days) and a data allowance. The plan ends when either is used up. The dates are visible on your order page.'],
                    ['Can I delete an eSIM I no longer need?', 'Yes, from your phone\'s eSIM settings. <strong>Do not delete it before the plan is finished</strong>, because most eSIM profiles cannot be reinstalled once removed.'],
                    ['Can I refund an eSIM?', 'A bundle that has <strong>not been started</strong> is refundable to your wallet. Once it has been activated and data has begun, it is consumed. Read the full <a href="/refund-policy">Refund Policy</a>.'],
                ],
            ],
            [
                'slug' => 'verify', 'title' => 'Verification Numbers & Rentals', 'eyebrow' => 'Section III',
                'blurb' => 'Numbers for receiving one-time codes, how the automatic refund works, and what to do when a code is late.',
                'tag_label' => 'Get a Number', 'tag_route' => 'numbers', 'sort_order' => 3, 'is_active' => true,
                'items' => [
                    ['What is a verification number?', 'A temporary phone number you use to receive an SMS code when signing up for or verifying an online service, without exposing your personal number.'],
                    ['How do I get a code?', 'Open <a href="/numbers">Numbers</a>, choose the service and country, and order. The number appears at once; enter it in the app or site, then wait for the code to show up in your Naara order.'],
                    ['What if my code never arrives?', 'If no code arrives within <strong>{{otp_timeout}} minutes</strong> of ordering, your wallet is <strong>automatically refunded</strong>. There is no ticket and no waiting for approval. See the <a href="/refund-policy">Refund Policy</a>.'],
                    ['Can I cancel before the code arrives?', 'Yes. Cancelling an order that has not received a code returns the amount to your wallet.'],
                    ['Which services and countries are supported?', 'The list changes as services and availability change. The service and country pickers on the Numbers page show what is available right now.'],
                    ['Why did the service say my number was invalid?', 'Some services block certain numbers. Cancel to get an automatic refund and try again, or choose another country. Different countries often work better for different services.'],
                    ['Can I reuse a number for the same service?', 'Standard verification numbers are for one code. If you need the same number again, look at the rental options where offered.'],
                    ['What is a number rental?', 'A rental holds a number for a set period so it can receive messages more than once. The rental length and price show before you confirm.'],
                    ['Can a number receive voice calls?', 'Verification numbers are built for SMS codes. For calls, use a <a href="/numbers/lines">Naara Line</a>, a permanent number with calling.'],
                    ['Are there rules for what I can use numbers for?', 'Yes. Numbers must be used lawfully and within each service\'s terms. Misuse can lead to a suspended account. See our <a href="/legal/terms">Terms</a>.'],
                ],
            ],
            [
                'slug' => 'line', 'title' => 'Naara Line (Permanent Number)', 'eyebrow' => 'Section IV',
                'blurb' => 'A number that stays yours: calls, texts, forwarding, voicemail, renewal and porting.',
                'tag_label' => 'Manage My Lines', 'tag_route' => 'numbers.lines', 'sort_order' => 4, 'is_active' => true,
                'items' => [
                    ['What is a Naara Line?', 'A permanent phone number you keep, for calls and text messages, managed inside Naara. Unlike a verification number, it is yours for as long as you renew it.'],
                    ['How do I make and receive calls?', 'Use the in-app <a href="/numbers/dialer">dialer</a> and <a href="/numbers/messages">messages</a> screens. Incoming calls ring in the app, or forward to another number you choose.'],
                    ['Can I forward calls to my other phone?', 'Yes. Set your forwarding rules on the <a href="/numbers/forwarding">Call Forwarding</a> page.'],
                    ['Does my line have voicemail?', 'Yes. Callers who cannot reach you can leave a message, which appears in the app, transcribed to text.'],
                    ['Can I save contacts?', 'Yes. Contacts are kept in your account so names appear on calls and messages.'],
                    ['How does renewal work?', 'A line renews from your wallet balance. Keep enough funds in your wallet before the renewal date and the line stays active. Auto-renew can be switched off on the line settings.'],
                    ['What if the renewal payment fails?', 'Add funds to your wallet and renew as soon as you can. A line that is not renewed in time can be released, so do not let the balance run short.'],
                    ['Can I bring my existing number to Naara?', 'For eligible US and Canadian numbers, yes, through <a href="/numbers/port-in">Port-in</a>. Submit the request and follow the steps shown. Your current provider must release the number.'],
                    ['Can I move my Naara Line to another provider?', 'Where porting out is supported, request it directly from the line\'s own menu on <a href="/numbers/lines">My Lines</a>. Settle the balance and keep the line active until the transfer completes.'],
                    ['Can I use it for emergency calls?', 'Please do not rely on an internet-based line for emergency services. Keep a regular mobile SIM available for emergency calls.'],
                ],
            ],
            [
                'slug' => 'gift', 'title' => 'Naara Gift', 'eyebrow' => 'Section V',
                'blurb' => 'Gift cards you buy from your wallet and deliver in the app, where the service is enabled.',
                'tag_label' => 'Open Gift Cards', 'tag_route' => 'gift-cards', 'sort_order' => 5, 'is_active' => true,
                'items' => [
                    ['What is Naara Gift?', 'Naara Gift lets you buy digital gift cards from popular brands using your Naara wallet, without a separate payment step.'],
                    ['Is Naara Gift available to me?', 'Availability depends on your account and region. If you see <a href="/gift-cards">Gift Cards</a> in your menu, it is available to you.'],
                    ['How do I buy a gift card?', 'Open Gift Cards, choose a brand and amount, and confirm from your wallet. Your card details appear in the order.'],
                    ['How fast is delivery?', 'Most cards are delivered within minutes. If yours is still processing, the order page shows its status.'],
                    ['Can I check a card balance?', 'Where the brand supports it, use the balance option on the order page.'],
                    ['Can I get a refund on a gift card?', 'Gift cards are generally <strong>final once the code has been shown or delivered</strong>. If a card fails, is invalid, or never arrives, contact <a href="/support">Support</a> with the order reference.'],
                    ['Can I send a card as a present?', 'Yes. Buy it, then share the details with the person you want to gift. Treat the code like cash and only send it through a channel you trust.'],
                ],
            ],
            [
                'slug' => 'wallet', 'title' => 'Wallet & Payments', 'eyebrow' => 'Section VI',
                'blurb' => 'One wallet, multiple ways to fund it, and clear pricing at every step.',
                'tag_label' => 'Open My Wallet', 'tag_route' => 'wallet', 'sort_order' => 6, 'is_active' => true,
                'items' => [
                    ['How does the Naara wallet work?', 'Your wallet holds a single balance. You fund it, then spend on eSIMs, numbers, gift cards and renewals. Refunds also return here.'],
                    ['Which currencies do you show?', 'Your balance is kept in USD, and prices are shown in USD and NGN so you can compare easily.'],
                    ['How can I add money?', 'Cards and bank transfer are available. In some countries, such as Ghana and Kenya, mobile money is also available. The options at checkout depend on where you are.'],
                    ['Is there a minimum top-up?', 'A token minimum applies mainly to stop $0 top-ups — see the exact figure on the <a href="/wallet">Wallet page</a> when you top up.'],
                    ['What does the "Taxes &amp; fees $0.00" line mean?', 'It confirms that what you see is what you pay. There are no hidden add-ons at checkout, and the total shown is the amount taken from your wallet.'],
                    ['My payment was taken but my wallet was not credited.', 'Wait a few minutes, since bank and card confirmations can be slightly delayed. If it still has not arrived, contact <a href="/support">Support</a> with your payment reference and the time you paid.'],
                    ['Can I withdraw my wallet balance?', 'Your wallet is for spending in Naara. Reward and partner earnings have their own <a href="/rewards/withdraw">withdrawal</a> option where enabled.'],
                    ['Is my card information stored by Naara?', 'Card payments are handled by secure payment providers. Naara does not keep your full card number.'],
                    ['Why was my payment declined?', 'Common reasons include insufficient funds, a bank security block, or a mismatched card country. Try another method or contact your bank, and message us if it keeps happening.'],
                ],
            ],
            [
                'slug' => 'pricing', 'title' => 'Pricing & Fairness', 'eyebrow' => 'Section VII',
                'blurb' => 'How prices are shown and what is (and is not) added at checkout.',
                'tag_label' => 'See Pricing', 'tag_route' => 'pricing', 'sort_order' => 7, 'is_active' => true,
                'items' => [
                    ['Where can I see all prices?', 'On the <a href="/pricing">Pricing page</a>. It lists a curated set of plans by destination, with a link to browse the full catalogue by data amount, validity and price.'],
                    ['Are there hidden fees?', 'No. The price you confirm is the price you pay, and the checkout shows taxes and fees as $0.00.'],
                    ['Why do prices differ between destinations?', 'Network costs and demand differ by country, so price and data amounts vary. Regional bundles can be cheaper when you visit several countries.'],
                    ['Can prices change after I buy?', 'No. Your price is locked when you confirm the order.'],
                    ['Do you have promotions and coupons?', 'Yes, from time to time. Apply a coupon at checkout, and see banners on the home page or Rewards page for current offers.'],
                    ['Why is the NGN price slightly different from last week?', 'NGN prices follow the current exchange rate, so they can move a little. The USD price is the anchor.'],
                ],
            ],
            [
                'slug' => 'refunds', 'title' => 'Refunds & Reliability', 'eyebrow' => 'Section VIII',
                'blurb' => 'What is refunded, when, and how automatic refunds protect you.',
                'tag_label' => 'Read the Refund Policy', 'tag_route' => 'refund-policy', 'sort_order' => 8, 'is_active' => true,
                'items' => [
                    ['What is your refund policy in short?', 'We aim to be fair and honest. Unstarted eSIMs are refundable, numbers that get no code refund automatically, and approved refunds go back to your wallet. Read the full <a href="/refund-policy">Refund Policy</a>.'],
                    ['When is an eSIM refundable?', 'A bundle that has <strong>not yet been activated</strong> on a device or network can be revoked and refunded to your wallet. After activation and first data use, it is generally not refundable.'],
                    ['How does the automatic number refund work?', 'If no verification code arrives within <strong>{{otp_timeout}} minutes</strong>, your wallet is refunded automatically. No ticket is needed.'],
                    ['Where do refunds go?', 'To your Naara wallet, where you can reuse them immediately on any purchase.'],
                    ['How long do refunds take?', 'Automatic refunds are immediate. Refunds that need review are returned to your wallet after a short review window.'],
                    ['How do I ask for a manual refund?', 'Open <a href="/support">Support</a> and share the <strong>order reference</strong> and what happened. Screenshots help.'],
                    ['What if the service fails through no fault of mine?', 'If a service you paid for could not be delivered, tell us and we will make it right, either with a replacement or a wallet refund.'],
                    ['Is my money safe if a system goes down?', 'Your wallet is recorded as a ledger, and balance changes are atomic, so a failed step does not leave you charged without an order or a refund.'],
                ],
            ],
            [
                'slug' => 'rewards', 'title' => 'NaaraCredits, Rewards & Referrals', 'eyebrow' => 'Section IX',
                'blurb' => 'Earn credits, share Naara with friends, and reduce what you pay.',
                'tag_label' => 'Open Rewards', 'tag_route' => 'rewards', 'sort_order' => 9, 'is_active' => true,
                'items' => [
                    ['What are NaaraCredits?', 'NaaraCredits are loyalty points you earn as you use Naara. They can reduce what you pay on eligible purchases.'],
                    ['How do I earn credits?', 'You earn <strong>{{credit_earn_rate}} credits per $1</strong> spent on eligible purchases, and through activities like referrals and campaigns. Your <a href="/rewards">Rewards page</a> shows your balance and history.'],
                    ['How do I use my credits?', 'At checkout, choose to apply credits. The discount is shown before you confirm.'],
                    ['How do referrals work?', 'Share your link from the <a href="/referrals">Referrals page</a>. When a friend joins and completes a qualifying purchase, you both benefit, as shown there.'],
                    ['What is Brand Hunt?', 'A rewards game where you find and collect brand items for extra credits. It appears on the <a href="/rewards/hunt">Rewards page</a> when a campaign is running.'],
                    ['Can I cash out my rewards?', 'Where enabled, use the <a href="/rewards/withdraw">Withdraw</a> page. Minimum amounts and methods are shown there.'],
                    ['Do credits expire?', 'Any expiry rule is shown next to your balance on the Rewards page, so check it there.'],
                    ['Why were my referral rewards not granted?', 'Rewards are granted after the friend\'s qualifying purchase clears. Self-referrals and duplicate accounts are not eligible.'],
                ],
            ],
            [
                'slug' => 'business', 'title' => 'Merchants, Partners & the Developer API', 'eyebrow' => 'Section X',
                'blurb' => 'Sell Naara under your own brand, earn as a partner, or build on our API.',
                'tag_label' => 'Become a Merchant', 'tag_route' => 'merchant.apply', 'sort_order' => 10, 'is_active' => true,
                'items' => [
                    ['What is a Naara merchant?', 'A merchant resells Naara eSIMs and numbers to their own customers, with a dashboard for clients, earnings and invoices.'],
                    ['How do I become a merchant?', 'Apply on the <a href="/merchant/apply">Become a Merchant</a> page. Once approved, your merchant dashboard is unlocked.'],
                    ['Can I run Naara under my own brand?', 'Yes, through <strong>white-label</strong>: your own name, colours, logo and domain, running on your own licensed copy of the platform. Approved Merchant-V2 accounts pick a plan (from <strong>{{whitelabel_from_price}}</strong>) on the white-label page — nothing is charged until an admin confirms your price.'],
                    ['How do partners earn?', 'Partners earn on activity from customers they bring. Track earnings on the <a href="/partner">Partner Earnings</a> page.'],
                    ['How do I get paid?', 'Earnings are shown in your dashboard and can be withdrawn where enabled, following the minimums and methods listed there.'],
                    ['Is there a developer API?', 'Yes. See the <a href="/developers">Developer docs</a>. After signing in, create and manage keys in your <a href="/developer">Developer Portal</a>.'],
                    ['What can I build with the API?', 'Typical uses are ordering eSIMs, checking order status and balances, and embedding Naara into your own app. The docs list the available endpoints.'],
                    ['Do merchants and API users get support?', 'Yes. Reach out through <a href="/support">Support</a> and mention your merchant or API account.'],
                ],
            ],
            [
                'slug' => 'account', 'title' => 'Account, Security & Privacy', 'eyebrow' => 'Section XI',
                'blurb' => 'Protect your account, control your data, and understand how we handle it.',
                'tag_label' => 'Security Settings', 'tag_route' => 'security', 'sort_order' => 11, 'is_active' => true,
                'items' => [
                    ['How do I secure my account?', 'Use a strong, unique password and switch on <strong>two-factor authentication</strong> in <a href="/account/security">Security</a>.'],
                    ['How does two-factor authentication work?', 'After your password, you enter a time-based code from an authenticator app. Save your recovery codes somewhere safe.'],
                    ['I forgot my password.', 'Use the "Forgot password" link on the login page and follow the email instructions.'],
                    ['Can I see where I am signed in?', 'Yes. Your Security page shows active sessions, and you can sign out of devices you do not recognize.'],
                    ['Why was I asked to verify my identity?', 'Verification protects you and us against fraud and may be needed for higher limits, certain numbers, or withdrawals. It is only requested when necessary.'],
                    ['What data do you collect, and do you sell it?', 'We collect what is needed to run your account and orders. We do not sell your personal data. Full details are in the <a href="/legal/privacy">Privacy Policy</a>.'],
                    ['Can I export or delete my data?', 'Yes. You can request a copy of your data or ask us to delete your account. See <a href="/legal/data-deletion">Data Deletion</a>.'],
                    ['What happens to my wallet if I delete my account?', 'Spend or withdraw eligible balances first. Once an account is deleted, remaining balances cannot be recovered.'],
                    ['Someone else accessed my account.', 'Change your password and sign out of all sessions right away, then message <a href="/support">Support</a> so we can help secure your account.'],
                    ['Do you use cookies?', 'Yes, essential ones to keep you signed in and optional ones to improve the site. See the <a href="/legal/cookies">Cookie Policy</a>.'],
                ],
            ],
            [
                'slug' => 'support', 'title' => 'Support & Troubleshooting', 'eyebrow' => 'Section XII',
                'blurb' => 'How to reach us, what to include, and quick fixes before you contact us.',
                'tag_label' => 'Contact Support', 'tag_route' => 'support', 'sort_order' => 12, 'is_active' => true,
                'items' => [
                    ['How do I contact support?', 'Open <a href="/support">Support</a> in the app. You can type a message, send a voice note, or attach a screenshot.'],
                    ['What is NaaraCare?', 'NaaraCare is our customer-care team and service. It handles orders, payments, refunds and account help.'],
                    ['Who is Nia?', 'Nia is our AI assistant. She answers common questions instantly and passes you to a person when needed.'],
                    ['Can I reach you on WhatsApp?', '{{whatsapp_answer}}'],
                    ['What should I include in my message?', 'Your <strong>order reference</strong>, the device and country, and what you expected versus what happened. A screenshot helps a lot.'],
                    ['How long will I wait for a reply?', 'Nia replies at once. Human replies depend on volume, and urgent issues like a failed eSIM abroad are prioritised.'],
                    ['Where can I see whether there is an outage?', 'Check the <a href="/status">status page</a> for live service information.'],
                    ['Something I need is not answered here.', 'Send us your question. We keep improving this page based on what customers ask.'],
                ],
            ],
        ];
    }
}
