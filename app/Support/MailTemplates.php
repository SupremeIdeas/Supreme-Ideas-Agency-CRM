<?php

namespace App\Support;

use App\Models\MailTemplateOverride;
use Illuminate\Support\Facades\Cache;

/**
 * Email Studio template registry + override resolver (NAARA-BUILD-20 §3.1). The
 * transactional Blade templates stay the real defaults; admin edits are stored as
 * overrides and merged in here. Every accessor falls back to the Blade default, so
 * a template can never break because no override exists.
 *
 * A special 'global' key holds cross-template styling (accent colour).
 */
class MailTemplates
{
    public const GLOBAL_KEY = 'global';

    private const CACHE_KEY = 'mail.template.overrides';

    private const BRAND_ACCENT = '#0A6E6E';

    /** In-memory preview override (Email Studio live preview of unsaved edits). */
    private static ?array $preview = null;

    /** Temporarily layer unsaved form fields over the stored override for a render. */
    public static function preview(?string $key, ?array $fields): void
    {
        self::$preview = ($key !== null) ? [$key => $fields] : null;
    }

    /**
     * The admin-editable transactional templates: the practical set an operator
     * actually tunes. Each carries its Blade view, default subject/heading, and
     * sample data for the live preview.
     *
     * @return array<string, array{label:string, view:string, subject:string, heading:string, sample:array<string,mixed>}>
     */
    public static function templates(): array
    {
        $app = config('app.name', 'NaaraSim');

        $all = [
            'verify' => [
                'label' => 'Email verification', 'view' => 'emails.verify',
                'subject' => 'Confirm your email', 'heading' => 'Welcome, Ada — one quick step',
                'sample' => ['name' => 'Ada', 'url' => 'https://example.com/verify/demo'],
            ],
            'welcome' => [
                'label' => 'Welcome', 'view' => 'emails.welcome',
                'subject' => 'Welcome to '.$app, 'heading' => 'Welcome to '.$app,
                'sample' => ['name' => 'Ada', 'url' => 'https://example.com/dashboard'],
            ],
            'reset' => [
                'label' => 'Password reset', 'view' => 'emails.reset',
                'subject' => 'Reset your password', 'heading' => 'Reset your password',
                'sample' => ['name' => 'Ada', 'url' => 'https://example.com/reset/demo', 'expires' => 60],
            ],
            'order-placed' => [
                'label' => 'Order confirmation', 'view' => 'emails.order-placed',
                'subject' => 'Your order is confirmed', 'heading' => 'Order confirmed',
                'sample' => ['name' => 'Ada', 'product' => 'esim', 'itemName' => 'Nigeria 5GB / 30 days', 'amount' => 12.50, 'currency' => 'USD', 'url' => 'https://example.com/dashboard'],
            ],
            'top-up' => [
                'label' => 'Wallet top-up', 'view' => 'emails.top-up',
                'subject' => 'Your wallet has been topped up', 'heading' => 'Wallet topped up',
                'sample' => ['name' => 'Ada', 'amount' => 20.00, 'currency' => 'USD', 'newBalance' => 32.50, 'gateway' => 'Paystack', 'url' => 'https://example.com/wallet'],
            ],
            'refund' => [
                'label' => 'Refund', 'view' => 'emails.refund',
                'subject' => 'Your refund has been processed', 'heading' => 'Refund processed',
                'sample' => ['name' => 'Ada', 'amount' => 5.00, 'currency' => 'USD', 'reason' => 'Order could not be fulfilled', 'url' => 'https://example.com/wallet'],
            ],

            // ---- Notifications that are branded and editable here (Bootstrap: every user-facing mail notification has a template). ----
            'account-lifecycle' => [
                'label' => 'Account deactivated / reactivated / deletion (all variants)', 'view' => 'emails.account-lifecycle',
                'subject' => 'Your account is deactivated', 'heading' => 'Your account is deactivated',
                'sample' => ['action' => 'deactivated', 'name' => 'Ada', 'heading' => 'Your account is deactivated', 'url' => 'https://example.com/account'],
            ],
            'kyc-result' => [
                'label' => 'Identity verification result', 'view' => 'emails.kyc-result',
                'subject' => 'You\'re verified', 'heading' => 'Identity verified',
                'sample' => ['name' => 'Ada', 'approved' => true, 'level' => 1, 'reason' => null, 'url' => 'https://example.com/account/verification'],
            ],
            'new-device-login' => [
                'label' => 'New sign-in alert', 'view' => 'emails.new-device-login',
                'subject' => 'New sign-in to your account', 'heading' => 'New sign-in to your account',
                'sample' => ['ipAddress' => '203.0.113.7', 'userAgent' => 'Chrome on macOS', 'when' => '6 Oct 2026, 09:30 UTC', 'url' => 'https://example.com/account/security', 'alertImageUrl' => null],
            ],
            'password-changed' => [
                'label' => 'Password changed', 'view' => 'emails.password-changed',
                'subject' => 'Your password was changed', 'heading' => 'Your password was changed',
                'sample' => ['when' => '6 Oct 2026, 09:30 UTC'],
            ],
            'two-factor' => [
                'label' => 'Two-factor on / off', 'view' => 'emails.two-factor-changed',
                'subject' => 'Two-factor authentication enabled', 'heading' => 'Two-factor authentication enabled',
                'sample' => ['enabled' => true, 'when' => '6 Oct 2026, 09:30 UTC'],
            ],
            'port-out' => [
                'label' => 'Port-out requested', 'view' => 'emails.port-out-requested',
                'subject' => 'Your port-out request', 'heading' => 'Port-out requested',
                'sample' => ['name' => 'Ada', 'phoneNumber' => '+15551234567', 'url' => 'https://example.com/numbers/lines'],
            ],
            'spam-blocked' => [
                'label' => 'Reported number blocked', 'view' => 'emails.spam-blocked',
                'subject' => 'A number you reported has been blocked', 'heading' => 'Number blocked',
                'sample' => ['name' => 'Ada', 'phoneNumber' => '+15551234567', 'url' => 'https://example.com/contacts'],
            ],
            'staff-account' => [
                'label' => 'Staff access granted / changed / removed (all variants)', 'view' => 'emails.staff-account',
                'subject' => 'You\'ve been given staff access', 'heading' => 'You now have staff access',
                'sample' => ['action' => 'granted', 'name' => 'Ada', 'heading' => 'You now have staff access', 'scopes' => ['Support', 'Orders'], 'url' => 'https://example.com/account'],
            ],
            'shared-plan-invite' => [
                'label' => 'Shared plan invite', 'view' => 'emails.wallet-group-invited',
                'subject' => 'You\'ve been invited to a shared plan', 'heading' => 'Shared plan invite',
                'sample' => ['name' => 'Ada', 'ownerName' => 'Chidi', 'url' => 'https://example.com/wallet'],
            ],
            'support-reply' => [
                'label' => 'Support replied', 'view' => 'emails.human-replied',
                'subject' => 'A reply from our support team', 'heading' => 'A reply from our support team',
                'sample' => ['url' => 'https://example.com/support'],
            ],
            'event-notice' => self::notice('Account event notices (reward credited, payouts paused, account removed, message not delivered)', 'An update on your account', 'Hi Ada,', ['A short description of what happened appears here.'], ['Open', 'https://example.com/notifications']),
            'payout-status' => self::notice('Withdrawal update', 'Your withdrawal update', 'Hi Ada,', ['Your withdrawal of 5,000.00 NGN was sent to your account.'], ['View withdrawals', 'https://example.com/rewards/withdraw']),
            'payout-requested' => self::notice('Withdrawal requested / payout account changed', 'Withdrawal requested', 'Hi Ada,', ['We received a withdrawal request for 5,000.00 NGN on your account.', 'You can cancel it from your Withdrawals page until it has been sent.', 'If this was you, there is nothing to do.'], ['This wasn\'t me', 'https://example.com/payouts/not-me'], 'That button pauses payouts on your account straight away and tells our team.'),
            'payout-step-up' => self::notice('Payout security code', 'Your security code', 'Hi Ada,', ['Use this code to confirm a payout change. It expires in 10 minutes.', ['code' => '482913']], null, 'If you did not ask for this, ignore this email — nothing will change. Consider changing your password.'),
            'earnings-transfer' => self::notice('Earnings transfer notices', 'You received a transfer request', 'Hi Ada,', ['Chidi would like to send you $5.00 of earnings. Accept it within 48 hours.'], ['Open send earnings', 'https://example.com/account/send-earnings']),
            'brand-billing' => self::notice('Brand listing paused (billing)', 'Your brand listing is paused', 'Hi Ada,', ['We couldn’t renew your **Lagos Beats** listing (Gold plan) — your wallet was short of $9.00.', 'Your listing is paused (not cancelled) and hidden from the directory. Top up and it resumes automatically on the next daily run — no re-approval needed.'], ['Top up & manage', 'https://example.com/brand/manage'], 'Follows and credits already earned are untouched.'),
            'number-renewal' => self::notice('Naara Line renewing / ending', 'Upcoming renewal', null, ['Your number +15551234567 will renew on October 20, 2026 for $3.00, charged to your wallet.', 'No action needed if you want to keep it — just make sure your wallet balance covers it.'], ['Manage this number', 'https://example.com/numbers/lines']),
            'receipt' => self::notice('Purchase receipt', 'Your receipt', 'Thanks for your purchase, Ada!', ['**Nigeria 5GB / 30 days**', 'Amount: $12.50', 'Reference: ord_1042', 'Date: Tue, Oct 6, 2026 9:30 AM'], ['View all receipts', 'https://example.com/receipts'], 'This is a receipt for your records, not a tax invoice.'),
            'merchant-subscription' => self::notice('Merchant client eSIM updates', 'Client subscription update', null, ['The eSIM for Ada Okafor is due in 3 day(s).'], ['Manage clients', 'https://example.com/merchant/clients']),
            'journey-alert' => self::notice('Journey alert (data low, eSIM expiring, renewal)', 'Your data is running low', 'Hi Ada,', ['You have used 91% of your eSIM data. About 450 MB is left.'], ['Top up your data', 'https://example.com/catalogue'], 'Why you are seeing this: your latest usage reading crossed 80%. Manage these alerts: https://example.com/account/alerts'),
            'status-update' => self::notice('Status page incident updates', 'Investigating: slow top-ups', null, ['Status: Investigating', 'Some top-ups are taking longer than usual. We are working on it.'], ['View status page', 'https://example.com/status']),
        ];

        foreach ($all as $key => &$t) {
            if (($t['view'] ?? '') === 'emails.notice') {
                $t['sample']['templateKey'] = $key;
            }
        }
        unset($t);

        return $all;
    }

    /**
     * One entry for a notification that renders through the shared branded body (emails.notice). The sample is exactly the data the
     * view needs, so the Email Studio preview and `naara:mail:preview-all` render the real thing.
     *
     * @param  list<string|array{code: string}>  $lines
     * @param  array{0: string, 1: string}|null  $action
     */
    private static function notice(string $label, string $heading, ?string $greeting, array $lines, ?array $action = null, ?string $footnote = null): array
    {
        return [
            'label' => $label, 'view' => 'emails.notice',
            'subject' => $heading, 'heading' => $heading,
            'sample' => [
                'templateKey' => null, // filled in by templates() from the entry's own key
                'heading' => $heading, 'greeting' => $greeting,
                'lines' => array_map(fn ($l) => is_array($l) ? $l : ['text' => $l], $lines),
                'actionText' => $action[0] ?? null, 'actionUrl' => $action[1] ?? null, 'footnote' => $footnote,
            ],
        ];
    }

    public static function isEditable(string $key): bool
    {
        return $key === self::GLOBAL_KEY || array_key_exists($key, self::templates());
    }

    /** @return array<string, array<string, mixed>> key => override fields */
    private static function map(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, function () {
            try {
                return MailTemplateOverride::all()
                    ->keyBy('template_key')
                    ->map(fn ($o) => $o->only(['subject', 'heading', 'intro', 'button_text', 'accent_color']))
                    ->toArray();
            } catch (\Throwable) {
                return [];
            }
        });
    }

    /** @return array<string, mixed> */
    public static function override(string $key): array
    {
        $base = self::map()[$key] ?? [];
        if (self::$preview !== null && isset(self::$preview[$key])) {
            $base = array_merge($base, array_filter(self::$preview[$key], fn ($v) => $v !== null && $v !== ''));
        }

        return $base;
    }

    public static function subject(string $key, string $default): string
    {
        return filled(self::override($key)['subject'] ?? null) ? self::override($key)['subject'] : $default;
    }

    public static function heading(string $key, ?string $default): ?string
    {
        return filled(self::override($key)['heading'] ?? null) ? self::override($key)['heading'] : $default;
    }

    /** An optional admin lead paragraph (plain text) prepended to the body, or null. */
    public static function intro(string $key): ?string
    {
        $v = self::override($key)['intro'] ?? null;

        return filled($v) ? $v : null;
    }

    public static function buttonText(string $key, string $default): string
    {
        return filled(self::override($key)['button_text'] ?? null) ? self::override($key)['button_text'] : $default;
    }

    /** Accent colour: per-template → global → brand teal. Always a safe hex. */
    public static function accent(string $key): string
    {
        $c = self::override($key)['accent_color'] ?? null;
        if (self::validHex($c)) {
            return $c;
        }
        $g = self::override(self::GLOBAL_KEY)['accent_color'] ?? null;

        return self::validHex($g) ? $g : self::BRAND_ACCENT;
    }

    public static function save(string $key, array $fields): void
    {
        if (! self::isEditable($key)) {
            return;
        }
        $clean = [];
        foreach (['subject', 'heading', 'intro', 'button_text', 'accent_color'] as $f) {
            $v = $fields[$f] ?? null;
            $clean[$f] = is_string($v) ? (trim($v) ?: null) : null;
        }
        if (isset($clean['accent_color']) && ! self::validHex($clean['accent_color'])) {
            $clean['accent_color'] = null;
        }

        MailTemplateOverride::updateOrCreate(['template_key' => $key], $clean);
        self::flush();
    }

    public static function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    private static function validHex(?string $c): bool
    {
        return is_string($c) && preg_match('/^#[0-9a-fA-F]{6}$/', $c) === 1;
    }
}
