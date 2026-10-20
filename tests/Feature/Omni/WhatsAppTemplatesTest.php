<?php

namespace Tests\Feature\Omni;

use App\Jobs\Omni\SendOmniMessage;
use App\Livewire\Omni\RespondInbox;
use App\Livewire\Omni\RespondTemplates;
use App\Models\ChannelConnection;
use App\Models\Omni\OmniContact;
use App\Models\Omni\OmniConversation;
use App\Models\Omni\OmniIdentity;
use App\Models\Omni\OmniMessage;
use App\Models\Setting;
use App\Models\User;
use App\Models\WalletGroup;
use App\Services\Inbox\ChannelSendException;
use App\Services\Omni\Dto\OutboundDraft;
use App\Services\Omni\Meta\WhatsAppTemplates;
use App\Services\Omni\Outbox;
use App\Services\Permissions\WorkspaceRoleService;
use App\Support\Permissions\BusinessRole;
use App\Support\Workspace;
use Database\Seeders\BusinessRoleSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/** WhatsApp templates: list with Meta's status and quality, create (rules checked first), delete, and re-open a closed conversation with an approved template. */
class WhatsAppTemplatesTest extends TestCase
{
    use \Tests\Concerns\EnablesBusinessSuite;
    use RefreshDatabase;

    private User $owner;

    private WalletGroup $ws;

    private ChannelConnection $conn;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $this->seed(RoleSeeder::class);
        $this->seed(BusinessRoleSeeder::class);
        Setting::setValue('business.module.inbox', true, 'business');
        Setting::setValue('business.module.omni', true, 'business');
        $this->owner = User::factory()->create(['is_active' => true, 'email_verified_at' => now()]);
        $this->ws = WalletGroup::create(['owner_user_id' => $this->owner->id, 'name' => 'Acme', 'kind' => WalletGroup::KIND_BUSINESS]);
        app(WorkspaceRoleService::class)->assignRole($this->ws, $this->owner, BusinessRole::OWNER);
        Workspace::setActive($this->ws);
        $this->conn = ChannelConnection::create(['wallet_group_id' => $this->ws->id, 'channel' => 'whatsapp', 'external_id' => '109876543210987', 'status' => 'active', 'label' => 'Acme WhatsApp',
            'credentials' => ['access_token' => 'EAAJ-token-0123456789abcdef', 'waba_id' => '104938271650321'], 'webhook_secret' => 'verify-0123456789']);
        $this->conn->forceFill(['public_id' => (string) Str::uuid()])->save();
    }

    private function listing(): array
    {
        return ['data' => [
            ['id' => '1', 'name' => 'order_update', 'language' => 'en', 'category' => 'UTILITY', 'status' => 'APPROVED', 'quality_score' => ['score' => 'GREEN'],
                'components' => [['type' => 'BODY', 'text' => 'Hi {{1}}, order {{2}} has shipped.'], ['type' => 'FOOTER', 'text' => 'Acme'], ['type' => 'BUTTONS', 'buttons' => [['type' => 'QUICK_REPLY', 'text' => 'Track it']]]]],
            ['id' => '2', 'name' => 'summer_sale', 'language' => 'en', 'category' => 'MARKETING', 'status' => 'APPROVED', 'components' => [['type' => 'BODY', 'text' => 'Big sale this weekend for you {{1}} only.']]],
            ['id' => '3', 'name' => 'old_one', 'language' => 'en', 'category' => 'UTILITY', 'status' => 'REJECTED', 'rejected_reason' => 'INVALID_FORMAT', 'components' => [['type' => 'BODY', 'text' => 'x']]],
        ]];
    }

    public function test_templates_are_listed_with_status_quality_and_only_approved_ones_are_sendable(): void
    {
        Http::fake(['graph.facebook.com/*/104938271650321/message_templates*' => Http::response($this->listing(), 200)]);
        $svc = app(WhatsAppTemplates::class);
        $all = $svc->all($this->conn);
        $this->assertCount(3, $all);
        $this->assertSame('GREEN', $all[0]['quality']);
        $this->assertSame(2, $all[0]['vars']);
        $this->assertSame('Track it', $all[0]['buttons'][0]);
        $this->assertSame('INVALID_FORMAT', $all[2]['reason']);
        $this->assertCount(2, $svc->approved($this->conn));
        Livewire::actingAs($this->owner)->test(RespondTemplates::class)->assertSee('order_update')->assertSee('Approved: you can send it')->assertSee('Rejected: edit and send again')->assertSee('Quality: high')->assertSee('INVALID_FORMAT');
    }

    public function test_creating_checks_meta_rules_first_and_sends_the_right_shape(): void
    {
        Http::fake(['graph.facebook.com/*/104938271650321/message_templates' => Http::response(['id' => '9', 'status' => 'PENDING'], 200)]);
        $svc = app(WhatsAppTemplates::class);
        $bad = [
            ['Order Update', 'UTILITY', 'en', 'Hello there you', [], 'lowercase'],
            ['ok_name', 'SPAM', 'en', 'Hello there you', [], 'Utility or Marketing'],
            ['ok_name', 'UTILITY', 'english', 'Hello there you', [], 'language'],
            ['ok_name', 'UTILITY', 'en', '', [], 'message text'],
            ['ok_name', 'UTILITY', 'en', '{{1}} your order is here', ['Ada'], 'start or very end'],
            ['ok_name', 'UTILITY', 'en', 'Hi {{2}} your order is here today', ['Ada', 'x'], 'in order'],
            ['ok_name', 'UTILITY', 'en', 'Hi {{1}} your order is here today', [], 'example value'],
        ];
        foreach ($bad as [$n, $c, $l, $b, $ex, $needle]) {
            try {
                $svc->create($this->conn, $n, $c, $l, $b, $ex);
                $this->fail('expected a refusal containing '.$needle);
            } catch (ChannelSendException $e) {
                $this->assertStringContainsStringIgnoringCase($needle, $e->getMessage());
            }
        }
        Http::assertNothingSent();

        $svc->create($this->conn, 'order_update', 'UTILITY', 'en', 'Hi {{1}}, order {{2}} has shipped today.', ['Ada', '#1001'], 'Acme', ['Track it']);
        Http::assertSent(function ($r) {
            $c = $r['components'];

            return str_contains($r->url(), '/104938271650321/message_templates') && $r['name'] === 'order_update' && $r['category'] === 'UTILITY' && $r['language'] === 'en'
                && $c[0]['type'] === 'BODY' && $c[0]['example']['body_text'][0] === ['Ada', '#1001'] && $c[1]['type'] === 'FOOTER' && $c[2]['buttons'][0]['type'] === 'QUICK_REPLY'
                && $r->hasHeader('Authorization', 'Bearer EAAJ-token-0123456789abcdef');
        });
    }

    public function test_meta_errors_come_back_in_plain_words_and_delete_calls_the_right_endpoint(): void
    {
        Http::fake([
            'graph.facebook.com/*/104938271650321/message_templates*' => Http::sequence()
                ->push(['error' => ['code' => 100, 'message' => 'x', 'error_user_msg' => 'Template name already exists.']], 400)
                ->push(['success' => true], 200),
        ]);
        $svc = app(WhatsAppTemplates::class);
        try {
            $svc->create($this->conn, 'order_update', 'UTILITY', 'en', 'Hello there you all today', []);
            $this->fail('expected a refusal');
        } catch (ChannelSendException $e) {
            $this->assertStringContainsString('Template name already exists', $e->getMessage());
        }
        $svc->delete($this->conn, 'order_update');
        Http::assertSent(fn ($r) => $r->method() === 'DELETE' && str_contains($r->url(), 'name=order_update'));
    }

    public function test_a_hand_connected_number_needs_its_waba_id_which_meta_must_confirm(): void
    {
        $c = ChannelConnection::create(['wallet_group_id' => $this->ws->id, 'channel' => 'whatsapp', 'external_id' => '555555555555555', 'status' => 'active', 'label' => 'Second', 'credentials' => ['access_token' => 'EAAJ-token-0123456789abcdef', 'app_secret' => 'x'], 'webhook_secret' => 'v2']);
        Http::fake(['graph.facebook.com/*/104938271650321*' => Http::response(['name' => 'Acme WABA', 'id' => '104938271650321'], 200)]);
        Livewire::actingAs($this->owner)->test(RespondTemplates::class)->call('choose', $c->id)->assertSee('data-needs-waba', false)
            ->set('wabaId', 'abc')->call('saveWaba')->assertSee('15 or 16 digits')
            ->set('wabaId', '104938271650321')->call('saveWaba')->assertSet('error', null)->assertSee('Account saved');
        $this->assertSame('104938271650321', $c->fresh()->secret('waba_id'));
        $this->assertSame('x', $c->fresh()->secret('app_secret'), 'the other credentials survive');
    }

    public function test_the_template_page_is_workspace_scoped(): void
    {
        $other = User::factory()->create(['is_active' => true, 'email_verified_at' => now()]);
        $g2 = WalletGroup::create(['owner_user_id' => $other->id, 'name' => 'Other', 'kind' => WalletGroup::KIND_BUSINESS]);
        app(WorkspaceRoleService::class)->assignRole($g2, $other, BusinessRole::OWNER);
        Workspace::setActive($g2);
        Http::fake(['graph.facebook.com/*' => Http::response($this->listing(), 200)]);
        Livewire::actingAs($other)->test(RespondTemplates::class)->assertDontSee('order_update')->assertSee('Connect a WhatsApp number first');
        Http::assertNothingSent();
    }

    private function closedConversation(bool $marketingOptIn = false): OmniConversation
    {
        $contact = OmniContact::create(['workspace_id' => $this->ws->id, 'name' => 'Ada', 'phone' => '+2348012345678', 'marketing_opt_in' => $marketingOptIn]);
        OmniIdentity::create(['workspace_id' => $this->ws->id, 'contact_id' => $contact->id, 'channel' => 'whatsapp', 'external_id' => '2348012345678']);

        return OmniConversation::create(['workspace_id' => $this->ws->id, 'contact_id' => $contact->id, 'connection_id' => $this->conn->id, 'channel' => 'whatsapp', 'status' => 'open',
            'last_message_at' => now()->subDays(3), 'last_inbound_at' => now()->subDays(3), 'last_preview' => 'old']);
    }

    public function test_a_closed_window_refuses_free_text_but_an_approved_template_goes_out_once(): void
    {
        Queue::fake();
        Http::fake(['graph.facebook.com/*/104938271650321/message_templates*' => Http::response($this->listing(), 200)]);
        $c = $this->closedConversation();
        $t = Livewire::actingAs($this->owner)->test(RespondInbox::class)->call('select', $c->id)
            ->assertSee('data-template-send', false)->assertSee('order_update (en)')->assertDontSee('old_one (en)')
            ->set('draft', 'free text')->call('send')->assertSee('24 hours');
        $this->assertSame(0, OmniMessage::count());

        $t->set('tplKey', 'order_update|en')->assertSee('Hi {{1}}, order {{2}} has shipped.')
            ->set('tplParams', ['Ada', '#1001'])->call('sendTemplate')->assertSet('error', null);
        $m = OmniMessage::firstOrFail();
        $this->assertSame('Hi Ada, order #1001 has shipped.', $m->body);
        $this->assertSame(['name' => 'order_update', 'language' => 'en', 'params' => ['Ada', '#1001']], $m->template);
        Queue::assertPushed(SendOmniMessage::class, 1);
    }

    public function test_a_marketing_template_needs_the_persons_opt_in_and_every_value_must_be_filled(): void
    {
        Queue::fake();
        Http::fake(['graph.facebook.com/*/104938271650321/message_templates*' => Http::response($this->listing(), 200)]);
        $c = $this->closedConversation(false);
        Livewire::actingAs($this->owner)->test(RespondInbox::class)->call('select', $c->id)
            ->set('tplKey', 'summer_sale|en')->set('tplParams', ['Ada'])->call('sendTemplate')->assertSee('not agreed to marketing');
        Livewire::actingAs($this->owner)->test(RespondInbox::class)->call('select', $c->id)
            ->set('tplKey', 'order_update|en')->set('tplParams', ['Ada', ''])->call('sendTemplate')->assertSee('Fill in every value');
        $this->assertSame(0, OmniMessage::count());
    }

    public function test_the_adapter_sends_a_template_payload_and_explains_a_missing_template(): void
    {
        Http::fake(['graph.facebook.com/*/109876543210987/messages' => Http::sequence()
            ->push(['messages' => [['id' => 'wamid.T1']]], 200)
            ->push(['error' => ['code' => 132001, 'message' => 'Template name does not exist']], 404)]);
        $a = app(\App\Services\Omni\AdapterRegistry::class)->for('whatsapp');
        $d = new OutboundDraft('2348012345678', 'Hi Ada', [], 'k1', ['name' => 'order_update', 'language' => 'en', 'params' => ['Ada', '#1001']]);
        $this->assertSame('wamid.T1', $a->send($this->conn, $d));
        Http::assertSent(fn ($r) => $r['type'] === 'template' && $r['template']['name'] === 'order_update' && $r['template']['language']['code'] === 'en'
            && $r['template']['components'][0]['parameters'][1]['text'] === '#1001');
        $this->expectException(ChannelSendException::class);
        $this->expectExceptionMessage('not approved');
        $a->send($this->conn, $d);
    }

    public function test_render_and_variable_count(): void
    {
        $this->assertSame(2, WhatsAppTemplates::variableCount('Hi {{1}} and {{2}} and {{1}}'));
        $this->assertSame(0, WhatsAppTemplates::variableCount('no variables'));
        $this->assertSame('Hi Ada, order #5.', WhatsAppTemplates::render('Hi {{1}}, order {{2}}.', ['Ada', '#5']));
    }
}
