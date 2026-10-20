<?php

namespace App\Support\Verify\Checks;

use App\Models\Omni\OmniAgentSetting;
use App\Models\Omni\OmniBroadcast;
use App\Models\Omni\OmniBroadcastRecipient;
use App\Models\Omni\OmniKbSource;
use App\Models\Omni\OmniWebhook;
use App\Services\Omni\Kb\SafeFetcher;
use App\Support\Verify\Check;
use App\Support\Verify\CheckResult;
use Illuminate\Support\Facades\Schema;
use Throwable;

/** MASTER-ONLY. `naara:verify omni`: the Respond automation tables exist, the SSRF guard still refuses the cloud-metadata address, no AI-on workspace has an empty knowledge base, no broadcast is stuck. Pure reads. */
class OmniAutomationSound implements Check
{
    public function area(): string
    {
        return 'omni';
    }

    public function name(): string
    {
        return 'Respond automation: SSRF guard, AI grounding, broadcasts, webhooks';
    }

    public function run(): CheckResult
    {
        try {
            foreach (['omni_kb_sources', 'omni_kb_chunks', 'omni_agent_settings', 'omni_agent_runs', 'omni_flows', 'omni_flow_runs', 'omni_broadcasts', 'omni_broadcast_recipients', 'omni_webhooks'] as $t) {
                if (! Schema::hasTable($t)) {
                    return CheckResult::fail("Table {$t} is missing: run the migrations.");
                }
            }
            try {
                (new SafeFetcher)->assertPublic('http://169.254.169.254/latest/meta-data');

                return CheckResult::fail('The web-page guard let the cloud metadata address through. Do not enable the knowledge-base crawler or webhooks until this is fixed.');
            } catch (\RuntimeException) {
                // refused, as it must be
            }
            $untaught = OmniAgentSetting::query()->whereIn('mode', ['suggest', 'auto'])->get()
                ->filter(fn ($s) => ! OmniKbSource::query()->where('workspace_id', $s->workspace_id)->where('status', 'ready')->exists())->count();
            $stuckIds = OmniBroadcast::query()->where('status', 'sending')->where('updated_at', '<', now()->subMinutes(15))->pluck('id');
            $stuck = $stuckIds->isEmpty() ? 0 : OmniBroadcastRecipient::query()->whereIn('broadcast_id', $stuckIds)->where('status', 'queued')->distinct()->count('broadcast_id');
            $hooksOff = OmniWebhook::query()->where('active', false)->count();
        } catch (Throwable $e) {
            return CheckResult::fail('Respond automation could not be checked: '.$e->getMessage());
        }
        if ($stuck > 0) {
            return CheckResult::fail("{$stuck} broadcast(s) have not moved for 15 minutes: the queue worker or the cron is not running.", ['stuck_broadcasts' => $stuck]);
        }
        if ($untaught > 0 || $hooksOff > 0) {
            return CheckResult::warn("{$untaught} workspace(s) have the AI assistant on with an empty knowledge base; {$hooksOff} webhook(s) were switched off after failures.", ['untaught' => $untaught, 'webhooks_off' => $hooksOff]);
        }

        return CheckResult::pass('Automation tables present, SSRF guard holds, AI is grounded, broadcasts and webhooks healthy.');
    }
}
