<?php

namespace App\Services\Business\AiVoice;

use App\Jobs\SummariseCallJob;
use App\Models\AiVoiceAgentSession;
use App\Models\CallRecording;
use App\Models\CallRecordingSetting;
use App\Models\CallTranscript;
use App\Support\Business\BusinessSettings;

/**
 * Wraps up an agent call (MASTER-ONLY, Business Suite Prompt 17): works out the outcome, bills it, and files the transcript in the SAME place a human-handled
 * call's transcript goes (a `call_recordings` row with source `ai_agent` and no audio, plus its `call_transcripts` row), so the call-detail view and the
 * intelligence summary (Prompt 15) cover AI calls without a second reporting system. Idempotent; runs from the server on close and from Twilio's action callback.
 */
class AgentSessionFinalizer
{
    public function __construct(private AgentBilling $billing)
    {
    }

    public function finalize(AiVoiceAgentSession $session): AiVoiceAgentSession
    {
        $session->refresh();
        $duration = (int) ($session->started_at ? max(0, now()->diffInSeconds($session->started_at, true)) : 0);
        $duration = min($duration, $session->fundedSeconds() + 15);

        if ($session->settled_at === null) {
            $handedOff = $session->handoff_reason !== null;
            $session->forceFill([
                'status' => $handedOff ? AiVoiceAgentSession::HANDED_OFF : AiVoiceAgentSession::ENDED,
                'outcome' => $session->outcome === 'booked' ? 'booked' : ($handedOff ? 'handed_off' : ($session->outcome ?: 'no_outcome')),
                'ended_at' => now(),
            ])->save();
            $session = $this->billing->settle($session, $duration);
        }

        return $this->file($session);
    }

    private function file(AiVoiceAgentSession $session): AiVoiceAgentSession
    {
        if ($session->recording_id || ! $session->transcript) {
            return $session;
        }
        $group = $session->walletGroup;
        $settings = CallRecordingSetting::forGroup($group);

        $recording = CallRecording::create([
            'wallet_group_id' => $group->id, 'user_id' => $group->owner_user_id, 'virtual_number_id' => $session->virtual_number_id, 'call_flow_id' => $session->call_flow_id,
            'call_reference' => (string) $session->call_reference, 'provider' => 'twilio', 'source' => 'ai_agent', 'provider_recording_sid' => 'ai:'.$session->id,
            'from_number' => $session->from_number, 'duration_seconds' => $session->duration_seconds, 'storage_path' => null, 'status' => CallRecording::STORED,
            'transcript_status' => 'complete', 'intelligence_status' => 'none',
            // The caller was told it is an automated assistant in the greeting: that is the disclosure evidence.
            'consent' => ['regime' => 'ai_disclosure', 'action' => 'greeting', 'announced_at' => optional($session->started_at)->toIso8601String() ?: now()->toIso8601String()],
            'expires_at' => now()->addDays($settings->effectiveRetentionDays()),
        ]);

        $text = collect((array) $session->transcript)->map(fn ($t) => ($t['role'] === 'agent' ? 'Assistant: ' : 'Caller: ').($t['text'] ?? ''))->implode("\n");
        CallTranscript::create(['call_recording_id' => $recording->id, 'transcript_text' => $text, 'transcript_source' => 'ai_agent']);
        $session->forceFill(['recording_id' => $recording->id])->save();

        if ($settings->intelligence_enabled && BusinessSettings::module('intelligence') && $recording->walletGroup && app(\App\Services\Business\BusinessAccess::class)->allowsWorkspace($recording->walletGroup, 'intelligence')) {
            $recording->update(['intelligence_status' => 'pending']);
            SummariseCallJob::dispatch($recording->id);
        }

        return $session;
    }
}
