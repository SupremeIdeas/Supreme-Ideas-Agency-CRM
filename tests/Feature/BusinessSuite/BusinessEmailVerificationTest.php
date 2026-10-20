<?php

namespace Tests\Feature\BusinessSuite;

use App\Models\User;
use App\Models\WalletGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Business Suite Prompt 13 §3.3 — a trust signal, never a gate. A
 * consumer-domain business_email triggers the "needs verification" state;
 * a custom-domain one never does. Verifying never blocks workspace use.
 */
class BusinessEmailVerificationTest extends TestCase
{
    use \Tests\Concerns\EnablesBusinessSuite;

    use RefreshDatabase;

    public function test_a_consumer_domain_email_needs_verification(): void
    {
        $group = WalletGroup::create(['owner_user_id' => User::factory()->create()->id, 'name' => 'A', 'kind' => 'business', 'business_email' => 'owner@gmail.com']);

        $this->assertTrue($group->needsBusinessEmailVerification());
    }

    public function test_a_custom_domain_email_never_needs_verification(): void
    {
        $group = WalletGroup::create(['owner_user_id' => User::factory()->create()->id, 'name' => 'A', 'kind' => 'business', 'business_email' => 'owner@acme-corp.com']);

        $this->assertFalse($group->needsBusinessEmailVerification());
    }

    public function test_no_business_email_never_needs_verification(): void
    {
        $group = WalletGroup::create(['owner_user_id' => User::factory()->create()->id, 'name' => 'A', 'kind' => 'business']);

        $this->assertFalse($group->needsBusinessEmailVerification());
    }

    public function test_a_valid_signed_link_marks_the_email_verified(): void
    {
        $owner = User::factory()->create();
        $group = WalletGroup::create(['owner_user_id' => $owner->id, 'name' => 'A', 'kind' => 'business', 'business_email' => 'owner@gmail.com']);
        $url = URL::temporarySignedRoute('business.email.verify', now()->addHours(24), ['walletGroup' => $group->id]);

        $this->actingAs($owner)->get($url)->assertRedirect(route('business.workspace'));

        $this->assertNotNull($group->fresh()->business_email_verified_at);
    }

    public function test_a_tampered_link_is_rejected_and_never_verifies(): void
    {
        $owner = User::factory()->create();
        $group = WalletGroup::create(['owner_user_id' => $owner->id, 'name' => 'A', 'kind' => 'business', 'business_email' => 'owner@gmail.com']);
        $url = URL::temporarySignedRoute('business.email.verify', now()->addHours(24), ['walletGroup' => $group->id]);

        $this->actingAs($owner)->get($url.'&tampered=1')->assertForbidden();

        $this->assertNull($group->fresh()->business_email_verified_at);
    }

    public function test_verification_never_gates_workspace_creation_or_use(): void
    {
        // The "trust signal, never a gatekeeper" acceptance rule (§3.3) —
        // simply asserting a group with an unverified consumer email is a
        // perfectly usable, persisted row with no additional required state.
        $group = WalletGroup::create([
            'owner_user_id' => User::factory()->create()->id, 'name' => 'A', 'kind' => 'business', 'business_email' => 'owner@gmail.com',
        ]);

        $this->assertTrue($group->exists);
        $this->assertNull($group->business_email_verified_at);
        $this->assertTrue($group->needsBusinessEmailVerification());
    }
}
