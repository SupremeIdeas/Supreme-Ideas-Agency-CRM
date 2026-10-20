<?php

namespace Tests\Feature;

use App\Livewire\Admin\ProviderLogins;
use App\Models\ProviderLogin;
use App\Models\ProviderLoginEmail;
use App\Models\User;
use App\Support\MasterOnlyModules;
use Database\Seeders\ProviderLoginSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/** Provider login manager (master only): a map of where and how the owner signs in to each supplier. It never holds a password. */
class ProviderLoginsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        Role::findOrCreate('admin');
        $u = User::factory()->create(['is_active' => true]);
        $u->assignRole('admin');

        return $u;
    }

    public function test_the_seed_gives_the_owners_defaults_and_the_three_email_hints(): void
    {
        $this->seed(ProviderLoginSeeder::class);

        $this->assertEqualsCanonicalizing(['support@naara.app', 'supremeideasz@gmail.com', 'naaraconnect@gmail.com'], ProviderLoginEmail::pluck('email')->all());

        $esimgo = ProviderLogin::where('provider_key', 'esimgo')->firstOrFail();
        $this->assertSame('google', $esimgo->login_method);
        $this->assertSame('supremeideasz@gmail.com', $esimgo->email_hint);
        $this->assertNotNull($esimgo->confirmed_at, 'the owner named this one');

        $getatext = ProviderLogin::where('provider_key', 'getatext')->firstOrFail();
        $this->assertSame('email_password', $getatext->login_method);

        // A provider with no API keys saved starts honestly unconfirmed, with no invented email.
        $other = ProviderLogin::where('provider_key', 'tillo')->firstOrFail();
        $this->assertSame('unconfirmed', $other->login_method);
        $this->assertNull($other->email_hint);
        $this->assertNull($other->confirmed_at);

        $this->seed(ProviderLoginSeeder::class);   // idempotent
        $this->assertSame(1, ProviderLogin::where('provider_key', 'esimgo')->count());
    }

    public function test_a_provider_with_saved_api_keys_starts_with_the_default_email_flagged_to_confirm(): void
    {
        config(['services.zendit.api_key' => 'k']);
        $this->seed(ProviderLoginSeeder::class);

        $z = ProviderLogin::where('provider_key', 'zendit')->firstOrFail();
        $this->assertSame('email_password', $z->login_method);
        $this->assertSame('supremeideasz@gmail.com', $z->email_hint);
        $this->assertNull($z->confirmed_at, 'a default the owner has not confirmed yet');
    }

    public function test_no_column_can_hold_a_password_and_the_class_is_master_only(): void
    {
        $cols = Schema::getColumnListing('provider_logins');
        foreach ($cols as $c) {
            $this->assertStringNotContainsStringIgnoringCase('pass', $c);
            $this->assertStringNotContainsStringIgnoringCase('secret', $c);
        }
        $this->assertTrue(MasterOnlyModules::matches('app/Livewire/Admin/ProviderLogins.php'));
        $this->assertTrue(MasterOnlyModules::matches('database/migrations/2027_01_27_110000_create_provider_logins_tables.php'));
        $this->assertTrue(MasterOnlyModules::matches('resources/views/livewire/admin/provider-logins.blade.php'));
        $this->assertTrue(MasterOnlyModules::matches('database/seeders/ProviderLoginSeeder.php'));
    }

    public function test_providers_with_api_keys_are_listed_first(): void
    {
        config(['services.paystack.secret_key' => 'k']);
        $this->seed(ProviderLoginSeeder::class);

        $first = Livewire::actingAs($this->admin())->test(ProviderLogins::class)->viewData('rows')->first();
        $this->assertSame('paystack', $first['row']->provider_key);
        $this->assertTrue($first['keys']);
    }

    public function test_the_admin_edits_a_provider_and_it_becomes_confirmed(): void
    {
        $this->seed(ProviderLoginSeeder::class);
        $row = ProviderLogin::where('provider_key', 'twilio')->firstOrFail();

        Livewire::actingAs($this->admin())->test(ProviderLogins::class)
            ->call('edit', $row->id)
            ->set('login_method', 'username_password')
            ->set('username_hint', 'naara-twilio')
            ->set('email_hint', 'naaraconnect@gmail.com')
            ->set('notes', '2-step code goes to the owner phone')
            ->call('save')
            ->assertHasNoErrors();

        $row->refresh();
        $this->assertSame('username_password', $row->login_method);
        $this->assertSame('naara-twilio', $row->username_hint);
        $this->assertSame('naaraconnect@gmail.com', $row->email_hint);
        $this->assertNotNull($row->confirmed_at);
    }

    public function test_an_email_outside_the_dropdown_and_a_password_in_the_note_are_refused(): void
    {
        $this->seed(ProviderLoginSeeder::class);
        $row = ProviderLogin::where('provider_key', 'twilio')->firstOrFail();

        Livewire::actingAs($this->admin())->test(ProviderLogins::class)
            ->call('edit', $row->id)
            ->set('email_hint', 'someone@else.com')
            ->call('save')->assertHasErrors(['email_hint']);

        Livewire::actingAs($this->admin())->test(ProviderLogins::class)
            ->call('edit', $row->id)
            ->set('notes', 'password: hunter2')
            ->call('save')->assertHasErrors(['notes']);

        Livewire::actingAs($this->admin())->test(ProviderLogins::class)
            ->call('edit', $row->id)
            ->set('login_url', 'http://insecure.example')
            ->call('save')->assertHasErrors(['login_url']);

        $this->assertNull($row->fresh()->confirmed_at);
    }

    public function test_emails_and_custom_providers_can_be_added_and_removed(): void
    {
        $this->seed(ProviderLoginSeeder::class);
        $c = Livewire::actingAs($this->admin())->test(ProviderLogins::class)
            ->set('newEmail', 'ops@naara.app')->set('newEmailLabel', 'Ops')->call('addEmail')->assertHasNoErrors();
        $this->assertTrue(ProviderLoginEmail::where('email', 'ops@naara.app')->exists());

        $c->set('newEmail', 'ops@naara.app')->call('addEmail')->assertHasErrors(['newEmail']);

        $c->set('customName', 'Acme Telecom')->set('customCategory', 'numbers')->call('addCustom')->assertHasNoErrors();
        $custom = ProviderLogin::where('is_custom', true)->firstOrFail();
        $this->assertSame('Acme Telecom', $custom->name);

        $c->call('deleteCustom', $custom->id);
        $this->assertSame(0, ProviderLogin::where('is_custom', true)->count());

        // A seeded provider can never be deleted through the custom-delete action.
        $seeded = ProviderLogin::where('provider_key', 'esimgo')->firstOrFail();
        $c->call('deleteCustom', $seeded->id);
        $this->assertTrue(ProviderLogin::where('provider_key', 'esimgo')->exists());
    }

    public function test_only_admins_reach_the_page(): void
    {
        $this->seed(ProviderLoginSeeder::class);
        $this->actingAs(User::factory()->create(['is_active' => true]))->get('/'.config('admin.path', 'adminmaster').'/provider-logins')->assertStatus(404);

        $admin = $this->admin();
        $this->actingAs($admin)->get(route('admin.provider-logins'))->assertOk()->assertSee('Provider logins')->assertSee('eSIM Go')->assertDontSee('type="password"', false);
    }

    public function test_a_fresh_install_seed_fills_the_provider_login_manager(): void
    {
        $this->seed(\Database\Seeders\DatabaseSeeder::class);

        $this->assertGreaterThan(30, ProviderLogin::count());
        $this->assertSame(3, ProviderLoginEmail::count());
    }
}
