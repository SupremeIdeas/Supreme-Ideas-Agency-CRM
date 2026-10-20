<?php

namespace Tests\Feature\N2N;

use App\Livewire\N2N\Chat;
use App\Services\Messaging\ConversationRouter;
use App\Services\Messaging\ConversationRouterPlan;
use Livewire\Livewire;

/** Brand-Growth Phase 9 (MASTER-ONLY): the new-chat sheet shows what the Conversation Router decided (channel, who pays, price) and never sends SMS from the sheet. */
class ChatRouteCardTest extends N2NTestCase
{
    private function routerReturning(ConversationRouterPlan $plan): void
    {
        $this->app->bind(ConversationRouter::class, fn () => new class($plan) extends ConversationRouter {
            public array $seen = [];

            public function __construct(private ConversationRouterPlan $plan)
            {
            }

            public function resolve(\App\Models\User $actor, string $target, string $intent, array $opts = []): ConversationRouterPlan
            {
                return $this->plan;
            }
        });
    }

    private function plan(string $channel, string $payer, ?float $price, bool $choice = false, array $blockers = [], ?string $e164 = null): ConversationRouterPlan
    {
        return new ConversationRouterPlan($channel, $payer, $price, 'message', $choice, $blockers, ['naara_id' => null, 'name' => null, 'e164' => $e164, 'on_naara' => $channel === 'n2n']);
    }

    public function test_a_naara_target_shows_the_chat_pill_paid_by_credit(): void
    {
        $this->routerReturning($this->plan('n2n', 'credit', 1.0));
        Livewire::actingAs($this->user(['email_verified_at' => now()]))->test(Chat::class)->call('openNew')->set('newTo', '@someone')
            ->assertSee('data-route-pill', false)->assertSee('Naara chat')->assertSee('Paid with NaaraCredit')->assertSee('credit per message');
    }

    public function test_a_number_not_on_naara_asks_for_the_sms_choice_and_never_starts_a_chat(): void
    {
        $this->routerReturning($this->plan('pstn_sms', 'wallet', 0.05, true, ['sms_opt_in_required'], '+2348000000001'));
        Livewire::actingAs($this->user(['email_verified_at' => now()]))->test(Chat::class)->call('openNew')->set('newTo', '+2348000000001')
            ->assertSee('Charged to your wallet')->assertSee('Send as SMS')->assertSee('0.0500')
            ->call('startChat')->assertSet('startedTo', null)->assertNotSet('error', null);
    }

    public function test_a_missing_line_points_to_getting_one_and_keeps_the_wallet_message(): void
    {
        $this->routerReturning($this->plan('pstn_sms', 'wallet', null, false, ['no_line'], '+2348000000001'));
        Livewire::actingAs($this->user(['email_verified_at' => now()]))->test(Chat::class)->call('openNew')->set('newTo', '+2348000000001')->call('chooseSms', true)
            ->assertSee('NaaraLine number')->assertSee('Get a Line');
    }

    public function test_an_unknown_handle_shows_the_router_reason(): void
    {
        $this->routerReturning($this->plan('none', 'none', null, false, ['unknown_target']));
        Livewire::actingAs($this->user(['email_verified_at' => now()]))->test(Chat::class)->call('openNew')->set('newTo', '@nobody')->assertSee('We could not find who that is.');
    }
}
