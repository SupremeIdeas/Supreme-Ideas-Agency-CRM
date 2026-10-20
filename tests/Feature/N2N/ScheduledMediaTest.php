<?php

namespace Tests\Feature\N2N;

use App\Models\BrandPartner;
use App\Models\BrandSubscription;
use App\Models\BrandSubscriptionPlan;
use App\Models\Contact;
use App\Models\N2N\Attachment;
use App\Models\N2N\Message;
use App\Models\N2N\ScheduledMessage;
use App\Models\Setting;
use App\Models\User;
use App\Services\N2N\Credit\UsageCredit;
use App\Services\N2N\Identity\NaaraId;
use App\Support\MediaStorage;
use App\Services\N2N\Messaging\ScheduledSend;
use App\Services\Wallet\WalletService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/** Scheduled media (MASTER-ONLY): files are staged and held at queue time, claimed and metered by the ordinary send path at delivery. */
class ScheduledMediaTest extends N2NTestCase
{
    private User $a;

    private User $b;

    private int $cid;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(MediaStorage::privateDisk());
        $this->a = $this->member(true);
        $this->b = $this->member(false);
        Contact::create(['user_id' => $this->b->id, 'name' => 'A', 'phone_number' => '0803'.random_int(1000000, 9999999)])->forceFill(['linked_user_id' => $this->a->id])->saveQuietly();
        $this->cid = $this->actingAs($this->a)->post('/n2n/api/messages?to='.$this->b->naara_id, ['meta' => json_encode(['text' => 'hello'])], ['Idempotency-Key' => 'first-message-1', 'Accept' => 'application/json'])->assertCreated()->json('conversation_id');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function member(bool $pro): User
    {
        $u = $this->user(['email_verified_at' => now()]);
        $u->forceFill(['naara_id' => NaaraId::generate(), 'created_at' => now()->subDays(30)])->save();
        app(WalletService::class)->credit($u, 20, 'USD', ['reference' => 'f'.$u->id]);
        app(UsageCredit::class)->purchase($u, 20, 'b'.$u->id);
        if ($pro) {
            $plan = BrandSubscriptionPlan::query()->first() ?? BrandSubscriptionPlan::create(['name' => 'Pro', 'price_usd_per_month' => 10, 'handles_included' => 3, 'guaranteed_followers_per_handle_per_month' => 0, 'video_previews_allowed' => 1, 'credit_reward_per_follow' => 0, 'is_active' => true, 'sort_order' => 1]);
            $brand = BrandPartner::query()->firstOrCreate(['owner_user_id' => $u->id], ['brand_name' => 'B'.$u->id, 'listing_status' => 'active', 'is_active' => true, 'current_plan_id' => $plan->id]);
            BrandSubscription::create(['brand_partner_id' => $brand->id, 'plan_id' => $plan->id, 'status' => BrandSubscription::ACTIVE, 'started_at' => now(), 'next_billing_at' => now()->addMonth(), 'last_charged_at' => now()]);
        }

        return $u->fresh();
    }

    private function png(): UploadedFile
    {
        $im = imagecreatetruecolor(40, 30);
        imagefill($im, 0, 0, imagecolorallocate($im, 10, 110, 110));
        ob_start();
        imagepng($im);
        imagedestroy($im);

        return UploadedFile::fake()->createWithContent('p.png', (string) ob_get_clean());
    }

    private function schedule(User $u, string $text, int $atMs, ?UploadedFile $file, string $key)
    {
        return $this->actingAs($u)->post('/n2n/api/conversations/'.$this->cid.'/messages', ['meta' => json_encode(['text' => $text, 'scheduledAt' => $atMs]), 'files' => $file ? [$file] : []], ['Idempotency-Key' => $key, 'Accept' => 'application/json']);
    }

    public function test_a_photo_can_be_scheduled_is_held_and_delivered_at_the_due_time(): void
    {
        $at = now()->addMinutes(10);
        $r = $this->schedule($this->a, 'for later', $at->getTimestampMs(), $this->png(), 'sch-media-0001')->assertCreated();
        $row = ScheduledMessage::query()->findOrFail($r->json('id'));
        $this->assertCount(1, $row->attachment_ids);
        $att = Attachment::query()->findOrFail($row->attachment_ids[0]);
        $this->assertSame(Attachment::STAGED, $att->status);
        $this->assertTrue($att->expires_at->gt($at), 'the staged upload must outlive the send time');
        $this->assertSame(1, Message::query()->count(), 'nothing is delivered yet');

        Carbon::setTestNow($at->copy()->addMinute());
        $this->assertSame(1, app(ScheduledSend::class)->dispatchDue());
        $msg = Message::query()->latest('id')->first();
        $this->assertSame('for later', $msg->body);
        $this->assertSame(1, Attachment::query()->where('message_id', $msg->id)->count());
        $this->assertSame(ScheduledMessage::SENT, $row->fresh()->status);
    }

    public function test_a_member_without_pro_is_refused_at_queue_time_and_nothing_is_stored(): void
    {
        $this->schedule($this->b, 'x', now()->addMinutes(10)->getTimestampMs(), $this->png(), 'sch-media-0002')->assertStatus(403);
        $this->assertSame(0, ScheduledMessage::query()->count());
        $this->assertSame(0, Attachment::query()->count());
    }

    public function test_cancelling_removes_the_held_upload(): void
    {
        $r = $this->schedule($this->a, '', now()->addMinutes(10)->getTimestampMs(), $this->png(), 'sch-media-0003')->assertCreated();
        $row = ScheduledMessage::query()->findOrFail($r->json('id'));
        $this->assertTrue(app(ScheduledSend::class)->cancel($this->a, $row->id));
        $this->assertSame(0, Attachment::query()->whereIn('id', $row->attachment_ids)->where('status', Attachment::STAGED)->count());
    }

    public function test_queued_media_messages_are_capped(): void
    {
        Setting::setValue('n2n.schedule_max_pending_media', 1);
        $at = now()->addMinutes(10)->getTimestampMs();
        $this->schedule($this->a, 'one', $at, $this->png(), 'sch-media-0004')->assertCreated();
        $this->schedule($this->a, 'two', $at, $this->png(), 'sch-media-0005')->assertStatus(422);
        $this->assertSame(1, Attachment::query()->where('status', Attachment::STAGED)->count(), 'the refused upload is discarded');
    }

    public function test_erasure_releases_held_uploads(): void
    {
        $r = $this->schedule($this->a, 'later', now()->addMinutes(10)->getTimestampMs(), $this->png(), 'sch-media-0006')->assertCreated();
        $row = ScheduledMessage::query()->findOrFail($r->json('id'));
        \App\Services\N2N\N2NErasure::erase($this->a);
        $this->assertSame(0, Attachment::query()->whereIn('id', $row->attachment_ids ?? [0])->where('status', Attachment::STAGED)->count());
        $this->assertSame(ScheduledMessage::CANCELLED, $row->fresh()->status);
    }
}
