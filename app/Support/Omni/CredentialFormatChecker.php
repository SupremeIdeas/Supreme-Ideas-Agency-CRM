<?php

namespace App\Support\Omni;

/**
 * Tells a person, in plain words, what a pasted credential looks like (MASTER-ONLY, Admin > Respond setup guide). Pure shape checks: it never contacts a provider, never stores
 * or logs the value, and cannot tell whether a well-formed value is still valid. It exists to catch the common slips: the ID pasted where the secret belongs, a phone number
 * instead of a Phone number ID, quotes or spaces around a token, a Bearer prefix, the half of a Telegram token.
 */
final class CredentialFormatChecker
{
    /** @return array{ok:bool,kind:?string,secret:bool,message:string,fixes:list<string>} */
    public static function check(string $raw): array
    {
        $fixes = [];
        $v = $raw;
        if ($v !== trim($v)) {
            $fixes[] = 'Remove the spaces or line break at the start or end.';
            $v = trim($v);
        }
        if (preg_match('/^["\'`].*["\'`]$/s', $v)) {
            $fixes[] = 'Remove the quote marks around the value.';
            $v = trim($v, "\"'`");
        }
        if (preg_match('/^(bearer|token|access_token|authorization)\s*[:=]?\s*/i', $v, $m)) {
            $fixes[] = 'Remove the word "'.trim($m[0]).'" in front: paste only the value itself.';
            $v = substr($v, strlen($m[0]));
        }
        if (preg_match('/\s/', $v) && ! str_starts_with($v, '<script')) {
            $fixes[] = 'The value contains spaces or line breaks inside it. A copy-paste from a narrow screen often splits a long token: copy it again.';
        }
        if ($v === '') {
            return self::r(false, null, false, 'Nothing to check yet. Paste a value.', $fixes);
        }
        $c = preg_replace('/\s+/', '', $v);

        $rules = [
            ['/^\d{6,12}:[A-Za-z0-9_-]{35}$/', 'Telegram bot token', true, 'This has the right shape for a Telegram bot token. Paste it in Business > Channels > Telegram. If it was ever shown in chat or email, send /revoke to @BotFather and use the new one.'],
            ['/^\d{6,12}:[A-Za-z0-9_-]{1,34}$/', 'Telegram bot token (too short)', true, 'This looks like a Telegram token that was cut off. A full token is about 45 characters: digits, a colon, then 35 more characters. Copy it again from BotFather.'],
            ['/^sk-ant-[A-Za-z0-9_-]{20,}$/', 'Anthropic API key', true, 'This has the right shape for an Anthropic API key. Put it in .env as ANTHROPIC_API_KEY.'],
            ['/^EAA[A-Za-z0-9]{100,}$/', 'Meta access token', true, 'This has the right shape for a Meta access token. A token from the API Setup page lasts only 24 hours; for production create a System user token, which does not expire.'],
            ['/^EAA[A-Za-z0-9]{1,99}$/', 'Meta access token (too short)', true, 'This starts like a Meta access token but is too short. Real tokens are over 150 characters. Copy it again, whole.'],
            ['/^[a-f0-9]{32}$/i', 'Meta App Secret', true, 'This has the right shape for a Meta App Secret (32 letters a to f and digits). Put it in .env as OMNI_META_APP_SECRET. Never share it.'],
            ['/^17841\d{12}$/', 'Instagram account ID', false, 'This looks like an Instagram account ID. It is an ID, not a secret.'],
            ['/^\d{15,16}$/', 'Meta ID (App ID, Phone number ID or Business Account ID)', false, 'This is a 15 to 16 digit ID. It could be an App ID, a Phone number ID or a WhatsApp Business Account ID. IDs are not secrets. It is NOT an App Secret or a token.'],
            ['/^\+?\d{10,14}$/', 'Phone number', false, 'This looks like a phone number. WhatsApp setup needs the Phone number ID (a 15 to 16 digit number from API Setup), not the phone number itself.'],
            ['/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', 'Connection or site ID (UUID)', false, 'This is a UUID such as the one at the end of a Naara webhook address or a web chat data-site value. It is not a secret.'],
            ['/^facebook-domain-verification=[a-z0-9]{20,}$/', 'Meta domain verification TXT value', false, 'This is the TXT record value for domain verification. Add it in DNS as a TXT record named @, exactly as shown, with no quotes.'],
            ['/^sha256=[0-9a-f]{64}$/i', 'Webhook signature', false, 'This is a webhook signature (sha256= and 64 hex characters), not a credential.'],
        ];
        foreach ($rules as [$re, $kind, $secret, $msg]) {
            if (preg_match($re, $c)) {
                $short = str_contains($kind, 'too short');

                return self::r(! $short && $fixes === [], $kind, $secret, $msg, $fixes);
            }
        }
        if (preg_match('#^https?://#i', $v)) {
            return self::r(false, 'Web address', false, 'This is a web address, not a credential. If it is a webhook address, paste it into the provider\'s Callback URL box.', $fixes);
        }
        if (preg_match('/^[A-Za-z0-9._~-]{32,}$/', $c)) {
            return self::r($fixes === [], 'Possible verify token', true, 'This could be a webhook verify token: 32 or more letters and digits. It only works if it is IDENTICAL in Meta and in .env (OMNI_META_VERIFY_TOKEN).', $fixes);
        }
        if (strlen($c) < 32) {
            return self::r(false, null, false, 'This does not match any credential Naara uses. If it is meant to be a webhook verify token it should be at least 32 characters.', $fixes);
        }

        return self::r(false, null, false, 'This does not match the shape of any credential Naara uses. Check you copied the right field.', $fixes);
    }

    /** @return array{ok:bool,kind:?string,secret:bool,message:string,fixes:list<string>} */
    private static function r(bool $ok, ?string $kind, bool $secret, string $message, array $fixes): array
    {
        return ['ok' => $ok, 'kind' => $kind, 'secret' => $secret, 'message' => $message, 'fixes' => $fixes];
    }
}
