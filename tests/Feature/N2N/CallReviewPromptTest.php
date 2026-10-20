<?php

namespace Tests\Feature\N2N;

use App\Models\N2N\Call;
use App\Models\N2N\CallRating;
use App\Models\Setting;
use App\Models\User;
use App\Services\N2N\Calls\CallReviewPrompt;
use App\Services\N2N\Connections\Connections;
use App\Services\N2N\Credit\UsageLedgerService;
use App\Services\N2N\Identity\NaaraId;
use App\Support\Trustpilot\TrustpilotSettings;

/** The after-call rating sheet and its Trustpilot invitation (MASTER-ONLY): off by default, never gated by the rating, one ask per call, cool-down per person. */
class CallReviewPromptTest extends N2NTestCase
{
    private function endedCall(int $seconds = 90): array
    {
        $mk = fn () => $this->user(['naara_id' => NaaraId::generate(), 'username' => 'u'.substr(md5(uniqid()), 0, 8)]);
        [$a, $b] = [$mk(), $mk()];
        $call = Call::query()->create(['caller_id' => $a->id, 'callee_id' => $b->id, 'state' => 'ended', 'end_reason' => 'hangup', 'hold_count' => 0, 'billed_seconds' => $seconds, 'billed_cents' => 0, 'rate_cents' => 1, 'increment_s' => 60, 'hold_cents_each' => 1,
            'answered_at' => now()->subSeconds($seconds), 'ended_at' => now(), 'ring_expires_at' => now()->subMinute()]);

        return [$a, $b, $call];
    }

    private function on(): void
    {
        Setting::setValue('n2n.call_review_enabled', true);
        TrustpilotSettings::put('profile_url', 'https://www.trustpilot.com/review/naara.app');
    }

    public function test_it_is_off_until_an_admin_switches_it_on(): void
    {
        [$a, , $call] = $this->endedCall();
        $this->assertFalse(CallReviewPrompt::enabled());
        $this->assertFalse(app(CallReviewPrompt::class)->eligible($a, $call));
        $this->actingAs($a)->getJson("/n2n/api/calls/{$call->id}/review")->assertOk()->assertJson(['show' => false]);

        $this->on();
        $this->assertTrue(app(CallReviewPrompt::class)->eligible($a, $call));
        Setting::setValue('n2n.call_review_enabled', false);
        $this->assertFalse(app(CallReviewPrompt::class)->eligible($a, $call));
    }

    public function test_an_address_that_is_not_trustpilot_is_never_used(): void
    {
        TrustpilotSettings::put('profile_url', 'https://evil.example/review/x');
        $this->assertNull(CallReviewPrompt::reviewUrl());   // so the sheet never shows rather than send people somewhere else
        Setting::setValue('n2n.call_review_enabled', true);
        $this->assertFalse(CallReviewPrompt::enabled());
    }

    public function test_the_review_link_points_at_trustpilots_own_form(): void
    {
        $this->on();
        $this->assertSame('https://www.trustpilot.com/evaluate/naara.app', CallReviewPrompt::reviewUrl());
    }

    public function test_a_real_call_is_eligible_once_and_a_short_one_is_not(): void
    {
        $this->on();
        [$a, $b, $call] = $this->endedCall(90);
        $this->actingAs($a)->getJson("/n2n/api/calls/{$call->id}/review")->assertOk()->assertJson(['show' => true]);
        [$c, , $short] = $this->endedCall(5);
        $this->actingAs($c)->getJson("/n2n/api/calls/{$short->id}/review")->assertOk()->assertJson(['show' => false]);
    }

    public function test_every_rating_gets_the_same_trustpilot_invitation_and_low_ones_add_a_support_link(): void
    {
        $this->on();
        Setting::setValue('n2n.call_review_cooldown_days', 0);
        foreach ([1, 3, 5] as $stars) {
            [$a, , $call] = $this->endedCall();
            $r = $this->actingAs($a)->postJson("/n2n/api/calls/{$call->id}/review", ['stars' => $stars])->assertOk();
            $r->assertJsonPath('review_url', 'https://www.trustpilot.com/evaluate/naara.app');
            $stars <= 3 ? $r->assertJsonPath('support_url', route('support')) : $r->assertJsonPath('support_url', null);
            $this->assertSame($stars, CallRating::query()->where('call_id', $call->id)->value('stars'));
        }
    }

    public function test_a_call_is_asked_about_once_and_the_person_is_not_asked_again_inside_the_cool_down(): void
    {
        $this->on();
        [$a, $b, $call] = $this->endedCall();
        $this->actingAs($a)->postJson("/n2n/api/calls/{$call->id}/review/dismiss")->assertOk();
        $this->actingAs($a)->getJson("/n2n/api/calls/{$call->id}/review")->assertJson(['show' => false]);

        $next = Call::query()->create(['caller_id' => $a->id, 'callee_id' => $b->id, 'state' => 'ended', 'end_reason' => 'hangup', 'hold_count' => 0, 'billed_seconds' => 60, 'billed_cents' => 0, 'rate_cents' => 1, 'increment_s' => 60, 'hold_cents_each' => 1, 'answered_at' => now()->subMinute(), 'ended_at' => now(), 'ring_expires_at' => now()->subMinutes(2)]);
        $this->actingAs($a)->getJson("/n2n/api/calls/{$next->id}/review")->assertJson(['show' => false]);
        $this->actingAs($b)->getJson("/n2n/api/calls/{$next->id}/review")->assertJson(['show' => true]);   // the other person is a different person
    }

    public function test_opening_trustpilot_is_recorded_and_strangers_are_refused(): void
    {
        $this->on();
        [$a, , $call] = $this->endedCall();
        $this->actingAs($a)->postJson("/n2n/api/calls/{$call->id}/review", ['stars' => 4])->assertOk();
        $this->actingAs($a)->postJson("/n2n/api/calls/{$call->id}/review/opened")->assertOk();
        $this->assertTrue((bool) CallRating::query()->where('call_id', $call->id)->value('trustpilot_opened'));

        $stranger = $this->user(['naara_id' => NaaraId::generate()]);
        $this->actingAs($stranger)->getJson("/n2n/api/calls/{$call->id}/review")->assertNotFound();
        $this->actingAs($stranger)->postJson("/n2n/api/calls/{$call->id}/review", ['stars' => 5])->assertNotFound();
    }

    public function test_bad_star_values_are_refused(): void
    {
        $this->on();
        [$a, , $call] = $this->endedCall();
        foreach ([0, 6, 'x'] as $bad) {
            $this->actingAs($a)->postJson("/n2n/api/calls/{$call->id}/review", ['stars' => $bad])->assertStatus(422);
        }
    }
}
