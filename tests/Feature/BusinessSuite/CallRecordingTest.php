<?php

namespace Tests\Feature\BusinessSuite;

use App\Jobs\StoreCallRecordingJob;
use App\Jobs\SummariseCallJob;
use App\Jobs\TranscribeCallRecordingJob;
use App\Models\CallFlow;
use App\Models\CallFlowNode;
use App\Models\CallRecording;
use App\Models\CallRecordingSetting;
use App\Models\RingGroup;
use App\Models\RingGroupMember;
use App\Models\Setting;
use App\Models\User;
use App\Models\VirtualNumber;
use App\Models\WalletGroup;
use App\Models\WalletGroupMember;
use App\Services\AI\AnthropicClient;
use App\Services\Business\RecordingRetention;
use App\Services\Support\Contracts\VoiceSynthesizer;
use App\Services\Wallet\WalletService;
use App\Support\MediaStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\Support\FakeAnthropicClient;
use Tests\Support\FakeVoiceSynthesizer;
use Tests\TestCase;

/** Business Suite Prompt 15: recording policy, the consent TwiML, the signed recording callback, and the billed store/transcribe/summarise chain. */
class CallRecordingTest extends TestCase
{
    use \Tests\Concerns\EnablesBusinessSuite;
    use RefreshDatabase;

    private const NUMBER = '+15005550006';

    private User $owner;

    private WalletGroup $group;

    private VirtualNumber $number;

    private CallFlow $flow;

    private CallFlowNode $queue;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.twilio.account_sid' => 'AC_test', 'services.twilio.auth_token' => 'secret_token']);
        Storage::fake(MediaStorage::privateDisk());

        $this->owner = User::factory()->create();
        app(WalletService::class)->credit($this->owner, 10, 'USD', ['reference' => 'seed']);
        $this->group = WalletGroup::create(['owner_user_id' => $this->owner->id, 'name' => 'Acme', 'kind' => WalletGroup::KIND_BUSINESS]);
        $this->number = VirtualNumber::create([
            'user_id' => $this->owner->id, 'wallet_group_id' => $this->group->id, 'provider' => 'twilio',
            'phone_number' => self::NUMBER, 'sid' => 'PN1', 'status' => 'active', 'recording_enabled' => true,
        ]);
        $this->flow = CallFlow::create(['wallet_group_id' => $this->group->id, 'virtual_number_id' => $this->number->id, 'status' => CallFlow::ACTIVE]);

        $member = WalletGroupMember::create([
            'wallet_group_id' => $this->group->id, 'user_id' => User::factory()->create()->id, 'role' => 'agent',
            'status' => 'active', 'invited_at' => now(), 'accepted_at' => now(),
        ]);
        $rg = RingGroup::create(['wallet_group_id' => $this->group->id, 'name' => 'Sales', 'strategy' => RingGroup::SIMULTANEOUS]);
        RingGroupMember::create(['ring_group_id' => $rg->id, 'wallet_group_member_id' => $member->id, 'priority' => 0, 'destination_number' => '+2348011110001', 'ring_in_browser' => false]);
        $this->queue = CallFlowNode::create(['call_flow_id' => $this->flow->id, 'type' => CallFlowNode::QUEUE, 'config' => ['ring_group_id' => $rg->id, 'max_wait_seconds' => 45]]);
        $this->flow->update(['root_node_id' => $this->queue->id]);
    }

    private function switchOn(bool $recording = true, bool $intelligence = false): void
    {
        Setting::setValue('business.module.recording', true, 'business');
        Setting::setValue('business.module.intelligence', true, 'business');
        CallRecordingSetting::updateOrCreate(['wallet_group_id' => $this->group->id], ['recording_enabled' => $recording, 'intelligence_enabled' => $intelligence, 'retention_days' => 30]);
    }

    private function sign(string $url, array $params): string
    {
        ksort($params);
        $data = $url;
        foreach ($params as $k => $v) {
            $data .= $k.$v;
        }

        return base64_encode(hash_hmac('sha1', $data, 'secret_token', true));
    }

    private function signedPost(string $url, array $params = [])
    {
        return $this->withHeaders(['X-Twilio-Signature' => $this->sign($url, $params)])->post($url, $params);
    }

    private function enter(string $from = '+2348012345678'): string
    {
        return $this->signedPost(route('webhooks.twilio.voice'), ['CallSid' => 'CA1', 'From' => $from, 'To' => self::NUMBER, 'CallStatus' => 'ringing'])->assertOk()->getContent();
    }

    // ── policy ──────────────────────────────────────────────────────────────

    public function test_nothing_is_recorded_until_every_switch_is_on(): void
    {
        $this->assertStringNotContainsString('record=', $this->enter());                // modules off

        Setting::setValue('business.module.recording', true, 'business');
        $this->assertStringNotContainsString('record=', $this->enter());                // workspace has not opted in

        CallRecordingSetting::create(['wallet_group_id' => $this->group->id, 'recording_enabled' => true]);
        $this->number->update(['recording_enabled' => false]);
        $this->assertStringNotContainsString('record=', $this->enter());                // this number has not opted in

        $this->number->update(['recording_enabled' => true]);
        $this->assertStringContainsString('Press 1', $this->enter());                  // all switches on: a strict-market caller is asked first
    }

    public function test_a_strict_market_caller_is_asked_to_press_one_before_anything_connects(): void
    {
        $this->switchOn();
        $body = $this->enter('+2348012345678');   // Nigeria calling a US number: the US end is strict, so this is a consent call

        $this->assertStringContainsString('<Gather', $body);
        $this->assertStringContainsString('Press 1', $body);
        $this->assertStringNotContainsString('<Dial', $body);
    }

    public function test_two_notice_markets_are_announced_and_connected_at_once(): void
    {
        $this->switchOn();
        $this->number->update(['phone_number' => '+2348099999999']);
        $body = $this->signedPost(route('webhooks.twilio.voice'), ['CallSid' => 'CA1', 'From' => '+2348012345678', 'To' => '+2348099999999', 'CallStatus' => 'ringing'])->assertOk()->getContent();

        $this->assertStringContainsString('This call may be recorded', $body);
        $this->assertStringContainsString('record="record-from-answer-dual"', $body);
        $this->assertStringContainsString('rg=notice', html_entity_decode($body));
        $this->assertStringContainsString('<Number url="', $body);   // the person who answers is told too
    }

    public function test_pressing_one_records_and_anything_else_connects_unrecorded(): void
    {
        $this->switchOn();
        $ask = route('webhooks.twilio.call-flow', ['callFlow' => $this->flow->id, 'node' => $this->queue->id, 'h' => 0, 'a' => 0, 'rc' => 'ask', 'rg' => 'consent', 'fc' => 'NG', 'tc' => 'US', 'av' => 'v1', 'ca' => 'notice', 't' => time()]);

        $yes = $this->signedPost($ask, ['CallSid' => 'CA1', 'From' => '+2348012345678', 'To' => self::NUMBER, 'Digits' => '1'])->assertOk()->getContent();
        $this->assertStringContainsString('record="record-from-answer-dual"', $yes);
        $this->assertStringContainsString('ca=pressed_1', html_entity_decode($yes));

        $no = $this->signedPost($ask, ['CallSid' => 'CA1', 'From' => '+2348012345678', 'To' => self::NUMBER, 'Digits' => '2'])->assertOk()->getContent();
        $this->assertStringNotContainsString('record=', $no);
        $this->assertStringContainsString('<Dial', $no);

        $silent = route('webhooks.twilio.call-flow', ['callFlow' => $this->flow->id, 'node' => $this->queue->id, 'h' => 1, 'a' => 0, 'rc' => '0']);
        $this->assertStringNotContainsString('record=', $this->signedPost($silent, ['CallSid' => 'CA1', 'From' => '+2348012345678', 'To' => self::NUMBER])->getContent());
    }

    public function test_announce_only_mode_skips_the_keypad_even_in_a_strict_market(): void
    {
        $this->switchOn();
        Setting::setValue('business.recording.consent_mode', 'announce_only', 'business');
        $body = $this->enter();

        $this->assertStringNotContainsString('<Gather', $body);
        $this->assertStringContainsString('record="record-from-answer-dual"', $body);
    }

    public function test_sensitive_numbers_and_an_empty_wallet_are_never_recorded(): void
    {
        $this->switchOn();
        Setting::setValue('business.recording.consent_mode', 'announce_only', 'business');
        CallRecordingSetting::where('wallet_group_id', $this->group->id)->update(['sensitive_numbers' => '+2348012345678, +2348000000000']);
        $this->assertStringNotContainsString('record=', $this->enter('+2348012345678'));

        CallRecordingSetting::where('wallet_group_id', $this->group->id)->update(['sensitive_numbers' => null]);
        $this->assertStringContainsString('record=', $this->enter());

        $this->owner->wallet->update(['usd_balance' => 0]);
        $this->assertStringNotContainsString('record=', $this->enter());
    }

    // ── callback + billing chain ────────────────────────────────────────────

    private function callbackUrl(array $evidence = []): string
    {
        return route('webhooks.twilio.call-recording.status', ['callFlow' => $this->flow->id, 'node' => $this->queue->id] + $evidence + [
            'rg' => 'consent', 'ca' => 'pressed_1', 'fc' => 'NG', 'tc' => 'US', 'av' => 'v1', 't' => time(), 'from' => '+2348012345678', 'to' => self::NUMBER,
        ]);
    }

    private function postStatus(string $url, array $over = [])
    {
        $params = $over + ['RecordingStatus' => 'completed', 'RecordingSid' => 'RE1', 'RecordingUrl' => 'https://api.twilio.com/2010-04-01/Accounts/AC_test/Recordings/RE1', 'RecordingDuration' => '90', 'CallSid' => 'CA1'];

        return $this->signedPost($url, $params);
    }

    public function test_an_unsigned_callback_is_rejected_and_queues_nothing(): void
    {
        Queue::fake();
        $this->post($this->callbackUrl(), ['RecordingStatus' => 'completed', 'RecordingSid' => 'RE1'])->assertStatus(403);
        Queue::assertNothingPushed();
    }

    public function test_a_signed_callback_queues_the_store_job_with_the_evidence(): void
    {
        Queue::fake();
        $this->postStatus($this->callbackUrl())->assertNoContent();

        Queue::assertPushed(StoreCallRecordingJob::class, function (StoreCallRecordingJob $j) {
            return $j->data['recording_sid'] === 'RE1' && $j->data['consent']['regime'] === 'consent' && $j->data['consent']['action'] === 'pressed_1'
                && $j->data['consent']['from_country'] === 'NG' && $j->data['duration'] === 90;
        });
    }

    private function store(array $data = []): void
    {
        Http::fake(['api.twilio.com/*' => Http::response('AUDIO', 200)]);
        (new StoreCallRecordingJob($data + [
            'flow_id' => $this->flow->id, 'node_id' => $this->queue->id, 'call_sid' => 'CA1', 'recording_sid' => 'RE1',
            'recording_url' => 'https://api.twilio.com/2010-04-01/Accounts/AC_test/Recordings/RE1', 'duration' => 90,
            'from' => '+2348012345678', 'to' => self::NUMBER,
            'consent' => ['regime' => 'consent', 'action' => 'pressed_1', 'announced_at' => now()->toIso8601String()],
        ]))->handle(app(\App\Services\Business\CallAddonBilling::class));
    }

    public function test_storing_charges_the_owner_once_keeps_the_file_private_and_is_idempotent(): void
    {
        $this->switchOn();
        $before = (float) $this->owner->wallet->fresh()->usd_balance;

        $this->store();
        $this->store(); // a repeated delivery changes nothing

        $rec = CallRecording::firstOrFail();
        $this->assertSame(CallRecording::STORED, $rec->status);
        Storage::disk(MediaStorage::privateDisk())->assertExists($rec->storage_path);
        $this->assertSame(1, CallRecording::count());
        $this->assertLessThan($before, (float) $this->owner->wallet->fresh()->usd_balance);
        $this->assertSame(1, \App\Models\WalletTransaction::where('reference', 'call-record:'.$rec->id)->count());
        $this->assertTrue($rec->expires_at->isFuture());
        $this->assertArrayNotHasKey('provider_recording_sid', $rec->toArray());   // supplier masked
        $this->assertArrayNotHasKey('billing', $rec->toArray());
    }

    public function test_a_recording_without_consent_evidence_is_never_kept(): void
    {
        $this->switchOn();
        $this->store(['consent' => null]);

        $this->assertSame(0, CallRecording::count());
        $this->assertSame(10.0, (float) $this->owner->wallet->fresh()->usd_balance);   // nothing was charged
    }

    public function test_an_empty_wallet_keeps_no_audio_and_charges_nothing(): void
    {
        $this->switchOn();
        $this->owner->wallet->update(['usd_balance' => 0]);
        $this->store();

        $rec = CallRecording::firstOrFail();
        $this->assertSame(CallRecording::UNFUNDED, $rec->status);
        $this->assertNull($rec->storage_path);
    }

    public function test_a_failed_download_is_refunded_in_full(): void
    {
        $this->switchOn();
        Http::fake(['api.twilio.com/*' => Http::response('nope', 500)]);
        (new StoreCallRecordingJob([
            'flow_id' => $this->flow->id, 'node_id' => $this->queue->id, 'call_sid' => 'CA1', 'recording_sid' => 'RE9',
            'recording_url' => 'https://api.twilio.com/2010-04-01/Accounts/AC_test/Recordings/RE9', 'duration' => 60, 'from' => '+1', 'to' => self::NUMBER,
            'consent' => ['regime' => 'notice', 'action' => 'notice', 'announced_at' => now()->toIso8601String()],
        ]))->handle(app(\App\Services\Business\CallAddonBilling::class));

        $this->assertSame(CallRecording::FAILED, CallRecording::firstOrFail()->status);
        $this->assertEqualsWithDelta(10.0, (float) $this->owner->wallet->fresh()->usd_balance, 0.0001);
    }

    public function test_a_recording_url_that_is_not_the_providers_is_never_fetched(): void
    {
        $this->switchOn();
        Http::fake();
        $this->store(['recording_url' => 'https://evil.example/steal']);

        Http::assertNotSent(fn ($r) => str_contains((string) $r->url(), 'evil.example'));
        $this->assertSame(CallRecording::FAILED, CallRecording::firstOrFail()->status);
    }

    public function test_intelligence_is_opt_in_and_billed_per_step(): void
    {
        Queue::fake();
        $this->switchOn(intelligence: false);
        $this->store();
        Queue::assertNotPushed(TranscribeCallRecordingJob::class);

        CallRecording::query()->delete();
        $this->switchOn(intelligence: true);
        $this->store(['recording_sid' => 'RE2']);
        Queue::assertPushed(TranscribeCallRecordingJob::class);
    }

    public function test_transcribe_then_summarise_writes_both_rows_and_never_sends_numbers_to_the_model(): void
    {
        Queue::fake();
        $this->switchOn(intelligence: true);
        $this->store();
        $rec = CallRecording::firstOrFail();

        $this->app->instance(VoiceSynthesizer::class, new FakeVoiceSynthesizer(transcript: 'Hello, I would like to renew my plan please.'));
        (new TranscribeCallRecordingJob($rec->id))->handle(app(\App\Services\Business\CallAddonBilling::class), app(VoiceSynthesizer::class));
        $this->assertSame('complete', $rec->fresh()->transcript_status);
        $this->assertSame('Hello, I would like to renew my plan please.', $rec->transcript->fresh()->transcript_text);
        Queue::assertPushed(SummariseCallJob::class);

        $fake = new FakeAnthropicClient(['sentiment' => 'positive', 'keywords' => ['renew', 'plan', 'billing'], 'coaching_notes' => 'Confirm the plan name.', 'summary' => 'Caller wants to renew.']);
        $this->app->instance(AnthropicClient::class, $fake);
        (new SummariseCallJob($rec->id))->handle(app(\App\Services\Business\CallAddonBilling::class), $fake);

        $s = $rec->fresh()->intelligence;
        $this->assertSame('positive', $s->sentiment);
        $this->assertNull($s->talk_listen_ratio);      // never fabricated
        $this->assertSame(['renew', 'plan', 'billing'], $s->keywords);
        $this->assertStringNotContainsString('+234', json_encode($fake->capturedMessages));
        $this->assertSame('complete', $rec->fresh()->intelligence_status);
        $this->assertSame(3, \App\Models\WalletTransaction::where('reference', 'like', 'call-%:'.$rec->id)->where('type', 'debit')->count());
    }

    public function test_a_badly_shaped_summary_is_refunded_not_stored(): void
    {
        Queue::fake();
        $this->switchOn(intelligence: true);
        $this->store();
        $rec = CallRecording::firstOrFail();
        $this->app->instance(VoiceSynthesizer::class, new FakeVoiceSynthesizer(transcript: 'hello there'));
        (new TranscribeCallRecordingJob($rec->id))->handle(app(\App\Services\Business\CallAddonBilling::class), app(VoiceSynthesizer::class));
        $balance = (float) $this->owner->wallet->fresh()->usd_balance;

        $fake = new FakeAnthropicClient(['sentiment' => 'ecstatic', 'summary' => '']);
        (new SummariseCallJob($rec->id))->handle(app(\App\Services\Business\CallAddonBilling::class), $fake);

        $this->assertSame('failed', $rec->fresh()->intelligence_status);
        $this->assertNull($rec->fresh()->intelligence);
        $this->assertEqualsWithDelta($balance, (float) $this->owner->wallet->fresh()->usd_balance, 0.0001);
    }

    // ── retention ───────────────────────────────────────────────────────────

    public function test_expired_recordings_are_purged_unless_there_is_a_legal_hold(): void
    {
        $this->switchOn();
        $this->store();
        $rec = CallRecording::firstOrFail();
        $path = $rec->storage_path;
        $rec->update(['expires_at' => now()->subDay()]);

        CallRecordingSetting::where('wallet_group_id', $this->group->id)->update(['legal_hold_at' => now()]);
        $this->assertSame(0, app(RecordingRetention::class)->sweep());
        Storage::disk(MediaStorage::privateDisk())->assertExists($path);

        CallRecordingSetting::where('wallet_group_id', $this->group->id)->update(['legal_hold_at' => null]);
        $this->assertSame(1, app(RecordingRetention::class)->sweep());
        Storage::disk(MediaStorage::privateDisk())->assertMissing($path);
        $this->assertSame(CallRecording::PURGED, $rec->fresh()->status);
    }

    public function test_retention_never_exceeds_the_platform_ceiling(): void
    {
        $s = new CallRecordingSetting(['retention_days' => 9999]);
        $this->assertSame(365, $s->effectiveRetentionDays());
        Setting::setValue('business.recording.retention_ceiling_days', 60, 'business');
        $this->assertSame(60, $s->effectiveRetentionDays());
    }

    public function test_it_is_registered_with_the_scheduler(): void
    {
        $this->assertArrayHasKey('business:purge-recordings', \App\Support\SchedulerHealth::tasks());
    }
}
