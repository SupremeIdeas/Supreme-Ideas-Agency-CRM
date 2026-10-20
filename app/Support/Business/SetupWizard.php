<?php

namespace App\Support\Business;

use App\Models\AiVoiceAgentConfig;
use App\Models\CallFlow;
use App\Models\CallRecordingSetting;
use App\Models\ChannelConnection;
use App\Models\User;
use App\Models\WalletGroup;
use App\Services\Business\BusinessAccess;

/**
 * The NaaraBusiness Suite Setup Wizard's brain (MASTER-ONLY): which steps a workspace has, which are open to the owner's Naara Pro plan, and the live status of each
 * (done, needs attention, not set up, locked). Status is read from what is really saved, never from a stored flag, so the overview and the wizard can never disagree.
 */
class SetupWizard
{
    public const DONE = 'done';

    public const ATTENTION = 'attention';

    public const TODO = 'todo';

    public const LOCKED = 'locked';

    /** key => [plan feature that unlocks it or null, optional?] in the order a person should do them */
    private const STEPS = [
        'profile' => [null, false],
        'channels' => ['inbox', false],
        'respond' => ['inbox', true],
        'team' => [null, true],
        'call_flows' => ['call_flows', true],
        'recording' => ['recording', true],
        'ai_voice' => ['ai_voice', true],
        'integrations' => ['integrations', true],
    ];

    /** Where a step with its own page sends the person. */
    private const PAGES = ['call_flows' => 'business.call-flows', 'recording' => 'business.recordings', 'ai_voice' => 'business.assistant', 'channels' => 'business.channels', 'respond' => 'business.respond.automation'];

    public function __construct(private BusinessAccess $access)
    {
    }

    /** @return list<array{key:string,title:string,enables:string,optional:bool,feature:?string,feature_label:?string,status:string,detail:string,page:?string}> */
    public function steps(WalletGroup $group, User $viewer): array
    {
        $owner = $group->owner ?? $viewer;
        $out = [];
        foreach (self::STEPS as $key => [$feature, $optional]) {
            if ($key === 'respond' && ! (class_exists(\App\Models\Omni\OmniKbSource::class) && BusinessSettings::omniLive())) {
                continue;   // Naara Respond is off (or absent on a fork): the wizard never mentions it
            }
            $guide = (array) config("business_suite_setup.{$key}", []);
            $open = $feature === null || $this->access->allows($owner, $feature);
            [$status, $detail] = $open ? $this->state($key, $group) : [self::LOCKED, 'Your plan does not include this yet. A higher Naara Pro plan unlocks it.'];
            $out[] = [
                'key' => $key,
                'title' => (string) ($guide['title'] ?? $key),
                'enables' => (string) ($guide['enables'] ?? ''),
                'optional' => $optional,
                'feature' => $feature,
                'feature_label' => $feature ? (BusinessPlanFeatures::FEATURES[$feature]['label'] ?? $feature) : null,
                'status' => $status,
                'detail' => $detail,
                'page' => isset(self::PAGES[$key]) && \Illuminate\Support\Facades\Route::has(self::PAGES[$key]) ? route(self::PAGES[$key]) : null,
            ];
        }

        return $out;
    }

    /** Percent complete over the steps the plan opens (locked steps are not counted against the person). */
    public function percent(array $steps): int
    {
        $counted = array_filter($steps, fn ($s) => $s['status'] !== self::LOCKED);
        if ($counted === []) {
            return 0;
        }
        $done = count(array_filter($counted, fn ($s) => $s['status'] === self::DONE));

        return (int) round($done / count($counted) * 100);
    }

    /** @return array{0:string,1:string} */
    private function state(string $key, WalletGroup $group): array
    {
        return match ($key) {
            'profile' => $this->profile($group),
            'respond' => $this->respond($group),
            'channels' => $this->channels($group),
            'team' => $group->members()->count() > 1
                ? [self::DONE, 'Your team is in.']
                : [self::TODO, 'Only you so far. Invite someone, or skip this for now.'],
            'call_flows' => CallFlow::where('wallet_group_id', $group->id)->exists()
                ? [self::DONE, 'A call flow is set up.']
                : [self::TODO, 'No call flow yet.'],
            'recording' => CallRecordingSetting::where('wallet_group_id', $group->id)->where('recording_enabled', true)->exists()
                ? [self::DONE, 'Recording is on.']
                : [self::TODO, 'Recording is off.'],
            'ai_voice' => AiVoiceAgentConfig::where('wallet_group_id', $group->id)->where('enabled', true)->exists()
                ? [self::DONE, 'The assistant is on.']
                : [self::TODO, 'The assistant is not switched on.'],
            default => [self::TODO, 'Open the page to set it up.'],
        };
    }

    /** @return array{0:string,1:string} */
    private function respond(WalletGroup $group): array
    {
        $ws = (int) $group->id;
        if (! \App\Models\Omni\OmniKbSource::query()->forWorkspace($ws)->where('status', 'ready')->exists()) {
            return [self::TODO, 'Teach it your business: add a FAQ or a web page so the assistant has something true to say.'];
        }
        $mode = \App\Models\Omni\OmniAgentSetting::query()->forWorkspace($ws)->value('mode');
        $flow = \App\Models\Omni\OmniFlow::query()->forWorkspace($ws)->where('status', 'live')->exists();
        if (($mode && $mode !== 'off') || $flow) {
            return [self::DONE, ($mode && $mode !== 'off' ? 'The assistant is on ('.$mode.')' : 'A flow is running').'.'];
        }

        return [self::TODO, 'Your knowledge is ready. Choose Suggest mode or turn on a starter flow.'];
    }

    /** @return array{0:string,1:string} */
    private function profile(WalletGroup $group): array
    {
        if (trim((string) $group->name) === '') {
            return [self::TODO, 'Give the workspace a name.'];
        }
        if (! $group->business_email) {
            return [self::ATTENTION, 'Add a business email so notices reach you.'];
        }
        if ($group->needsBusinessEmailVerification()) {
            return [self::ATTENTION, 'Check your inbox and confirm the business email.'];
        }

        return [self::DONE, 'Name and business email are set.'];
    }

    /** @return array{0:string,1:string} */
    private function channels(WalletGroup $group): array
    {
        $all = ChannelConnection::where('wallet_group_id', $group->id)->get();
        if ($all->isEmpty()) {
            return [self::TODO, 'No channel connected yet.'];
        }
        $bad = $all->first(fn (ChannelConnection $c) => $c->status === ChannelConnection::ERROR);
        if ($bad) {
            return [self::ATTENTION, ChannelConnection::label($bad->channel).' needs attention: '.($bad->last_error ?: 'reconnect it.')];
        }

        return [self::DONE, $all->where('status', ChannelConnection::ACTIVE)->count().' channel(s) connected.'];
    }
}
