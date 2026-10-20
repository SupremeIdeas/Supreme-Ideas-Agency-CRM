<?php

namespace Tests\Feature;

use App\Jobs\ProcessWhiteLabelApprovalJob;
use App\Livewire\Admin\KycReview;
use App\Livewire\Admin\WhiteLabelRegistry;
use App\Models\KycVerification;
use App\Models\User;
use App\Models\WhiteLabelInstance;
use App\Services\AI\AnthropicClient;
use App\Services\Kyc\KycCrossCheckService;
use App\Services\Updater\WhiteLabelLicenseService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\Support\FakeAnthropicClient;
use Tests\TestCase;

/** Oversight/Withdrawal blueprint Phase E2 (advisory KYC triage) + E3 (queued WL approval). */
class OversightPhaseETest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    private function pending(int $level = KycVerification::L2): KycVerification
    {
        return KycVerification::create([
            'user_id' => User::factory()->create(['name' => 'Ada Obi'])->id,
            'level' => $level, 'provider' => 'manual', 'status' => KycVerification::PENDING,
            'reference' => 'kyc:'.uniqid(), 'checks' => ['id_type' => 'NIN', 'country' => 'NG'],
        ]);
    }

    public function test_cross_check_attaches_an_assessment_but_never_decides(): void
    {
        $fake = new FakeAnthropicClient(['consistent' => true, 'confidence' => 'high', 'reasoning' => 'Details agree.']);
        $this->app->instance(AnthropicClient::class, $fake);
        $v = $this->pending();

        $this->assertSame(1, app(KycCrossCheckService::class)->assessEscalated());

        $v->refresh();
        $this->assertSame(KycVerification::PENDING, $v->status, 'AI triage must never change the decision');
        $this->assertTrue($v->ai_assessment['consistent']);
        $this->assertSame('high', $v->ai_assessment['confidence']);
        $this->assertNotNull($v->ai_assessed_at);

        // Already assessed -> not re-sent on the next run.
        $this->assertSame(0, app(KycCrossCheckService::class)->assessEscalated());
    }

    public function test_cross_check_is_a_noop_without_an_api_key(): void
    {
        $this->app->instance(AnthropicClient::class, new FakeAnthropicClient([], on: false));
        $v = $this->pending();

        $this->assertSame(0, app(KycCrossCheckService::class)->assessEscalated());
        $this->assertNull($v->fresh()->ai_assessment);
    }

    public function test_the_review_screen_shows_the_assessment_and_a_human_still_decides(): void
    {
        $v = $this->pending();
        $v->forceFill(['ai_assessment' => ['consistent' => false, 'confidence' => 'medium', 'reasoning' => 'Business name differs.'], 'ai_assessed_at' => now()])->save();
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        Livewire::actingAs($admin)->test(KycReview::class)
            ->assertSee('Flagged')->assertSee('Business name differs.');
        $this->assertSame(KycVerification::PENDING, $v->fresh()->status);
    }

    public function test_approving_a_white_label_request_queues_the_charge_instead_of_running_it_inline(): void
    {
        Queue::fake();
        $super = User::factory()->create();
        $super->assignRole('super_admin');
        $owner = User::factory()->create();
        $instance = app(WhiteLabelLicenseService::class)->register([
            'brand_name' => 'Q Co', 'contact_email' => 'q@co.test', 'owner_user_id' => $owner->id,
            'acquisition_method' => WhiteLabelInstance::ACQUISITION_MERCHANT_SELF_SERVICE,
            'requested_tier' => WhiteLabelInstance::TIER_NORMAL,
        ]);
        $instance->forceFill(['price_usd' => 1500])->save();

        Livewire::actingAs($super)->test(WhiteLabelRegistry::class)->call('approveAndActivate', $instance->id);

        Queue::assertPushed(ProcessWhiteLabelApprovalJob::class, fn ($j) => $j->instanceId === $instance->id);
        $instance->refresh();
        $this->assertNotNull($instance->approval_queued_at);
        $this->assertSame(WhiteLabelInstance::PENDING, $instance->status, 'nothing charged until the job runs');
    }
}
