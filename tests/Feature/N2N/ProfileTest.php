<?php

namespace Tests\Feature\N2N;

use App\Models\Contact;
use App\Models\N2N\Block;
use App\Models\N2N\ProfilePicture;
use App\Models\N2N\UserPhone;
use App\Models\User;
use App\Services\N2N\Identity\NaaraId;
use App\Services\N2N\N2NErasure;
use App\Services\N2N\Profiles\ProfileService;
use App\Services\N2N\Profiles\ProfileVisibility;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/** N2N Batch 8 Phase F: the local member profile and its privacy matrix (MASTER-ONLY). */
class ProfileTest extends N2NTestCase
{
    private function member(array $attrs = []): User
    {
        return $this->user(array_merge([
            'naara_id' => NaaraId::generate(), 'username' => 'u'.substr(md5(uniqid()), 0, 8), 'bio' => 'Hello there', 'date_of_birth' => '1990-03-12',
            'n2n_profile_reveal' => [], 'n2n_profile_visibility' => 'connections',
        ], $attrs));
    }

    private function connect(User $a, User $b): void
    {
        $c = Contact::create(['user_id' => $b->id, 'name' => 'x', 'phone_number' => '0800000'.random_int(1000, 9999)]);
        $c->forceFill(['linked_user_id' => $a->id])->save();
    }

    private function fields(User $viewer, User $subject): ?array
    {
        return ProfileVisibility::resolve($viewer, $subject)['fields'] ?? null;
    }

    // ---- the matrix: visibility x relationship x field -----------------------------------------------------------

    public function test_public_profile_shows_the_base_fields_to_a_stranger_but_never_email_or_phone(): void
    {
        $s = $this->member(['n2n_profile_visibility' => 'public', 'n2n_profile_reveal' => ['email', 'phone', 'naara_id', 'images']]);
        $f = $this->fields($this->member(), $s);
        foreach (['name', 'username', 'naara_id', 'bio', 'birthday', 'images'] as $ok) {
            $this->assertContains($ok, $f, $ok);
        }
        $this->assertNotContains('email', $f);
        $this->assertNotContains('phone', $f);
    }

    public function test_connections_profile_hides_everything_but_name_and_username_from_strangers(): void
    {
        $s = $this->member(['n2n_profile_visibility' => 'connections', 'n2n_profile_reveal' => ['email', 'phone', 'naara_id', 'images']]);
        $this->assertEqualsCanonicalizing(['name', 'username'], $this->fields($this->member(), $s));
        $friend = $this->member();
        $this->connect($friend, $s);
        $f = $this->fields($friend, $s);
        foreach (['name', 'username', 'naara_id', 'bio', 'birthday', 'images', 'email', 'phone'] as $ok) {
            $this->assertContains($ok, $f, $ok);
        }
    }

    public function test_private_profile_shows_only_what_the_owner_ticked(): void
    {
        $s = $this->member(['n2n_profile_visibility' => 'private', 'n2n_profile_reveal' => ['naara_id']]);
        $stranger = $this->member();
        $this->assertEqualsCanonicalizing(['name', 'username', 'naara_id'], $this->fields($stranger, $s));
        $this->connect($stranger, $s);                                    // a connection still sees no bio or birthday on a private profile
        $this->assertEqualsCanonicalizing(['name', 'username', 'naara_id'], $this->fields($stranger, $s));
        $s->forceFill(['n2n_profile_reveal' => ['naara_id', 'images', 'email', 'phone']])->save();
        $this->assertEqualsCanonicalizing(['name', 'username', 'naara_id', 'images', 'email', 'phone'], $this->fields($stranger, $s->fresh()));
        $this->assertNotContains('email', $this->fields($this->member(), $s->fresh()));   // an unconnected stranger still never gets email/phone
    }

    public function test_email_and_phone_need_the_tick_even_for_a_connection(): void
    {
        $s = $this->member(['n2n_profile_visibility' => 'public', 'n2n_profile_reveal' => ['email']]);
        $friend = $this->member();
        $this->connect($friend, $s);
        $f = $this->fields($friend, $s);
        $this->assertContains('email', $f);
        $this->assertNotContains('phone', $f);
    }

    public function test_self_sees_everything(): void
    {
        $s = $this->member(['n2n_profile_visibility' => 'private']);
        $f = $this->fields($s, $s);
        $this->assertContains('email', $f);
        $this->assertContains('birthday', $f);
    }

    public function test_blocked_either_way_inactive_and_hidden_all_resolve_to_null(): void
    {
        $s = $this->member(['n2n_profile_visibility' => 'public']);
        $v = $this->member();
        Block::create(['blocker_id' => $s->id, 'blocked_id' => $v->id, 'created_at' => now()]);
        $this->assertNull(ProfileVisibility::resolve($v, $s));
        Block::query()->delete();
        Block::create(['blocker_id' => $v->id, 'blocked_id' => $s->id, 'created_at' => now()]);
        $this->assertNull(ProfileVisibility::resolve($v, $s));
        Block::query()->delete();
        $this->assertNotNull(ProfileVisibility::resolve($v, $s));
        $s->forceFill(['is_active' => false])->save();
        $this->assertNull(ProfileVisibility::resolve($v, $s->fresh()));
        $s->forceFill(['is_active' => true, 'n2n_hide_me' => true])->save();
        $this->assertNull(ProfileVisibility::resolve($v, $s->fresh()));
        $this->connect($v, $s);
        $this->assertNotNull(ProfileVisibility::resolve($v, $s->fresh()));   // hide-me only hides from people who are not connected
    }

    public function test_birthday_shows_day_and_month_only_unless_the_year_is_opted_in(): void
    {
        $s = $this->member(['n2n_profile_visibility' => 'public']);
        $v = $this->member();
        $this->assertSame('12 March', ProfileVisibility::payload($v, $s)['birthday']);
        $s->forceFill(['n2n_dob_show_year' => true])->save();
        $this->assertSame('12 March 1990', ProfileVisibility::payload($v, $s->fresh())['birthday']);
    }

    public function test_the_payload_never_contains_a_field_the_viewer_may_not_see(): void
    {
        $s = $this->member(['n2n_profile_visibility' => 'public', 'n2n_profile_reveal' => ['email', 'phone']]);
        UserPhone::create(['user_id' => $s->id, 'e164' => '+2348012345678', 'country_code' => 'NG', 'verified_at' => now()]);
        $p = ProfileVisibility::payload($this->member(), $s);
        $this->assertArrayNotHasKey('email', $p);
        $this->assertArrayNotHasKey('phone', $p);
        $this->assertStringNotContainsString($s->email, json_encode($p));
        $this->assertStringNotContainsString('2348012345678', json_encode($p));
    }

    // ---- pages ---------------------------------------------------------------------------------------------------------

    public function test_the_profile_page_is_found_by_username_or_naara_id_and_every_miss_is_the_same_404(): void
    {
        $s = $this->member(['n2n_profile_visibility' => 'public', 'username' => 'ada_lovelace']);
        $v = $this->member();
        $this->actingAs($v)->get('/u/ada_lovelace')->assertOk()->assertSee('ada_lovelace')->assertSee($s->naara_id);
        $this->get('/u/'.$s->naara_id)->assertOk();
        $this->get('/u/@ADA_LOVELACE')->assertOk();
        $missing = $this->get('/u/nobody_here');
        Block::create(['blocker_id' => $s->id, 'blocked_id' => $v->id, 'created_at' => now()]);
        $blocked = $this->get('/u/ada_lovelace');
        $missing->assertNotFound();
        $blocked->assertNotFound();
        $this->assertSame($missing->status(), $blocked->status());
    }

    public function test_a_naara_id_whose_check_character_is_a_symbol_still_opens_the_profile(): void
    {
        do {
            $id = NaaraId::generate();
        } while (! in_array(substr($id, -1), ['*', '~', '$', '='], true));
        $s = $this->member(['n2n_profile_visibility' => 'public', 'naara_id' => $id]);
        $this->actingAs($this->member())->get('/u/'.rawurlencode($id))->assertOk();
        $this->get('/u/'.$id)->assertOk();
    }

    public function test_the_profile_page_requires_login_and_n2n_on(): void
    {
        $s = $this->member(['n2n_profile_visibility' => 'public', 'username' => 'pub_user']);
        $this->get('/u/pub_user')->assertRedirect();
        \App\Models\Setting::setValue('n2n.enabled', false);
        $this->actingAs($this->member())->get('/u/pub_user')->assertOk()->assertSee('Naara chat is coming soon');
    }

    public function test_the_page_never_prints_email_or_phone_to_a_stranger(): void
    {
        $s = $this->member(['n2n_profile_visibility' => 'public', 'username' => 'leaky', 'n2n_profile_reveal' => ['email', 'phone']]);
        UserPhone::create(['user_id' => $s->id, 'e164' => '+2348099999999', 'country_code' => 'NG', 'verified_at' => now()]);
        $this->actingAs($this->member())->get('/u/leaky')->assertOk()->assertDontSee($s->email)->assertDontSee('2348099999999');
    }

    // ---- pictures ---------------------------------------------------------------------------------------------------------

    private function jpeg(int $w = 600, int $h = 400): UploadedFile
    {
        $im = imagecreatetruecolor($w, $h);
        imagefill($im, 0, 0, imagecolorallocate($im, 10, 110, 110));
        ob_start();
        imagejpeg($im, null, 90);
        $bytes = ob_get_clean();
        $path = tempnam(sys_get_temp_dir(), 'pp').'.jpg';
        file_put_contents($path, $bytes);

        return new UploadedFile($path, 'me.jpg', 'image/jpeg', null, true);
    }

    public function test_two_pictures_are_stored_privately_re_encoded_small_and_served_only_through_the_visibility_check(): void
    {
        Storage::fake('local');
        config()->set('filesystems.disks.private', config('filesystems.disks.local'));
        $s = $this->member(['n2n_profile_visibility' => 'public']);
        $svc = app(ProfileService::class);
        $p1 = $svc->setPicture($s, 1, $this->jpeg());
        $svc->setPicture($s, 2, $this->jpeg());
        $this->assertLessThan(150_000, $p1->bytes);
        $this->assertSame(2, ProfilePicture::where('user_id', $s->id)->count());
        $this->expectExceptionMessage('two pictures');
        try {
            $svc->setPicture($s, 3, $this->jpeg());
        } finally {
            $v = $this->member();
            $this->actingAs($v)->get(route('n2n.profile.picture', ['userId' => $s->id, 'slot' => 1]))->assertOk()->assertHeader('Content-Type', 'image/jpeg');
            $s->forceFill(['n2n_profile_visibility' => 'private', 'n2n_profile_reveal' => []])->save();
            $this->get(route('n2n.profile.picture', ['userId' => $s->id, 'slot' => 1]))->assertNotFound();
            $this->get(route('n2n.profile.picture', ['userId' => 999999, 'slot' => 1]))->assertNotFound();
        }
    }

    public function test_a_non_image_renamed_to_jpg_is_refused(): void
    {
        Storage::fake('local');
        $path = tempnam(sys_get_temp_dir(), 'pp').'.jpg';
        file_put_contents($path, "MZ\x90\x00this is an executable, not a picture");
        $this->expectExceptionMessage('JPEG, PNG or WebP');
        app(ProfileService::class)->setPicture($this->member(), 1, new UploadedFile($path, 'me.jpg', 'image/jpeg', null, true));
    }

    public function test_erasure_deletes_the_picture_objects_and_hides_the_profile(): void
    {
        Storage::fake('local');
        config()->set('filesystems.disks.private', config('filesystems.disks.local'));
        $s = $this->member(['n2n_profile_visibility' => 'public']);
        $pic = app(ProfileService::class)->setPicture($s, 1, $this->jpeg());
        Storage::disk($pic->disk)->assertExists($pic->storage_key);
        N2NErasure::erase($s);
        Storage::disk($pic->disk)->assertMissing($pic->storage_key);
        $this->assertSame(0, ProfilePicture::where('user_id', $s->id)->count());
        $this->assertSame('private', $s->fresh()->n2n_profile_visibility);
    }

    public function test_settings_page_saves_visibility_and_filters_unknown_reveal_keys(): void
    {
        $u = $this->member();
        $this->actingAs($u);
        \Livewire\Livewire::test(\App\Livewire\N2N\ProfileSettings::class)
            ->set('visibility', 'private')->set('reveal', ['naara_id', 'password', 'phone'])->set('showYear', true)->set('bio', '<b>Hi</b> there')->call('save')
            ->assertSet('message', 'Profile saved.');
        $u = $u->fresh();
        $this->assertSame('private', $u->n2n_profile_visibility);
        $this->assertSame(['naara_id', 'phone'], $u->n2n_profile_reveal);
        $this->assertTrue($u->n2n_dob_show_year);
        $this->assertSame('Hi there', $u->bio);
        \Livewire\Livewire::test(\App\Livewire\N2N\ProfileSettings::class)->set('visibility', 'everyone')->call('save')->assertSet('error', 'Choose public, connections or private.');
    }
}
