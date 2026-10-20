<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Jobs\RecordChannelMessageJob;
use App\Models\ChannelConnection;
use App\Support\Business\BusinessSettings;
use Illuminate\Http\Request;

/**
 * Telegram bot updates for a workspace's connected bot (MASTER-ONLY, Business Suite Prompt 16). Telegram has no body signature, so the proof is the
 * per-connection secret token we gave setWebhook(): it comes back in X-Telegram-Bot-Api-Secret-Token and is compared with hash_equals() BEFORE the payload is
 * read. Only private chats are filed (a bot added to a group is ignored). The update is queued, never processed inline.
 */
class TelegramWebhookController extends Controller
{
    public function __invoke(Request $request, int $connection)
    {
        $conn = ChannelConnection::query()->where('channel', ChannelConnection::TELEGRAM)->find($connection);
        $expected = (string) ($conn?->webhook_secret ?? '');
        $given = (string) $request->header('X-Telegram-Bot-Api-Secret-Token', '');
        abort_unless($expected !== '' && hash_equals($expected, $given), 401);

        if (! $conn->isActive() || ! BusinessSettings::module('inbox')) {
            return response()->json(['ok' => true]);   // acknowledged so Telegram does not retry, but nothing is filed
        }

        $message = (array) $request->input('message', []);
        $chatId = (string) ($message['chat']['id'] ?? '');
        $text = (string) ($message['text'] ?? '');
        $updateId = (string) $request->input('update_id', '');

        if (($message['chat']['type'] ?? '') === 'private' && $chatId !== '' && $updateId !== '' && trim($text) !== '' && empty($message['from']['is_bot'])) {
            // The update id is only unique per bot, so the reference carries the connection.
            RecordChannelMessageJob::dispatch($conn->id, $chatId, $text, 'tg:'.$conn->id.':'.$updateId, isset($message['date']) ? (int) $message['date'] : null);
        }

        return response()->json(['ok' => true]);
    }
}
