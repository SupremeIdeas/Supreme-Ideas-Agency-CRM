<?php

namespace Tests\Feature\BusinessSuite;

use App\Models\User;
use App\Models\WalletGroup;
use App\Support\Business\BusinessNav;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** A "Business Suite" link goes straight to the dashboard for someone who has a workspace, and to the public page only for everyone else. */
class BusinessEntryRouteTest extends TestCase
{
    use \Tests\Concerns\EnablesBusinessSuite;
    use RefreshDatabase;

    public function test_owners_land_on_the_dashboard_and_strangers_on_the_public_page(): void
    {
        $this->seed(RoleSeeder::class);
        $owner = User::factory()->create(['is_active' => true, 'email_verified_at' => now()]);
        WalletGroup::create(['owner_user_id' => $owner->id, 'name' => 'Acme Ltd', 'kind' => WalletGroup::KIND_BUSINESS]);
        $other = User::factory()->create(['is_active' => true, 'email_verified_at' => now()]);

        $this->assertSame('business.workspace', BusinessNav::entryRoute($owner));
        $this->assertSame('business', BusinessNav::entryRoute($other));
        $this->assertSame('business', BusinessNav::entryRoute(null));
    }

    public function test_the_header_and_more_sheet_use_that_entry(): void
    {
        $this->seed(RoleSeeder::class);
        $owner = User::factory()->create(['is_active' => true, 'email_verified_at' => now()]);
        WalletGroup::create(['owner_user_id' => $owner->id, 'name' => 'Acme Ltd', 'kind' => WalletGroup::KIND_BUSINESS]);
        $more = collect(\App\Support\BottomNav::eligibleMainItems($owner)['more']);
        $biz = $more->firstWhere('label', 'Business Suite');
        $this->assertNotNull($biz, 'Business Suite is listed');
        $this->assertSame('business.workspace', $biz['route']);
    }
}
