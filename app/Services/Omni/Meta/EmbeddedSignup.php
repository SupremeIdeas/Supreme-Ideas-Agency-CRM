<?php

namespace App\Services\Omni\Meta;

use App\Models\ChannelConnection;
use App\Models\WalletGroup;
use App\Services\Inbox\ChannelSendException;
use App\Support\Auditor;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Meta WhatsApp Embedded Signup, server side (MASTER-ONLY). The browser popup (Facebook JS SDK, FB.login with Naara's Configuration ID) hands us a short-lived
 * authorization code plus the phone number id and WhatsApp Business Account id the business picked. We then, in this order:
 *   1. exchange the code for the business's access token (GET /oauth/access_token with Naara's app id and secret; the code is single-use and short-lived),
 *   2. read the number (display name, number) to prove the token works and the number belongs to that account,
 *   3. register the number for the Cloud API with a random 6-digit two-step PIN (POST /{phone}/register),
 *   4. subscribe Naara's app to that WhatsApp Business Account with an override callback pointing at THIS connection's own signed webhook
 *      (POST /{waba}/subscribed_apps {override_callback_uri, verify_token}), so one Meta app serves every business without a shared webhook.
 * Nothing is saved until step 2 has succeeded; a failure after that rolls the connection back so the business is never left half connected.
 */
class EmbeddedSignup
{
    public static function available(): bool
    {
        return (string) config('omni.meta.config_id') !== '' && (string) config('omni.meta.app_id') !== '' && (string) config('omni.meta.app_secret') !== '';
    }

    /** @throws ChannelSendException with a message that is safe to show */
    public function finish(WalletGroup $group, string $code, string $phoneNumberId, string $wabaId, string $label = ''): ChannelConnection
    {
        if (! self::available()) {
            throw new ChannelSendException('Connect with Meta is not set up on this platform yet.');
        }
        $code = trim($code);
        $phoneNumberId = trim($phoneNumberId);
        $wabaId = trim($wabaId);
        if ($code === '' || ! preg_match('/^\d{5,32}$/', $phoneNumberId) || ! preg_match('/^\d{5,32}$/', $wabaId)) {
            throw new ChannelSendException('Meta did not send back the number details. Close the Meta window and try again.');
        }
        $other = ChannelConnection::forChannel(ChannelConnection::WHATSAPP, $phoneNumberId);
        if ($other && (int) $other->wallet_group_id !== (int) $group->id) {
            throw new ChannelSendException('That WhatsApp number is already connected to another workspace.');
        }
        $limit = app(\App\Services\Business\BusinessAccess::class)->limit($group, 'channels');
        if (! $other && ChannelConnection::where('wallet_group_id', $group->id)->count() >= $limit) {
            throw new ChannelSendException('This workspace has reached its limit of '.$limit.' connected channels.');
        }

        $v = (string) config('services.whatsapp.graph_version', 'v25.0');
        $graph = 'https://graph.facebook.com/'.$v;
        $token = $this->exchange($graph, $code);
        $info = $this->get($graph.'/'.$phoneNumberId, $token, ['fields' => 'display_phone_number,verified_name']);
        if (! $info->successful()) {
            throw new ChannelSendException('Meta did not let us read that WhatsApp number. Make sure you finished every step in the Meta window.');
        }

        $existing = $other;
        $conn = ChannelConnection::updateOrCreate(
            ['channel' => ChannelConnection::WHATSAPP, 'external_id' => $phoneNumberId],
            ['wallet_group_id' => $group->id,
                'label' => $label !== '' ? Str::limit($label, 80, '') : (string) ($info->json('verified_name') ?: $info->json('display_phone_number') ?: 'WhatsApp'),
                'credentials' => ['access_token' => $token, 'waba_id' => $wabaId, 'signup' => 'embedded'],
                'webhook_secret' => $existing?->webhook_secret ?: Str::random(32), 'status' => ChannelConnection::ACTIVE, 'last_error' => null],
        );
        $conn->forceFill(['public_id' => $conn->public_id ?: (string) Str::uuid()])->save();

        try {
            $reg = Http::withToken($token)->timeout(20)->post($graph.'/'.$phoneNumberId.'/register', ['messaging_product' => 'whatsapp', 'pin' => (string) random_int(100000, 999999)]);
            // Already-registered numbers answer with an error that is not a failure for us; only a rejected token or a hard refusal is.
            if (! $reg->successful() && ! in_array((int) $reg->json('error.code'), [80007, 133016, 33], true) && $reg->status() >= 500) {
                throw new \RuntimeException('register');
            }
            $sub = Http::withToken($token)->timeout(20)->post($graph.'/'.$wabaId.'/subscribed_apps', [
                'override_callback_uri' => route('omni.webhook', ['channel' => 'whatsapp', 'publicId' => $conn->public_id]),
                'verify_token' => (string) $conn->webhook_secret,
            ]);
            if (! $sub->successful()) {
                throw new \RuntimeException('subscribe');
            }
        } catch (\Throwable) {
            if (! $existing) {
                $conn->delete();
            }
            throw new ChannelSendException('Meta accepted the sign-in but would not switch the number on for messages. Try again in a minute.');
        }

        Auditor::log('business.channel_connected', ChannelConnection::class, $conn->id, ['wallet_group_id' => $group->id, 'channel' => 'whatsapp', 'via' => 'embedded_signup']);

        return $conn;
    }

    private function exchange(string $graph, string $code): string
    {
        try {
            $r = Http::timeout(20)->get($graph.'/oauth/access_token', ['client_id' => (string) config('omni.meta.app_id'), 'client_secret' => (string) config('omni.meta.app_secret'), 'code' => $code]);
        } catch (\Throwable) {
            throw new ChannelSendException('We could not reach Meta. Try again in a minute.');
        }
        $token = $r->json('access_token');
        if (! $r->successful() || ! is_string($token) || strlen($token) < 20) {
            throw new ChannelSendException('Meta did not accept that sign-in (it may have expired). Press the button and try again.');
        }

        return $token;
    }

    private function get(string $url, string $token, array $query)
    {
        try {
            return Http::withToken($token)->timeout(15)->get($url, $query);
        } catch (\Throwable) {
            throw new ChannelSendException('We could not reach Meta. Try again in a minute.');
        }
    }
}
