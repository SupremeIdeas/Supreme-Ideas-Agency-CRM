<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** `?ref=CODE` is remembered and attributes the new account (it used to be ignored everywhere). */
class ReferralCaptureTest extends TestCase
{
    use RefreshDatabase;

    private function register(array $cookies = []): User
    {
        $this->withCookies($cookies)->post('/register', ['name' => 'New Person', 'email' => 'new'.uniqid().'@example.com', 'password' => 'Str0ng-Passw0rd!x', 'password_confirmation' => 'Str0ng-Passw0rd!x'])->assertRedirect();

        return User::query()->latest('id')->firstOrFail();
    }

    public function test_a_referral_link_sets_a_cookie_and_the_signup_is_attributed(): void
    {
        $referrer = User::factory()->create(['referral_code' => 'ABCD1234', 'is_active' => true]);
        $res = $this->get('/?ref=abcd1234');
        $res->assertCookie('naara_ref');

        $new = $this->register(['naara_ref' => 'ABCD1234']);
        $this->assertSame($referrer->id, $new->referred_by);
    }

    public function test_junk_codes_unknown_codes_and_inactive_referrers_are_ignored(): void
    {
        User::factory()->create(['referral_code' => 'GONE1234', 'is_active' => false]);
        $this->get('/?ref=<script>')->assertCookieMissing('naara_ref');
        $this->get('/?ref=ab')->assertCookieMissing('naara_ref');
        $this->assertNull($this->register(['naara_ref' => 'NOSUCH99'])->referred_by);
        $this->assertNull($this->register(['naara_ref' => 'GONE1234'])->referred_by);
        $this->assertNull($this->register()->referred_by, 'no cookie, no attribution');
    }
}
