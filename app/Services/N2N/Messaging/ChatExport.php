<?php

namespace App\Services\N2N\Messaging;

use App\Models\N2N\Message;
use App\Models\N2N\Participant;
use App\Models\User;
use App\Support\Auditor;
use App\Support\N2N\N2NSettings;

/**
 * Text-only export of ONE chat for the member who asks (N2N Batch 12, MASTER-ONLY). No media, and nothing about the other person beyond the
 * display name they chose to show: no email, phone, username or Naara ID. Only messages this member could already see are included; a
 * message the other person had dropped by a block never appears, and deleted messages are marked as deleted without their text.
 */
class ChatExport
{
    public function __construct(private readonly MessageService $messages) {}

    public function text(User $user, int $conversationId): string
    {
        $c = $this->messages->mine($user, $conversationId);
        $peerId = (int) Participant::query()->where('conversation_id', $c->id)->where('user_id', '!=', $user->id)->value('user_id');
        $peer = User::query()->select('id', 'name')->find($peerId);
        $peerName = trim((string) $peer?->name) !== '' ? (string) $peer->name : 'Naara member';
        $max = N2NSettings::int('export_max_messages', 5000, 100);

        $rows = Message::query()->where('conversation_id', $c->id)->forViewer($user->id)
            ->orderByDesc('id')->limit($max)->get()->reverse()->values();

        $out = [];
        $out[] = 'Naara chat export';
        $out[] = 'Chat with: '.$peerName;
        $out[] = 'Exported: '.now()->utc()->format('Y-m-d H:i').' UTC';
        $out[] = 'Messages: '.$rows->count().($rows->count() >= $max ? " (the latest {$max})" : '');
        $out[] = 'Text only. Photos, videos and voice notes are not included.';
        $out[] = 'This file is a copy of your own view of the chat. Keep it private: anyone who has it can read the conversation.';
        $out[] = str_repeat('-', 48);
        foreach ($rows as $m) {
            $who = (int) $m->sender_id === $user->id ? 'You' : $peerName;
            $when = $m->created_at?->utc()->format('Y-m-d H:i');
            if ($m->deleted_for_all_at !== null) {
                $text = '[message deleted]';
            } else {
                $body = trim((string) $m->body);
                $text = $body !== '' ? $body : '['.(['image' => 'photo', 'voice' => 'voice message', 'video' => 'video'][$m->type] ?? 'media').' not included]';
            }
            $out[] = "[{$when}] {$who}: ".str_replace(["\r\n", "\r", "\n"], "\n    ", $text);
        }
        Auditor::log('n2n.chat_exported', \App\Models\N2N\Conversation::class, $c->id, ['count' => $rows->count()]);

        return implode("\n", $out)."\n";
    }
}
