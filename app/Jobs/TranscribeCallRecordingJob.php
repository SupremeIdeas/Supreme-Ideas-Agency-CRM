<?php

namespace App\Jobs;

use App\Exceptions\InsufficientBalanceException;
use App\Exceptions\OrphanChargeRefundedException;
use App\Models\CallRecording;
use App\Models\CallTranscript;
use App\Services\Business\CallAddonBilling;
use App\Services\Support\Contracts\VoiceSynthesizer;
use App\Support\MediaStorage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Turns a stored call recording into a transcript (MASTER-ONLY, Business Suite Prompt 15), same shape as CaptureEsimUsageSnapshotJob: fetch, normalise,
 * write one clean row, leave the status on the parent. Reuses the speech-to-text capability the voicemail transcription already runs on
 * (VoiceSynthesizer::transcribe), so there is no new vendor; `transcript_source` records which engine produced it for a future swap.
 * Only runs for workspaces that switched intelligence on. Billed through CallAddonBilling (WalletService::charge), never retried blind.
 */
class TranscribeCallRecordingJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public int $recordingId)
    {
    }

    public function handle(CallAddonBilling $billing, VoiceSynthesizer $stt): void
    {
        $rec = CallRecording::find($this->recordingId);
        if (! $rec || ! $rec->isPlayable() || $rec->transcript()->exists() || $rec->transcript_status === 'processing') {
            return;
        }
        if (! $stt->available()) {
            $rec->update(['transcript_status' => 'skipped']); // no engine configured: nothing is charged

            return;
        }

        $rec->update(['transcript_status' => 'processing']);

        try {
            $text = $billing->charge($rec, CallAddonBilling::TRANSCRIBE, function () use ($rec, $stt) {
                $disk = Storage::disk(MediaStorage::privateDisk());
                if (! $disk->exists((string) $rec->storage_path)) {
                    throw new RuntimeException('Recording file is missing.');
                }
                $text = trim((string) $stt->transcribe($disk->get((string) $rec->storage_path), 'audio/mpeg'));
                if ($text === '') {
                    throw new RuntimeException('Transcription returned nothing.');
                }
                CallTranscript::create(['call_recording_id' => $rec->id, 'transcript_text' => $text, 'transcript_source' => 'elevenlabs']);

                return $text;
            });
        } catch (InsufficientBalanceException) {
            $rec->update(['transcript_status' => 'failed']);

            return;
        } catch (OrphanChargeRefundedException) {
            $rec->update(['transcript_status' => 'failed']);

            return;
        }

        $rec->update(['transcript_status' => 'complete', 'intelligence_status' => 'pending']);
        SummariseCallJob::dispatch($rec->id);
    }
}
