<?php

namespace Tests\Feature\Business;

use App\Models\BusinessSoftphoneDevice;
use App\Models\Setting;
use App\Models\User;
use App\Models\WalletGroup;
use App\Models\WalletGroupMember;
use App\Services\Business\Softphone\SoftphoneAccess;
use App\Services\Permissions\WorkspaceRoleService;
use App\Support\Permissions\BusinessRole;
use Database\Seeders\BusinessRoleSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The native softphone's server half: pairing from the web view, one-purpose tokens, Twilio access tokens with a push credential, the device list, and the readiness check. */
class NativeSoftphoneTest extends TestCase
{
    use \Tests\Concerns\EnablesBusinessSuite;
    use RefreshDatabase;

    private const IOS = 'CR0123456789abcdef0123456789abcdef';

    private const ANDROID = 'CRfedcba9876543210fedcba9876543210';

    private User $member;

    private WalletGroup $ws;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(BusinessRoleSeeder::class);
        Setting::setValue('business.module.softphone', true, 'business');
        Setting::setValue('providers.twilio.enabled', true);
        config(['services.twilio.account_sid' => 'AC'.str_repeat('a', 32), 'services.twilio.auth_token' => 'tok', 'services.twilio.api_key_sid' => 'SK'.str_repeat('b', 32), 'services.twilio.api_key_secret' => 'keysecret',
            'services.twilio.twiml_app_sid' => 'AP'.str_repeat('c', 32), 'services.twilio.caller_id' => '+15550001111', 'services.twilio.push_credential_ios' => self::IOS, 'services.twilio.push_credential_android' => self::ANDROID]);
        $owner = User::factory()->create(['is_active' => true, 'email_verified_at' => now()]);
        $this->ws = WalletGroup::create(['owner_user_id' => $owner->id, 'name' => 'Acme', 'kind' => WalletGroup::KIND_BUSINESS]);
        $this->member = User::factory()->create(['is_active' => true, 'email_verified_at' => now()]);
        app(WorkspaceRoleService::class)->assignRole($this->ws, $this->member, BusinessRole::AGENT);
        WalletGroupMember::create(['wallet_group_id' => $this->ws->id, 'user_id' => $this->member->id, 'role' => 'agent', 'status' => WalletGroupMember::STATUS_ACTIVE, 'invited_at' => now(), 'joined_at' => now()]);
    }

    private function pair(?User $u = null, string $platform = 'ios', string $device = 'device-abc-12345')
    {
        return $this->actingAs($u ?? $this->member)->postJson(route('softphone.pair'), ['device_id' => $device, 'platform' => $platform, 'app_version' => '1.0.0', 'os_version' => '17.4']);
    }

    private function claims(string $jwt): array
    {
        return json_decode((string) base64_decode(strtr(explode('.', $jwt)[1], '-_', '+/')), true);
    }

    private function bearer(string $token): self
    {
        $this->app['auth']->forgetGuards();

        return $this->withHeader('Authorization', 'Bearer '.$token);
    }

    public function test_a_workspace_member_pairs_a_phone_and_gets_a_one_purpose_token(): void
    {
        $r = $this->pair()->assertOk()->assertJsonStructure(['token', 'expires_in_days', 'push_ready']);
        $this->assertTrue($r->json('push_ready'));
        $d = BusinessSoftphoneDevice::firstOrFail();
        $this->assertSame($this->member->id, (int) $d->user_id);
        $this->assertSame('ios', $d->platform);
        $this->assertSame('1.0.0', $d->app_version);
        $tok = $this->member->tokens()->firstOrFail();
        $this->assertSame(['softphone'], $tok->abilities, 'the token can do one thing only');
        $this->assertNotNull($tok->expires_at);
        $this->assertSame($tok->id, (int) $d->token_id);
        $this->assertStringNotContainsString('keysecret', json_encode($r->json()), 'no secret in the response');
    }

    public function test_the_module_off_a_non_member_and_voice_off_are_refused_plainly(): void
    {
        $stranger = User::factory()->create(['is_active' => true, 'email_verified_at' => now()]);
        $this->pair($stranger)->assertForbidden();
        $this->assertSame(0, BusinessSoftphoneDevice::count());

        Setting::setValue('providers.twilio.enabled', false);
        config(['services.twilio.account_sid' => '', 'services.twilio.auth_token' => '']);
        $this->pair()->assertForbidden();

        Setting::setValue('business.module.softphone', false, 'business');
        $this->pair()->assertNotFound();
        $this->assertSame(0, BusinessSoftphoneDevice::count());
    }

    public function test_a_removed_member_can_no_longer_pair_or_get_tokens(): void
    {
        $this->pair()->assertOk();
        WalletGroupMember::where('user_id', $this->member->id)->update(['status' => WalletGroupMember::STATUS_REMOVED]);
        $this->pair()->assertForbidden();
    }

    public function test_the_paired_token_asks_for_a_twilio_access_token_with_the_right_identity_and_push_credential(): void
    {
        $plain = $this->pair(null, 'ios')->json('token');
        $r = $this->bearer($plain)->postJson('/api/softphone/token', ['device_id' => 'device-abc-12345', 'platform' => 'ios'])->assertOk();
        $this->assertSame('naara_user_'.$this->member->id, $r->json('identity'));
        $this->assertTrue($r->json('push'));
        $c = $this->claims($r->json('token'));
        $this->assertSame('naara_user_'.$this->member->id, $c['grants']['identity']);
        $this->assertTrue($c['grants']['voice']['incoming']['allow']);
        $this->assertSame(self::IOS, $c['grants']['voice']['push_credential_sid']);
        $this->assertSame('AP'.str_repeat('c', 32), $c['grants']['voice']['outgoing']['application_sid']);
        $this->assertLessThanOrEqual(3700, $c['exp'] - $c['iat']);
        $this->assertNotNull(BusinessSoftphoneDevice::firstOrFail()->last_seen_at);
    }

    public function test_android_gets_the_android_credential_and_a_phone_without_one_is_told_it_cannot_be_woken(): void
    {
        $plain = $this->pair(null, 'android', 'android-dev-12345')->json('token');
        $c = $this->claims($this->bearer($plain)->postJson('/api/softphone/token', ['device_id' => 'android-dev-12345', 'platform' => 'android'])->json('token'));
        $this->assertSame(self::ANDROID, $c['grants']['voice']['push_credential_sid']);

        config(['services.twilio.push_credential_android' => '']);
        $r = $this->bearer($plain)->postJson('/api/softphone/token', ['device_id' => 'android-dev-12345', 'platform' => 'android'])->assertOk();
        $this->assertFalse($r->json('push'), 'the app must be able to say that this phone will only ring while the app is open');
        $this->assertArrayNotHasKey('push_credential_sid', $this->claims($r->json('token'))['grants']['voice']);
        $this->assertFalse((bool) BusinessSoftphoneDevice::firstOrFail()->push_ready);
    }

    public function test_the_token_only_works_for_its_own_phone_and_for_nothing_else(): void
    {
        $plain = $this->pair(null, 'ios', 'device-one-12345')->json('token');
        $this->pair(null, 'ios', 'device-two-12345');
        $this->bearer($plain)->postJson('/api/softphone/token', ['device_id' => 'device-two-12345', 'platform' => 'ios'])->assertForbidden();
        $this->bearer($plain)->postJson('/api/softphone/token', ['device_id' => 'unknown-device-1', 'platform' => 'ios'])->assertNotFound();
        $this->bearer($plain)->postJson('/api/softphone/token', ['device_id' => 'device-one-12345', 'platform' => 'android'])->assertNotFound();
        // an ordinary full-access token is not a paired phone's token, even though '*' includes the ability
        $other = $this->member->createToken('whatever', ['*'])->plainTextToken;
        $this->bearer($other)->postJson('/api/softphone/token', ['device_id' => 'device-one-12345', 'platform' => 'ios'])->assertForbidden();
    }

    public function test_pairing_the_same_phone_again_replaces_its_token_and_unpairing_removes_everything(): void
    {
        $first = $this->pair()->json('token');
        $second = $this->pair()->json('token');
        $this->assertNotSame($first, $second);
        $this->assertSame(1, $this->member->tokens()->count(), 'the old token is gone');
        $this->assertSame(1, BusinessSoftphoneDevice::count());
        $this->bearer($second)->postJson('/api/softphone/status', ['device_id' => 'device-abc-12345', 'platform' => 'ios'])->assertOk()->assertJson(['paired' => true, 'push_ready' => true]);

        $this->bearer($second)->postJson('/api/softphone/unpair', ['device_id' => 'device-abc-12345', 'platform' => 'ios'])->assertOk();
        $this->assertSame(0, BusinessSoftphoneDevice::count());
        $this->assertSame(0, $this->member->tokens()->count());
        $this->app['auth']->forgetGuards();
        $this->withHeader('Authorization', 'Bearer '.$second)->postJson('/api/softphone/status', ['device_id' => 'device-abc-12345', 'platform' => 'ios'])->assertUnauthorized();
    }

    public function test_input_is_validated_and_requests_are_throttled(): void
    {
        $this->actingAs($this->member)->postJson(route('softphone.pair'), ['device_id' => 'short', 'platform' => 'ios'])->assertStatus(422);
        $this->actingAs($this->member)->postJson(route('softphone.pair'), ['device_id' => 'device-abc-12345', 'platform' => 'windows'])->assertStatus(422);
        $this->actingAs($this->member)->postJson(route('softphone.pair'), ['device_id' => 'bad id with spaces!', 'platform' => 'ios'])->assertStatus(422);
        for ($i = 0; $i < 7; $i++) {   // the three refused requests above count too: ten attempts a minute in all
            $this->pair()->assertOk();
        }
        $this->pair()->assertStatus(429);
    }

    public function test_a_phone_that_stopped_asking_for_tokens_shows_as_stale(): void
    {
        $this->pair();
        $d = BusinessSoftphoneDevice::firstOrFail();
        $this->assertFalse($d->isStale());
        $d->forceFill(['last_seen_at' => now()->subDays(BusinessSoftphoneDevice::STALE_DAYS + 1)])->save();
        $this->assertTrue($d->fresh()->isStale());
    }

    public function test_calls_to_a_ring_group_seat_still_ring_the_same_identity_the_phone_registers(): void
    {
        $this->assertSame('naara_user_'.$this->member->id, \App\Http\Controllers\VoiceTokenController::identityFor($this->member->id));
        $plain = $this->pair()->json('token');
        $identity = $this->bearer($plain)->postJson('/api/softphone/token', ['device_id' => 'device-abc-12345', 'platform' => 'ios'])->json('identity');
        $this->assertSame(\App\Http\Controllers\VoiceTokenController::identityFor($this->member->id), $identity, 'ring groups dial <Client>naara_user_N</Client>: a paired phone answers to exactly that');
    }

    public function test_the_readiness_check_names_what_is_missing_and_fails_only_when_switched_on_without_it(): void
    {
        $check = new \App\Support\Verify\Checks\SoftphoneReadiness;
        $this->assertSame('pass', $check->run()->status);
        config(['services.twilio.push_credential_ios' => '']);
        $this->assertSame('fail', $check->run()->status, 'switched on but a phone could not be woken');
        $this->assertStringContainsString('iOS', $check->run()->detail);
        Setting::setValue('business.module.softphone', false, 'business');
        $this->assertSame('warn', $check->run()->status);
        $this->assertStringNotContainsString('keysecret', $check->run()->detail);
    }

    public function test_the_push_credential_is_only_accepted_in_twilios_real_shape(): void
    {
        config(['services.twilio.push_credential_ios' => 'not-a-credential']);
        $this->assertNull(SoftphoneAccess::pushCredentialFor('ios'));
        config(['services.twilio.push_credential_ios' => self::IOS]);
        $this->assertSame(self::IOS, SoftphoneAccess::pushCredentialFor('ios'));
    }
}
