<?php

namespace App\Http\Controllers;

use App\Models\CallRecording;
use App\Support\Auditor;
use App\Support\Business\WorkspaceAdminAccess;
use App\Support\MediaStorage;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams a call recording from the PRIVATE disk (MASTER-ONLY, Business Suite Prompt 15). Only the workspace owner or an admin of that workspace,
 * every play is audited, and the response is never cacheable. A provider URL is never handed to a browser.
 */
class RecordingAudioController extends Controller
{
    public function __invoke(CallRecording $recording): StreamedResponse
    {
        $user = request()->user();
        abort_unless($user && $recording->walletGroup && WorkspaceAdminAccess::can($recording->walletGroup, $user), 403);
        abort_unless($recording->isPlayable(), 404);

        $disk = Storage::disk(MediaStorage::privateDisk());
        abort_unless($disk->exists((string) $recording->storage_path), 404);

        Auditor::log('business.recording_played', CallRecording::class, $recording->id, ['wallet_group_id' => $recording->wallet_group_id]);

        return $disk->response((string) $recording->storage_path, null, ['Content-Type' => 'audio/mpeg', 'Cache-Control' => 'private, no-store']);
    }
}
