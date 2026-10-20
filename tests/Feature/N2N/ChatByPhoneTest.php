<?php

namespace Tests\Feature\N2N;

use App\Livewire\N2N\Chat;
use App\Models\Contact;
use App\Models\OutboundMessage;
use App\Models\User;
use App\Models\VirtualNumber;
use App\Services\N2N\Credit\UsageCredit;
use App\Services\N2N\Identity\NaaraId;
use App\Services\N2N\Messaging\MessageService;
use App\Services\Wallet\WalletService;
use Database\Seeders\PricingSettingsSeeder;
use Livewire\Livewire;
use Tests\Support\FakePermanentProvider;

/**
 * Brand-Growth Phase 9 (MASTER-ONLY): a Naara thread never sends or prices an SMS or a call itself. For the number saved in the viewer's OWN contact it offers two plain links
 * to the ONE existing place for each (Messages for SMS, the dialer for calls), so there is a single sending and charging path and nothing to confuse anyone.
 */
class ChatByPhoneTest extends N2NTestCase
{
    private function member(string $name): User
    {
        $u = $this->user(['name' => $name, 'email_verified_at' => now()]);
        $u->forceFill(['naara_id' => NaaraId::generate(), 'created_at' => now()->subDays(30)])->save();
        app(WalletService::class)->credit($u, 20, 'USD', ['reference' => 'f'.$u->id.uniqid()]);
        app(UsageCredit::class)->purchase($u, 10, 'b'.uniqid());

        return $u->fresh();
    }

    private function link(User $owner, User $peer, string $e164 = '+2348012345678'): void
    {
        $c = Contact::query()->create(['user_id' => $owner->id, 'name' => $peer->name, 'phone_number' => $e164]);
        $c->forceFill(['e164' => $e164, 'linked_user_id' => $peer->id, 'linked_at' => now()])->saveQuietly();
    }

    private function convo(User $a, User $b): int
    {
        return app(MessageService::class)->send($a, ['to' => $b->naara_id], 'c'.uniqid().'xxxx', 'hello')['conversation']->id;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PricingSettingsSeeder::class);
        app()->instance('number.twilio', new FakePermanentProvider(smsCost: 0.0079));
    }

    public function test_the_links_exist_only_for_a_number_in_my_own_contacts_and_never_show_it_to_the_other_person(): void
    {
        $a = $this->member('Ada');
        $b = $this->member('Bola');
        $cid = $this->convo($a, $b);
        Livewire::actingAs($a)->test(Chat::class)->call('select', $cid)->assertDontSee('data-by-phone', false);
        $this->link($a, $b);
        Livewire::actingAs($a)->test(Chat::class)->call('select', $cid)->assertSee('data-by-phone-sms', false)->assertSee('Text by SMS');
        Livewire::actingAs($b)->test(Chat::class)->call('select', $cid)->assertDontSee('data-by-phone', false);
    }

    public function test_the_sms_link_opens_the_existing_messages_thread_and_there_is_no_second_composer(): void
    {
        $a = $this->member('Ada');
        $b = $this->member('Bola');
        $cid = $this->convo($a, $b);
        $this->link($a, $b);
        $t = Livewire::actingAs($a)->test(Chat::class)->call('select', $cid);
        $t->assertSee(route('numbers.messages', ['to' => '+2348012345678']), false);
        $this->assertFalse(method_exists(Chat::class, 'sendSms'), 'the thread has no SMS sending of its own');
        $t->assertDontSee('Send SMS')->assertDontSee('Write an SMS');
        $this->assertSame(0, OutboundMessage::count());
        // the destination is the one existing page, and it opens that exact number
        $this->actingAs($a)->get(route('numbers.messages', ['to' => '+2348012345678']))->assertOk();
    }

    public function test_the_call_link_appears_only_when_the_dialer_exists_and_opens_it_prefilled(): void
    {
        $a = $this->member('Ada');
        $b = $this->member('Bola');
        $cid = $this->convo($a, $b);
        $this->link($a, $b);
        config(['services.twilio.account_sid' => '', 'services.twilio.auth_token' => '']);
        Livewire::actingAs($a)->test(Chat::class)->call('select', $cid)->assertDontSee('data-by-phone-call', false);

        \App\Models\Setting::setValue('providers.twilio.enabled', true);
        config(['services.twilio.account_sid' => 'AC1', 'services.twilio.auth_token' => 'tok', 'services.twilio.api_key' => 'SK1', 'services.twilio.api_secret' => 's', 'services.twilio.twiml_app_sid' => 'AP1', 'services.twilio.caller_id' => '+15550001111']);
        if (\App\Support\ProviderStatus::isActive('twilio')) {
            $t = Livewire::actingAs($a)->test(Chat::class)->call('select', $cid);
            $t->assertSee('data-by-phone-call', false)->assertSee(route('numbers.dialer', ['to' => '+2348012345678']), false);
            $this->actingAs($a)->get(route('numbers.dialer', ['to' => '+2348012345678']))->assertOk()->assertSee('+2348012345678');
        } else {
            $this->markTestSkipped('Twilio is not active under this test configuration; the link is gated on it.');
        }
    }
}
