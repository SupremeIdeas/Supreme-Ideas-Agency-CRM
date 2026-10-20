<?php

namespace App\Support\Business;

use App\Models\BrandSubscriptionPlan;
use Illuminate\Support\Collection;

/**
 * The ONE description of what Naara Pro is (MASTER-ONLY). Naara Pro is the combination of every Pro capability on the platform: the brand listing,
 * Naara chat, handbooks, the NaaraBusiness Suite and the perks. Every place that shows a plan (Get Listed, the Business page, the brand dashboard,
 * the setup wizard, the admin plan editor) reads THIS catalogue, so the comparison can never disagree with itself and a capability is listed once.
 *
 * A capability is one of three kinds:
 *  - included: every Pro plan has it (the gate is simply "is this account Pro").
 *  - scaled:   every plan has it, with a number that grows by plan (handles, guaranteed follows, videos, credits).
 *  - granted:  a plan has it only when the admin ticked it (Business Suite modules, premium skins).
 * A capability whose module is switched off today is not listed at all, so nobody is promised what they cannot use.
 */
class NaaraProCatalogue
{
    public const GROUPS = [
        'listing' => 'Brand listing',
        'chat' => 'Naara chat',
        'learn' => 'Handbooks',
        'suite' => 'NaaraBusiness Suite',
        'perks' => 'Perks and limits',
    ];

    /**
     * @return list<array{key:string, group:string, label:string, blurb:string, kind:string, icon:string, text:?\Closure}> every capability available on this install right now.
     * `text` renders a scaled value for a plan ("3 handles"); null for included/granted ones.
     */
    public static function capabilities(): array
    {
        $caps = [
            ['listing', 'handles', 'Social handles', 'The exact profiles people follow. Each real follow counts toward your monthly guarantee.', 'scaled', 'hash',
                fn (BrandSubscriptionPlan $p) => $p->handles_included.' '.\Illuminate\Support\Str::plural('handle', $p->handles_included)],
            ['listing', 'guarantee', 'Guaranteed follows', 'A real follower count promised per handle each month. Miss it and your listing is boosted until it catches up.', 'scaled', 'shield',
                fn (BrandSubscriptionPlan $p) => (int) $p->guaranteed_followers_per_handle_per_month > 0 ? $p->guaranteed_followers_per_handle_per_month.' per handle each month' : null],
            ['listing', 'videos', 'Video previews', 'Short YouTube or Vimeo clips that play on your profile.', 'scaled', 'play',
                fn (BrandSubscriptionPlan $p) => (int) $p->video_previews_allowed > 0 ? $p->video_previews_allowed.' '.\Illuminate\Support\Str::plural('video', $p->video_previews_allowed) : null],
            ['listing', 'seal', 'Verified Naara Pro seal', 'The badge that tells people your listing is real and in good standing.', 'included', 'check', null],
            ['listing', 'card', 'Business Card, QR code and short link', 'A readable address and printable QR code for your brand.', 'included', 'cards', null],
            ['listing', 'links', 'Website link on your profile', 'Your website or booking page, checked so visitors only meet safe pages.', 'included', 'globe', null],
        ];
        if (class_exists(\App\Services\N2N\Media\NaaraPro::class) && \App\Support\N2N\N2NSettings::enabled()) {
            $caps[] = ['chat', 'media', 'Photos, videos, voice notes and files in chats', 'Send media in Naara chat instead of text only.', 'included', 'image', null];
            $caps[] = ['chat', 'status', 'Post status updates', 'Share updates that connected people see in their Updates tab.', 'included', 'story-ring', null];
            $caps[] = ['chat', 'reach', 'Be reachable by anyone on Naara', 'People can message and call your brand without being connected first.', 'included', 'message-circle', null];
        }
        if (class_exists(\App\Support\Handbooks\HandbookSettings::class) && \App\Support\Handbooks\HandbookSettings::get('pro_unlock_enabled') && \App\Support\Handbooks\HandbookSettings::proSlots() > 0) {
            $slots = \App\Support\Handbooks\HandbookSettings::proSlots();
            $caps[] = ['learn', 'handbooks', 'Travel handbooks included', 'Unlock handbooks while Naara Pro is active.', 'included', 'book-open', null, $slots.' '.\Illuminate\Support\Str::plural('handbook', $slots)];
        }
        foreach (BusinessPlanFeatures::FEATURES as $key => $f) {
            if (BusinessPlanFeatures::live($key)) {
                $caps[] = ['suite', 'suite.'.$key, $f['label'], $f['blurb'], 'granted', $f['icon'], null];
            }
        }
        $caps[] = ['perks', 'credits', 'Monthly NaaraCredits', 'Credit refilled to your wallet each billing month, for chat and calls.', 'scaled', 'wallet',
            fn (BrandSubscriptionPlan $p) => $p->monthlyCredits() > 0 ? number_format($p->monthlyCredits()).' every month' : null];
        $caps[] = ['perks', 'skins', 'Premium dashboard skins', 'Extra looks for your whole dashboard.', 'granted', 'sparkles', null];
        if (BusinessSuiteSettings::enabled()) {
            foreach (BusinessPlanFeatures::LIMITS as $name => $d) {
                $caps[] = ['perks', 'limit.'.$name, $d['label'], $d['blurb'], 'scaled', 'users',
                    fn (BrandSubscriptionPlan $p) => ($p->grantsFeature('workspace') || BusinessSettings::accessModel() === 'open') ? 'up to '.max(BusinessSettings::limit($name), (int) $p->limitOverride($name)) : null];
            }
        }

        return array_map(fn (array $c) => ['group' => $c[0], 'key' => $c[1], 'label' => $c[2], 'blurb' => $c[3], 'kind' => $c[4], 'icon' => $c[5], 'text' => $c[6], 'always' => $c[7] ?? null], $caps);
    }

    /** What one plan gives for one capability. @return array{on: bool, text: ?string} */
    public static function cell(BrandSubscriptionPlan $plan, array $cap): array
    {
        if ($cap['kind'] === 'included') {
            return ['on' => true, 'text' => $cap['always'] ?? null];
        }
        if ($cap['kind'] === 'scaled') {
            $text = $cap['text'] ? ($cap['text'])($plan) : null;

            return ['on' => $text !== null, 'text' => $text];
        }
        $on = match (true) {
            $cap['key'] === 'skins' => $plan->grantsPremiumSkins(),
            str_starts_with($cap['key'], 'suite.') => $plan->grantsFeature(substr($cap['key'], 6)),
            default => false,
        };

        return ['on' => $on, 'text' => null];
    }

    /** @return list<array{key:string, label:string, rows:list<array>}> capabilities by group, in display order (empty groups dropped) */
    public static function groups(): array
    {
        $caps = self::capabilities();
        $out = [];
        foreach (self::GROUPS as $key => $label) {
            $rows = array_values(array_filter($caps, fn ($c) => $c['group'] === $key));
            if ($rows !== []) {
                $out[] = ['key' => $key, 'label' => $label, 'rows' => $rows];
            }
        }

        return $out;
    }

    /** Everything a plan turns on, as short lines for a plan card. @return list<string> */
    public static function bullets(BrandSubscriptionPlan $plan): array
    {
        $out = [];
        foreach (self::capabilities() as $cap) {
            $cell = self::cell($plan, $cap);
            if (! $cell['on']) {
                continue;
            }
            $out[] = $cap['kind'] === 'scaled' && $cell['text'] !== null ? $cap['label'].': '.$cell['text'] : ($cell['text'] ? $cap['label'].': '.$cell['text'] : $cap['label']);
        }

        return $out;
    }

    /** @param Collection<int, BrandSubscriptionPlan> $plans @return array{plans: Collection, groups: list<array>} one grid for a comparison table: each row carries one cell per plan */
    public static function matrix(Collection $plans): array
    {
        $groups = self::groups();
        foreach ($groups as &$g) {
            foreach ($g['rows'] as &$row) {
                $row['cells'] = $plans->mapWithKeys(fn ($p) => [$p->id => self::cell($p, $row)])->all();
            }
            unset($row);
        }
        unset($g);

        return ['plans' => $plans, 'groups' => $groups];
    }

    /** What moving from one plan to another adds (or the whole plan when $from is null). @return list<string> */
    public static function delta(?BrandSubscriptionPlan $from, BrandSubscriptionPlan $to): array
    {
        $out = [];
        foreach (self::capabilities() as $cap) {
            $b = self::cell($to, $cap);
            $a = $from ? self::cell($from, $cap) : ['on' => false, 'text' => null];
            if ($b['on'] && (! $a['on'] || $a['text'] !== $b['text'])) {
                $out[] = $cap['label'].($b['text'] ? ': '.$b['text'] : '');
            }
        }

        return $out;
    }

    /** Which capabilities an account holds on its plan, and which a higher plan would add: the "your account unlocks" view. @return array{on: list<array>, locked: list<array{cap: array, plan: ?string}>} */
    public static function entitlements(?BrandSubscriptionPlan $plan, Collection $allPlans): array
    {
        $on = [];
        $locked = [];
        foreach (self::capabilities() as $cap) {
            $cell = $plan ? self::cell($plan, $cap) : ['on' => false, 'text' => null];
            if ($cell['on']) {
                $on[] = ['cap' => $cap, 'text' => $cell['text']];

                continue;
            }
            $unlocker = $allPlans->first(fn ($p) => self::cell($p, $cap)['on']);
            $locked[] = ['cap' => $cap, 'plan' => $unlocker?->name];
        }

        return ['on' => $on, 'locked' => $locked];
    }
}
