<?php

namespace App\Jobs;

use App\Exceptions\InsufficientBalanceException;
use App\Exceptions\OrphanChargeRefundedException;
use App\Models\CallFlow;
use App\Models\CallRecording;
use App\Models\CallRecordingSetting;
use App\Services\Business\CallAddonBilling;
use App\Support\Business\BusinessSettings;
use App\Support\MediaStorage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * A recording the telephony provider finished (MASTER-ONLY, Business Suite Prompt 15): keep it only if the call carried consent evidence,
 * charge the workspace owner for it, store it on the PRIVATE disk, then remove the provider's copy. Idempotent per provider recording SID.
 *
 * $tries = 1 on purpose: the charge is refunded if storing fails, and a retry would find that refunded debit under the same reference and
 * deliver for free (money-safety rule 7: money actions never blind-retry).
 */
class StoreCallRecordingJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    /** @param array<string,mixed> $data flow_id, node_id, call_sid, recording_sid, recording_url, duration, from, to, consent */
    public function __construct(public array $data)
    {
    }

    public function handle(CallAddonBilling $billing): void
    {
        $sid = (string) $this->data['recording_sid'];
        if ($sid === '' || CallRecording::where('provider_recording_sid', $sid)->exists()) {
            return;
        }

        $flow = CallFlow::query()->with(['walletGroup.owner', 'virtualNumber'])->find((int) $this->data['flow_id']);
        $group = $flow?->walletGroup;
        if (! $flow || ! $group || ! $group->owner) {
            $this->deleteAtProvider($sid);

        if (class_exists(\App\Services\Business\Integrations\WebhookEvents::class)) {
            // metadata only: audio and transcript text stay behind the workspace's own owner/admin access
            \App\Services\Business\Integrations\WebhookEvents::fire('call.recording_ready', $recording->wallet_group_id, [
                'recording_id' => $recording->id, 'call_id' => $recording->call_reference, 'source' => $recording->source, 'duration_seconds' => $recording->duration_seconds,
            ]);
        }

            return;
        }

        // Steer §4: a recording without a consent record is never kept.
        $consent = $this->data['consent'] ?? null;
        if (! is_array($consent) || empty($consent['regime']) || empty($consent['announced_at'])) {
            $this->deleteAtProvider($sid);

            return;
        }

        $settings = CallRecordingSetting::forGroup($group);
        $recording = CallRecording::create([
            'wallet_group_id' => $group->id,
            'user_id' => $group->owner_user_id,
            'virtual_number_id' => $flow->virtual_number_id,
            'call_flow_id' => $flow->id,
            'call_reference' => (string) ($this->data['call_sid'] ?? ''),
            'provider' => 'twilio',
            'provider_recording_sid' => $sid,
            'from_number' => $this->data['from'] ?? null,
            'to_number' => $this->data['to'] ?? null,
            'duration_seconds' => max(0, (int) ($this->data['duration'] ?? 0)),
            'status' => CallRecording::RECEIVED,
            'consent' => $consent,
            'expires_at' => now()->addDays($settings->effectiveRetentionDays()),
        ]);

        try {
            $path = $billing->charge($recording, CallAddonBilling::RECORD, function () use ($recording) {
                $audio = $this->download((string) $this->data['recording_url']);
                if ($audio === null) {
                    throw new RuntimeException('Recording could not be downloaded.');
                }
                $path = 'call-recordings/'.$recording->wallet_group_id.'/'.Str::uuid()->toString().'.mp3';
                Storage::disk(MediaStorage::privateDisk())->put($path, $audio);

                return $path;
            });
        } catch (InsufficientBalanceException) {
            $recording->update(['status' => CallRecording::UNFUNDED]);
            $this->deleteAtProvider($sid);

            return;
        } catch (OrphanChargeRefundedException) {
            $recording->update(['status' => CallRecording::FAILED]);

            return; // the refund is done; the provider copy stays until the next purge pass so support can look at it
        }

        $recording->update(['status' => CallRecording::STORED, 'storage_path' => $path]);
        $this->deleteAtProvider($sid);

        if ($settings->intelligence_enabled && BusinessSettings::module('intelligence') && $recording->walletGroup && app(\App\Services\Business\BusinessAccess::class)->allowsWorkspace($recording->walletGroup, 'intelligence')) {
            $recording->update(['transcript_status' => 'pending']);
            TranscribeCallRecordingJob::dispatch($recording->id);
        }
    }

    private function download(string $url): ?string
    {
        $sid = (string) config('services.twilio.account_sid');
        $token = (string) config('services.twilio.auth_token');
        if ($sid === '' || $token === '' || ! str_starts_with($url, 'https://api.twilio.com/')) {
            return null; // only ever fetch from the provider, never an arbitrary URL carried in a webhook
        }

        try {
            $r = Http::withBasicAuth($sid, $token)->connectTimeout(3)->timeout(60)->get($url.'.mp3');

            return $r->successful() ? $r->body() : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /** Data minimisation: once we hold the file (or decided not to), the provider keeps nothing. Best effort. */
    private function deleteAtProvider(string $recordingSid): void
    {
        $sid = (string) config('services.twilio.account_sid');
        $token = (string) config('services.twilio.auth_token');
        if ($sid === '' || $token === '') {
            return;
        }

        try {
            Http::withBasicAuth($sid, $token)->connectTimeout(3)->timeout(15)
                ->delete('https://api.twilio.com/2010-04-01/Accounts/'.$sid.'/Recordings/'.$recordingSid.'.json');
        } catch (\Throwable) {
            // the retention job is the backstop
        }
    }
}
