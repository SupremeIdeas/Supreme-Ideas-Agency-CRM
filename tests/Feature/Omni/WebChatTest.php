<?php

namespace Tests\Feature\Omni;

use App\Models\ChannelConnection;
use App\Models\Omni\OmniConversation;
use App\Models\Omni\OmniMessage;
use App\Models\User;
use App\Models\WalletGroup;
use App\Services\Omni\Outbox;
use App\Services\Omni\WebChatToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Web chat: signed visitor token, strict CORS, honeypot, length limits, a visitor sees only their own conversation, and the agent's reply reaches the poll. */
class WebChatTest extends TestCase
{
    use RefreshDatabase;

    private array $site = [];

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['a', 'b'] as $k) {
            $g = WalletGroup::create(['owner_user_id' => User::factory()->create()->id, 'name' => 'Shop '.$k, 'kind' => WalletGroup::KIND_BUSINESS]);
            $c = ChannelConnection::create(['wallet_group_id' => $g->id, 'channel' => 'webchat', 'external_id' => 'site-'.$k, 'status' => 'active', 'label' => 'Shop '.$k,
                'credentials' => ['allowed_origins' => ['https://shop-'.$k.'.example'], 'greeting' => 'Hello from '.$k, 'color' => '#A21CAF'], 'webhook_secret' => 'sec-'.$k.'-0123456789']);
            $c->forceFill(['public_id' => (string) Str::uuid()])->save();
            $this->site[$k] = $c;
        }
    }

    private function open(string $k, array $headers = [], ?string $token = null): array
    {
        $r = $this->postJson('/omni/webchat/'.$this->site[$k]->public_id.'/session', [], $headers + ($token ? ['Authorization' => 'Bearer '.$token] : []));
        $r->assertOk();

        return [$r->json('token'), $r];
    }

    private function say(string $k, string $token, string $text, array $extra = [], array $headers = [])
    {
        return $this->postJson('/omni/webchat/'.$this->site[$k]->public_id.'/messages', ['text' => $text, 'mid' => 'mid-'.Str::random(10)] + $extra, ['Authorization' => 'Bearer '.$token] + $headers);
    }

    private function poll(string $k, string $token, int $after = 0)
    {
        return $this->getJson('/omni/webchat/'.$this->site[$k]->public_id.'/messages?after='.$after, ['Authorization' => 'Bearer '.$token]);
    }

    public function test_a_session_returns_the_widget_settings_and_a_token_that_is_stable_for_the_same_visitor(): void
    {
        [$t1, $r] = $this->open('a');
        $r->assertJsonPath('greeting', 'Hello from a')->assertJsonPath('color', '#A21CAF');
        [$t2] = $this->open('a', [], $t1);
        $this->assertSame(WebChatToken::visitor($this->site['a'], $t1), WebChatToken::visitor($this->site['a'], $t2), 'a returning browser keeps its visitor id');
    }

    public function test_the_token_is_bound_to_its_own_site_and_cannot_be_forged_or_expired(): void
    {
        [$ta] = $this->open('a');
        $this->assertNotNull(WebChatToken::visitor($this->site['a'], $ta));
        $this->assertNull(WebChatToken::visitor($this->site['b'], $ta), 'site A token is useless on site B');
        $this->assertNull(WebChatToken::visitor($this->site['a'], $ta.'x'));
        $this->assertNull(WebChatToken::visitor($this->site['a'], 'garbage'));
        $this->say('b', $ta, 'hi')->assertUnauthorized();
        $this->travel(WebChatToken::TTL_DAYS + 1)->days();
        $this->assertNull(WebChatToken::visitor($this->site['a'], $ta), 'tokens expire');
    }

    public function test_cors_is_granted_only_to_listed_origins(): void
    {
        [$t, $ok] = $this->open('a', ['Origin' => 'https://shop-a.example']);
        $this->assertSame('https://shop-a.example', $ok->headers->get('Access-Control-Allow-Origin'));
        $this->postJson('/omni/webchat/'.$this->site['a']->public_id.'/session', [], ['Origin' => 'https://evil.example'])->assertForbidden();
        $this->say('a', $t, 'hi', [], ['Origin' => 'https://evil.example'])->assertForbidden();
        $pre = $this->call('OPTIONS', '/omni/webchat/'.$this->site['a']->public_id.'/messages', [], [], [], ['HTTP_ORIGIN' => 'https://shop-a.example']);
        $pre->assertNoContent();
        $this->assertSame('https://shop-a.example', $pre->headers->get('Access-Control-Allow-Origin'));
        $bad = $this->call('OPTIONS', '/omni/webchat/'.$this->site['a']->public_id.'/messages', [], [], [], ['HTTP_ORIGIN' => 'https://evil.example']);
        $this->assertNull($bad->headers->get('Access-Control-Allow-Origin'));
    }

    public function test_a_message_reaches_the_inbox_at_once_and_the_agents_reply_reaches_the_poll(): void
    {
        Queue::fake();
        [$t] = $this->open('a');
        $this->say('a', $t, 'Is the blue one in stock?', ['name' => 'Ngozi', 'email' => 'ngozi@example.com'])->assertStatus(202);
        $ws = (int) $this->site['a']->wallet_group_id;
        $conv = OmniConversation::query()->forWorkspace($ws)->firstOrFail();
        $this->assertSame('Ngozi', $conv->contact->name);
        $this->assertSame('ngozi@example.com', $conv->contact->email);

        $m = app(Outbox::class)->queue($ws, $conv, 'Yes, two left.', [], null, 'r1');
        app(Outbox::class)->deliver($m->id);
        $rows = $this->poll('a', $t)->assertOk()->json('messages');
        $this->assertSame(['visitor', 'team'], array_column($rows, 'from'));
        $this->assertSame('Yes, two left.', $rows[1]['text']);
        $this->assertSame([], $this->poll('a', $t, $rows[1]['id'])->json('messages'), 'only newer messages');
    }

    public function test_the_same_message_id_twice_is_one_message_and_a_honeypot_or_long_text_is_refused(): void
    {
        Queue::fake();
        [$t] = $this->open('a');
        $payload = ['text' => 'Hello', 'mid' => 'mid-fixed-0001'];
        $this->postJson('/omni/webchat/'.$this->site['a']->public_id.'/messages', $payload, ['Authorization' => 'Bearer '.$t])->assertStatus(202);
        $this->postJson('/omni/webchat/'.$this->site['a']->public_id.'/messages', $payload, ['Authorization' => 'Bearer '.$t])->assertOk()->assertJson(['duplicate' => true]);
        $this->assertSame(1, OmniMessage::query()->count());
        $this->say('a', $t, 'spam', ['website' => 'http://bot.example'])->assertStatus(422);
        $this->say('a', $t, str_repeat('x', 2001))->assertStatus(422);
        $this->say('a', $t, '')->assertStatus(422);
    }

    public function test_a_visitor_can_never_read_another_visitors_or_another_sites_conversation(): void
    {
        Queue::fake();
        [$t1] = $this->open('a');
        [$t2] = $this->open('a');
        [$tb] = $this->open('b');
        $this->say('a', $t1, 'Private note one');
        $this->say('b', $tb, 'Other shop');
        $this->assertSame(['Private note one'], array_column($this->poll('a', $t1)->json('messages'), 'text'));
        $this->assertSame([], $this->poll('a', $t2)->json('messages'), 'a second visitor on the same site sees nothing of the first');
        $this->assertSame(['Other shop'], array_column($this->poll('b', $tb)->json('messages'), 'text'));
        $this->poll('a', $tb)->assertUnauthorized();
    }

    public function test_a_paused_or_unknown_site_is_a_plain_404(): void
    {
        $this->site['a']->forceFill(['status' => 'paused'])->save();
        $this->postJson('/omni/webchat/'.$this->site['a']->public_id.'/session')->assertNotFound();
        $this->postJson('/omni/webchat/'.Str::uuid().'/session')->assertNotFound();
    }
}
