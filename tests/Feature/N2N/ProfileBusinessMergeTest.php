<?php

namespace Tests\Feature\N2N;

use App\Models\BrandPartner;
use App\Services\N2N\Identity\NaaraId;
use App\Services\N2N\Profiles\ProfileVisibility;

/** Upgrade merge: an upgraded (Naara Pro) member's chat profile carries the business card, derived from the single brand record (MASTER-ONLY). */
class ProfileBusinessMergeTest extends N2NTestCase
{
    private function member(): \App\Models\User
    {
        return $this->user(['naara_id' => NaaraId::generate(), 'username' => 'u'.substr(md5(uniqid()), 0, 8), 'n2n_profile_visibility' => 'public']);
    }

    private function brand(int $owner, array $a = []): BrandPartner
    {
        return BrandPartner::create(array_merge(['owner_user_id' => $owner, 'brand_name' => 'Acme Telecom', 'category' => 'Telecom', 'short_description' => 'We connect people.', 'listing_status' => 'active', 'is_active' => true], $a));
    }

    public function test_a_member_without_an_upgrade_shows_no_business_card(): void
    {
        $owner = $this->member(); $viewer = $this->member();
        $this->brand($owner->id);   // listed but not Pro
        $this->assertArrayNotHasKey('business', ProfileVisibility::payload($viewer, $owner));
    }

    public function test_upgrading_merges_the_business_card_and_it_follows_the_brand_record(): void
    {
        $owner = $this->member(); $viewer = $this->member();
        $b = $this->brand($owner->id, ['pro_granted_at' => now()]);
        $this->assertSame('Acme Telecom', ProfileVisibility::payload($viewer, $owner)['business']['name']);
        $b->update(['brand_name' => 'Acme Mobile']);
        $this->assertSame('Acme Mobile', ProfileVisibility::payload($viewer, $owner)['business']['name']);
        $this->assertSame(['name', 'category', 'about', 'image'], array_keys(ProfileVisibility::payload($viewer, $owner)['business']));
    }

    public function test_a_lapsed_upgrade_or_unlisted_brand_removes_the_card(): void
    {
        $owner = $this->member(); $viewer = $this->member();
        $b = $this->brand($owner->id, ['pro_granted_at' => now()]);
        $b->update(['pro_granted_at' => null]);
        $this->assertArrayNotHasKey('business', ProfileVisibility::payload($viewer, $owner));
        $b->update(['pro_granted_at' => now(), 'is_active' => false]);
        $this->assertArrayNotHasKey('business', ProfileVisibility::payload($viewer, $owner));
    }

    public function test_a_blocked_viewer_gets_no_profile_and_so_no_business_card(): void
    {
        $owner = $this->member(); $viewer = $this->member();
        $this->brand($owner->id, ['pro_granted_at' => now()]);
        \App\Models\N2N\Block::create(['blocker_id' => $owner->id, 'blocked_id' => $viewer->id, 'created_at' => now()]);
        $this->assertNull(ProfileVisibility::payload($viewer, $owner));
    }
}
