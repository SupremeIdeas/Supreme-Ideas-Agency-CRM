<?php

namespace App\Services\BrandLinks;

use App\Models\BrandLink;
use App\Models\BrandPartner;
use App\Support\Auditor;
use App\Support\Notifications\Notify;

/**
 * The write side of Business Links (Brand-Growth Phase 2, MASTER-ONLY): add through the policy, ask for a review, admin decisions, and the weekly re-check
 * that suspends a link that has started to fail, redirect elsewhere or be flagged. Every state change tells the brand owner once.
 */
class BrandLinkService
{
    public function __construct(private readonly BrandLinkPolicy $policy) {}

    public static function limit(): int
    {
        return BrandLinkSettings::int('links_per_brand', 5);
    }

    /** @throws \DomainException when the brand is at its limit */
    public function add(BrandPartner $brand, string $label, string $url): array
    {
        if ($brand->links()->whereIn('status', [BrandLink::PENDING, BrandLink::APPROVED, BrandLink::REJECTED])->count() >= self::limit()) {
            throw new \DomainException('You can add up to '.self::limit().' links. Remove one first.');
        }
        $v = $this->policy->evaluate((string) $brand->brand_name, $label, $url);
        $status = match ($v->status) { 'approved' => BrandLink::APPROVED, 'pending' => BrandLink::PENDING, default => BrandLink::REJECTED };
        // A rejected link is kept (so the owner sees why and can ask for a review) only when a human could still approve it; a plainly unsafe one is not stored.
        if ($status === BrandLink::REJECTED && ! $v->canRequestReview && ! $v->canRetry) {
            return ['link' => null, 'verdict' => $v];
        }
        $link = $brand->links()->create([
            'label' => mb_substr(trim($label), 0, 40), 'url' => mb_substr(trim($url), 0, 500), 'normalized_url' => $v->url ?: mb_substr(trim($url), 0, 500), 'host' => $v->host, 'registrable_domain' => $v->registrable,
            'tld' => $v->tld, 'subdomain' => $v->subdomain ?: null, 'resemblance_score' => $v->score, 'status' => $status, 'reject_reason' => $status === BrandLink::REJECTED ? mb_substr((string) $v->reason, 0, 300) : null,
            'last_checked_at' => now(), 'last_http_status' => $v->httpStatus, 'final_host' => $v->finalHost, 'safe_browsing_status' => $v->safeBrowsing, 'sort_order' => (int) $brand->links()->max('sort_order') + 1,
        ]);
        if ($status === BrandLink::APPROVED) {
            $this->tell($link, 'brand.link_approved');
        }

        return ['link' => $link, 'verdict' => $v];
    }

    public function requestReview(BrandLink $link): void
    {
        if ($link->status === BrandLink::REJECTED && ! $link->review_requested) {
            $link->update(['status' => BrandLink::PENDING, 'review_requested' => true]);
        }
    }

    public function approve(BrandLink $link, ?int $adminId = null): void
    {
        $link->update(['status' => BrandLink::APPROVED, 'reject_reason' => null, 'review_requested' => false]);
        Auditor::log('brand_link.approved', BrandLink::class, $link->id, ['by' => $adminId]);
        $this->tell($link, 'brand.link_approved');
    }

    public function reject(BrandLink $link, string $reason, ?int $adminId = null): void
    {
        $link->update(['status' => BrandLink::REJECTED, 'reject_reason' => mb_substr($reason, 0, 300), 'review_requested' => false]);
        Auditor::log('brand_link.rejected', BrandLink::class, $link->id, ['by' => $adminId, 'reason' => $reason]);
        $this->tell($link, 'brand.link_rejected');
    }

    public function suspend(BrandLink $link, string $reason, ?int $adminId = null): void
    {
        if ($link->status === BrandLink::SUSPENDED) {
            return;
        }
        $link->update(['status' => BrandLink::SUSPENDED, 'reject_reason' => mb_substr($reason, 0, 300)]);
        Auditor::log('brand_link.suspended', BrandLink::class, $link->id, ['by' => $adminId, 'reason' => $reason]);
        $this->tell($link, 'brand.link_suspended');
    }

    /** A visitor reports a link; three reports park it for a person to look at (it stays visible to nobody until then). */
    public function report(BrandLink $link): void
    {
        $link->increment('report_count');
        if ($link->report_count >= 3 && $link->status === BrandLink::APPROVED) {
            $this->suspend($link, 'Reported by several visitors; waiting for a review.');
        }
    }

    /** Re-run the policy against a live link. Approved links that now fail are suspended; a single unreachable week alone is not enough (the next check decides). */
    public function recheck(BrandLink $link): LinkVerdict
    {
        $brand = $link->brand;
        $wasDown = $link->last_checked_at !== null && $link->last_http_status === null;   // the previous check could not reach it either
        $v = $this->policy->evaluate((string) $brand?->brand_name, $link->label, $link->url);
        $link->forceFill(['last_checked_at' => now(), 'last_http_status' => $v->httpStatus, 'final_host' => $v->finalHost, 'safe_browsing_status' => $v->safeBrowsing, 'resemblance_score' => $v->score])->save();
        if ($link->status === BrandLink::APPROVED && $v->status === 'rejected') {
            $failed = array_column(array_filter($v->checks, fn ($c) => ! $c['ok']), 'key');
            $hard = array_diff($failed, ['reachable']);       // redirect elsewhere, unsafe, policy change: act now
            if ($hard !== [] || $wasDown) {
                $this->suspend($link, (string) $v->reason);
            }
        }

        return $v;
    }

    private function tell(BrandLink $link, string $event): void
    {
        $owner = $link->brand?->owner;
        if ($owner) {
            Notify::event($owner, $event, ['label' => $link->label, 'host' => $link->host, 'reason' => (string) $link->reject_reason], dedupe: $event.'-'.$link->id.'-'.$link->updated_at?->timestamp);
        }
    }
}
