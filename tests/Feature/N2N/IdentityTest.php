<?php

namespace Tests\Feature\N2N;

use App\Jobs\N2N\LinkContactsJob;
use App\Jobs\N2N\SendPhoneOtpJob;
use App\Livewire\N2N\IdentitySettings;
use App\Models\BrandPartner;
use App\Models\Contact;
use App\Models\N2N\PhoneVerification;
use App\Models\N2N\UserPhone;
use App\Models\Setting;
use App\Services\Account\AccountService;
use App\Services\N2N\Identity\ContactImporter;
use App\Services\N2N\Identity\ContactLinker;
use App\Services\N2N\Identity\IdentityResolver;
use App\Services\N2N\Identity\NaaraId;
use App\Services\N2N\Identity\PhoneLinkService;
use App\Services\N2N\Identity\PhoneNormalizer;
use App\Services\N2N\Identity\ResolveThrottled;
use App\Services\N2N\Identity\UsernamePolicy;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;

/** N2N Batch 3: Naara ID, username, verified phone, resolver, contact linking (MASTER-ONLY). */
class IdentityTest extends N2NTestCase
{
    /** Link a number for a user by running the real start + verify flow and reading the issued code back from the job. */
    private function link($user, string $number): void
    {
        Queue::fake([SendPhoneOtpJob::class, LinkContactsJob::class]);
        $svc = app(PhoneLinkService::class);
        $this->assertTrue($svc->start($user, $number, '10.0.0.1')['ok']);
        $code = null;
        Queue::assertPushed(SendPhoneOtpJob::class, function ($j) use (&$code) { $code = $j->code; return true; });
        $this->assertTrue($svc->verify($user, $code)['ok']);
        $svc->setDiscoverable($user, true);   // discovery is opt-in; these tests exercise the opted-in path
    }

    public function test_a_freshly_verified_number_is_not_discoverable_until_the_member_opts_in(): void
    {
        $u = $this->user();
        Queue::fake([SendPhoneOtpJob::class, LinkContactsJob::class]);
        $svc = app(PhoneLinkService::class);
        $svc->start($u, '08037770001', '10.0.0.1');
        $code = null;
        Queue::assertPushed(SendPhoneOtpJob::class, function ($j) use (&$code) { $code = $j->code; return true; });
        $svc->verify($u, $code);
        $this->assertFalse((bool) \App\Models\N2N\UserPhone::query()->where('user_id', $u->id)->value('discoverable'));
    }

    // ---- Phase A: Naara ID and username
    public function test_naara_ids_are_checksummed_unique_and_typos_are_caught(): void
    {
        $id = NaaraId::generate();
        $this->assertMatchesRegularExpression('/^NR-[0-9A-Z]{10}[0-9A-Z*~$=]$/', $id);
        $this->assertSame($id, NaaraId::normalise(strtolower(str_replace('-', ' ', $id))));
        $bad = substr($id, 0, 5).(substr($id, 5, 1) === 'A' ? 'B' : 'A').substr($id, 6);
        $this->assertNull(NaaraId::normalise($bad), 'a changed character must fail the check');
        $this->assertCount(200, array_unique(array_map(fn () => NaaraId::generate(), range(1, 200))));
    }

    public function test_the_backfill_command_gives_every_user_an_id_and_is_safe_to_rerun(): void
    {
        $a = $this->user(); $b = $this->user();
        $this->artisan('n2n:backfill-identity')->assertSuccessful();
        $first = $a->fresh()->naara_id;
        $this->assertNotNull($first);
        $this->assertNotNull($b->fresh()->naara_id);
        $this->artisan('n2n:backfill-identity')->assertSuccessful();
        $this->assertSame($first, $a->fresh()->naara_id, 'ids are immutable');
    }

    public function test_usernames_are_validated_case_insensitive_unique_and_reserved_names_and_brands_are_protected(): void
    {
        $policy = app(UsernamePolicy::class);
        $a = $this->user(); $b = $this->user();
        $this->assertTrue($policy->claim($a, '@Frank.C')['ok']);
        $this->assertSame('frank.c', $a->fresh()->username);

        $this->assertFalse($policy->claim($b, 'FRANK.C')['ok'], 'same name in any casing');
        foreach (['ab', 'has space', 'bad..dots', '_lead', 'trail.', 'Admin', 'NIA', 'x'.str_repeat('y', 25)] as $bad) {
            $this->assertNotNull($policy->problem(UsernamePolicy::normalise($bad)), $bad);
        }
        Setting::setValue('n2n.reserved_usernames', 'ceo, founder');
        $this->assertNotNull($policy->problem('founder'));

        $owner = $this->user();
        BrandPartner::create(['owner_user_id' => $owner->id, 'brand_name' => 'Sahara Wear', 'listing_status' => 'approved', 'is_active' => true]);
        $this->assertSame('That username belongs to a brand.', $policy->problem('sahara_wear', $b));
        $this->assertNull($policy->problem('sahara_wear', $owner), 'the brand owner may take it');
    }

    public function test_a_username_change_respects_the_admin_cooldown(): void
    {
        Setting::setValue('n2n.username_cooldown_days', 30);
        $u = $this->user();
        $policy = app(UsernamePolicy::class);
        $this->assertTrue($policy->claim($u, 'first_name')['ok']);
        $r = $policy->claim($u->fresh(), 'second_name');
        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('change your username again', $r['message']);
        $this->travel(31)->days();
        $this->assertTrue($policy->claim($u->fresh(), 'second_name')['ok']);
    }

    public function test_the_suggestion_comes_from_the_name_and_is_free(): void
    {
        $this->user(['username' => 'amaka']);
        $u = $this->user(['name' => 'Amaka']);
        $s = app(UsernamePolicy::class)->suggest($u);
        $this->assertNotSame('amaka', $s);
        $this->assertStringStartsWith('amaka', $s);
        $this->assertNull(app(UsernamePolicy::class)->problem($s, $u));
    }

    // ---- Phase B: verified phone
    public function test_five_formats_of_one_number_normalise_to_the_same_e164(): void
    {
        foreach (['0803 123 4567', '08031234567', '+234 803 123 4567', '+2348031234567', '(0803) 123-4567'] as $in) {
            $this->assertSame('+2348031234567', PhoneNormalizer::normalise($in, 'NG')['e164'], $in);
        }
        $this->assertNull(PhoneNormalizer::normalise('12345', 'NG'));
    }

    public function test_a_number_links_only_after_the_correct_code_and_the_code_is_never_stored_in_clear(): void
    {
        $u = $this->user();
        Queue::fake([SendPhoneOtpJob::class, LinkContactsJob::class]);
        $svc = app(PhoneLinkService::class);
        $svc->start($u, '0803 123 4567', '1.1.1.1');
        $row = PhoneVerification::first();
        Queue::assertPushed(SendPhoneOtpJob::class, fn ($j) => $j->code !== $row->code_hash && strlen($j->code) === 6);

        $this->assertFalse($svc->verify($u, '000000')['ok']);
        $this->assertSame(0, UserPhone::count());
        $code = null;
        Queue::assertPushed(SendPhoneOtpJob::class, function ($j) use (&$code) { $code = $j->code; return true; });
        $this->assertTrue($svc->verify($u, $code)['ok']);
        $this->assertSame('+2348031234567', UserPhone::first()->e164);
    }

    public function test_brute_force_locks_the_code_after_the_attempt_limit(): void
    {
        Setting::setValue('n2n.otp_max_attempts', 3);
        $u = $this->user();
        Queue::fake();
        $svc = app(PhoneLinkService::class);
        $svc->start($u, '08031234567', '1.1.1.1');
        $code = null;
        Queue::assertPushed(SendPhoneOtpJob::class, function ($j) use (&$code) { $code = $j->code; return true; });
        $wrong = $code === '111111' ? '222222' : '111111';
        foreach (range(1, 3) as $_) { $svc->verify($u, $wrong); }
        $this->assertFalse($svc->verify($u, $code)['ok'], 'even the right code is refused once locked');
    }

    public function test_expired_codes_are_refused(): void
    {
        $u = $this->user();
        Queue::fake();
        $svc = app(PhoneLinkService::class);
        $svc->start($u, '08031234567', '1.1.1.1');
        $code = null;
        Queue::assertPushed(SendPhoneOtpJob::class, function ($j) use (&$code) { $code = $j->code; return true; });
        $this->travel(11)->minutes();
        $this->assertFalse($svc->verify($u, $code)['ok']);
    }

    public function test_two_accounts_cannot_hold_the_same_verified_number_and_the_reply_never_reveals_it(): void
    {
        $a = $this->user(); $b = $this->user();
        $this->link($a, '08031234567');

        Queue::fake([SendPhoneOtpJob::class]);
        $r = app(PhoneLinkService::class)->start($b, '08031234567', '2.2.2.2');
        $this->assertSame(PhoneLinkService::GENERIC_SENT, $r['message'], 'same reply as a normal send');
        Queue::assertNothingPushed();   // but no SMS is sent to a number that belongs to someone else
        $this->assertFalse(app(PhoneLinkService::class)->verify($b, '123456')['ok']);
        $this->assertSame(1, UserPhone::count());
    }

    public function test_otp_caps_apply_per_user_number_and_ip_and_a_resend_gap_applies(): void
    {
        Setting::setValue('n2n.otp_resend_seconds', 0);
        Setting::setValue('n2n.otp_per_user_day', 2);
        Queue::fake();
        $u = $this->user();
        $svc = app(PhoneLinkService::class);
        $this->assertTrue($svc->start($u, '08031234567', '1.1.1.1')['ok']);
        $this->assertTrue($svc->start($u, '08031234568', '1.1.1.1')['ok']);
        $this->assertFalse($svc->start($u, '08031234569', '1.1.1.1')['ok'], 'per-user daily cap');

        Setting::setValue('n2n.otp_per_ip_day', 1);
        $w = $this->user();
        $this->assertTrue($svc->start($w, '08031230000', '9.9.9.9')['ok']);
        $this->assertFalse($svc->start($this->user(), '08031230001', '9.9.9.9')['ok'], 'per-IP daily cap');

        Setting::setValue('n2n.otp_resend_seconds', 60);
        $this->assertFalse($svc->start($w, '08031230002', '8.8.8.8')['ok'], 'resend gap');
    }

    public function test_relinking_replaces_the_old_number_and_unlinking_removes_it(): void
    {
        $u = $this->user();
        Setting::setValue('n2n.otp_resend_seconds', 0);
        $this->link($u, '08031234567');
        $this->link($u, '08039999999');
        $this->assertSame('+2348039999999', UserPhone::where('user_id', $u->id)->value('e164'));
        $this->assertSame(1, UserPhone::count());
        Queue::fake();
        app(PhoneLinkService::class)->unlink($u);
        $this->assertSame(0, UserPhone::count());
    }

    public function test_the_cost_report_is_measured_never_guessed(): void
    {
        $this->assertNull(app(PhoneLinkService::class)->costReport()['avg_per_send_usd']);
        PhoneVerification::create(['user_id' => $this->user()->id, 'e164' => '+2348031234567', 'code_hash' => 'x', 'cost_usd' => 0.06, 'expires_at' => now()->addMinutes(5)]);
        PhoneVerification::create(['user_id' => $this->user()->id, 'e164' => '+2348031234568', 'code_hash' => 'x', 'cost_usd' => 0.04, 'expires_at' => now()->addMinutes(5)]);
        $this->assertSame(0.05, app(PhoneLinkService::class)->costReport()['avg_per_send_usd']);
    }

    // ---- Phase C: resolver
    public function test_one_person_resolves_the_same_from_every_identifier(): void
    {
        $target = $this->user(['email' => 'zed@example.com', 'n2n_find_by_email' => true]);
        $target->forceFill(['naara_id' => NaaraId::generate(), 'username' => 'zed.k'])->save();
        $this->link($target, '08031234567');
        $actor = $this->user();
        $r = app(IdentityResolver::class);

        foreach ([$target->naara_id, strtolower($target->naara_id), '@zed.k', 'zed.k', 'zed@example.com', '08031234567', '+234 803 123 4567'] as $in) {
            $res = $r->resolve($in, 'search', $actor, '3.3.3.3');
            $this->assertTrue($res->found, $in);
            $this->assertSame($target->id, $res->userId, $in);
        }
        $public = $r->resolve('zed.k', 'search', $actor)->toPublic();
        $this->assertSame(['found', 'naara_id', 'username', 'name', 'avatar', 'match'], array_keys($public));
        $this->assertStringNotContainsString('zed@example.com', json_encode($public));
        $this->assertStringNotContainsString('803', json_encode($public));
    }

    public function test_discovery_switches_hide_people_from_search_but_not_from_delivery_or_their_own_shared_id(): void
    {
        $t = $this->user(['email' => 'q@example.com']);
        $t->forceFill(['naara_id' => NaaraId::generate(), 'username' => 'quiet'])->save();
        $this->link($t, '08031234567');
        $actor = $this->user();
        $r = app(IdentityResolver::class);

        $this->assertFalse($r->resolve('q@example.com', 'search', $actor)->found, 'email discovery is opt-in');
        $this->assertTrue($r->resolve('q@example.com', 'deliver')->found);

        app(PhoneLinkService::class)->setDiscoverable($t, false);
        $this->assertFalse($r->resolve('08031234567', 'search', $actor)->found);
        $this->assertTrue($r->resolve('08031234567', 'deliver', $actor)->found, 'not discoverable != unreachable');

        $t->forceFill(['n2n_hide_me' => true])->save();
        $this->assertFalse($r->resolve('quiet', 'search', $actor)->found);
        $this->assertTrue($r->resolve($t->naara_id, 'search', $actor)->found, 'a Naara ID they shared on purpose still works');
        $this->assertTrue($r->resolve('@quiet', 'deliver')->found);
    }

    public function test_a_miss_looks_exactly_like_a_hit_in_shape_and_inactive_users_are_unreachable(): void
    {
        $actor = $this->user();
        $gone = $this->user(['is_active' => false]);
        $gone->forceFill(['naara_id' => NaaraId::generate(), 'username' => 'gone'])->save();
        $r = app(IdentityResolver::class);

        $miss = $r->resolve('@nobody', 'search', $actor)->toPublic();
        $this->assertSame(array_keys($miss), array_keys($r->resolve($gone->naara_id, 'search', $actor)->toPublic()));
        $this->assertFalse($r->resolve($gone->naara_id, 'deliver')->found);
        $this->assertFalse($r->resolve('', 'search', $actor)->found);
    }

    public function test_enumeration_is_throttled_per_user_and_per_ip(): void
    {
        Setting::setValue('n2n.search_per_min', 3);
        RateLimiter::clear('n2n-resolve:user:1');
        $actor = $this->user();
        $r = app(IdentityResolver::class);
        foreach (range(1, 3) as $_) { $r->resolve('@x'.$_.'abc', 'search', $actor, '4.4.4.4'); }
        $this->expectException(ResolveThrottled::class);
        $r->resolve('@xyz', 'search', $actor, '4.4.4.4');
    }

    public function test_the_lookup_endpoint_surface_is_unreachable_when_n2n_is_off(): void
    {
        Setting::setValue('n2n.enabled', false);
        $this->actingAs($this->user())->get('/account/naara-id')->assertOk()->assertSee('Naara chat is coming soon');
        Setting::setValue('n2n.enabled', true);
        $this->actingAs($this->user())->get('/account/naara-id')->assertOk()->assertSee('NR-');
    }

    // ---- Phase D: contacts
    public function test_importing_500_contacts_links_only_verified_discoverable_numbers(): void
    {
        $owner = $this->user();
        $a = $this->user(); $b = $this->user(); $hidden = $this->user();
        $this->link($a, '08030000001'); $this->link($b, '08030000002'); $this->link($hidden, '08030000003');
        app(PhoneLinkService::class)->setDiscoverable($hidden, false);

        $csv = "name,phone\nAda,08030000001\nBen,08030000002\nHidden,08030000003\nStranger,08030000004\n";
        for ($i = 100; $i < 596; $i++) { $csv .= "P{$i},0803{$i}7000\n"; }
        $importer = app(ContactImporter::class);
        $rows = $importer->parse($csv);
        $this->assertCount(500, $rows);
        Queue::fake([LinkContactsJob::class]);
        $res = $importer->import($owner, $rows, true);
        $this->assertTrue($res['ok']);

        app(ContactLinker::class)->linkOwner($owner->id);
        $linked = Contact::where('user_id', $owner->id)->whereNotNull('linked_user_id')->pluck('linked_user_id')->sort()->values()->all();
        $this->assertSame([$a->id, $b->id], $linked);
    }

    public function test_import_needs_consent_respects_the_row_and_daily_caps_and_can_be_deleted(): void
    {
        $u = $this->user();
        $imp = app(ContactImporter::class);
        $this->assertFalse($imp->import($u, [['name' => 'A', 'phone' => '08031112222']], false)['ok']);

        Setting::setValue('n2n.import_max_contacts', 2);
        $this->assertFalse($imp->import($u, array_fill(0, 3, ['name' => 'A', 'phone' => '08031112222']), true)['ok']);

        Setting::setValue('n2n.import_max_contacts', 500);
        Setting::setValue('n2n.import_per_day', 1);
        Queue::fake();
        $this->assertTrue($imp->import($u, [['name' => 'A', 'phone' => '08031112222']], true)['ok']);
        $this->assertFalse($imp->import($u, [['name' => 'B', 'phone' => '08031113333']], true)['ok'], 'daily cap');

        Contact::create(['user_id' => $u->id, 'name' => 'Manual', 'phone_number' => '08035554444']);
        $this->assertSame(1, $imp->deleteImported($u));
        $this->assertSame(1, Contact::where('user_id', $u->id)->count(), 'hand-made contacts are untouched');
    }

    public function test_vcards_parse_and_duplicate_numbers_are_skipped(): void
    {
        $vcf = "BEGIN:VCARD\nVERSION:3.0\nFN:Chidi\nTEL;TYPE=CELL:+234 803 111 2222\nEND:VCARD\nBEGIN:VCARD\nFN:Chidi Again\nTEL:08031112222\nEND:VCARD\n";
        $imp = app(ContactImporter::class);
        $rows = $imp->parse($vcf);
        $this->assertCount(2, $rows);
        Queue::fake();
        $res = $imp->import($this->user(), $rows, true);
        $this->assertSame(1, $res['imported']);
        $this->assertSame(1, $res['skipped']);
    }

    public function test_unlinking_a_phone_unlinks_contacts_within_one_job_cycle(): void
    {
        $owner = $this->user(); $friend = $this->user();
        $this->link($friend, '08030000009');
        Queue::fake([LinkContactsJob::class]);
        $c = Contact::create(['user_id' => $owner->id, 'name' => 'Friend', 'phone_number' => '08030000009']);
        $this->assertSame('+2348030000009', $c->fresh()->e164, 'the observer keeps e164 in step');
        app(ContactLinker::class)->linkOwner($owner->id);
        $this->assertSame($friend->id, $c->fresh()->linked_user_id);

        app(PhoneLinkService::class)->unlink($friend);
        Queue::assertPushed(LinkContactsJob::class, fn ($j) => $j->e164 === '+2348030000009');
        (new LinkContactsJob(null, '+2348030000009'))->handle(app(ContactLinker::class));
        $this->assertNull($c->fresh()->linked_user_id);
    }

    public function test_a_contact_never_links_to_its_own_owner(): void
    {
        $u = $this->user();
        $this->link($u, '08030000010');
        Queue::fake();
        $c = Contact::create(['user_id' => $u->id, 'name' => 'Me', 'phone_number' => '08030000010']);
        app(ContactLinker::class)->linkOwner($u->id);
        $this->assertNull($c->fresh()->linked_user_id);
    }

    // ---- Phase E: settings screen + erasure
    public function test_the_settings_screen_claims_a_username_links_a_phone_and_saves_discovery(): void
    {
        $u = $this->user();
        Queue::fake();
        Livewire::actingAs($u)->test(IdentitySettings::class)
            ->set('username', 'chidi_o')->call('saveUsername')->assertSet('error', null)
            ->set('byEmail', true)->set('hideMe', true)->call('saveDiscovery');

        $u->refresh();
        $this->assertSame('chidi_o', $u->username);
        $this->assertNotNull($u->naara_id);
        $this->assertTrue((bool) $u->n2n_find_by_email);
        $this->assertTrue((bool) $u->n2n_hide_me);
    }

    public function test_erasure_unlinks_the_phone_nulls_contact_links_and_releases_the_username_but_keeps_the_naara_id(): void
    {
        $u = $this->user();
        $u->forceFill(['naara_id' => NaaraId::generate(), 'username' => 'leaver'])->save();
        $this->link($u, '08030000020');
        $owner = $this->user();
        Queue::fake();
        $c = Contact::create(['user_id' => $owner->id, 'name' => 'Leaver', 'phone_number' => '08030000020']);
        app(ContactLinker::class)->linkOwner($owner->id);
        $this->assertSame($u->id, $c->fresh()->linked_user_id);
        $id = $u->naara_id;

        app(AccountService::class)->erase($u->fresh());

        $this->assertSame(0, UserPhone::where('user_id', $u->id)->count());
        $this->assertNull($c->fresh()->linked_user_id);
        $this->assertNull($u->fresh()->username);
        $this->assertSame($id, $u->fresh()->naara_id, 'an id is never re-issued');
        $this->assertFalse(app(IdentityResolver::class)->resolve($id, 'deliver')->found);
    }

    // ---- Admin lookup
    public function test_an_admin_can_look_up_rename_and_release_a_phone_and_every_step_is_audited_but_a_member_cannot(): void
    {
        $this->seed(\Database\Seeders\RoleSeeder::class);
        $admin = $this->user(); $admin->assignRole('admin');
        $target = $this->user();
        $target->forceFill(['naara_id' => NaaraId::generate(), 'username' => 'oldname', 'n2n_hide_me' => true])->save();
        $this->link($target, '08031234567');
        Queue::fake();

        Livewire::actingAs($admin)->test(\App\Livewire\Admin\N2N\IdentityLookup::class)
            ->set('q', 'oldname')->call('find')->assertSee($target->naara_id)->assertDontSee('+2348031234567')
            ->set('newUsername', 'newname')->call('rename')->assertSee('Username changed.')
            ->call('releasePhone')->assertSee('Phone released.');

        $this->assertSame('newname', $target->fresh()->username);
        $this->assertSame(0, UserPhone::count());
        foreach (['n2n.admin_lookup', 'n2n.username_forced', 'n2n.phone_unlinked'] as $a) {
            $this->assertDatabaseHas('audit_logs', ['action' => $a]);
        }
        Livewire::actingAs($this->user())->test(\App\Livewire\Admin\N2N\IdentityLookup::class)->set('q', 'newname')->call('find')->assertForbidden();
    }
}
