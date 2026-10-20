<?php

namespace App\Services\Omni\Adapters;

use App\Models\ChannelConnection;
use App\Services\Inbox\ChannelSendException;
use App\Services\Omni\ChannelAdapter;
use App\Services\Omni\Dto\NormalizedEvent;
use App\Services\Omni\Dto\OutboundDraft;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Email adapter (MASTER-ONLY, blueprint Phase 4). Inbound is a forwarding webhook: the workspace points an inbound-email service (or a mail rule) at its
 * connection URL and the service posts JSON { message_id, from:{email,name}, subject, text, date }. The body is signed with the connection secret
 * (X-Naara-Signature: sha256=HMAC(raw body)), compared in constant time. One sender is one conversation. Outbound goes through the platform's mail
 * transport from the workspace's own verified from-address. Needs no OAuth, so it works on a shared cPanel host.
 */
final class EmailAdapter implements ChannelAdapter
{
    public function channel(): string
    {
        return ChannelConnection::EMAIL;
    }

    public function verify(Request $request, ChannelConnection $connection): bool
    {
        $secret = (string) $connection->webhook_secret;
        $given = (string) $request->header('X-Naara-Signature', '');
        if ($secret === '' || ! str_starts_with($given, 'sha256=')) {
            return false;
        }

        return hash_equals('sha256='.hash_hmac('sha256', $request->getContent(), $secret), $given);
    }

    public function eventId(array $payload): string
    {
        return 'em:'.(string) ($payload['message_id'] ?? sha1(json_encode($payload)));
    }

    public function events(array $payload, ?ChannelConnection $connection = null): array
    {
        $from = (array) ($payload['from'] ?? []);
        $email = strtolower(trim((string) ($from['email'] ?? '')));
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return [];
        }
        $subject = trim((string) ($payload['subject'] ?? ''));
        $body = trim((string) ($payload['text'] ?? ''));
        // Auto-replies, bounces and mailing-list traffic are not customers: filed nowhere, answered never (no mail loops).
        $auto = strtolower((string) ($payload['auto_submitted'] ?? ''));
        if (($auto !== '' && $auto !== 'no') || preg_match('/^(mailer-daemon|postmaster|no-?reply|do-?not-?reply)@/i', $email)) {
            return [];
        }
        $text = trim(($subject !== '' ? $subject."\n\n" : '').$body);
        if ($text === '') {
            return [];
        }

        return [new NormalizedEvent(
            type: NormalizedEvent::MESSAGE,
            externalId: $email,
            providerMessageId: isset($payload['message_id']) ? mb_substr((string) $payload['message_id'], 0, 190) : null,
            text: mb_substr($text, 0, 8000),
            name: ($from['name'] ?? '') !== '' ? mb_substr((string) $from['name'], 0, 160) : null,
            email: $email,
            at: isset($payload['date']) && strtotime((string) $payload['date']) ? Carbon::createFromTimestamp(strtotime((string) $payload['date'])) : null,
        )];
    }

    public function requiresWindow(): bool
    {
        return false;   // email has no 24-hour rule
    }

    public function send(ChannelConnection $connection, OutboundDraft $draft): string
    {
        $to = strtolower(trim($draft->externalId));
        $text = trim((string) $draft->text);
        $fromAddress = (string) ($connection->credentials['from_address'] ?? '');
        if (! filter_var($to, FILTER_VALIDATE_EMAIL) || $text === '' || ! filter_var($fromAddress, FILTER_VALIDATE_EMAIL)) {
            throw new ChannelSendException('The email address or the sender address is not valid.');
        }
        $id = Str::uuid()->toString().'@'.(substr(strrchr($fromAddress, '@'), 1) ?: 'naara.app');
        $subject = (string) ($connection->credentials['subject'] ?? 'Re: your message');
        try {
            Mail::raw($text, function ($m) use ($to, $fromAddress, $connection, $subject, $id) {
                $m->to($to)->from($fromAddress, $connection->label)->subject($subject);
                if (! empty($connection->credentials['reply_to']) && filter_var($connection->credentials['reply_to'], FILTER_VALIDATE_EMAIL)) {
                    $m->replyTo($connection->credentials['reply_to']);
                }
                $m->getSymfonyMessage()->getHeaders()->addIdHeader('Message-ID', $id);
                $m->getSymfonyMessage()->getHeaders()->addTextHeader('Auto-Submitted', 'auto-generated');
            });
        } catch (\Throwable $e) {
            throw new ChannelSendException('The email could not be sent. Check the mail settings.');
        }

        return $id;
    }
}
