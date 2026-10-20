<?php

namespace App\Services\Inbox;

use App\Models\ChannelConnection;
use App\Models\MessageThread;
use App\Models\OutboundMessage;
use App\Models\User;
use App\Models\VirtualNumber;
use App\Services\SMS\MessageSenderService;
use App\Services\WhatsApp\WhatsAppCloudClient;
use App\Support\Business\BusinessSettings;

/**
 * The one place a reply is routed to the right channel (Business Suite Prompt 16): switch on the thread's channel, never branch in the UI.
 *  - sms:       the existing MessageSenderService (quote, atomic wallet charge, provider routing: untouched);
 *  - whatsapp, telegram, instagram: the workspace's OWN account, so there is no Naara wallet charge for these (the channel's own provider bills
 *    the workspace directly), and a failed send writes nothing.
 */
class MessageDispatcher
{
    public function __construct(
        private MessageSenderService $sms,
        private WhatsAppCloudClient $whatsapp,
        private TelegramBotClient $telegram,
        private InstagramClient $instagram,
    ) {
    }

    /** @throws ChannelSendException when the reply could not go out */
    public function sendReply(MessageThread $thread, string $body, User $by): OutboundMessage
    {
        $body = trim($body);
        if ($body === '') {
            throw new ChannelSendException('Write a message first.');
        }

        if (($thread->channel ?: 'sms') === 'sms') {
            $line = VirtualNumber::query()->where('user_id', $thread->user_id)->find($thread->virtual_number_id);
            if (! $line) {
                throw new ChannelSendException('Choose one of your numbers to send from.');
            }

            return $this->sms->send($by, $line, $thread->counterpart_number, $body);
        }

        if ($thread->opted_out_at !== null) {
            throw new ChannelSendException('This person asked not to be messaged. You can reply once they write to you again.');
        }

        if (! BusinessSettings::module('inbox')) {
            throw new ChannelSendException('The shared inbox is not available right now.');
        }

        $connection = ChannelConnection::query()
            ->where('wallet_group_id', $thread->wallet_group_id)->where('channel', $thread->channel)->where('status', ChannelConnection::ACTIVE)->first();
        if (! $connection) {
            throw new ChannelSendException('This channel is not connected any more.');
        }

        $ref = match ($thread->channel) {
            ChannelConnection::WHATSAPP => $this->whatsapp->sendMessage($thread->counterpart_number, $body, $connection->external_id, (string) $connection->secret('access_token')),
            ChannelConnection::TELEGRAM => $this->telegram->sendMessage((string) $connection->secret('bot_token'), $thread->counterpart_number, $body),
            ChannelConnection::INSTAGRAM => $this->instagram->sendMessage((string) $connection->secret('access_token'), $connection->external_id, $thread->counterpart_number, $body),
            default => throw new ChannelSendException('Unknown channel.'),
        };

        if ($ref === null) {
            $connection->forceFill(['last_error' => 'The last reply could not be sent.'])->save();
            throw new ChannelSendException($thread->channel === ChannelConnection::WHATSAPP || $thread->channel === ChannelConnection::INSTAGRAM
                ? 'The reply could not be sent. '.ChannelConnection::label($thread->channel).' only allows replies within 24 hours of their last message.'
                : 'The reply could not be sent. Check the connection.');
        }

        // created hook updates the thread preview and clears unread
        return OutboundMessage::create([
            'user_id' => $thread->user_id, 'channel' => $thread->channel, 'wallet_group_id' => $thread->wallet_group_id,
            'virtual_number_id' => null, 'to_number' => $thread->counterpart_number, 'body' => $body,
            'provider' => $thread->channel, 'provider_ref' => $ref, 'status' => 'sent', 'segments' => 1,
            'reference' => 'chan:'.$thread->channel.':'.$thread->counterpart_number.':'.$ref,
        ]);
    }
}
