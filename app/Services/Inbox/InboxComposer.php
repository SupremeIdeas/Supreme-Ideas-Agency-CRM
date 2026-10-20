<?php

namespace App\Services\Inbox;

use App\Models\MessageThread;
use App\Models\ScheduledChannelMessage;
use App\Models\User;
use App\Support\Business\BusinessSettings;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Turns what the Naara composer posts (text, files, a voice note, location / contact / poll items, an optional send time) into a send or a scheduled send
 * (MASTER-ONLY, Business Suite inbox). All the platform ceilings set in Admin > Business Suite > Shared inbox sending are enforced here, before anything
 * is stored or sent, and every refusal is a plain sentence the composer shows to the person.
 */
class InboxComposer
{
    public function __construct(private InboxMedia $media, private ChannelSender $sender)
    {
    }

    /**
     * @param  array<string,mixed>  $meta  the composer's `meta` JSON
     * @param  list<UploadedFile>  $files
     * @return array{status: string, count?: int, at?: string}
     *
     * @throws ChannelSendException
     */
    public function submit(MessageThread $thread, User $by, array $meta, array $files, ?UploadedFile $voice, string $key): array
    {
        $limit = (int) BusinessSettings::inbox('sends_per_minute');
        if (RateLimiter::tooManyAttempts('inbox-send:'.$by->id, $limit)) {
            throw new ChannelSendException('You are sending very quickly. Wait a few seconds and try again.');
        }

        $items = array_values(array_filter((array) ($meta['items'] ?? []), 'is_array'));
        $text = trim((string) ($meta['text'] ?? ''));
        $rich = $files !== [] || $voice !== null || $items !== [];
        if ($rich && ! BusinessSettings::inbox('attachments')) {
            throw new ChannelSendException('Sending files, voice notes and locations from the inbox is switched off for now. You can still send text.');
        }
        if (count($files) + ($voice ? 1 : 0) > (int) BusinessSettings::inbox('max_files')) {
            throw new ChannelSendException('You can send up to '.(int) BusinessSettings::inbox('max_files').' files in one message.');
        }

        $when = $this->when($meta['scheduledAt'] ?? null);

        $maxBytes = (int) BusinessSettings::inbox('max_file_mb') * 1048576;
        foreach ([...$files, ...($voice ? [$voice] : [])] as $f) {
            if (! $f->isValid()) {
                throw new ChannelSendException('“'.$f->getClientOriginalName().'” did not upload. Try attaching it again.');
            }
            if ($f->getSize() > $maxBytes) {
                throw new ChannelSendException('“'.$f->getClientOriginalName().'” is larger than the '.(int) BusinessSettings::inbox('max_file_mb').' MB limit.');
            }
        }

        // One of each structured item per message: a second location, contact or poll is a second message.
        $by_type = [];
        foreach ($items as $it) {
            $t = (string) ($it['type'] ?? '');
            if ($t === 'file') {
                continue;
            }
            if (isset($by_type[$t])) {
                throw new ChannelSendException('Send one '.$t.' per message.');
            }
            $by_type[$t] = $it;
        }

        $location = null;
        if (isset($by_type['location'])) {
            $l = $by_type['location'];
            $lat = (float) ($l['lat'] ?? 0);
            $lng = (float) ($l['lng'] ?? 0);
            if (abs($lat) > 90 || abs($lng) > 180 || (! isset($l['lat']) || ! isset($l['lng']))) {
                throw new ChannelSendException('That location is not valid.');
            }
            $location = ['lat' => $lat, 'lng' => $lng, 'label' => null];
        }
        $contact = null;
        if (isset($by_type['contact'])) {
            $name = trim((string) ($by_type['contact']['label'] ?? ''));
            if ($name === '') {
                throw new ChannelSendException('A contact needs a name.');
            }
            $contact = ['name' => $name, 'phone' => isset($by_type['contact']['phone']) ? (string) $by_type['contact']['phone'] : null];
        }
        $poll = null;
        if (isset($by_type['poll'])) {
            $options = array_values(array_filter(array_map(fn ($o) => trim((string) $o), (array) ($by_type['poll']['options'] ?? []))));
            if (trim((string) ($by_type['poll']['label'] ?? '')) === '' || count($options) < 2) {
                throw new ChannelSendException('A poll needs a question and at least two options.');
            }
            $poll = ['question' => trim((string) $by_type['poll']['label']), 'options' => $options, 'multiple' => (bool) ($by_type['poll']['multiple'] ?? false)];
        }

        $stored = [];
        foreach ($files as $f) {
            $stored[] = $this->media->store($f, (int) $thread->wallet_group_id);
        }
        if ($voice) {
            $stored[] = $this->media->store($voice, (int) $thread->wallet_group_id, voice: true);
        }
        $message = new OutgoingMessage($text, $stored, $location, $contact, $poll);
        if ($message->isEmpty()) {
            throw new ChannelSendException('Write a message first.');
        }
        // Refuse a message the channel can never carry now, not after a delay.
        try {
            $this->sender->check((string) $thread->channel, $message);
        } catch (ChannelSendException $e) {
            $this->media->forget($stored);   // nothing will reference these files

            throw $e;
        }

        RateLimiter::hit('inbox-send:'.$by->id, 60);

        if ($when !== null) {
            ScheduledChannelMessage::firstOrCreate(
                ['user_id' => $by->id, 'idempotency_key' => mb_substr($key, 0, 80)],
                ['wallet_group_id' => $thread->wallet_group_id, 'message_thread_id' => $thread->id, 'payload' => $message->toArray(), 'send_at' => $when, 'status' => ScheduledChannelMessage::PENDING],
            );

            return ['status' => 'scheduled', 'at' => $when->toIso8601String()];
        }

        // Sending twice with the same key (a retried request) must not deliver twice.
        $lock = 'inbox-send:'.$by->id.':'.$key;
        if (! Cache::add($lock, 1, 600)) {
            return ['status' => 'duplicate'];
        }
        try {
            $rows = $this->sender->send($thread, $message, $by);
        } catch (ChannelSendException $e) {
            Cache::forget($lock);
            $this->media->forget($stored);

            throw $e;
        }

        return ['status' => 'sent', 'count' => count($rows)];
    }

    /** The composer sends the chosen time as epoch milliseconds (or null). Under a minute ahead means "now". */
    private function when(mixed $at): ?\Illuminate\Support\Carbon
    {
        if ($at === null || $at === '' || $at === false) {
            return null;
        }
        $ts = \Illuminate\Support\Carbon::createFromTimestampMs((int) $at);
        if ($ts->lte(now()->addMinute())) {
            return null;
        }
        if (! BusinessSettings::inbox('scheduling')) {
            throw new ChannelSendException('Scheduled messages are switched off for now.');
        }
        if ($ts->gt(now()->addDays((int) BusinessSettings::inbox('max_schedule_days')))) {
            throw new ChannelSendException('Messages can be scheduled up to '.(int) BusinessSettings::inbox('max_schedule_days').' days ahead.');
        }

        return $ts;
    }
}
