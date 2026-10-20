<?php

namespace Tests\Feature\Omni;

use App\Livewire\BusinessChannels;
use App\Models\ChannelConnection;
use App\Models\Setting;
use App\Models\User;
use App\Models\WalletGroup;
use App\Services\Permissions\WorkspaceRoleService;
use App\Support\Permissions\BusinessRole;
use App\Support\Workspace;
use Database\Seeders\BusinessRoleSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

/** Naara Respond connect flow: web chat creation (origin validation, snippet) and Telegram pointing at the omni webhook when the switch is on. */
class ConnectWebChatTest extends TestCase
{
    use \Tests\Concerns\EnablesBusinessSuite;
    use RefreshDatabase;

    private User $owner;

    private WalletGroup $ws;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(BusinessRoleSeeder::class);
        Setting::setValue('business.module.inbox', true, 'business');
        Setting::setValue('business.module.omni', true, 'business');
        $this->owner = User::factory()->create(['is_active' => true, 'email_verified_at' => now()]);
        $this->ws = WalletGroup::create(['owner_user_id' => $this->owner->id, 'name' => 'Acme', 'kind' => WalletGroup::KIND_BUSINESS]);
        app(WorkspaceRoleService::class)->assignRole($this->ws, $this->owner, BusinessRole::OWNER);
        Workspace::setActive($this->ws);
    }

    public function test_creating_web_chat_stores_origins_and_shows_the_snippet(): void
    {
        $t = Livewire::actingAs($this->owner)->test(BusinessChannels::class)
            ->set('wcOrigins', 'https://shop.example/, https://www.shop.example')->set('wcGreeting', 'Hello!')->call('connectWebChat')
            ->assertSet('error', null);
        $c = ChannelConnection::where('wallet_group_id', $this->ws->id)->where('channel', 'webchat')->firstOrFail();
        $this->assertSame(['https://shop.example', 'https://www.shop.example'], $c->credentials['allowed_origins']);
        $this->assertNotEmpty($c->public_id);
        $t->assertSee('data-site=&quot;'.$c->public_id.'&quot;', false);
    }

    public function test_bad_origins_are_refused_with_a_plain_message(): void
    {
        foreach (['', 'shop.example', 'https://shop.example/page', 'http://shop.example', 'javascript:alert(1)'] as $bad) {
            Livewire::actingAs($this->owner)->test(BusinessChannels::class)->set('wcOrigins', $bad)->call('connectWebChat')->assertNotSet('error', null);
        }
        $this->assertSame(0, ChannelConnection::where('channel', 'webchat')->count());
    }

    public function test_the_card_is_hidden_while_the_omni_switch_is_off(): void
    {
        Setting::setValue('business.module.omni', false, 'business');
        Livewire::actingAs($this->owner)->test(BusinessChannels::class)->assertDontSee('Create web chat');
    }

    public function test_telegram_registers_the_omni_webhook_when_respond_is_live(): void
    {
        Http::fake([
            '*getMe*' => Http::response(['ok' => true, 'result' => ['id' => 777, 'username' => 'acme_bot']]),
            '*setWebhook*' => Http::response(['ok' => true, 'result' => true]),
            '*' => Http::response(['ok' => true, 'result' => true]),
        ]);
        Livewire::actingAs($this->owner)->test(BusinessChannels::class)->set('telegramToken', '123:ABC')->call('connectTelegram')->assertSet('error', null);
        $c = ChannelConnection::where('channel', 'telegram')->firstOrFail();
        $this->assertNotEmpty($c->public_id);
        Http::assertSent(fn ($r) => str_contains($r->url(), 'setWebhook') && str_contains((string) ($r->data()['url'] ?? ''), '/webhooks/omni/telegram/'.$c->public_id));
    }
}
