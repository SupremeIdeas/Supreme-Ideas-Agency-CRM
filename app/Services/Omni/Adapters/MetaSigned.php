<?php

namespace App\Services\Omni\Adapters;

use App\Models\ChannelConnection;
use Illuminate\Http\Request;

/**
 * Shared Meta webhook proof for the WhatsApp, Instagram and Messenger adapters (MASTER-ONLY). Meta signs the RAW body with the app secret as
 * X-Hub-Signature-256 = "sha256=" + hex(hmac_sha256(body, app_secret)). A connection that brought its own Meta app carries its own `app_secret`;
 * a connection made through Naara's Embedded Signup uses Naara's platform app secret. Compared in constant time; no secret means no proof.
 */
trait MetaSigned
{
    public function verify(Request $request, ChannelConnection $connection): bool
    {
        $secret = (string) ($connection->secret('app_secret') ?? config('omni.meta.app_secret', ''));
        $given = (string) $request->header('X-Hub-Signature-256', '');
        if ($secret === '' || $given === '') {
            return false;
        }

        return hash_equals('sha256='.hash_hmac('sha256', $request->getContent(), $secret), $given);
    }

    public function requiresWindow(): bool
    {
        return true;   // WhatsApp, Instagram and Messenger: a free-form reply needs the customer to have written in the last 24 hours
    }

    protected function graph(): string
    {
        return 'https://graph.facebook.com/'.(string) config('services.whatsapp.graph_version', 'v25.0');
    }
}
