<?php

namespace Tests\Feature;

use App\Livewire\Admin\WhiteLabelRegistry;
use App\Models\Setting;
use App\Models\User;
use App\Models\WhiteLabelInstance;
use App\Services\Updater\WhiteLabelLicenseService;
use App\Support\WhiteLabel\SkinAllowance;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Prompt 22 Batch 3 — the master is the ONLY authority for how many skins a white-label licence unlocks. It reaches a fork as
 * `skins.allowance` in the same entitlement block that carries `level` and `locks`.
 */
class SkinAllowanceTest extends TestCase
{
    use RefreshDatabase;

    private function licensed(string $tier): WhiteLabelInstance
    {
        Setting::setValue('white_label_api.enabled', true);
        $svc = app(WhiteLabelLicenseService::class);

        return $svc->issueLicense($svc->register(['brand_name' => 'Acme '.uniqid(), 'contact_email' => 'a@acme.test']), $tier);
    }

    private function admin(): User
    {
        $this->seed(RoleSeeder::class);
        $u = User::factory()->create();
        $u->assignRole('admin');

        return $u;
    }

    public function test_tier_defaults_are_two_for_normal_and_five_for_extended(): void
    {
        $this->assertSame(2, SkinAllowance::forTier(WhiteLabelInstance::TIER_NORMAL));
        $this->assertSame(5, SkinAllowance::forTier(WhiteLabelInstance::TIER_EXTENDED));
    }

    public function test_an_unknown_or_missing_tier_gets_the_minimum(): void
    {
        $this->assertSame(1, SkinAllowance::forTier(null));
        $this->assertSame(1, SkinAllowance::forTier('enterprise'));
    }

    public function test_extended_v2_shares_the_extended_tier_and_therefore_the_allowance(): void
    {
        $v2 = $this->licensed(WhiteLabelInstance::TIER_EXTENDED);
        $this->assertSame(5, SkinAllowance::forInstance($v2));
    }

    public function test_a_per_instance_override_wins_and_is_clamped_to_the_catalogue(): void
    {
        $i = $this->licensed(WhiteLabelInstance::TIER_NORMAL);
        $i->forceFill(['skin_allowance_override' => 4])->save();
        $this->assertSame(4, SkinAllowance::forInstance($i->fresh()));

        $i->forceFill(['skin_allowance_override' => 200])->save();
        $this->assertSame(SkinAllowance::catalogueSize(), SkinAllowance::forInstance($i->fresh()));

        $i->forceFill(['skin_allowance_override' => null])->save();
        $this->assertSame(2, SkinAllowance::forInstance($i->fresh()));
    }

    public function test_an_admin_saved_tier_default_beats_the_config_default(): void
    {
        SkinAllowance::saveTierDefaults(['normal' => 3]);
        $this->assertSame(3, SkinAllowance::forTier('normal'));
        $this->assertSame(5, SkinAllowance::forTier('extended'));
    }

    public function test_activation_and_the_entitlement_endpoint_both_carry_the_allowance_and_keep_level_and_locks(): void
    {
        $normal = $this->licensed(WhiteLabelInstance::TIER_NORMAL);
        $ext = $this->licensed(WhiteLabelInstance::TIER_EXTENDED);

        $this->postJson('/api/v1/white-label/activate', ['license_key' => $normal->license_key])
            ->assertOk()
            ->assertJsonPath('entitlement.skins.allowance', 2)
            ->assertJsonPath('entitlement.level', 'basic')
            ->assertJsonStructure(['entitlement' => ['level', 'locks', 'skins' => ['allowance']]]);

        $this->postJson('/api/v1/white-label/activate', ['license_key' => $ext->license_key])
            ->assertOk()
            ->assertJsonPath('entitlement.skins.allowance', 5)
            ->assertJsonPath('entitlement.locks', []);

        $token = $normal->fresh()->createToken('t', WhiteLabelInstance::SCOPES)->plainTextToken;
        $this->withToken($token)->getJson('/api/v1/white-label/entitlement')
            ->assertOk()
            ->assertJsonPath('skins.allowance', 2)
            ->assertJsonPath('level', 'basic');
    }

    public function test_the_registry_edits_tier_defaults_and_instance_overrides_with_an_audit_trail(): void
    {
        $admin = $this->admin();
        $i = $this->licensed(WhiteLabelInstance::TIER_NORMAL);

        Livewire::actingAs($admin)->test(WhiteLabelRegistry::class)
            ->call('saveSkinTierDefault', 'normal', '3')
            ->call('setSkinOverride', $i->id, '4');

        $this->assertSame(3, SkinAllowance::forTier('normal'));
        $this->assertSame(4, SkinAllowance::forInstance($i->fresh()));
        $this->assertDatabaseHas('audit_logs', ['action' => 'white_label.skin_allowance_set']);

        Livewire::actingAs($admin)->test(WhiteLabelRegistry::class)->call('setSkinOverride', $i->id, '');
        $this->assertSame(3, SkinAllowance::forInstance($i->fresh()));
    }

    public function test_a_non_admin_cannot_change_the_allowance(): void
    {
        $this->seed(RoleSeeder::class);
        $i = $this->licensed(WhiteLabelInstance::TIER_NORMAL);

        Livewire::actingAs(User::factory()->create())->test(WhiteLabelRegistry::class)->assertStatus(403);
        $this->assertSame(2, SkinAllowance::forInstance($i->fresh()));
    }
}
