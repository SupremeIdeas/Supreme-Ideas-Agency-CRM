<?php

namespace App\Support\Business;

/**
 * What a Naara Pro (NaaraBusiness Suite) plan can grant (MASTER-ONLY). The ONE list the admin plan editor, the access gate, the dashboard and the
 * marketing page all read, so they can never drift apart.
 *
 * Two kinds of capability:
 *  - FEATURES: Business Suite modules. A feature is assignable to a plan ONLY while the admin has switched its module on in Business Suite settings
 *    (and the Suite itself is on). Switching a module off removes the access instantly and keeps the assignment, so switching it back on restores it.
 *  - PERKS: things Naara Pro grants outside the Suite (premium dashboard skins, a monthly NaaraCredit refill) and per-plan workspace limits.
 *
 * Stored on `brand_subscription_plans.capabilities` as {"features": [...], "premium_skins": bool, "monthly_credits": int, "limits": {...}}.
 */
class BusinessPlanFeatures
{
    public const WORKSPACE = 'workspace';

    /** @var array<string, array{label: string, blurb: string, module: ?string, icon: string, needs: list<string>}> */
    public const FEATURES = [
        'workspace' => ['label' => 'Team workspace', 'icon' => 'users', 'module' => null, 'needs' => [],
            'blurb' => 'One shared wallet, seats for your team and roles (owner, admin, agent, viewer). Every other Suite feature sits on top of this.'],
        'call_flows' => ['label' => 'Call flows', 'icon' => 'phone', 'module' => 'call_flows', 'needs' => ['workspace'],
            'blurb' => 'Menus, ring groups, business hours and voicemail on your business numbers.'],
        'inbox' => ['label' => 'Shared inbox', 'icon' => 'message-circle', 'module' => 'inbox', 'needs' => ['workspace'],
            'blurb' => 'WhatsApp, Telegram, Instagram and SMS conversations in one team inbox.'],
        'recording' => ['label' => 'Call recording', 'icon' => 'mic', 'module' => 'recording', 'needs' => ['workspace'],
            'blurb' => 'Record calls with a clear notice for every caller, then review and delete them under your own retention rules.'],
        'intelligence' => ['label' => 'Call intelligence', 'icon' => 'sparkles', 'module' => 'intelligence', 'needs' => ['workspace', 'recording'],
            'blurb' => 'Transcripts, summaries and coaching notes on recorded calls.'],
        'ai_voice' => ['label' => 'AI voice assistant', 'icon' => 'bot', 'module' => 'ai_voice', 'needs' => ['workspace'],
            'blurb' => 'An assistant that answers your number, takes callback requests and hands over to a person.'],
        'brain_assist' => ['label' => 'NaaraBrain assistant', 'icon' => 'sparkles', 'module' => 'brain_assist', 'needs' => ['workspace', 'inbox'],
            'blurb' => 'Draft replies, summarise a conversation and suggest follow-ups in the shared inbox, and (with Naara Respond) let the AI answer from your knowledge base, all inside one monthly allowance. In Suggest mode you always review and send.'],
        'integrations' => ['label' => 'Integrations and API', 'icon' => 'plug', 'module' => 'integrations', 'needs' => ['workspace'],
            'blurb' => 'A workspace API key and signed webhooks, so Zapier and your own tools can connect.'],
    ];

    /** Per-plan workspace limits a plan may raise above the platform default (0 or empty = use the platform default). */
    public const LIMITS = [
        'seats' => ['label' => 'Seats', 'blurb' => 'People (with pending invites) one workspace can hold.'],
        'numbers' => ['label' => 'Numbers', 'blurb' => 'Phone lines one workspace can bring in.'],
        'channels' => ['label' => 'Connected channels', 'blurb' => 'WhatsApp numbers, Telegram bots and Instagram accounts one workspace can connect.'],
    ];

    /** @return list<string> */
    public static function keys(): array
    {
        return array_keys(self::FEATURES);
    }

    /** Is this feature's module switched on right now (the Suite itself must be on too)? */
    public static function live(string $key): bool
    {
        $f = self::FEATURES[$key] ?? null;
        if ($f === null || ! BusinessSuiteSettings::enabled()) {
            return false;
        }

        return $f['module'] === null || BusinessSettings::module($f['module']);
    }

    /** @return list<string> features the admin may assign to a plan today */
    public static function assignable(): array
    {
        return array_values(array_filter(self::keys(), fn (string $k) => self::live($k)));
    }

    /**
     * Make a chosen set consistent: only known keys, and every feature brings the ones it stands on (inbox needs the workspace, intelligence needs
     * recording). Order follows the catalogue.
     *
     * @param  array<int, mixed>  $keys
     * @return list<string>
     */
    public static function normalize(array $keys): array
    {
        $want = array_intersect(self::keys(), array_map('strval', $keys));
        $out = [];
        foreach ($want as $k) {
            foreach (self::FEATURES[$k]['needs'] as $dep) {
                $out[$dep] = true;
            }
            $out[$k] = true;
        }

        return array_values(array_intersect(self::keys(), array_keys($out)));
    }

    /**
     * Clean the raw capabilities an admin form submits into what is stored. Unknown keys are dropped, numbers are clamped, nothing is trusted.
     *
     * @param  array<string, mixed>  $in
     * @return array{features: list<string>, premium_skins: bool, monthly_credits: int, limits: array<string, int>}
     */
    public static function clean(array $in): array
    {
        $limits = [];
        foreach (array_keys(self::LIMITS) as $name) {
            $v = (int) ($in['limits'][$name] ?? 0);
            if ($v > 0) {
                $limits[$name] = min($v, 10000);
            }
        }

        $out = [
            'features' => self::normalize((array) ($in['features'] ?? [])),
            'premium_skins' => (bool) ($in['premium_skins'] ?? false),
            'monthly_credits' => max(0, min((int) ($in['monthly_credits'] ?? 0), 100000)),
            'limits' => $limits,
        ];
        // NaaraBrain (master only): questions per day and the depths a plan opens. Stored only when set, so a plan without it uses the Pro defaults.
        if (class_exists(\App\Support\Brain\BrainAccess::class) && ($brain = \App\Support\Brain\BrainAccess::cleanPlanCaps((array) ($in['brain'] ?? []))) !== []) {
            $out['brain'] = $brain;
        }

        // The inbox assistant's monthly allowance for this plan (blank = the platform default in Business Suite settings).
        $actions = (int) ($in['brain_business']['monthly_actions'] ?? 0);
        if ($actions > 0) {
            $out['brain_business'] = ['monthly_actions' => min($actions, 100000)];
        }

        return $out;
    }

    /** What a brand-new plan grants until the admin decides: nothing from the Suite. */
    public static function empty(): array
    {
        return ['features' => [], 'premium_skins' => false, 'monthly_credits' => 0, 'limits' => []];
    }
}
