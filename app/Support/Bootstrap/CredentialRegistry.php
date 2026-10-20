<?php

namespace App\Support\Bootstrap;

use App\Support\ProviderKeys;
use Illuminate\Support\Facades\Route;

/**
 * Credential slot registry (Bootstrap blueprint 4B.1): ONE read-only view over every third-party credential the platform can take, built on the admin schema that
 * already exists (ProviderKeys::schema(), which is what the admin panel edits) plus the provider blocks in config/services.php. It invents nothing and stores nothing:
 * a slot is "empty" until a value reaches config(), whether pasted in the admin panel or set in .env.
 *
 * Status here is only `empty` or `saved`. A real "verified" needs a no-spend test per provider; that is a separate, per-provider piece of work (see ADMIN-FIRST-DAY.md).
 */
final class CredentialRegistry
{
    /** Providers that are configured on their own admin screens, not in ProviderKeys. provider => where. */
    public const ELSEWHERE = [
        'postmark' => 'Admin > Email settings (mail transport)',
        'ses' => 'Admin > Email settings (mail transport)',
        'resend' => 'Admin > Email settings (mail transport)',
        'slack' => 'Optional log/alert channel (.env only)',
        'whatsapp' => 'Admin > WhatsApp Autopilot',
        'offerwall' => 'Admin > Credits (offerwall postback secret)',
    ];

    /**
     * Webhook / callback URLs the admin pastes INTO the provider's console, by provider. Each entry is [label, route name, route parameters]. Built from named routes
     * and APP_URL at call time, never typed by hand, so they cannot drift from routes/web.php.
     *
     * @var array<string, list<array{0: string, 1: string, 2: array<string, string>}>>
     */
    private const WEBHOOKS = [
        'paystack' => [['Payment webhook', 'webhooks.payments', ['gateway' => 'paystack']]],
        'flutterwave' => [['Payment webhook', 'webhooks.payments', ['gateway' => 'flutterwave']]],
        'stripe' => [['Payment webhook', 'webhooks.payments', ['gateway' => 'stripe']], ['Connect account webhook', 'webhooks.stripe-connect.account', []]],
        'paypal' => [['Payment webhook', 'webhooks.payments', ['gateway' => 'paypal']]],
        'binance' => [['Payment webhook', 'webhooks.payments', ['gateway' => 'binance']]],
        'nowpayments' => [['Payment webhook (IPN)', 'webhooks.payments', ['gateway' => 'nowpayments']], ['Payout webhook (IPN)', 'webhooks.payouts', ['provider' => 'nowpayments']]],
        'grey' => [['Payout webhook', 'webhooks.payouts', ['provider' => 'grey']]],
        'cryptomus' => [['Payment webhook', 'webhooks.payments', ['gateway' => 'cryptomus']]],
        'coinpayments' => [['Payment webhook (IPN)', 'webhooks.payments', ['gateway' => 'coinpayments']]],
        'payssion' => [['Payment webhook', 'webhooks.payments', ['gateway' => 'payssion']]],
        'smileid' => [['KYC callback', 'webhooks.kyc', ['provider' => 'smileid']]],
        'dojah' => [['KYC callback', 'webhooks.kyc', ['provider' => 'dojah']]],
        'getatext' => [['Inbound SMS / OTP webhook', 'webhooks.getatext', []]],
        'twilio' => [['Inbound SMS', 'webhooks.sms-inbound', ['provider' => 'twilio']], ['Inbound voice', 'webhooks.twilio.voice', []], ['Voicemail recording', 'webhooks.twilio.recording', []], ['Browser dialer (TwiML app voice URL)', 'webhooks.twilio.dial', []], ['Dial status callback', 'webhooks.twilio.dial-status', []]],
        'telnyx' => [['Inbound SMS', 'webhooks.sms-inbound', ['provider' => 'telnyx']], ['Inbound voice (TeXML application voice URL)', 'webhooks.telnyx.voice', []], ['Voicemail recording', 'webhooks.telnyx.recording', []]],
        'africastalking' => [['Delivery reports (add your token as ?token=)', 'webhooks.sms-delivery', ['provider' => 'africastalking']]],
        'fivesim' => [['Inbound SMS', 'webhooks.sms-inbound', ['provider' => 'fivesim']]],
        'herosms' => [['Inbound SMS', 'webhooks.sms-inbound', ['provider' => 'herosms']]],
        'plivo' => [['Inbound SMS', 'webhooks.sms-inbound', ['provider' => 'plivo']]],
        'vonage' => [['Inbound SMS', 'webhooks.sms-inbound', ['provider' => 'vonage']]],
        'sinch' => [['Inbound SMS', 'webhooks.sms-inbound', ['provider' => 'sinch']]],
        'reloadly' => [['Gift-card webhook', 'webhooks.giftcards', ['provider' => 'reloadly']]],
        'bitrefill' => [['Gift-card webhook', 'webhooks.giftcards', ['provider' => 'bitrefill']]],
        'tillo' => [['Gift-card webhook', 'webhooks.giftcards', ['provider' => 'tillo']]],
        'whatsapp' => [['WhatsApp webhook (verify + inbound)', 'webhooks.whatsapp', []]],
        'offerwall' => [['Offerwall postback', 'webhooks.offerwall', []]],
        'google' => [['OAuth redirect URI', 'social.callback', ['provider' => 'google']]],
        'facebook' => [['OAuth redirect URI', 'social.callback', ['provider' => 'facebook']]],
        'twitter' => [['OAuth redirect URI', 'social.callback', ['provider' => 'twitter']]],
        'apple' => [['OAuth redirect URI', 'social.callback', ['provider' => 'apple']]],
        'microsoft' => [['OAuth redirect URI', 'social.callback', ['provider' => 'microsoft']]],
        'discord' => [['OAuth redirect URI', 'social.callback', ['provider' => 'discord']]],
    ];

    /**
     * Named webhook routes that are intentionally not tied to one provider slot (internal callers). The call-flow routes are written into the
     * TwiML the app itself returns (Twilio is never told to call them by hand); the Telegram and Meta callbacks are per Business channel and
     * shown on the Business Channels screen next to the connection they belong to.
     */
    public const INTERNAL_WEBHOOK_ROUTES = [
        'webhooks.appbuild', 'webhooks.payouts',
        'webhooks.twilio.call-flow', 'webhooks.twilio.call-flow.digits', 'webhooks.twilio.call-flow.dial', 'webhooks.twilio.call-flow.whisper',
        'webhooks.twilio.ai-agent.continue', 'webhooks.twilio.call-recording.status',
        'webhooks.telegram', 'webhooks.meta',
    ];

    /**
     * @return array<string, array{id: string, group: string, group_label: string, label: string, fields: list<array{label: string, config: string, env: string, secret: bool, set: bool}>, status: string, webhooks: list<array{label: string, url: string}>}>
     */
    public static function slots(): array
    {
        $out = [];
        foreach (ProviderKeys::schema() as $group => $g) {
            foreach ($g['fields'] as $f) {
                if (! preg_match('/^services\.([a-z0-9_]+)\./', $f['config'], $m)) {
                    continue;
                }
                $id = $m[1];
                $out[$id] ??= ['id' => $id, 'group' => $group, 'group_label' => $g['label'], 'label' => trim(explode('—', $f['label'])[0]), 'fields' => [], 'status' => 'empty', 'webhooks' => []];
                $out[$id]['fields'][] = ['label' => $f['label'], 'config' => $f['config'], 'env' => $f['env'], 'secret' => (bool) $f['secret'], 'set' => self::filled(config($f['config']))];
            }
        }
        foreach ($out as $id => &$slot) {
            $secrets = array_filter($slot['fields'], fn ($f) => $f['secret']);
            $check = $secrets ?: $slot['fields'];
            $slot['status'] = array_filter($check, fn ($f) => $f['set']) ? 'saved' : 'empty';
            $slot['webhooks'] = self::webhooksFor($id);
        }
        unset($slot);
        ksort($out);

        return $out;
    }

    /** @return list<array{label: string, url: string}> */
    public static function webhooksFor(string $provider): array
    {
        $rows = [];
        foreach (self::WEBHOOKS[$provider] ?? [] as [$label, $route, $params]) {
            if (Route::has($route)) {
                // Built from APP_URL (what the provider will call), not from whichever host the admin happens to be browsing on.
                $rows[] = ['label' => $label, 'url' => rtrim((string) config('app.url'), '/').route($route, $params, false)];
            }
        }

        return $rows;
    }

    /** Named webhook/callback routes that no slot mentions and that are not marked internal: the gap CI should catch. @return list<string> */
    public static function uncoveredWebhookRoutes(): array
    {
        $covered = [];
        foreach (self::WEBHOOKS as $rows) {
            foreach ($rows as [, $route]) {
                $covered[$route] = true;
            }
        }
        $gap = [];
        foreach (Route::getRoutes()->getRoutesByName() as $name => $r) {
            if ((str_starts_with($name, 'webhooks.') || $name === 'social.callback') && ! isset($covered[$name]) && ! in_array($name, self::INTERNAL_WEBHOOK_ROUTES, true)) {
                $gap[] = $name;
            }
        }

        return $gap;
    }

    private static function filled(mixed $v): bool
    {
        return is_string($v) ? trim($v) !== '' : ! empty($v);
    }
}
