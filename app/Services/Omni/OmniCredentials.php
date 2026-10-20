<?php

namespace App\Services\Omni;

/**
 * Every credential Naara Respond can use, and where each one comes from right now (MASTER-ONLY): DEFAULT (a visible non-secret default in config/omni.php that
 * you may override), SET (a value in .env or Admin > API keys), or MISSING. Values are never returned, only their status, so the page and the verify check
 * can be shown to anyone who may open System Verify.
 */
class OmniCredentials
{
    /** @return list<array{key:string,label:string,env:string,secret:bool,needed_for:string,status:string,where:string}> */
    public static function status(): array
    {
        $rows = [];
        $add = function (string $key, string $label, string $env, bool $secret, string $neededFor, string $where, ?string $default = null) use (&$rows) {
            $value = (string) config($key, '');
            $status = $value === '' ? 'MISSING' : ($default !== null && $value === $default && ! getenv($env) ? 'DEFAULT' : 'SET');
            $rows[] = ['key' => $key, 'label' => $label, 'env' => $env, 'secret' => $secret, 'needed_for' => $neededFor, 'status' => $status, 'where' => $where];
        };
        $add('omni.meta.app_id', 'Meta App ID', 'OMNI_META_APP_ID', false, 'WhatsApp, Instagram, Messenger', 'Meta for Developers > your app > App settings > Basic', '2195469598059043');
        $add('omni.meta.business_id', 'Meta Business (portfolio) ID', 'OMNI_META_BUSINESS_ID', false, 'WhatsApp, Instagram, Messenger', 'Meta Business Settings > Business info', '4491570984493160');
        $add('omni.meta.domain', 'Verified domain', 'OMNI_META_DOMAIN', false, 'Business verification, Embedded Signup', 'Meta Business Settings > Brand safety > Domains', 'naara.app');
        $add('omni.meta.app_secret', 'Meta App Secret', 'OMNI_META_APP_SECRET', true, 'Checking that webhooks really come from Meta', 'Meta for Developers > App settings > Basic > App secret (Show)');
        $add('omni.meta.verify_token', 'Webhook verify token', 'OMNI_META_VERIFY_TOKEN', true, 'Meta webhook handshake (you invent this text)', 'Any long random text you choose; paste the same text into Meta');
        $add('omni.meta.config_id', 'Embedded Signup Config ID', 'OMNI_META_CONFIG_ID', false, 'Letting a business connect its own WhatsApp number with Meta\'s popup (stored now; the popup itself is not built yet)', 'App Dashboard > WhatsApp > Embedded Signup > Configuration ID');
        $add('services.whatsapp.access_token', 'WhatsApp system user token', 'WHATSAPP_ACCESS_TOKEN', true, 'Sending from Naara\'s own WhatsApp number', 'Business Settings > Users > System users > Generate token');
        $add('services.whatsapp.waba_id', 'Test WhatsApp Business Account ID', 'WHATSAPP_WABA_ID', false, 'Templates and the test number', 'App Dashboard > WhatsApp > API Setup');
        $add('services.whatsapp.phone_number_id', 'Test phone number ID', 'WHATSAPP_PHONE_NUMBER_ID', false, 'Sending from the test number', 'App Dashboard > WhatsApp > API Setup > Phone number ID');
        $add('omni.telegram.platform_bot_username', 'Naara Telegram bot username', 'OMNI_TELEGRAM_BOT', false, 'Showing "Message us on Telegram"', 'BotFather', 'NaaraConnectBot');
        $add('omni.telegram.platform_bot_token', 'Naara Telegram bot token', 'OMNI_TELEGRAM_BOT_TOKEN', true, 'Naara\'s own support bot (or connect it from Channels instead)', 'BotFather > /mybots > your bot > API Token');
        $add('services.anthropic.api_key', 'Anthropic API key', 'ANTHROPIC_API_KEY', true, 'AI assistant, NaaraBrain', 'console.anthropic.com > API keys');
        $add('mail.mailers.smtp.host', 'Mail server host', 'MAIL_HOST', false, 'Email replies and notices', 'Your email provider (SMTP, Postmark, SES, Resend...)');

        return $rows;
    }
}
