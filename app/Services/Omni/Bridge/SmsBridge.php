<?php

namespace App\Services\Omni\Bridge;

use App\Jobs\Omni\ProcessOmniRawEvent;
use App\Models\ChannelConnection;
use App\Models\Omni\OmniMessage;
use App\Models\OutboundMessage;
use App\Models\VirtualNumber;
use App\Support\Business\BusinessSettings;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * The two seams between the existing SMS system and the omni inbox (MASTER-ONLY). Neither changes anything the SMS system already does; both are no-ops unless the number
 * belongs to a workspace, Respond is on, and that workspace connected the number as an SMS channel.
 *   inbound(): a carrier text that the SMS inbound webhook has already verified is also filed as a raw omni event (idempotent per provider reference).
 *   delivery(): when a carrier delivery report settles one of our texts, the matching omni message moves to delivered or failed.
 */
class SmsBridge
{
    public static function connectionFor(VirtualNumber $line): ?ChannelConnection
    {
        if (! $line->wallet_group_id || ! BusinessSettings::omniLive()) {
            return null;
        }

        return ChannelConnection::query()->where('channel', ChannelConnection::SMS)->where('external_id', $line->phone_number)->where('wallet_group_id', $line->wallet_group_id)->where('status', ChannelConnection::ACTIVE)->first();
    }

    public static function inbound(VirtualNumber $line, string $from, ?string $body, ?string $media, ?string $ref): void
    {
        try {
            $c = self::connectionFor($line);
            if (! $c) {
                return;
            }
            $ref = $ref !== null && $ref !== '' ? $ref : sha1($from.'|'.$body.'|'.now()->format('YmdHi'));
            $raw = \App\Models\Omni\OmniRawEvent::query()->create([
                'connection_id' => $c->id, 'provider_event_id' => substr('sms:'.$ref, 0, 190), 'payload' => ['ref' => $ref, 'from' => $from, 'body' => $body, 'media' => $media, 'at' => now()->timestamp],
                'status' => 'pending', 'received_at' => now(),
            ]);
            $c->forceFill(['last_event_at' => now()])->save();
            ProcessOmniRawEvent::dispatch($raw->id);
        } catch (UniqueConstraintViolationException) {
            // the same text again: already filed
        } catch (\Throwable $e) {
            report($e);   // never let the omni copy break the existing inbound path
        }
    }

    /** @param 'delivered'|'failed' $final */
    public static function delivery(OutboundMessage $msg, string $final): void
    {
        try {
            $m = OmniMessage::query()->where('direction', 'out')->where('provider_message_id', 'sms-'.$msg->id)->first();
            if (! $m) {
                return;
            }
            if ($final === 'failed') {
                if (in_array($m->status, ['queued', 'sent'], true)) {
                    $m->forceFill(['status' => 'failed', 'error' => 'The carrier could not deliver this text.'])->save();
                }

                return;
            }
            if ((OmniMessage::RANK['delivered'] ?? 3) > (OmniMessage::RANK[$m->status] ?? 0)) {
                $m->forceFill(['status' => 'delivered'])->save();
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
