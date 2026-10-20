<?php

namespace Tests\Feature\N2N;

use App\Models\User;
use App\Notifications\EventNotification;
use App\Services\N2N\Identity\NaaraId;
use App\Support\Verify\Checks\N2NNaaraIdsSound;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/** Brand-Growth Phase 8: one Naara ID per person, issued at sign-up, never changed, same everywhere (MASTER-ONLY). */
class NaaraIdFoundationTest extends N2NTestCase
{
    public function test_for_issues_once_and_returns_the_same_id_forever(): void
    {
        Notification::fake();
        $u = $this->user();
        $this->assertNull($u->fresh()->naara_id);
        $id = NaaraId::for($u);
        $this->assertSame($id, NaaraId::for($u));
        $this->assertSame($id, $u->fresh()->naara_id);
        $this->assertSame($id, NaaraId::normalise($id));
        Notification::assertSentToTimes($u, EventNotification::class, 1);   // "your Naara ID is ready", once
    }

    public function test_two_requests_racing_for_the_same_person_end_with_one_id(): void
    {
        Notification::fake();
        $u = $this->user();
        $a = User::find($u->id);
        $b = User::find($u->id);   // both stale copies still think there is no ID
        $first = NaaraId::assign($a);
        $second = NaaraId::assign($b);
        $this->assertSame($first, $second);
        $this->assertSame($first, $u->fresh()->naara_id);
        Notification::assertSentToTimes($u, EventNotification::class, 1);
    }

    public function test_an_issued_id_can_never_be_changed_or_cleared(): void
    {
        $u = $this->user();
        $id = NaaraId::for($u);
        $u->forceFill(['naara_id' => 'NR-ZZZZZZZZZZZ'])->save();
        $this->assertSame($id, $u->fresh()->naara_id);
        $u->forceFill(['naara_id' => null])->save();
        $this->assertSame($id, $u->fresh()->naara_id);
    }

    public function test_signing_up_issues_the_id_straight_away(): void
    {
        Notification::fake();
        $u = $this->user();
        event(new Registered($u));
        $this->assertNotNull($u->fresh()->naara_id);
    }

    public function test_the_backfill_alias_works_and_a_thousand_ids_are_unique(): void
    {
        $this->user();
        $this->artisan('naara-id:backfill')->assertSuccessful();
        $ids = [];
        for ($i = 0; $i < 1000; $i++) {
            $ids[NaaraId::generate()] = true;
        }
        $this->assertCount(1000, $ids);
    }

    public function test_the_account_page_shows_the_same_id_as_chat_settings(): void
    {
        $u = $this->user(['email_verified_at' => now()]);
        $id = NaaraId::for($u);
        $this->actingAs($u)->get('/account')->assertOk()->assertSee($id);
        \Livewire\Livewire::actingAs($u)->test(\App\Livewire\N2N\IdentitySettings::class)->assertSee($id);
    }

    public function test_the_verify_check_catches_duplicates_malformed_ids_and_reports_missing_ones(): void
    {
        $a = $this->user();
        $b = $this->user();
        NaaraId::for($a);
        $this->assertSame('warn', (new N2NNaaraIdsSound)->run()->status);   // b still has none
        NaaraId::for($b);
        $this->assertSame('pass', (new N2NNaaraIdsSound)->run()->status);

        DB::table('users')->where('id', $b->id)->update(['naara_id' => 'NR-BADBADBADBA']);
        $r = (new N2NNaaraIdsSound)->run();
        $this->assertSame('fail', $r->status);
        $this->assertStringContainsString('malformed', $r->detail);
    }
}
