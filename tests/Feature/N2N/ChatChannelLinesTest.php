<?php

namespace Tests\Feature\N2N;

use App\Livewire\N2N\Chat;
use App\Models\Contact;
use App\Models\ConversationEvent;
use App\Models\User;
use App\Services\N2N\Credit\UsageCredit;
use App\Services\N2N\Identity\NaaraId;
use App\Services\N2N\Messaging\MessageService;
use App\Services\Wallet\WalletService;
use Livewire\Livewire;

/** Brand-Growth Phase 9 (MASTER-ONLY): SMS and calls with the same person appear inside their Naara thread, linked only through the viewer's own contacts; contacts say whether they are on Naara. */
class ChatChannelLinesTest extends N2NTestCase
{
    private function member(string $name): User
    {
        $u = $this->user(['name' => $name, 'email_verified_at' => now()]);
        $u->forceFill(['naara_id' => NaaraId::generate(), 'created_at' => now()->subDays(30)])->save();
        app(WalletService::class)->credit($u, 10, 'USD', ['reference' => 'f'.$u->id.uniqid()]);
        app(UsageCredit::class)->purchase($u, 10, 'b'.uniqid());

        return $u->fresh();
    }

    /** The observer clears the link on create, so link the way ContactLinker does: quietly, after the row exists. */
    private function linked(User $owner, User $peer): void
    {
        $c = Contact::query()->create(['user_id' => $owner->id, 'name' => $peer->name, 'phone_number' => '+2348012345678']);
        $c->forceFill(['e164' => '+2348012345678', 'linked_user_id' => $peer->id, 'linked_at' => now()])->saveQuietly();
    }

    private function conversation(User $a, User $b): int
    {
        return app(MessageService::class)->send($a, ['to' => $b->naara_id], 'c'.uniqid().'xxxx', 'hello')['conversation']->id;
    }

    private function event(User $u, string $peer, string $channel, string $dir, string $summary, int $minsAgo): void
    {
        ConversationEvent::create(['user_id' => $u->id, 'peer_key' => $peer, 'channel' => $channel, 'kind' => $channel === 'call' ? 'call' : 'message', 'direction' => $dir, 'status' => 'sent', 'summary' => $summary,
            'source_type' => $channel === 'call' ? 'voice_call' : 'outbound_sms', 'source_id' => random_int(1, 9999999), 'occurred_at' => now()->addMinutes(-$minsAgo)]);
    }

    public function test_sms_and_calls_with_a_linked_contact_show_in_the_naara_thread(): void
    {
        $a = $this->member('Ada');
        $b = $this->member('Bola');
        $cid = $this->conversation($a, $b);
        $this->linked($a, $b);
        $this->event($a, '+2348012345678', 'sms', 'out', 'see you at six', 0);
        $this->event($a, '+2348012345678', 'call', 'out', 'Call (2 min)', 0);
        $this->event($a, '+2348099999999', 'sms', 'out', 'someone else entirely', 0);   // a different person: never shown here

        Livewire::actingAs($a)->test(Chat::class)->call('select', $cid)
            ->assertSee('data-channel-line="sms"', false)->assertSee('see you at six')->assertSee('data-channel-line="call"', false)->assertSee('You called')
            ->assertDontSee('someone else entirely');
    }

    public function test_without_a_linked_contact_no_channel_lines_appear_and_no_number_is_guessed(): void
    {
        $a = $this->member('Ada');
        $b = $this->member('Bola');
        $cid = $this->conversation($a, $b);
        $this->event($a, '+2348012345678', 'sms', 'out', 'orphan sms', 0);

        Livewire::actingAs($a)->test(Chat::class)->call('select', $cid)->assertDontSee('orphan sms')->assertDontSee('data-channel-line', false);
    }

    public function test_the_other_person_never_sees_my_sms_lines(): void
    {
        $a = $this->member('Ada');
        $b = $this->member('Bola');
        $cid = $this->conversation($a, $b);
        $this->linked($a, $b);
        $this->event($a, '+2348012345678', 'sms', 'out', 'private to ada', 0);

        Livewire::actingAs($b)->test(Chat::class)->call('select', $cid)->assertDontSee('private to ada');
    }

    public function test_contacts_say_whether_they_are_on_naara(): void
    {
        $a = $this->member('Ada');
        $b = $this->member('Bola');
        $this->linked($a, $b);
        $c = Contact::query()->create(['user_id' => $a->id, 'name' => 'Chidi', 'phone_number' => '+2348055555555']);
        $c->forceFill(['e164' => '+2348055555555'])->saveQuietly();

        Livewire::actingAs($a)->test(\App\Livewire\Messages::class)->set('tab', 'contacts')
            ->assertSee('data-contact-label="naara"', false)->assertSee('On Naara')->assertSee('data-contact-label="not-naara"', false)->assertSee('Not on Naara');
    }
}
