<?php

namespace App\Services\Inbox;

use App\Models\ChannelConnection;
use App\Models\WalletGroup;
use App\Support\Auditor;
use App\Support\Business\BusinessSettings;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Connects and disconnects a workspace's own channel accounts (MASTER-ONLY, Business Suite Prompt 16). Every connect VERIFIES the credentials with the
 * channel first, so a typo shows up here and not as silence later. Secrets are stored encrypted and are never echoed back. One external account can
 * belong to one workspace only.
 */
class ChannelConnector
{
    public function __construct(private TelegramBotClient $telegram)
    {
    }

    /** @throws ChannelSendException with a message that is safe to show */
    public function connectTelegram(WalletGroup $group, string $botToken): ChannelConnection
    {
        $botToken = trim($botToken);
        $this->assertRoom($group);

        $me = $this->telegram->me($botToken);
        if (! $me) {
            throw new ChannelSendException('Telegram did not accept that bot token. Copy it again from BotFather.');
        }
        $this->assertFree(ChannelConnection::TELEGRAM, $me['id'], $group);

        $secret = Str::random(40);
        $conn = ChannelConnection::updateOrCreate(
            ['channel' => ChannelConnection::TELEGRAM, 'external_id' => $me['id']],
            ['wallet_group_id' => $group->id, 'label' => $me['username'] ? '@'.$me['username'] : 'Telegram bot', 'credentials' => ['bot_token' => $botToken], 'webhook_secret' => $secret, 'status' => ChannelConnection::ACTIVE, 'last_error' => null],
        );

        $url = route('webhooks.telegram', ['connection' => $conn->id]);
        if (class_exists(\App\Services\Omni\Adapters\TelegramAdapter::class) && BusinessSettings::omniLive()) {
            // Naara Respond is live: new bots file into the omni inbox through the signed omni webhook.
            $conn->forceFill(['public_id' => $conn->public_id ?: (string) Str::uuid()])->save();
            $url = route('omni.webhook', ['channel' => 'telegram', 'publicId' => $conn->public_id]);
        }
        if (! $this->telegram->setWebhook($botToken, $url, $secret)) {
            $conn->delete();
            throw new ChannelSendException('Telegram would not register our address for that bot. Try again in a minute.');
        }

        Auditor::log('business.channel_connected', ChannelConnection::class, $conn->id, ['wallet_group_id' => $group->id, 'channel' => 'telegram']);

        return $conn;
    }

    /**
     * Web chat for a website (MASTER-ONLY, Naara Respond). No external account to verify: the workspace lists the origins allowed to embed the widget.
     *
     * @param list<string> $origins https origins such as https://shop.example (http only for localhost)
     *
     * @throws ChannelSendException
     */
    public function connectWebChat(WalletGroup $group, string $label, array $origins, string $greeting = '', string $color = ''): ChannelConnection
    {
        abort_unless(class_exists(\App\Services\Omni\Adapters\WebChatAdapter::class), 404);
        $this->assertRoom($group);
        $clean = [];
        foreach ($origins as $o) {
            $o = strtolower(rtrim(trim($o), '/'));
            if ($o === '') {
                continue;
            }
            $host = parse_url($o);
            $ok = is_array($host) && isset($host['scheme'], $host['host']) && ! isset($host['path']) && ! isset($host['query'])
                && ($host['scheme'] === 'https' || ($host['scheme'] === 'http' && in_array($host['host'], ['localhost', '127.0.0.1'], true)));
            if (! $ok) {
                throw new ChannelSendException('"'.$o.'" is not a valid website address. Use the form https://yourshop.com with no page path.');
            }
            $clean[] = $o;
        }
        if ($clean === []) {
            throw new ChannelSendException('Add the website address where the chat will appear, for example https://yourshop.com.');
        }
        if ($color !== '' && ! preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
            throw new ChannelSendException('Pick the chat colour as a six-digit hex such as #0A6E6E.');
        }
        $conn = ChannelConnection::create([
            'wallet_group_id' => $group->id, 'channel' => ChannelConnection::WEBCHAT, 'external_id' => 'wc-'.Str::lower(Str::random(16)),
            'label' => trim($label) !== '' ? Str::limit(trim($label), 60, '') : 'Website chat',
            'credentials' => ['allowed_origins' => array_values(array_unique($clean)), 'greeting' => Str::limit(trim($greeting), 160, '') ?: 'Hi! How can we help?', 'color' => $color ?: '#0A6E6E'],
            'webhook_secret' => Str::random(40), 'status' => ChannelConnection::ACTIVE, 'last_error' => null,
        ]);
        $conn->forceFill(['public_id' => (string) Str::uuid()])->save();
        Auditor::log('business.channel_connected', ChannelConnection::class, $conn->id, ['wallet_group_id' => $group->id, 'channel' => 'webchat']);

        return $conn;
    }

    /**
     * Email into the omni inbox (MASTER-ONLY, Naara Respond). The workspace forwards its support mail to the connection URL; replies leave from its own address.
     *
     * @throws ChannelSendException
     */
    public function connectEmail(WalletGroup $group, string $label, string $fromAddress, string $replyTo = ''): ChannelConnection
    {
        abort_unless(class_exists(\App\Services\Omni\Adapters\EmailAdapter::class), 404);
        $this->assertRoom($group);
        $fromAddress = strtolower(trim($fromAddress));
        if (! filter_var($fromAddress, FILTER_VALIDATE_EMAIL)) {
            throw new ChannelSendException('Enter the support address replies should come from, for example support@yourshop.com.');
        }
        $replyTo = strtolower(trim($replyTo));
        if ($replyTo !== '' && ! filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
            throw new ChannelSendException('The reply-to address is not valid.');
        }
        $this->assertFree(ChannelConnection::EMAIL, $fromAddress, $group);
        $conn = ChannelConnection::create([
            'wallet_group_id' => $group->id, 'channel' => ChannelConnection::EMAIL, 'external_id' => $fromAddress,
            'label' => trim($label) !== '' ? Str::limit(trim($label), 60, '') : 'Support email', 'credentials' => ['from_address' => $fromAddress, 'reply_to' => $replyTo ?: null],
            'webhook_secret' => Str::random(40), 'status' => ChannelConnection::ACTIVE, 'last_error' => null,
        ]);
        $conn->forceFill(['public_id' => (string) Str::uuid()])->save();
        Auditor::log('business.channel_connected', ChannelConnection::class, $conn->id, ['wallet_group_id' => $group->id, 'channel' => 'email']);

        return $conn;
    }

    /** @throws ChannelSendException */
    public function connectWhatsApp(WalletGroup $group, string $phoneNumberId, string $accessToken, string $appSecret, string $label = ''): ChannelConnection
    {
        return $this->connectMeta($group, ChannelConnection::WHATSAPP, trim($phoneNumberId), trim($accessToken), trim($appSecret), $label, 'display_phone_number');
    }

    /** @throws ChannelSendException */
    public function connectInstagram(WalletGroup $group, string $accountId, string $accessToken, string $appSecret, string $label = ''): ChannelConnection
    {
        return $this->connectMeta($group, ChannelConnection::INSTAGRAM, trim($accountId), trim($accessToken), trim($appSecret), $label, 'username');
    }

    /**
     * A workspace's own SMS number into the omni inbox (MASTER-ONLY, Naara Respond). The number must already belong to the workspace (a Business number); nothing is verified
     * with a provider because the carrier already delivers its texts to the SMS webhook. Texts are sent and billed through the existing SMS pipeline.
     *
     * @throws ChannelSendException
     */
    public function connectSms(WalletGroup $group, int $virtualNumberId): ChannelConnection
    {
        abort_unless(class_exists(\App\Services\Omni\Adapters\SmsAdapter::class), 404);
        $line = \App\Models\VirtualNumber::query()->where('wallet_group_id', $group->id)->where('status', 'active')->find($virtualNumberId);
        if (! $line) {
            throw new ChannelSendException('That number is not an active number of this workspace.');
        }
        $caps = (array) $line->capabilities;
        if (array_key_exists('sms', $caps) && ! $caps['sms']) {
            throw new ChannelSendException('This number cannot send text messages.');
        }
        $this->assertFree(ChannelConnection::SMS, $line->phone_number, $group);
        $this->assertRoom($group);
        $conn = ChannelConnection::updateOrCreate(
            ['channel' => ChannelConnection::SMS, 'external_id' => $line->phone_number],
            ['wallet_group_id' => $group->id, 'label' => $line->phone_number, 'credentials' => ['virtual_number_id' => $line->id], 'webhook_secret' => Str::random(32), 'status' => ChannelConnection::ACTIVE, 'last_error' => null],
        );
        $conn->forceFill(['public_id' => $conn->public_id ?: (string) Str::uuid()])->save();
        Auditor::log('business.channel_connected', ChannelConnection::class, $conn->id, ['wallet_group_id' => $group->id, 'channel' => 'sms']);

        return $conn;
    }

    /** @throws ChannelSendException */
    public function connectMessenger(WalletGroup $group, string $pageId, string $accessToken, string $appSecret, string $label = ''): ChannelConnection
    {
        return $this->connectMeta($group, ChannelConnection::MESSENGER, trim($pageId), trim($accessToken), trim($appSecret), $label, 'name');
    }

    public function disconnect(ChannelConnection $conn): void
    {
        if ($conn->channel === ChannelConnection::TELEGRAM && $conn->secret('bot_token')) {
            $this->telegram->deleteWebhook((string) $conn->secret('bot_token'));
        }
        Auditor::log('business.channel_disconnected', ChannelConnection::class, $conn->id, ['wallet_group_id' => $conn->wallet_group_id, 'channel' => $conn->channel]);
        $conn->delete();   // threads and messages stay: they are the workspace's records
    }

    private function connectMeta(WalletGroup $group, string $channel, string $id, string $token, string $appSecret, string $label, string $field): ChannelConnection
    {
        if (! preg_match('/^\d{5,32}$/', $id) || strlen($token) < 20 || strlen($appSecret) < 16) {
            throw new ChannelSendException('Enter the numeric account id, the access token and the app secret from your Meta app.');
        }
        $this->assertRoom($group);
        $this->assertFree($channel, $id, $group);

        $version = (string) config('services.whatsapp.graph_version', 'v25.0');
        try {
            $r = Http::withToken($token)->timeout(10)->get("https://graph.facebook.com/{$version}/{$id}", ['fields' => $field]);
        } catch (\Throwable) {
            throw new ChannelSendException('We could not reach Meta to check those details. Try again in a minute.');
        }
        if (! $r->successful()) {
            throw new ChannelSendException('Meta did not accept that account id and token. Check both and try again.');
        }

        // Re-connecting keeps the verify token already pasted into Meta, so the webhook does not have to be set up twice.
        $existing = ChannelConnection::forChannel($channel, $id);
        $conn = ChannelConnection::updateOrCreate(
            ['channel' => $channel, 'external_id' => $id],
            ['wallet_group_id' => $group->id, 'label' => $label !== '' ? Str::limit($label, 80, '') : (string) ($r->json($field) ?: ChannelConnection::label($channel)),
                'credentials' => ['access_token' => $token, 'app_secret' => $appSecret], 'webhook_secret' => $existing?->webhook_secret ?: Str::random(32), 'status' => ChannelConnection::ACTIVE, 'last_error' => null],
        );
        if (class_exists(\App\Services\Omni\Adapters\WhatsAppAdapter::class) && BusinessSettings::omniLive()) {
            // Naara Respond is live: this account files into the omni inbox through the signed omni webhook (the callback shown on the card).
            $conn->forceFill(['public_id' => $conn->public_id ?: (string) Str::uuid()])->save();
        }
        Auditor::log('business.channel_connected', ChannelConnection::class, $conn->id, ['wallet_group_id' => $group->id, 'channel' => $channel]);

        return $conn;
    }

    private function assertRoom(WalletGroup $group): void
    {
        $limit = app(\App\Services\Business\BusinessAccess::class)->limit($group, 'channels');
        if (ChannelConnection::where('wallet_group_id', $group->id)->count() >= $limit) {
            throw new ChannelSendException('This workspace has reached its limit of '.$limit.' connected channels.');
        }
    }

    private function assertFree(string $channel, string $externalId, WalletGroup $group): void
    {
        $other = ChannelConnection::forChannel($channel, $externalId);
        if ($other && (int) $other->wallet_group_id !== (int) $group->id) {
            throw new ChannelSendException('That account is already connected to another workspace.');
        }
    }
}
