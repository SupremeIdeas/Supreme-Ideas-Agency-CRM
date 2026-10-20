<?php

namespace App\Services\Business;

use App\Models\CallRecording;
use App\Models\CallRecordingSetting;
use App\Support\Auditor;
use App\Support\MediaStorage;
use Illuminate\Support\Facades\Storage;

/**
 * Deletes call recordings for real (MASTER-ONLY, Business Suite Prompt 15): the audio file, the transcript and the summary go; a thin row stays
 * (who/when/how long, consent evidence, billing references) so the books and the consent trail still add up. Used by the retention job, by a
 * workspace deleting one recording, and by account erasure.
 */
class RecordingRetention
{
    public function purge(CallRecording $rec, string $why): void
    {
        if ($rec->purged_at !== null) {
            return;
        }

        if ($rec->storage_path) {
            Storage::disk(MediaStorage::privateDisk())->delete($rec->storage_path);
        }
        $rec->transcript()->delete();
        $rec->intelligence()->delete();
        $rec->forceFill([
            'status' => CallRecording::PURGED, 'storage_path' => null, 'purged_at' => now(),
            'transcript_status' => 'none', 'intelligence_status' => 'none',
        ])->save();

        Auditor::log('business.recording_purged', CallRecording::class, $rec->id, ['why' => $why, 'wallet_group_id' => $rec->wallet_group_id]);
    }

    /**
     * Retention pass: expired recordings (unless the workspace has a legal hold) and any recording that has no consent record.
     *
     * @return int how many were purged
     */
    public function sweep(int $limit = 500): int
    {
        $held = CallRecordingSetting::query()->whereNotNull('legal_hold_at')->pluck('wallet_group_id')->all();
        $n = 0;

        CallRecording::query()->whereNull('purged_at')
            ->where(function ($q) use ($held) {
                $q->where(fn ($e) => $e->where('expires_at', '<=', now())->whereNotIn('wallet_group_id', $held))
                    ->orWhereNull('consent');
            })
            ->limit($limit)->get()
            ->each(function (CallRecording $r) use (&$n) {
                $this->purge($r, $r->consent === null ? 'no_consent_record' : 'retention_expired');
                $n++;
            });

        return $n;
    }
}
