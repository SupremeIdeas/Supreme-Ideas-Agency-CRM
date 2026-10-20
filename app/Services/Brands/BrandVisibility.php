<?php

namespace App\Services\Brands;

use App\Models\BrandPartner;
use App\Models\BrandSubscription;
use App\Models\Setting;
use App\Support\Auditor;

/**
 * Brand visibility, as two separate concerns with explicit reasons (Brand-Growth Phase 3, MASTER-ONLY):
 *
 *   CONTENT   draft -> ready -> published   (the soft gate: cover photo, profile photo, name, description, at least 2 social links; everything else is optional)
 *   ENTITLEMENT  none | trialing | active | past_due | cancelled | granted   (derived from billing, never stored)
 *
 *   live = published AND entitled AND active AND not paused/disabled.
 *
 * `reason()` returns ONE machine-readable code that the owner and the admin both see, so "why am I not in the directory?" always has a precise answer.
 * Brands created under the old gate (gate_version 1) keep its rules and are grandfathered as published: a gate change never unpublishes anyone.
 */
final class BrandVisibility
{
    public const GATE_V1 = 1;

    public const GATE_V2 = 2;

    /** The version stamped on a brand created from now on (admin setting; the soft gate by default). */
    public static function newBrandVersion(): int
    {
        try {
            $v = (int) Setting::getValue('brand.gate_version_new', self::GATE_V2);
        } catch (\Throwable) {
            $v = self::GATE_V2;
        }

        return $v === self::GATE_V1 ? self::GATE_V1 : self::GATE_V2;
    }

    /**
     * @return list<array{key: string, label: string, required: bool, met: bool, hint: string}>
     */
    public static function requirements(BrandPartner $b, ?int $version = null): array
    {
        $v2 = ($version ?? (int) $b->gate_version) >= self::GATE_V2;
        $handles = $b->relationLoaded('handles') ? $b->handles->where('is_active', true)->count() : $b->handles()->where('is_active', true)->count();

        if (! $v2) {   // the original gate, unchanged
            return [
                ['key' => 'name', 'label' => 'Brand name', 'required' => true, 'met' => filled($b->brand_name), 'hint' => 'Add your brand name.'],
                ['key' => 'category', 'label' => 'Category', 'required' => true, 'met' => filled($b->category), 'hint' => 'Choose a category.'],
                ['key' => 'cover', 'label' => 'Cover image', 'required' => true, 'met' => filled($b->hero_image_path) || filled($b->fallback_image), 'hint' => 'Upload a cover image.'],
                ['key' => 'handles', 'label' => 'A social handle', 'required' => true, 'met' => $handles >= 1, 'hint' => 'Add at least one social handle.'],
            ];
        }

        return [
            ['key' => 'cover', 'label' => 'Cover photo', 'required' => true, 'met' => filled($b->hero_image_path) || filled($b->fallback_image), 'hint' => 'Upload a cover photo.'],
            ['key' => 'logo', 'label' => 'Profile photo (logo)', 'required' => true, 'met' => filled($b->logo_path), 'hint' => 'Upload your logo or profile photo.'],
            ['key' => 'name', 'label' => 'Brand name', 'required' => true, 'met' => filled($b->brand_name), 'hint' => 'Add your brand name.'],
            ['key' => 'description', 'label' => 'Description', 'required' => true, 'met' => filled(trim((string) $b->short_description)), 'hint' => 'Write a short description of your business.'],
            ['key' => 'handles', 'label' => 'Two social links', 'required' => true, 'met' => $handles >= 2, 'hint' => $handles === 1 ? 'Add one more social link (you have 1 of 2).' : 'Add two social links.'],
            // recommended, never blocking
            ['key' => 'category', 'label' => 'Category', 'required' => false, 'met' => filled($b->category), 'hint' => 'Pick a category so people can find you.'],
            ['key' => 'links', 'label' => 'A website link', 'required' => false, 'met' => class_exists(\App\Models\BrandLink::class) && $b->links()->approved()->exists(), 'hint' => 'Add your website under Business links.'],
            ['key' => 'videos', 'label' => 'A video preview', 'required' => false, 'met' => $b->videos()->exists(), 'hint' => 'Add a video preview if your plan includes it.'],
        ];
    }

    /** @return list<string> the labels of the required items still missing */
    public static function missing(BrandPartner $b): array
    {
        return array_values(array_map(fn ($r) => $r['label'], array_filter(self::requirements($b), fn ($r) => $r['required'] && ! $r['met'])));
    }

    public static function passes(BrandPartner $b): bool
    {
        return self::missing($b) === [];
    }

    /** @return array{done: int, total: int, percent: int} progress over the REQUIRED items */
    public static function progress(BrandPartner $b): array
    {
        $req = array_filter(self::requirements($b), fn ($r) => $r['required']);
        $done = count(array_filter($req, fn ($r) => $r['met']));

        return ['done' => $done, 'total' => count($req), 'percent' => count($req) > 0 ? (int) round($done / count($req) * 100) : 100];
    }

    /** draft: something required is missing; ready: all required items present, not published; published. */
    public static function contentState(BrandPartner $b): string
    {
        if ($b->published_at !== null) {
            return 'published';
        }

        return self::passes($b) ? 'ready' : 'draft';
    }

    public static function entitlement(BrandPartner $b): string
    {
        $sub = $b->subscription;
        $status = $sub?->status;
        if ($status === BrandSubscription::ACTIVE) {
            return 'active';
        }
        if (defined(BrandSubscription::class.'::TRIALING') && $status === constant(BrandSubscription::class.'::TRIALING')) {
            return 'trialing';
        }
        if ($b->hasProGrant()) {
            return 'granted';
        }

        return match ($status) { BrandSubscription::PAST_DUE => 'past_due', BrandSubscription::CANCELLED => 'cancelled', default => 'none' };
    }

    public static function isEntitled(BrandPartner $b): bool
    {
        return in_array(self::entitlement($b), ['active', 'trialing', 'granted'], true);
    }

    /** One machine-readable reason, evaluated in a fixed order. */
    public static function reason(BrandPartner $b): string
    {
        if ($b->listing_status === BrandPartner::STATUS_DISABLED) {
            return 'disabled';
        }
        if (! $b->is_active) {
            return 'inactive';
        }
        if ($b->listing_status === BrandPartner::STATUS_PAUSED || self::entitlement($b) === 'past_due') {
            return 'past_due';
        }
        $state = self::contentState($b);
        if ($state === 'draft') {
            return 'unpublished_incomplete';
        }
        if ($state === 'ready') {
            return 'ready_to_publish';
        }
        if (! self::isEntitled($b)) {
            return self::entitlement($b) === 'cancelled' ? 'cancelled' : 'not_entitled';
        }

        return $b->listing_status === BrandPartner::STATUS_ACTIVE ? 'live' : 'syncing';
    }

    public static function isLive(BrandPartner $b): bool
    {
        return self::reason($b) === 'live';
    }

    /** What the owner reads. */
    public static function message(BrandPartner $b): string
    {
        return match (self::reason($b)) {
            'live' => 'You are live in the directory.',
            'unpublished_incomplete' => 'Finish your profile to publish: '.implode(', ', self::missing($b)).'.',
            'ready_to_publish' => 'Your profile is ready. Press Publish.',
            'not_entitled' => 'Published. Activate Naara Pro to appear in the directory.',
            'cancelled' => 'Published, but your Naara Pro subscription ended. Restart it to appear in the directory.',
            'past_due' => 'Your listing is paused because the last payment did not go through. Fund your wallet to bring it back.',
            'disabled' => 'Your listing is switched off.',
            'inactive' => 'Your listing is switched off by an administrator.',
            default => 'Your listing is being updated.',
        };
    }

    /**
     * Press Publish. Allowed only when the content gate passes; idempotent. Sets the CONTENT state, then lets the billing service flip the listing live if the
     * owner is entitled (so publishing before paying is fine: it simply waits for Naara Pro).
     *
     * @throws \DomainException listing the missing items
     */
    public static function publish(BrandPartner $b): BrandPartner
    {
        if ($b->published_at !== null) {
            return $b;
        }
        $missing = self::missing($b);
        if ($missing !== []) {
            throw new \DomainException('Before you publish, add: '.implode(', ', $missing).'.');
        }
        $b->forceFill(['published_at' => now()])->save();
        Auditor::log('brand.published', 'BrandPartner', $b->id);
        app(BrandSubscriptionService::class)->activateIfComplete($b->fresh(['subscription', 'handles']));

        return $b->fresh();
    }
}
