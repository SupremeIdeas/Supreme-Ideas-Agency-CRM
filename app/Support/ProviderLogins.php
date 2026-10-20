<?php

namespace App\Support;

use App\Models\ProviderLogin;

/**
 * The provider login manager's catalogue (MASTER-ONLY): every third-party dashboard the platform depends on, its default login link, and the field that tells us
 * whether its API credentials are saved here. Defaults are a starting point the owner confirms after a fresh install; nothing in this feature stores a password.
 */
final class ProviderLogins
{
    public const METHODS = [
        'google' => 'Google sign-in',
        'email_password' => 'Email and password',
        'username_password' => 'Username and password',
        'sso' => 'Single sign-on',
        'unconfirmed' => 'Not confirmed yet',
    ];

    public const CATEGORIES = [
        'esim' => 'eSIM data', 'numbers' => 'Numbers and SMS', 'voice' => 'Voice and calls', 'giftcards' => 'Gift cards and airtime', 'payments' => 'Payments',
        'crypto' => 'Crypto payments', 'identity' => 'Identity and security', 'ai' => 'AI and media', 'dev' => 'Developer tools', 'other' => 'Other',
    ];

    /** The emails offered in the dropdown on a fresh install (the owner can add more). */
    public const DEFAULT_EMAILS = [
        ['support@naara.app', 'Support'],
        ['supremeideasz@gmail.com', 'Owner'],
        ['naaraconnect@gmail.com', 'Naara Connect'],
    ];

    /** The email used for the providers whose API keys are saved, until the owner confirms each one. */
    public const DEFAULT_EMAIL = 'supremeideasz@gmail.com';

    /**
     * key => [name, category, login link, credential field in ProviderKeys (or null), confirmed method + email (the owner's own words) or null]
     *
     * @return array<string, array{0:string,1:string,2:string,3:?string,4:?array{0:string,1:string}}>
     */
    public static function catalogue(): array
    {
        return [
            // eSIM data (and Full eSIM)
            'esimgo' => ['eSIM Go', 'esim', 'https://sso.esim-go.com/login', 'esimgo_api_key', ['google', 'supremeideasz@gmail.com']],
            'airalo' => ['Airalo Partners', 'esim', 'https://partners.airalo.com/login', 'airalo_client_id', null],
            'quibity' => ['Quibity (eSIM.sm)', 'esim', 'https://esim.sm/login', 'quibity_api_key', null],
            'zendit' => ['Zendit', 'esim', 'https://app.zendit.io/login', 'zendit_api_key', null],
            'oneglobal' => ['1GLOBAL', 'esim', 'https://portal.1global.com', 'oneglobal_client_id', null],
            'gigs' => ['Gigs', 'esim', 'https://app.gigs.com/login', 'gigs_api_key', null],
            'montymobile' => ['Monty Mobile', 'esim', 'https://montymobile.com', 'montymobile_api_key', null],
            'esimaccess' => ['eSIM Access', 'esim', 'https://console.esimaccess.com', 'esimaccess_api_key', null],
            'ubigi' => ['Ubigi', 'esim', 'https://partner.ubigi.com', 'ubigi_api_key', null],
            // Numbers and SMS
            'getatext' => ['Getatext', 'numbers', 'https://www.getatext.com/login', 'getatext_api_key', ['email_password', 'supremeideasz@gmail.com']],
            'fivesim' => ['5sim', 'numbers', 'https://5sim.net/login', 'fivesim_api_key', null],
            'herosms' => ['HeroSMS', 'numbers', 'https://hero-sms.com/login', 'herosms_api_key', null],
            'virtsms' => ['VirtSMS', 'numbers', 'https://virtsms.com/login', 'virtsms_api_key', null],
            'smspool' => ['SMSPool', 'numbers', 'https://www.smspool.net/login', 'smspool_api_key', null],
            'onlinesim' => ['OnlineSIM', 'numbers', 'https://onlinesim.io/login', 'onlinesim_api_key', null],
            'africastalking' => ["Africa's Talking", 'numbers', 'https://account.africastalking.com/auth/login', 'africastalking_api_key', null],
            // Voice and calls
            'twilio' => ['Twilio', 'voice', 'https://console.twilio.com', 'twilio_account_sid', null],
            'telnyx' => ['Telnyx', 'voice', 'https://portal.telnyx.com', 'telnyx_api_key', null],
            'plivo' => ['Plivo', 'voice', 'https://console.plivo.com', 'plivo_auth_token', null],
            'vonage' => ['Vonage', 'voice', 'https://dashboard.nexmo.com', 'vonage_api_key', null],
            'sinch' => ['Sinch', 'voice', 'https://dashboard.sinch.com', 'sinch_client_id', null],
            // Gift cards and airtime
            'bitrefill' => ['Bitrefill', 'giftcards', 'https://www.bitrefill.com/account', 'bitrefill_api_key', null],
            'reloadly' => ['Reloadly', 'giftcards', 'https://www.reloadly.com/login', 'reloadly_client_id', null],
            'tillo' => ['Tillo', 'giftcards', 'https://portal.tillo.io', 'tillo_api_key', null],
            // Payments
            'paystack' => ['Paystack', 'payments', 'https://dashboard.paystack.com/#/login', 'paystack_secret_key', null],
            'stripe' => ['Stripe', 'payments', 'https://dashboard.stripe.com/login', 'stripe_secret_key', null],
            'paypal' => ['PayPal', 'payments', 'https://developer.paypal.com/dashboard', 'paypal_client_id', null],
            'flutterwave' => ['Flutterwave', 'payments', 'https://app.flutterwave.com/login', 'flutterwave_secret_key', null],
            'payssion' => ['Payssion', 'payments', 'https://www.payssion.com/login', 'payssion_api_key', null],
            'grey' => ['Grey', 'payments', 'https://app.grey.co/login', 'grey_api_secret_key', null],
            // Crypto payments
            'nowpayments' => ['NOWPayments', 'crypto', 'https://account.nowpayments.io/sign-in', 'nowpayments_api_key', null],
            'cryptomus' => ['Cryptomus', 'crypto', 'https://app.cryptomus.com/signin', 'cryptomus_api_key', null],
            'binance' => ['Binance Pay', 'crypto', 'https://merchant.binance.com', 'binance_api_key', null],
            'coinpayments' => ['CoinPayments', 'crypto', 'https://www.coinpayments.net/login', 'coinpayments_public_key', null],
            // Identity and security
            'dojah' => ['Dojah', 'identity', 'https://app.dojah.io/login', 'dojah_api_key', null],
            'smileid' => ['Smile ID', 'identity', 'https://portal.usesmileid.com', 'smileid_api_key', null],
            'turnstile' => ['Cloudflare (Turnstile)', 'identity', 'https://dash.cloudflare.com/login', 'turnstile_secret_key', null],
            // AI and media
            'anthropic' => ['Anthropic (Claude)', 'ai', 'https://console.anthropic.com', 'anthropic_api_key', null],
            'elevenlabs' => ['ElevenLabs', 'ai', 'https://elevenlabs.io/app/sign-in', 'elevenlabs_api_key', null],
            'giphy' => ['GIPHY', 'ai', 'https://developers.giphy.com/dashboard', 'giphy_api_key', null],
            'tenor' => ['Tenor (Google)', 'ai', 'https://console.cloud.google.com', 'tenor_api_key', null],
            // Developer tools
            'github' => ['GitHub', 'dev', 'https://github.com/login', 'github_maintenance_token', null],
            'codemagic' => ['Codemagic', 'dev', 'https://codemagic.io/login', 'codemagic_api_token', null],
        ];
    }

    /** Whether the API credentials for a provider are saved on this platform (the overview lists these first). */
    public static function hasCredentials(string $providerKey): bool
    {
        $field = self::catalogue()[$providerKey][3] ?? null;

        return $field !== null && ProviderKeys::hasValue($field);
    }

    /** Whether a custom (owner-added) row has any API field to look at: none, so it sorts by name after the known ones. */
    public static function credentialsFor(ProviderLogin $row): bool
    {
        return ! $row->is_custom && self::hasCredentials($row->provider_key);
    }

    /** Seeds anything missing; never overwrites a row the owner has edited. */
    public static function seed(): int
    {
        $made = 0;
        foreach (self::DEFAULT_EMAILS as [$email, $label]) {
            \App\Models\ProviderLoginEmail::firstOrCreate(['email' => $email], ['label' => $label]);
        }
        foreach (self::catalogue() as $key => [$name, $category, $url, $field, $confirmed]) {
            if (ProviderLogin::where('provider_key', $key)->exists()) {
                continue;
            }
            // A provider whose API keys are saved starts with the default email and the usual email-and-password route, flagged "please confirm"; the two the
            // owner named (eSIM Go with Google, Getatext with a normal sign-up) are already confirmed. Everything else starts honestly unconfirmed.
            $hasKeys = $field !== null && ProviderKeys::hasValue($field);
            ProviderLogin::create([
                'provider_key' => $key, 'name' => $name, 'category' => $category, 'login_url' => $url,
                'login_method' => $confirmed[0] ?? ($hasKeys ? 'email_password' : 'unconfirmed'),
                'email_hint' => $confirmed[1] ?? ($hasKeys ? self::DEFAULT_EMAIL : null),
                'confirmed_at' => $confirmed ? now() : null,
            ]);
            $made++;
        }

        return $made;
    }
}
