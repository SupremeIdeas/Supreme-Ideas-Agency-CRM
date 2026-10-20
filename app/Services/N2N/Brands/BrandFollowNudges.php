<?php

namespace App\Services\N2N\Brands;

use App\Models\BrandPartner;
use App\Models\BrandPartnerHandle;
use App\Models\SocialFollowClaim;
use App\Models\User;
use App\Support\Notifications\Notify;

/**
 * MASTER-ONLY. Two notifications that connect a brand's chat/profile connection to its social pages:
 *  - to the PERSON who just connected with a brand: "follow their pages too and earn" (only when there is something left to earn);
 *  - to the BRAND OWNER when that person then follows one of the brand's pages: proof they truly connect, not just a tap.
 * Both go through Notify (preferences, quiet hours, once-only delivery) and never throw into the follow or reward path.
 */
class BrandFollowNudges
{
    /** @return array{count:int, credits:float} pages this person has not followed yet, and what they would earn */
    public function remaining(BrandPartner $brand, User $user): array
    {
        $official = BrandPartnerHandle::officialUrlSet();
        $claimed = SocialFollowClaim::query()->where('user_id', $user->id)->whereNotNull('brand_partner_handle_id')->pluck('brand_partner_handle_id')->flip();
        $left = $brand->handles()->active()->get()->reject(fn ($h) => $h->isOfficial($official) || isset($claimed[$h->id]));

        return ['count' => $left->count(), 'credits' => round((float) $left->sum(fn ($h) => $h->effectiveCreditReward()), 2)];
    }

    public function afterConnect(User $user, BrandPartner $brand): void
    {
        try {
            if ((int) $brand->owner_user_id === (int) $user->id) {
                return;
            }
            $r = $this->remaining($brand, $user);
            if ($r['count'] < 1) {
                return;
            }
            $amount = $r['credits'] > 0 ? rtrim(rtrim(number_format($r['credits'], 2), '0'), '.') : 'bonus';
            Notify::event($user, 'brand.follow_socials_nudge', ['brand' => (string) $brand->brand_name, 'amount' => $amount], 'nudge-'.$brand->id.'-'.$user->id);
        } catch (\Throwable) {
        }
    }

    public function afterHandleFollowed(User $user, BrandPartnerHandle $handle): void
    {
        try {
            $brand = $handle->brandPartner;
            $owner = $brand?->owner;
            if (! $brand || ! $owner instanceof User || (int) $owner->id === (int) $user->id) {
                return;
            }
            $name = (bool) $user->n2n_private_visits ? 'A person' : (string) $user->name;
            Notify::event($owner, 'brand.socials_followed', ['name' => $name, 'what' => (string) $handle->handle_label, 'brand' => (string) $brand->brand_name], 'socials-'.$handle->id.'-'.$user->id);
        } catch (\Throwable) {
        }
    }
}
