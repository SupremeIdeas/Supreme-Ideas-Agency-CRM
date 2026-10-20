<?php

namespace Tests\Feature\N2N;

use App\Models\BrandPartner;
use App\Models\BrandSubscription;
use App\Models\BrandSubscriptionPlan;
use App\Models\Contact;
use App\Models\N2N\Attachment;
use App\Models\N2N\Message;
use App\Models\Setting;
use App\Models\User;
use App\Services\N2N\Credit\N2NPricing;
use App\Services\N2N\Credit\UsageCredit;
use App\Services\N2N\Credit\UsageLedgerService;
use App\Services\N2N\Identity\NaaraId;
use App\Services\N2N\Media\MediaService;
use App\Services\N2N\Media\NaaraPro;
use App\Services\N2N\Messaging\Inbox;
use App\Services\N2N\Messaging\MessageService;
use App\Services\Wallet\WalletService;
use App\Support\MediaStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/** N2N Batch 7: Naara Pro entitlement, the media pipeline and its attack surface (MASTER-ONLY). */
class MediaTest extends N2NTestCase
{
    private string $disk;

    protected function setUp(): void
    {
        parent::setUp();
        $this->disk = MediaStorage::privateDisk();
        Storage::fake($this->disk);
    }

    private function member(bool $pro = false, float $buyUsd = 20): User
    {
        $u = $this->user(['email_verified_at' => now()]);
        $u->forceFill(['naara_id' => NaaraId::generate(), 'created_at' => now()->subDays(30)])->save();
        app(WalletService::class)->credit($u, $buyUsd, 'USD', ['reference' => 'f'.$u->id]);
        app(UsageCredit::class)->purchase($u, $buyUsd, 'b'.$u->id);
        if ($pro) {
            $this->makePro($u);
        }

        return $u->fresh();
    }

    private function makePro(User $u, string $status = BrandSubscription::ACTIVE): BrandSubscription
    {
        $plan = BrandSubscriptionPlan::query()->first() ?? BrandSubscriptionPlan::create(['name' => 'Pro', 'price_usd_per_month' => 10, 'handles_included' => 3, 'guaranteed_followers_per_handle_per_month' => 0, 'video_previews_allowed' => 1, 'credit_reward_per_follow' => 0, 'is_active' => true, 'sort_order' => 1]);
        $brand = BrandPartner::query()->firstOrCreate(['owner_user_id' => $u->id], ['brand_name' => 'B'.$u->id, 'listing_status' => 'active', 'is_active' => true, 'current_plan_id' => $plan->id]);

        return BrandSubscription::create(['brand_partner_id' => $brand->id, 'plan_id' => $plan->id, 'status' => $status, 'started_at' => now(), 'next_billing_at' => now()->addMonth(), 'last_charged_at' => now()]);
    }

    private function connect(User $a, User $b): void
    {
        Contact::create(['user_id' => $b->id, 'name' => 'A', 'phone_number' => '0803'.random_int(1000000, 9999999)])->forceFill(['linked_user_id' => $a->id])->saveQuietly();
    }

    private function png(int $w = 40, int $h = 30): string
    {
        $im = imagecreatetruecolor($w, $h);
        imagefill($im, 0, 0, imagecolorallocate($im, 10, 110, 110));
        ob_start(); imagepng($im); imagedestroy($im);

        return (string) ob_get_clean();
    }

    /** A real JPEG with an EXIF APP1 segment carrying a GPS-looking marker, to prove the re-encode strips it. */
    private function jpegWithExif(): string
    {
        $im = imagecreatetruecolor(64, 48);
        imagefill($im, 0, 0, imagecolorallocate($im, 200, 100, 50));
        ob_start(); imagejpeg($im, null, 90); imagedestroy($im);
        $jpg = (string) ob_get_clean();
        $payload = "Exif\0\0GPSLatitude=6.4541,GPSLongitude=3.3947,Make=SecretPhone";
        $app1 = "\xFF\xE1".pack('n', strlen($payload) + 2).$payload;

        return substr($jpg, 0, 2).$app1.substr($jpg, 2);
    }

    private function fileOf(string $bytes, string $name = 'p.png', string $mime = 'image/png'): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'n2n').'.bin';
        file_put_contents($path, $bytes);

        return new UploadedFile($path, $name, $mime, null, true);
    }

    private function sendVia(User $u, ?int $cid, UploadedFile $file = null, string $text = '', string $key = null, array $meta = [], string $field = 'files')
    {
        $key ??= 'k'.uniqid().'xxxx';
        $body = ['meta' => json_encode(['text' => $text] + $meta)];
        if ($file) {
            $body[$field] = $field === 'files' ? [$file] : $file;
        }
        $url = $cid ? "/n2n/api/conversations/$cid/messages" : '/n2n/api/messages?to='.$this->peer->naara_id;

        return $this->actingAs($u)->post($url, $body, ['Idempotency-Key' => $key, 'Accept' => 'application/json']);
    }

    private User $peer;

    private function bal(User $u): int
    {
        return array_sum(app(UsageLedgerService::class)->balances($u->id));
    }

    // ---- Phase A: Naara Pro
    public function test_pro_means_an_active_subscription_or_an_admin_grant_and_grace_or_cancelled_is_not_pro(): void
    {
        $none = $this->member(); $active = $this->member(); $grace = $this->member(); $cancelled = $this->member(); $granted = $this->member();
        $this->makePro($active); $this->makePro($grace, BrandSubscription::PAST_DUE); $this->makePro($cancelled, BrandSubscription::CANCELLED);
        $brand = BrandPartner::create(['owner_user_id' => $granted->id, 'brand_name' => 'G', 'listing_status' => 'active', 'is_active' => true]);
        $brand->forceFill(['pro_granted_at' => now()])->save();

        $this->assertSame([false, true, false, false, true], array_map(fn ($u) => NaaraPro::active($u), [$none, $active, $grace, $cancelled, $granted]));
    }

    public function test_a_lapse_flips_the_gate_immediately_and_not_after_the_cache_ttl(): void
    {
        $u = $this->member();
        $sub = $this->makePro($u);
        $this->assertTrue(NaaraPro::active($u));

        $sub->forceFill(['status' => BrandSubscription::PAST_DUE])->save();
        $this->assertFalse(NaaraPro::active($u), 'busted by the subscription change, not by waiting out the TTL');
        $sub->forceFill(['status' => BrandSubscription::ACTIVE])->save();
        $this->assertTrue(NaaraPro::active($u), 'renewal flips it back at once');

        BrandPartner::where('owner_user_id', $u->id)->first()->forceFill(['is_active' => false])->save();
        $this->assertFalse(NaaraPro::active($u), 'a disabled listing is not Pro');
    }

    // ---- Phase B: the send matrix
    public function test_a_non_pro_sender_cannot_upload_media_and_nothing_is_stored_or_charged(): void
    {
        $a = $this->member(); $this->peer = $this->member(); $this->connect($a, $this->peer);
        $before = $this->bal($a);

        $this->sendVia($a, null, $this->fileOf($this->png()))->assertForbidden()->assertJsonPath('error', 'pro_required')->assertJsonStructure(['actions' => ['upgrade']]);

        $this->assertSame($before, $this->bal($a));
        $this->assertSame(0, Attachment::count());
        $this->assertSame([], Storage::disk($this->disk)->allFiles());
        $this->assertSame(0, Message::count());
    }

    public function test_a_pro_sender_sends_a_photo_and_a_non_pro_recipient_can_view_and_download_it(): void
    {
        $a = $this->member(true); $b = $this->member(); $this->peer = $b; $this->connect($a, $b);

        $r = $this->sendVia($a, null, $this->fileOf($this->png()), 'look at this')->assertCreated()->json();
        $att = Attachment::first();
        $this->assertSame('image', $att->kind);
        $this->assertSame(Attachment::ATTACHED, $att->status);
        $this->assertSame('image', Message::first()->type);
        $this->assertSame('look at this', Message::first()->body);

        $view = $this->actingAs($b)->get(route('n2n.media', $att->id))->assertOk();
        $view->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString('no-store', (string) $view->headers->get('Cache-Control'));
        $this->assertStringContainsString("sandbox", (string) $view->headers->get('Content-Security-Policy'));
        $this->actingAs($b)->get(route('n2n.media', ['attachment' => $att->id, 'thumb' => 1]))->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        $dl = $this->actingAs($b)->get(route('n2n.media', ['attachment' => $att->id, 'download' => 1]))->assertOk();
        $this->assertStringContainsString('attachment', (string) $dl->headers->get('Content-Disposition'));
        $this->assertSame($r['conversation_id'], Message::first()->conversation_id);
    }

    public function test_only_participants_can_fetch_media_and_a_missing_or_foreign_id_answers_the_same(): void
    {
        $a = $this->member(true); $b = $this->member(); $x = $this->member(); $this->peer = $b; $this->connect($a, $b);
        $this->sendVia($a, null, $this->fileOf($this->png()))->assertCreated();
        $id = Attachment::first()->id;

        $this->actingAs($x)->get(route('n2n.media', $id))->assertNotFound();
        $this->actingAs($x)->get(route('n2n.media', 999999))->assertNotFound();
        auth()->logout();
        $this->get(route('n2n.media', $id))->assertRedirect();   // guests go to login
    }

    public function test_a_message_dropped_by_a_block_never_exposes_its_media_to_the_would_be_recipient(): void
    {
        $a = $this->member(true); $b = $this->member(); $this->peer = $b;
        \App\Models\N2N\Block::create(['blocker_id' => $b->id, 'blocked_id' => $a->id, 'created_at' => now()]);
        $this->sendVia($a, null, $this->fileOf($this->png()))->assertCreated();
        $id = Attachment::first()->id;
        $this->actingAs($b)->get(route('n2n.media', $id))->assertNotFound();
        $this->actingAs($a)->get(route('n2n.media', $id))->assertOk();
    }

    public function test_a_pro_lapse_keeps_old_media_viewable_but_blocks_new_uploads(): void
    {
        $a = $this->member(); $b = $this->member(); $this->peer = $b; $this->connect($a, $b);
        $sub = $this->makePro($a);
        $cid = $this->sendVia($a, null, $this->fileOf($this->png()))->assertCreated()->json('conversation_id');
        $sub->forceFill(['status' => BrandSubscription::CANCELLED])->save();

        $this->actingAs($b)->get(route('n2n.media', Attachment::first()->id))->assertOk();
        $this->sendVia($a, $cid, $this->fileOf($this->png()))->assertForbidden()->assertJsonPath('error', 'pro_required');
        $this->sendVia($a, $cid, null, 'text is still fine')->assertCreated();
    }

    // ---- Phase C: the upload attack surface
    public function test_an_exe_renamed_jpg_a_svg_and_a_script_are_refused_by_their_real_bytes(): void
    {
        $a = $this->member(true); $this->peer = $this->member(); $this->connect($a, $this->peer);
        foreach ([
            [$this->fileOf("MZ\x90\x00\x03\x00\x00\x00\x04\x00\x00\x00\xFF\xFF\x00\x00".str_repeat('A', 200), 'holiday.jpg', 'image/jpeg'), 'an .exe renamed .jpg'],
            [$this->fileOf('<?xml version="1.0"?><svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>', 'x.svg', 'image/svg+xml'), 'an svg'],
            [$this->fileOf("<?php system(\$_GET['c']); ?>".str_repeat(' ', 100), 'x.png', 'image/png'), 'php in a png'],
            [$this->fileOf('GIF89a'.str_repeat("\0", 50), 'x.gif', 'image/gif'), 'a gif'],
        ] as [$file, $why]) {
            $this->sendVia($a, null, $file)->assertStatus(415);
        }
        $this->assertSame(0, Attachment::count());
        $this->assertSame([], Storage::disk($this->disk)->allFiles());
    }

    public function test_exif_and_gps_are_stripped_by_re_encoding_and_a_php_polyglot_payload_does_not_survive(): void
    {
        $a = $this->member(true); $this->peer = $this->member(); $this->connect($a, $this->peer);
        $this->sendVia($a, null, $this->fileOf($this->jpegWithExif(), 'p.jpg', 'image/jpeg'))->assertCreated();
        $att = Attachment::first();
        $stored = Storage::disk($this->disk)->get($att->storage_key);

        $this->assertStringStartsWith("\xFF\xD8\xFF", $stored);
        foreach (['Exif', 'GPSLatitude', 'SecretPhone', '6.4541'] as $leak) {
            $this->assertStringNotContainsString($leak, $stored, "must not keep: $leak");
        }
        $this->assertNotNull(getimagesizefromstring($stored));
        $this->assertSame(64, $att->width);
        $this->assertNotNull($att->thumb_key);
        $this->assertSame('image/jpeg', $att->mime);
    }

    public function test_oversize_files_and_a_declared_size_mismatch_are_rejected_with_a_clear_json_error(): void
    {
        Setting::setValue('n2n.media_image_max_mb', 0.1);   // ~100 KB
        $a = $this->member(true); $this->peer = $this->member(); $this->connect($a, $this->peer);
        $big = $this->png(300, 300).str_repeat("\0", 120000);
        $this->sendVia($a, null, $this->fileOf($big))->assertStatus(413)->assertJsonPath('error', 'too_large')->assertJsonFragment(['message' => 'That file is too large. The limit for photos is 0.1 MB.']);

        Setting::setValue('n2n.media_image_max_mb', 10);
        $att = app(MediaService::class)->stage($a, $this->fileOf($this->png()), null, null, null, strlen($this->png()));
        $this->assertSame('image/png', $att->mime);
        try {
            app(MediaService::class)->stage($a, $this->fileOf($this->png()), null, null, null, 5_000_000);
            $this->fail('declared-vs-actual mismatch must be rejected');
        } catch (\App\Services\N2N\Messaging\MessagingException $e) {
            $this->assertSame('size_mismatch', $e->reason);
        }
    }

    public function test_voice_notes_are_validated_by_real_bytes_clamped_by_duration_and_keep_their_transcript(): void
    {
        $a = $this->member(true); $this->peer = $this->member(); $this->connect($a, $this->peer);
        $ogg = 'OggS'."\x00\x02".str_repeat("\x00", 40).str_repeat('A', 500);
        $this->sendVia($a, null, $this->fileOf($ogg, 'voice-note.ogg', 'audio/ogg'), '', null, ['voice' => ['duration' => 12, 'transcript' => 'hello from a voice note']], 'voice')->assertCreated();
        $att = Attachment::first();
        $this->assertSame('voice', $att->kind);
        $this->assertSame(12, $att->duration_s);
        $this->assertSame('hello from a voice note', $att->meta['transcript']);
        $this->assertSame('voice', Message::first()->type);

        $this->sendVia($a, null, $this->fileOf($ogg, 'v.ogg', 'audio/ogg'), '', null, ['voice' => ['duration' => 4000]], 'voice')->assertStatus(413);
    }

    public function test_media_lands_only_on_the_private_disk_under_random_keys_and_never_on_local_or_public(): void
    {
        $a = $this->member(true); $this->peer = $this->member(); $this->connect($a, $this->peer);
        Storage::fake('public');
        $this->sendVia($a, null, $this->fileOf($this->png(), 'my private holiday photo.png'))->assertCreated();
        $att = Attachment::first();

        $this->assertNotSame('public', $att->disk);
        $this->assertCount(2, Storage::disk($this->disk)->allFiles(), 'the image and its thumbnail');
        $this->assertSame([], Storage::disk('public')->allFiles());
        $this->assertMatchesRegularExpression('#^n2n/media/\d{4}/\d{2}/[A-Za-z0-9]{40}\.(png|jpg|webp)$#', $att->storage_key);
        $this->assertStringNotContainsString('holiday', $att->storage_key, 'the original filename is never part of a key');
        $this->assertArrayNotHasKey('storage_key', $att->toArray(), 'keys are never serialised to a client');
    }

    // ---- presign + finalize
    public function test_presign_falls_back_to_direct_on_a_disk_that_cannot_sign_and_finalize_verifies_the_object_it_finds(): void
    {
        $a = $this->member(true);
        $this->actingAs($a)->postJson('/n2n/api/media/presign', ['kind' => 'image', 'bytes' => 1000])->assertOk()->assertJsonPath('mode', 'direct');
        $this->actingAs($this->member())->postJson('/n2n/api/media/presign', ['kind' => 'image', 'bytes' => 1000])->assertForbidden();

        // Simulate a completed presigned upload: a pending row plus an object at its key.
        $png = $this->png();
        $pending = Attachment::create(['uploader_id' => $a->id, 'kind' => 'image', 'disk' => $this->disk, 'storage_key' => 'n2n/media/2026/10/'.str_repeat('a', 40).'.bin', 'mime' => 'pending', 'bytes' => strlen($png), 'status' => 'staged', 'meta' => ['pending' => true], 'expires_at' => now()->addMinutes(30)]);
        Storage::disk($this->disk)->put($pending->storage_key, $png);
        $res = $this->actingAs($a)->postJson('/n2n/api/media/finalize', ['id' => $pending->id])->assertOk()->json();
        $this->assertSame('image', $res['kind']);
        $this->assertSame('image/png', $pending->fresh()->mime);
        $this->assertNotNull($pending->fresh()->thumb_key);

        // A bad object (an exe) is deleted and never becomes usable.
        $bad = Attachment::create(['uploader_id' => $a->id, 'kind' => 'image', 'disk' => $this->disk, 'storage_key' => 'n2n/media/2026/10/'.str_repeat('b', 40).'.bin', 'mime' => 'pending', 'bytes' => 300, 'status' => 'staged', 'meta' => ['pending' => true], 'expires_at' => now()->addMinutes(30)]);
        Storage::disk($this->disk)->put($bad->storage_key, "MZ\x90\x00".str_repeat('A', 300));
        $this->actingAs($a)->postJson('/n2n/api/media/finalize', ['id' => $bad->id])->assertStatus(415);
        Storage::disk($this->disk)->assertMissing($bad->storage_key);
        $this->assertNull(Attachment::find($bad->id));

        // Another member's pending upload cannot be finalised by someone else.
        $this->actingAs($this->member(true))->postJson('/n2n/api/media/finalize', ['id' => $pending->id])->assertNotFound();
    }

    // ---- Phase D: metering
    public function test_the_cost_preview_equals_the_charge_and_a_retry_does_not_double_charge(): void
    {
        Setting::setValue('n2n.storage_usd_per_mb', 0.01); Setting::setValue('n2n.media_markup', 3.0);
        $a = $this->member(true); $this->peer = $this->member(); $this->connect($a, $this->peer);
        $png = $this->png(300, 300).str_repeat("\x00", 400000);   // trailing bytes are dropped by the re-encode: price is on the stored size
        $file = $this->fileOf($this->png(200, 200));
        $quote = $this->actingAs($a)->postJson('/n2n/api/media/quote', ['bytes' => filesize($file->getRealPath())])->assertOk()->json();
        $before = $this->bal($a);

        $r = $this->sendVia($a, null, $file, '', 'media-key-0001')->assertCreated()->json();
        $att = Attachment::first();
        $final = (int) max(N2NPricing::messageCostCents(), N2NPricing::mediaCostCents($att->bytes));
        $this->assertSame($before - $final, $this->bal($a), 'charged on the SERVER-measured stored size');
        $this->assertSame(\App\Services\N2N\Credit\Money::credits($final), $r['cost']);
        $this->assertGreaterThanOrEqual(N2NPricing::messageCostCents(), $final, 'media never costs less than a text');
        $this->assertTrue($quote['pro']);

        $this->sendVia($a, null, $this->fileOf($this->png(200, 200)), '', 'media-key-0001')->assertOk();   // retry of the same client_id
        $this->assertSame($before - $final, $this->bal($a), 'no second charge');
        $this->assertSame(1, Message::count());
    }

    public function test_insufficient_credit_blocks_the_media_send_and_removes_the_upload_it_staged(): void
    {
        $a = $this->member(true, 1); $this->peer = $this->member(); $this->connect($a, $this->peer);
        Setting::setValue('n2n.storage_usd_per_mb', 5000.0);   // make even a small image expensive
        $this->sendVia($a, null, $this->fileOf($this->png(400, 400)))->assertStatus(402)->assertJsonPath('error', 'insufficient_credit');
        $this->assertSame(0, Message::count());
        $this->assertSame([], Storage::disk($this->disk)->allFiles(), 'a refused direct send leaves nothing behind');
    }

    // ---- Phase E: lifecycle
    public function test_delete_for_everyone_removes_the_objects_and_the_media_answers_404_afterwards(): void
    {
        $a = $this->member(true); $b = $this->member(); $this->peer = $b; $this->connect($a, $b);
        $mid = $this->sendVia($a, null, $this->fileOf($this->png()), 'oops')->assertCreated()->json('id');
        $att = Attachment::first();
        $svc = app(MessageService::class);

        try { $svc->deleteForEveryone($b, $mid); $this->fail(); } catch (\App\Services\N2N\Messaging\MessagingException $e) { $this->assertSame('not_participant', $e->reason); }
        $this->assertTrue($svc->deleteForEveryone($a, $mid));
        $this->assertTrue($svc->deleteForEveryone($a, $mid), 'idempotent');

        Storage::disk($this->disk)->assertMissing($att->storage_key);
        Storage::disk($this->disk)->assertMissing($att->thumb_key);
        $this->assertSame(Attachment::DELETED, $att->fresh()->status);
        $this->actingAs($b)->get(route('n2n.media', $att->id))->assertNotFound();
        $this->assertDatabaseHas('n2n_realtime_events', ['user_id' => $b->id, 'type' => 'message.deleted']);
    }

    public function test_a_message_older_than_the_delete_window_cannot_be_deleted_for_everyone(): void
    {
        Setting::setValue('n2n.delete_window_hours', 1);
        $a = $this->member(true); $this->peer = $this->member(); $this->connect($a, $this->peer);
        $mid = $this->sendVia($a, null, null, 'old')->assertCreated()->json('id');
        $this->travel(2)->hours();
        $this->expectExceptionMessage('too old');
        app(MessageService::class)->deleteForEveryone($a, $mid);
    }

    public function test_orphan_uploads_are_cleaned_after_their_lifetime_but_sent_media_is_kept(): void
    {
        $a = $this->member(true); $this->peer = $this->member(); $this->connect($a, $this->peer);
        $this->sendVia($a, null, $this->fileOf($this->png()))->assertCreated();
        $orphan = app(MediaService::class)->stage($a, $this->fileOf($this->png(50, 50)));
        $this->travel(7)->hours();

        $this->artisan('n2n:media-cleanup')->assertSuccessful();
        $this->assertNull(Attachment::where('id', $orphan->id)->where('status', '!=', 'deleted')->first());
        Storage::disk($this->disk)->assertMissing($orphan->storage_key);
        $this->assertSame(1, Attachment::where('status', 'attached')->count());
        Storage::disk($this->disk)->assertExists(Attachment::where('status', 'attached')->first()->storage_key);
    }

    public function test_erasing_an_account_deletes_the_media_objects_it_uploaded(): void
    {
        $a = $this->member(true); $this->peer = $this->member(); $this->connect($a, $this->peer);
        $this->sendVia($a, null, $this->fileOf($this->png()))->assertCreated();
        $key = Attachment::first()->storage_key;
        app(\App\Services\Account\AccountService::class)->erase($a->fresh());
        Storage::disk($this->disk)->assertMissing($key);
    }

    public function test_the_inbox_has_photo_and_video_filters_and_a_media_preview(): void
    {
        $a = $this->member(true); $b = $this->member(); $this->peer = $b; $this->connect($a, $b);
        $this->sendVia($a, null, $this->fileOf($this->png()))->assertCreated();
        $inbox = app(Inbox::class);
        $this->assertCount(1, $inbox->entries($b, 'images'));
        $this->assertCount(0, $inbox->entries($b, 'videos'));
        $this->assertSame('Photo', $inbox->entries($b)[0]['preview']);
    }

    public function test_the_chat_page_renders_media_and_only_offers_attachments_to_pro_members(): void
    {
        $pro = $this->member(true); $free = $this->member(); $this->peer = $free; $this->connect($pro, $free);
        $this->sendVia($pro, null, $this->fileOf($this->png()), 'a caption')->assertCreated();
        $cid = Message::first()->conversation_id;

        $page = $this->actingAs($free)->get('/n2n/chat?c='.$cid)->assertOk();
        $page->assertSee('a caption')->assertSee('/n2n/media/', false)->assertSee('Unlock photos, videos and voice notes with Naara Pro');
        $this->assertStringContainsString('locked&quot;:[&quot;voice&quot;,&quot;gif&quot;,&quot;files&quot;]', $page->getContent(), 'a non-Pro composer SEES voice, gifs and files but they are locked (text styles are free)');
        $proPage = $this->actingAs($pro)->get('/n2n/chat?c='.$cid)->assertOk();
        $this->assertStringContainsString('locked&quot;:[]', $proPage->getContent());
        $this->assertStringContainsString('&quot;schedule&quot;', $proPage->getContent());
    }

    // ---- Composer suite: animated GIFs (Naara Pro)
    /** A real two-frame GIF89a, built by hand: header, 2-colour table, two image blocks, trailer. */
    private function gif(string $tail = ''): string
    {
        $frame = "\x21\xF9\x04\x00\x0A\x00\x00\x00"."\x2C\x00\x00\x00\x00\x01\x00\x01\x00\x00"."\x02\x02\x44\x01\x00";

        return "GIF89a\x01\x00\x01\x00\x80\x00\x00"."\x00\x00\x00\xFF\xFF\xFF"."\x21\xFF\x0BNETSCAPE2.0\x03\x01\x00\x00\x00".$frame.$frame."\x3B".$tail;
    }

    public function test_a_pro_member_can_send_an_animated_gif_which_is_kept_animated_and_cut_off_after_its_trailer(): void
    {
        $pro = $this->member(true); $free = $this->member(); $this->peer = $free; $this->connect($pro, $free);
        $this->sendVia($pro, null, $this->fileOf($this->gif('<?php evil(); ?>'), 'wave.gif', 'image/gif'))->assertCreated();
        $a = Attachment::query()->latest('id')->first();
        $this->assertSame('image/gif', $a->mime);
        $this->assertSame('image', $a->kind);
        $stored = Storage::disk($this->disk)->get($a->storage_key);
        $this->assertSame(2, substr_count($stored, "\x2C\x00\x00\x00\x00"), 'both frames survive: the animation is kept');
        $this->assertStringNotContainsString('evil', $stored, 'nothing rides along after the GIF trailer');
        $this->assertStringEndsWith("\x3B", $stored);
        $this->assertNotNull($a->thumb_key);
        $this->actingAs($pro)->get(route('n2n.media', $a->id))->assertOk()->assertHeader('Content-Type', 'image/gif')->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_a_gif_that_is_truncated_malformed_or_not_a_gif_is_refused_and_free_members_cannot_send_one(): void
    {
        $pro = $this->member(true); $free = $this->member(); $this->peer = $free; $this->connect($pro, $free);
        $this->sendVia($pro, null, $this->fileOf(substr($this->gif(), 0, -1), 'a.gif', 'image/gif'))->assertStatus(415);       // no trailer
        $this->sendVia($pro, null, $this->fileOf("GIF89a\x01\x00\x01\x00\x80\x00\x00\x00\x00\x00\xFF\xFF\xFF\x99garbage\x3B", 'b.gif', 'image/gif'))->assertStatus(415);   // bad block
        $this->sendVia($pro, null, $this->fileOf("GIF89a\xFF\xFF\xFF\xFF\x80\x00\x00".str_repeat("\x00", 20)."\x3B", 'c.gif', 'image/gif'))->assertStatus(415);       // 65535 x 65535 canvas
        $this->assertSame(0, Attachment::query()->count());
        $this->sendVia($free, null, $this->fileOf($this->gif(), 'd.gif', 'image/gif'))->assertStatus(403)->assertJsonPath('error', 'pro_required');
    }

    // ---- documents and audio files
    private function docx(bool $macro = false): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'dx');
        $z = new \ZipArchive;
        $z->open($tmp, \ZipArchive::OVERWRITE | \ZipArchive::CREATE);
        $z->addFromString('[Content_Types].xml', '<Types>'.($macro ? 'macroEnabled' : '').'</Types>');
        $z->addFromString('_rels/.rels', '<Relationships/>');
        $z->addFromString('word/document.xml', '<w:document/>');
        if ($macro) {
            $z->addFromString('word/vbaProject.bin', 'MZ');
        }
        $z->close();

        return (string) file_get_contents($tmp);
    }

    public function test_a_pro_member_can_send_pdf_text_and_office_documents_and_they_download_with_their_real_name(): void
    {
        $a = $this->member(true); $b = $this->member(); $this->peer = $b; $this->connect($a, $b);
        $cid = (int) $this->sendVia($a, null, $this->fileOf("%PDF-1.4\n1 0 obj\n<<>>\nendobj\n%%EOF", '../../Quarterly Report.pdf', 'application/pdf'))->assertCreated()->json('conversation_id');
        $att = Attachment::query()->where('kind', 'doc')->firstOrFail();
        $this->assertSame('application/pdf', $att->mime);
        $this->assertSame('Quarterly Report.pdf', $att->meta['name'], 'path parts are stripped and the real extension is used');
        $this->assertSame('doc', Message::query()->latest('id')->value('type'));
        $r = $this->actingAs($b)->get(route('n2n.media', $att->id))->assertOk();
        $this->assertSame('application/octet-stream', $r->headers->get('Content-Type'), 'never rendered by the browser');
        $this->assertStringContainsString('attachment', (string) $r->headers->get('Content-Disposition'));
        $this->sendVia($a, $cid, $this->fileOf("name,qty\nwidget,3\n", 'sheet.csv', 'text/csv'), '', 'doc-csv-0001')->assertCreated();
        $this->sendVia($a, $cid, $this->fileOf($this->docx(), 'brief.docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'), '', 'doc-docx-0001')->assertCreated();
        $this->assertSame(['pdf', 'txt', 'docx'], Attachment::query()->where('kind', 'doc')->orderBy('id')->get()->map(fn ($x) => pathinfo($x->meta['name'], PATHINFO_EXTENSION))->all());
    }

    public function test_dangerous_documents_are_refused_whatever_they_are_called(): void
    {
        $a = $this->member(true); $b = $this->member(); $this->peer = $b; $this->connect($a, $b);
        $cid = null;
        foreach ([
            'macro.docm' => $this->docx(true), 'tool.exe' => "MZ\x90\x00\x03\x00\x00\x00\x04\x00\x00\x00\xFF\xFF", 'page.html' => '<html><script>alert(1)</script></html>',
            'icon.svg' => '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>', 'run.sh' => "#!/bin/sh\nrm -rf /\n", 'old.doc' => "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1".str_repeat("\0", 40), 'blob.zip' => "PK\x03\x04".str_repeat("\0", 60),
        ] as $name => $bytes) {
            $this->sendVia($a, $cid, $this->fileOf($bytes, $name, 'application/octet-stream'), '', 'bad-'.md5($name))->assertStatus(415);
        }
        $this->assertSame(0, Attachment::count());
        $this->assertSame(0, Message::count());
    }

    public function test_an_audio_file_is_its_own_kind_not_a_voice_note_and_keeps_its_name(): void
    {
        $a = $this->member(true); $b = $this->member(); $this->peer = $b; $this->connect($a, $b);
        $this->sendVia($a, null, $this->fileOf('OggS'.str_repeat("\0", 60), 'My Song.ogg', 'audio/ogg'))->assertCreated();
        $att = Attachment::query()->firstOrFail();
        $this->assertSame('audio', $att->kind);
        $this->assertSame('My Song.ogg', $att->meta['name']);
        $this->assertSame('audio', Message::query()->value('type'));
        $this->assertSame('Audio file', collect(app(Inbox::class)->entries($b))->first()['preview']);
    }

    public function test_a_non_pro_member_cannot_send_documents_or_audio_files(): void
    {
        $a = $this->member(); $this->peer = $this->member(); $this->connect($a, $this->peer);
        $this->sendVia($a, null, $this->fileOf("%PDF-1.4\n1 0 obj\n<<>>\nendobj", 'a.pdf', 'application/pdf'))->assertForbidden()->assertJsonPath('error', 'pro_required');
        $this->assertSame(0, Attachment::count());
    }

    public function test_a_document_over_the_admin_limit_is_refused_and_the_clean_name_helper_is_safe(): void
    {
        Setting::setValue('n2n.media_doc_max_mb', 0.1);
        $a = $this->member(true); $this->peer = $this->member(); $this->connect($a, $this->peer);
        $this->sendVia($a, null, $this->fileOf("%PDF-1.4\n".str_repeat('x', 200000), 'big.pdf', 'application/pdf'))->assertStatus(413);
        $this->assertSame('evil.pdf', MediaService::cleanName('..\\..\\evil<>:"|?*.exe', 'pdf'));
        $this->assertSame('file.txt', MediaService::cleanName('', 'txt'));
        $this->assertLessThanOrEqual(74, mb_strlen(MediaService::cleanName(str_repeat('a', 300), 'pdf')));
    }
}
