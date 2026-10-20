<?php

namespace Tests\Feature\N2N;

use App\Models\ConversationEvent;
use App\Models\InboundMessage;
use App\Models\OutboundMessage;
use App\Models\VirtualNumber;
use App\Models\VoiceCall;
use App\Services\Messaging\ConversationTimeline;
use App\Models\User;
use App\Services\N2N\Credit\UsageCredit;
use App\Services\N2N\Identity\NaaraId;
use App\Services\N2N\Messaging\MessageService;
use App\Services\Wallet\WalletService;
use Illuminate\Support\Facades\Queue;

/** Brand-Growth Phase 9 (MASTER-ONLY): one ordered timeline per peer across Naara chat, SMS and calls, kept current by hooks and rebuilt by messaging:reconcile. */
class ConversationTimelineTest extends N2NTestCase
{
    private function member(array $attrs = []): User
    {
        $u = $this->user(array_merge(['email_verified_at' => now()], $attrs));
        $u->forceFill(['naara_id' => NaaraId::generate(), 'created_at' => now()->subDays(30)])->save();
        app(WalletService::class)->credit($u, 10, 'USD', ['reference' => 'f'.$u->id.uniqid()]);
        app(UsageCredit::class)->purchase($u, 10, 'b'.uniqid());

        return $u->fresh();
    }

    private function send(User $from, User $to, string $body)
    {
        return app(MessageService::class)->send($from, ['to' => $to->naara_id], 'c'.uniqid().'xxxx', $body);
    }

    private function line($user): VirtualNumber
    {
        return VirtualNumber::create(['user_id' => $user->id, 'provider' => 'twilio', 'phone_number' => '+15005550006', 'sid' => 'PN1', 'status' => 'active', 'monthly_cost' => 1.0, 'monthly_retail' => 3.0]);
    }

    public function test_peer_keys_are_stable(): void
    {
        $this->assertSame('+2348012345678', ConversationTimeline::peerKey('+234 801 234 5678'));
        $this->assertSame('NR-ABC123', ConversationTimeline::peerKey('nr-abc123'));
    }

    public function test_sms_and_calls_land_on_one_timeline_in_order_and_status_follows_the_source(): void
    {
        $u = $this->user(['email_verified_at' => now()]);
        $line = $this->line($u);
        $out = OutboundMessage::create(['user_id' => $u->id, 'virtual_number_id' => $line->id, 'to_number' => '+2348012345678', 'body' => 'hello', 'provider' => 'twilio', 'reference' => 'r-hello', 'status' => 'queued', 'created_at' => now()->subMinutes(5)]);
        $out->forceFill(['created_at' => now()->subMinutes(5)])->save();
        InboundMessage::create(['user_id' => $u->id, 'virtual_number_id' => $line->id, 'from_number' => '+2348012345678', 'body' => 'hi back', 'provider' => 'twilio', 'received_at' => now()->subMinutes(3)]);
        VoiceCall::create(['user_id' => $u->id, 'destination' => '+234 801 234 5678', 'provider' => 'twilio', 'status' => 'completed', 'duration_seconds' => 70, 'retail_per_min' => 0.1, 'minutes_authorized' => 5, 'amount_held' => 0.5, 'hold_reference' => 'h1']);

        $rows = app(ConversationTimeline::class)->with($u, '+2348012345678');
        $this->assertSame(['sms:out', 'sms:in', 'call:out'], $rows->map(fn ($e) => $e->channel.':'.$e->direction)->all());
        $this->assertSame('Call (2 min)', $rows[2]->summary);
        $this->assertSame('queued', $rows[0]->status);

        $out->update(['status' => 'delivered']);
        $this->assertSame('delivered', ConversationEvent::where('source_type', 'outbound_sms')->first()->status);
        $this->assertSame(3, ConversationEvent::count(), 'an update never adds a second line');
    }

    public function test_a_naara_chat_message_gives_each_person_a_line_pointing_at_the_other(): void
    {
        $a = $this->member(['name' => 'Ada']);
        $b = $this->member(['name' => 'Bola']);
        $this->send($a, $b, 'hello Bola');

        $mine = app(ConversationTimeline::class)->with($a, (string) $b->naara_id);
        $theirs = app(ConversationTimeline::class)->with($b, (string) $a->naara_id);
        $this->assertSame(['n2n:out'], $mine->map(fn ($e) => $e->channel.':'.$e->direction)->all());
        $this->assertSame(['n2n:in'], $theirs->map(fn ($e) => $e->channel.':'.$e->direction)->all());
        $this->assertSame('hello Bola', $mine[0]->summary);
    }

    public function test_reconcile_rebuilds_missing_lines_fixes_drift_and_flags_stuck_sms_without_refunding(): void
    {
        Queue::fake();
        $u = $this->user(['email_verified_at' => now()]);
        $line = $this->line($u);
        $stuck = OutboundMessage::create(['user_id' => $u->id, 'virtual_number_id' => $line->id, 'to_number' => '+2348012345678', 'body' => 'x', 'provider' => 'twilio', 'status' => 'queued', 'amount_charged' => 0.05, 'reference' => 'sms-send:1:a']);
        OutboundMessage::query()->whereKey($stuck->id)->update(['created_at' => now()->subHour()]);
        $sent = OutboundMessage::create(['user_id' => $u->id, 'virtual_number_id' => $line->id, 'to_number' => '+2348012345678', 'body' => 'y', 'provider' => 'twilio', 'reference' => 'r-y', 'status' => 'sent', 'created_at' => now()->subMinutes(2)]);
        ConversationEvent::query()->where('source_id', $stuck->id)->delete();                       // a skipped hook
        OutboundMessage::query()->whereKey($sent->id)->update(['status' => 'delivered']);          // a raw update that bypassed the hook

        $this->artisan('messaging:reconcile', ['--dry' => true])->expectsOutputToContain('Timeline lines rebuilt: 1')->assertExitCode(0);
        $this->assertSame(1, ConversationEvent::count(), 'dry run writes nothing');

        $this->artisan('messaging:reconcile')->expectsOutputToContain('Statuses corrected: 1. SMS stuck in queued: 1.')->assertExitCode(0);
        $this->assertSame(2, ConversationEvent::count());
        $this->assertSame('delivered', ConversationEvent::where('source_id', $sent->id)->where('source_type', 'outbound_sms')->value('status'));
        Queue::assertPushed(\App\Jobs\AlertAdminJob::class, fn ($j) => $j->code === 'sms_stale_queued');
        $this->assertSame('queued', $stuck->fresh()->status, 'nothing is refunded or changed on a guess');

        $this->artisan('messaging:reconcile')->assertExitCode(0);
        Queue::assertPushedTimes(\App\Jobs\AlertAdminJob::class, 1);   // alerted once, not every hour
    }
}
