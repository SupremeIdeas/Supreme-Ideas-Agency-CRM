<?php

namespace Tests\Feature\BusinessSuite;

use App\Livewire\BusinessRecordings;
use App\Models\AuditLog;
use App\Models\CallRecording;
use App\Models\CallRecordingSetting;
use App\Models\Setting;
use App\Models\User;
use App\Models\VirtualNumber;
use App\Models\WalletGroup;
use App\Models\WalletGroupMember;
use App\Services\Account\AccountService;
use App\Services\Permissions\WorkspaceRoleService;
use App\Support\MediaStorage;
use App\Support\Permissions\BusinessRole;
use App\Support\UserDataExporter;
use Database\Seeders\BusinessRoleSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/** Business Suite Prompt 15: the recordings page (settings + list), who may hear a recording, and erasure/export reach. */
class BusinessRecordingsPageTest extends TestCase
{
    use \Tests\Concerns\EnablesBusinessSuite;
    use RefreshDatabase;

    private User $owner;

    private WalletGroup $group;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(BusinessRoleSeeder::class);
        Storage::fake(MediaStorage::privateDisk());
        Setting::setValue('business.module.recording', true, 'business');
        Setting::setValue('business.module.intelligence', true, 'business');

        $this->owner = User::factory()->create(['is_active' => true, 'email_verified_at' => now()]);
        $this->group = WalletGroup::create(['owner_user_id' => $this->owner->id, 'name' => 'Acme', 'kind' => WalletGroup::KIND_BUSINESS]);
        app(WorkspaceRoleService::class)->assignRole($this->group, $this->owner, BusinessRole::OWNER);
    }

    private function member(string $role): User
    {
        $u = User::factory()->create(['is_active' => true, 'email_verified_at' => now()]);
        WalletGroupMember::create(['wallet_group_id' => $this->group->id, 'user_id' => $u->id, 'role' => $role, 'status' => 'active', 'invited_at' => now(), 'accepted_at' => now()]);
        app(WorkspaceRoleService::class)->assignRole($this->group, $u, $role);
        \App\Support\Workspace::setActive($this->group);

        return $u;
    }

    private function recording(): CallRecording
    {
        Storage::disk(MediaStorage::privateDisk())->put('call-recordings/x.mp3', 'AUDIO');

        return CallRecording::create([
            'wallet_group_id' => $this->group->id, 'user_id' => $this->owner->id, 'call_reference' => 'CA1', 'provider_recording_sid' => 'RE'.uniqid(),
            'duration_seconds' => 75, 'storage_path' => 'call-recordings/x.mp3', 'status' => CallRecording::STORED, 'from_number' => '+2348011112222',
            'consent' => ['regime' => 'notice', 'action' => 'notice', 'announced_at' => now()->toIso8601String()], 'expires_at' => now()->addDays(30),
        ]);
    }

    public function test_only_the_owner_or_an_admin_can_open_the_page(): void
    {
        $this->actingAs($this->owner)->get('/business/recordings')->assertOk()->assertSee('Call recordings');
        $this->actingAs($this->member(BusinessRole::ADMIN))->get('/business/recordings')->assertOk();

        $this->actingAs($this->member(BusinessRole::AGENT))->get('/business/recordings')->assertNotFound();
        $this->actingAs(User::factory()->create(['is_active' => true, 'email_verified_at' => now()]))->get('/business/recordings')->assertNotFound();
    }

    public function test_the_module_switch_off_shows_coming_soon(): void
    {
        Setting::setValue('business.module.recording', false, 'business');
        $this->actingAs($this->owner)->get('/business/recordings')->assertOk()->assertSee('Call recording is coming soon');
    }

    public function test_saving_turns_recording_on_per_number_and_is_audited(): void
    {
        $n = VirtualNumber::create(['user_id' => $this->owner->id, 'wallet_group_id' => $this->group->id, 'provider' => 'twilio', 'phone_number' => '+15005550006', 'sid' => 'PN1', 'status' => 'active']);

        Livewire::actingAs($this->owner)->test(BusinessRecordings::class)
            ->set('recording', true)->set('numbers.'.$n->id, true)->set('retention', 60)->set('sensitive', '+2348012345678')->call('save')
            ->assertHasNoErrors();

        $s = CallRecordingSetting::where('wallet_group_id', $this->group->id)->firstOrFail();
        $this->assertTrue($s->recording_enabled);
        $this->assertFalse($s->intelligence_enabled);   // separate opt-in
        $this->assertSame(60, $s->retention_days);
        $this->assertTrue($n->fresh()->recording_enabled);
        $this->assertTrue(AuditLog::where('action', 'business.recording_settings_changed')->exists());
    }

    public function test_intelligence_needs_recording_and_its_own_tick(): void
    {
        Livewire::actingAs($this->owner)->test(BusinessRecordings::class)->set('recording', false)->set('intelligence', true)->call('save');
        $this->assertFalse(CallRecordingSetting::firstOrFail()->intelligence_enabled);

        Livewire::actingAs($this->owner)->test(BusinessRecordings::class)->set('recording', true)->set('intelligence', true)->call('save');
        $this->assertTrue(CallRecordingSetting::firstOrFail()->intelligence_enabled);
    }

    public function test_retention_above_the_platform_ceiling_and_bad_numbers_are_refused(): void
    {
        Livewire::actingAs($this->owner)->test(BusinessRecordings::class)->set('recording', true)->set('retention', 9999)->call('save')->assertHasErrors('retention');
        Livewire::actingAs($this->owner)->test(BusinessRecordings::class)->set('recording', true)->set('sensitive', 'call me')->call('save')->assertHasErrors('sensitive');
    }

    public function test_the_switch_off_at_platform_level_cannot_be_bypassed_by_a_workspace(): void
    {
        Setting::setValue('business.module.intelligence', false, 'business');

        Livewire::actingAs($this->owner)->test(BusinessRecordings::class)->set('recording', true)->set('intelligence', true)->call('save');
        $this->assertFalse(CallRecordingSetting::firstOrFail()->intelligence_enabled);
    }

    public function test_audio_is_streamed_to_the_owner_only_and_every_play_is_audited(): void
    {
        $rec = $this->recording();
        $url = route('business.recordings.audio', $rec);

        $this->actingAs($this->owner)->get($url)->assertOk()->assertHeader('Content-Type', 'audio/mpeg');
        $this->assertTrue(AuditLog::where('action', 'business.recording_played')->where('model_id', $rec->id)->exists());

        $this->actingAs($this->member(BusinessRole::ADMIN))->get($url)->assertOk();
        $this->actingAs($this->member(BusinessRole::AGENT))->get($url)->assertForbidden();

        $stranger = User::factory()->create(['is_active' => true, 'email_verified_at' => now()]);
        $this->actingAs($stranger)->get($url)->assertForbidden();
    }

    public function test_a_platform_admin_cannot_hear_a_workspace_recording(): void
    {
        $rec = $this->recording();
        $sa = User::factory()->create(['is_active' => true, 'email_verified_at' => now()]);
        $sa->assignRole('super_admin');

        $this->actingAs($sa)->get(route('business.recordings.audio', $rec))->assertForbidden();
    }

    public function test_the_list_never_shows_the_provider_or_the_cost(): void
    {
        $this->recording();
        $html = Livewire::actingAs($this->owner)->test(BusinessRecordings::class)->html();

        $this->assertStringNotContainsString('twilio', strtolower($html));
        $this->assertStringNotContainsString('provider_recording_sid', $html);
        $this->assertStringNotContainsString('cost', strtolower($html));
    }

    public function test_deleting_a_recording_removes_the_file_and_its_text(): void
    {
        $rec = $this->recording();
        \App\Models\CallTranscript::create(['call_recording_id' => $rec->id, 'transcript_text' => 'hello']);

        Livewire::actingAs($this->owner)->test(BusinessRecordings::class)->call('delete', $rec->id);

        Storage::disk(MediaStorage::privateDisk())->assertMissing('call-recordings/x.mp3');
        $this->assertSame(0, \App\Models\CallTranscript::count());
        $this->assertSame(CallRecording::PURGED, $rec->fresh()->status);
    }

    public function test_another_workspace_cannot_delete_this_recording(): void
    {
        $rec = $this->recording();
        $other = User::factory()->create(['is_active' => true, 'email_verified_at' => now()]);
        WalletGroup::create(['owner_user_id' => $other->id, 'name' => 'Other', 'kind' => WalletGroup::KIND_BUSINESS]);

        Livewire::actingAs($other)->test(BusinessRecordings::class)->call('delete', $rec->id);
        $this->assertNull($rec->fresh()->purged_at);
    }

    public function test_erasing_the_owner_deletes_recordings_for_real_and_export_has_metadata_only(): void
    {
        $rec = $this->recording();
        $export = UserDataExporter::for($this->owner);
        $row = $export['business_call_recordings'][0];
        $this->assertSame(75, $row['duration_seconds']);
        $this->assertStringNotContainsString('+234801', json_encode($export['business_call_recordings']));

        app(AccountService::class)->erase($this->owner->fresh());
        Storage::disk(MediaStorage::privateDisk())->assertMissing('call-recordings/x.mp3');
        $this->assertSame(CallRecording::PURGED, $rec->fresh()->status);
    }
}
