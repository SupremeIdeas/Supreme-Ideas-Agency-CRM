<?php

namespace Tests\Feature\N2N;

use App\Models\N2N\Call;
use App\Models\N2N\RealtimeEvent;
use App\Models\Setting;
use App\Models\User;
use App\Services\N2N\Calls\CallException;
use App\Services\N2N\Calls\CallService;
use App\Services\N2N\Calls\CallsAvailability;
use App\Services\N2N\Connections\Connections;
use App\Services\N2N\Credit\N2NPricing;
use App\Services\N2N\Credit\UsageLedgerService;
use App\Services\N2N\Identity\NaaraId;

/** N2N 1:1 video calls (MASTER-ONLY): the flag, the admin switch, the video rate and the camera-state signal. The media itself: tests/visual/n2n/video-call-smoke.mjs. */
class VideoCallsTest extends N2NTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Setting::setValue('n2n.calls_enabled', true);
        Setting::setValue('n2n.calls_dev_mode', true);
    }

    private function pair(): array
    {
        $mk = fn () => $this->user(['naara_id' => NaaraId::generate(), 'username' => 'u'.substr(md5(uniqid()), 0, 8)]);
        [$a, $b] = [$mk(), $mk()];
        app(Connections::class)->follow($a, $b);
        app(Connections::class)->follow($b, $a);
        app(UsageLedgerService::class)->apply([[$a->id, 'shareable', 100000, 'seed', 'seed:'.$a->id.uniqid(), null, []]]);

        return [$a, $b];
    }

    public function test_a_video_call_is_flagged_and_billed_at_the_video_rate(): void
    {
        [$a, $b] = $this->pair();
        $voice = $this->svc()->start($a, $b->naara_id);
        $this->assertFalse($voice->video);
        $this->assertSame(N2NPricing::callRatePerMinuteCents(), (int) $voice->rate_cents);
        $this->svc()->hangup($a, $voice->id);

        Setting::setValue('n2n.call_video_multiplier', 3);
        $video = $this->svc()->start($a, $b->naara_id, null, true);
        $this->assertTrue($video->video);
        $this->assertSame(N2NPricing::videoRatePerMinuteCents(), (int) $video->rate_cents);
        $this->assertGreaterThan(N2NPricing::callRatePerMinuteCents(), (int) $video->rate_cents);
    }

    public function test_the_incoming_event_tells_the_callee_it_is_a_video_call(): void
    {
        [$a, $b] = $this->pair();
        $this->svc()->start($a, $b->naara_id, null, true);
        $ev = RealtimeEvent::query()->where('user_id', $b->id)->where('type', 'call.incoming')->latest('id')->first();
        $this->assertNotNull($ev);
        $this->assertTrue((bool) data_get($ev->payload, 'video'));
    }

    public function test_the_admin_switch_turns_video_off_and_a_video_request_falls_back_to_voice(): void
    {
        [$a, $b] = $this->pair();
        Setting::setValue('n2n.calls_video_enabled', false);
        $this->assertFalse(CallsAvailability::videoEnabled());
        $call = $this->svc()->start($a, $b->naara_id, null, true);
        $this->assertFalse($call->video);
        $this->assertSame(N2NPricing::callRatePerMinuteCents(), (int) $call->rate_cents);
    }

    public function test_video_is_never_on_while_calls_are_off(): void
    {
        Setting::setValue('n2n.calls_enabled', false);
        $this->assertFalse(CallsAvailability::videoEnabled());
    }

    public function test_the_camera_state_signal_reaches_only_the_other_person_and_strangers_are_refused(): void
    {
        [$a, $b] = $this->pair();
        $call = $this->svc()->start($a, $b->naara_id, null, true);
        $this->svc()->accept($b, $call->id);
        $this->svc()->signal($b, $call->id, 'media', ['cam' => false]);
        $ev = RealtimeEvent::query()->where('user_id', $a->id)->where('type', 'call.signal')->latest('id')->first();
        $this->assertSame('media', data_get($ev->payload, 'kind'));
        $this->assertFalse((bool) data_get($ev->payload, 'data.cam'));

        $stranger = $this->user(['naara_id' => NaaraId::generate()]);
        $this->expectException(CallException::class);
        $this->svc()->signal($stranger, $call->id, 'media', ['cam' => true]);
    }

    public function test_media_signals_carry_only_camera_share_and_a_short_reaction(): void
    {
        [$a, $b] = $this->pair();
        $call = $this->svc()->start($a, $b->naara_id, null, true);
        $this->svc()->accept($b, $call->id);
        $this->svc()->signal($a, $call->id, 'media', ['cam' => true, 'react' => '❤️']);
        $this->svc()->signal($a, $call->id, 'media', ['cam' => true, 'share' => true]);
        foreach ([['cam' => 'yes'], ['x' => 1], ['react' => str_repeat('a', 20)], 'text'] as $bad) {
            try {
                $this->svc()->signal($a, $call->id, 'media', $bad);
                $this->fail('A bad media signal was accepted: '.json_encode($bad));
            } catch (CallException $e) {
                $this->assertSame('invalid', $e->reason);
            }
        }
    }

    public function test_the_calls_config_reports_video_availability_and_start_accepts_the_flag(): void
    {
        [$a, $b] = $this->pair();
        $this->actingAs($a)->getJson('/n2n/api/calls/config')->assertOk()->assertJsonPath('video', true);
        $this->actingAs($a)->postJson('/n2n/api/calls', ['to' => $b->naara_id, 'video' => true])->assertOk()->assertJsonPath('call.video', true);
    }

    private function svc(): CallService
    {
        return app(CallService::class);
    }
}
