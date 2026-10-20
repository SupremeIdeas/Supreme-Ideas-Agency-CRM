<?php

namespace Tests\Feature;

use App\Livewire\Admin\HotMenu;
use App\Models\AdminSectionView;
use App\Models\KycVerification;
use App\Models\Merchant;
use App\Models\PortInRequest;
use App\Models\User;
use App\Models\WhiteLabelInstance;
use App\Support\AdminHotMenu;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Content/billing/hot-menu blueprint Phase G — the admin "Hot Menu": a
 * header-triggered modal listing every admin section the signed-in admin/
 * staff member may reach, gated identically to the sidebar nav, each with a
 * live "new since last view" badge count backed by `admin_section_views`.
 */
class AdminHotMenuTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    private function superAdmin(): User
    {
        $u = User::factory()->create();
        $u->assignRole('super_admin');

        return $u;
    }

    private function admin(): User
    {
        $u = User::factory()->create();
        $u->assignRole('admin');

        return $u;
    }

    public function test_a_plain_user_sees_no_sections(): void
    {
        $sections = AdminHotMenu::for(User::factory()->create());

        $this->assertSame([], $sections);
    }

    public function test_gating_mirrors_the_sidebar_nav_exactly(): void
    {
        $admin = $this->admin();
        $superAdmin = $this->superAdmin();

        $adminKeys = collect(AdminHotMenu::for($admin))->pluck('key')->all();
        $superKeys = collect(AdminHotMenu::for($superAdmin))->pluck('key')->all();

        // Staff and Maintenance are super_admin only — a plain admin never sees them.
        $this->assertNotContains('staff', $adminKeys);
        $this->assertNotContains('maintenance', $adminKeys);
        $this->assertContains('staff', $superKeys);
        $this->assertContains('maintenance', $superKeys);

        // Everything else is shared between admin and super_admin.
        $this->assertContains('merchants', $adminKeys);
        $this->assertContains('kyc', $adminKeys);
        $this->assertContains('white_label', $adminKeys);
        $this->assertContains('deletions', $adminKeys);
    }

    public function test_a_staff_member_with_only_tickets_manage_sees_only_tickets(): void
    {
        $staff = User::factory()->create();
        $staff->givePermissionTo('tickets.manage');

        $keys = collect(AdminHotMenu::for($staff))->pluck('key')->all();

        $this->assertSame(['tickets'], $keys);
    }

    public function test_a_pending_merchant_counts_toward_the_badge_until_viewed(): void
    {
        $admin = $this->admin();
        $owner = User::factory()->create();
        Merchant::create([
            'owner_user_id' => $owner->id, 'business_name' => 'Biz', 'slug' => 'biz-1',
            'status' => Merchant::PENDING, 'tier' => Merchant::TIER_STANDARD,
        ]);

        $sections = collect(AdminHotMenu::for($admin))->keyBy('key');
        $this->assertSame(1, $sections['merchants']['count']);

        AdminHotMenu::markViewed($admin, 'merchants');

        $sections = collect(AdminHotMenu::for($admin))->keyBy('key');
        $this->assertSame(0, $sections['merchants']['count']);
    }

    public function test_a_new_pending_kyc_counts_but_never_fabricates_an_escalation(): void
    {
        $admin = $this->admin();
        $subject = User::factory()->create();
        KycVerification::create([
            'user_id' => $subject->id, 'level' => KycVerification::L2, 'provider' => 'dojah',
            'status' => KycVerification::PENDING, 'reference' => 'kyc-ref-1',
        ]);

        $sections = collect(AdminHotMenu::for($admin))->keyBy('key');
        $this->assertSame(1, $sections['kyc']['count']);
        $this->assertSame(0, $sections['kyc']['escalation'], 'no escalation concept exists yet (Tier 5 #11) — must never be fabricated');
    }

    public function test_white_label_and_deletions_and_port_in_queues_are_real(): void
    {
        $admin = $this->admin();
        $owner = User::factory()->create();

        app(\App\Services\Updater\WhiteLabelLicenseService::class)->register([
            'brand_name' => 'Biz', 'contact_email' => 'biz@test.co', 'owner_user_id' => $owner->id,
            'acquisition_method' => WhiteLabelInstance::ACQUISITION_MERCHANT_SELF_SERVICE,
            'requested_tier' => WhiteLabelInstance::TIER_NORMAL,
        ]);

        $deletionRequester = User::factory()->create(['deletion_requested_at' => now()]);

        PortInRequest::create([
            'user_id' => $owner->id, 'phone_number' => '+15551234567', 'status' => PortInRequest::STATUS_SUBMITTED,
            'billing_name' => 'Biz Owner', 'billing_address' => '1 Main St',
        ]);

        $sections = collect(AdminHotMenu::for($admin))->keyBy('key');
        $this->assertSame(1, $sections['white_label']['count']);
        $this->assertSame(1, $sections['deletions']['count']);
        $this->assertSame(1, $sections['port_in']['count']);
    }

    public function test_informational_sections_carry_no_fabricated_badge(): void
    {
        $superAdmin = $this->superAdmin();

        $sections = collect(AdminHotMenu::for($superAdmin))->keyBy('key');

        foreach (['staff', 'users', 'blog', 'maintenance', 'coupons', 'announcements', 'login_notice', 'email_broadcast'] as $key) {
            $this->assertNull($sections[$key]['count'], "{$key} must have no fabricated queue count");
        }
    }

    public function test_the_livewire_component_renders_the_total_badge_and_marks_viewed_on_open(): void
    {
        $admin = $this->admin();
        $owner = User::factory()->create();
        Merchant::create([
            'owner_user_id' => $owner->id, 'business_name' => 'Biz', 'slug' => 'biz-2',
            'status' => Merchant::PENDING, 'tier' => Merchant::TIER_STANDARD,
        ]);

        Livewire::actingAs($admin)
            ->test(HotMenu::class)
            ->assertViewHas('total', 1)
            ->call('open', 'merchants', 'admin.merchants');

        $this->assertNotNull(AdminSectionView::where('user_id', $admin->id)->where('section_key', 'merchants')->first());

        Livewire::actingAs($admin)
            ->test(HotMenu::class)
            ->assertViewHas('total', 0);
    }

    /**
     * Frontend-UX-fix blueprint Phase A — the admin layout mounts a mobile
     * AND a desktop instance of this same component (identical pattern to
     * NotificationCenter, and the same collision it turned out to share
     * before being fixed): open-modal/close-modal are global window events
     * matched by name, so a shared literal modal name would open both
     * instances' dialogs together. The name must be derived from this
     * Livewire instance's own id.
     */
    public function test_two_hot_menu_instances_never_share_a_modal_name(): void
    {
        $admin = $this->admin();

        $mobile = Livewire::actingAs($admin)->test(HotMenu::class);
        $desktop = Livewire::actingAs($admin)->test(HotMenu::class);

        preg_match("/name: '([^']+)'/", $mobile->html(), $mobileMatch);
        preg_match("/name: '([^']+)'/", $desktop->html(), $desktopMatch);

        $this->assertNotEmpty($mobileMatch[1] ?? null);
        $this->assertNotEmpty($desktopMatch[1] ?? null);
        $this->assertNotSame($mobileMatch[1], $desktopMatch[1]);
    }
}
