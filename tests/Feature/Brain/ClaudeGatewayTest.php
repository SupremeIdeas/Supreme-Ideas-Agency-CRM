<?php

namespace Tests\Feature\Brain;

use App\Livewire\Admin\Brain\AiCosts;
use App\Models\AiUsageLog;
use App\Models\Setting;
use App\Models\User;
use App\Services\AI\AiUsage;
use App\Services\AI\AnthropicClient;
use App\Services\AI\ClaudeGateway;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;

/** NaaraBrain Sprint 0 (master-only): one Claude gateway, measured usage, caching, retries, admin cost view. */
class ClaudeGatewayTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.anthropic.api_key' => 'sk-test', 'services.anthropic.model' => 'claude-sonnet-5-5', 'ai.backoff_ms' => 0, 'ai.retries' => 2, 'ai.cache' => true]);
    }

    private function ok(array $usage = []): array
    {
        return [
            'id' => 'msg_1', 'model' => 'claude-sonnet-5-5', 'stop_reason' => 'end_turn',
            'content' => [['type' => 'text', 'text' => '{"ok":true}']],
            'usage' => $usage + ['input_tokens' => 1000, 'output_tokens' => 200, 'cache_creation_input_tokens' => 0, 'cache_read_input_tokens' => 0],
        ];
    }

    public function test_a_successful_call_logs_tokens_cost_model_feature_and_user(): void
    {
        Http::fake(['*/messages' => Http::response($this->ok(['input_tokens' => 1_000_000, 'output_tokens' => 100_000]), 200, ['request-id' => 'req_9'])]);
        $user = User::factory()->create();

        $body = AiUsage::as('unit_test', $user->id, fn () => app(ClaudeGateway::class)->messages(['max_tokens' => 50, 'messages' => [['role' => 'user', 'content' => 'hi']]]));

        $this->assertSame('{"ok":true}', ClaudeGateway::text($body));
        $row = AiUsageLog::first();
        $this->assertSame('unit_test', $row->feature);
        $this->assertSame($user->id, (int) $row->user_id);
        $this->assertSame('claude-sonnet-5-5', $row->model);
        $this->assertSame(1_000_000, (int) $row->input_tokens);
        $this->assertSame(100_000, (int) $row->output_tokens);
        $this->assertSame('ok', $row->status);
        $this->assertSame('req_9', $row->request_id);
        // 1M in x $2 + 0.1M out x $10 = $3.00 at the shipped sonnet prices.
        $this->assertEqualsWithDelta(3.0, (float) $row->cost_usd, 0.000001);
        $this->assertSame(1, (int) $row->attempts);
    }

    public function test_no_prompt_or_reply_text_is_stored(): void
    {
        Http::fake(['*/messages' => Http::response($this->ok())]);
        app(ClaudeGateway::class)->messages(['max_tokens' => 50, 'messages' => [['role' => 'user', 'content' => 'SECRET-PROMPT-TEXT']]], 'unit_test');

        $this->assertStringNotContainsString('SECRET-PROMPT-TEXT', json_encode(AiUsageLog::first()->getAttributes()));
        $this->assertStringNotContainsString('"ok":true', json_encode(AiUsageLog::first()->getAttributes()));
    }

    public function test_it_retries_a_529_with_backoff_then_succeeds_and_records_the_attempts(): void
    {
        Http::fake(['*/messages' => Http::sequence()->push(['error' => 'overloaded'], 529)->push(['error' => 'rate'], 429)->push($this->ok(), 200)]);

        $body = app(ClaudeGateway::class)->messages(['max_tokens' => 50, 'messages' => [['role' => 'user', 'content' => 'hi']]], 'unit_test');

        $this->assertSame('{"ok":true}', ClaudeGateway::text($body));
        Http::assertSentCount(3);
        $this->assertSame(3, (int) AiUsageLog::first()->attempts);
        $this->assertSame('ok', AiUsageLog::first()->status);
    }

    public function test_a_client_error_is_not_retried_and_is_logged_as_an_error(): void
    {
        Http::fake(['*/messages' => Http::response(['error' => 'bad'], 400)]);

        try {
            app(ClaudeGateway::class)->messages(['max_tokens' => 50, 'messages' => [['role' => 'user', 'content' => 'hi']]], 'unit_test');
            $this->fail('should throw');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('HTTP 400', $e->getMessage());
        }

        Http::assertSentCount(1);
        $row = AiUsageLog::first();
        $this->assertSame('error', $row->status);
        $this->assertSame(400, (int) $row->http_status);
        $this->assertSame(0, (int) $row->input_tokens);
    }

    public function test_it_gives_up_after_the_configured_retries(): void
    {
        config(['ai.retries' => 1]);
        Http::fake(['*/messages' => Http::response(['error' => 'down'], 503)]);

        $this->expectException(RuntimeException::class);
        try {
            app(ClaudeGateway::class)->messages(['max_tokens' => 50, 'messages' => [['role' => 'user', 'content' => 'hi']]], 'unit_test');
        } finally {
            Http::assertSentCount(2);
            $this->assertSame(2, (int) AiUsageLog::first()->attempts);
        }
    }

    public function test_the_system_prompt_and_last_tool_carry_cache_control(): void
    {
        Http::fake(['*/messages' => Http::response($this->ok())]);

        app(ClaudeGateway::class)->messages([
            'max_tokens' => 50, 'system' => 'You are helpful.', 'messages' => [['role' => 'user', 'content' => 'hi']],
            'tools' => [['name' => 'a', 'description' => 'a', 'input_schema' => ['type' => 'object']], ['name' => 'b', 'description' => 'b', 'input_schema' => ['type' => 'object']]],
        ], 'unit_test');

        Http::assertSent(function (Request $r) {
            $d = $r->data();

            return $r->hasHeader('x-api-key', 'sk-test')
                && $d['model'] === 'claude-sonnet-5-5'
                && $d['system'][0]['text'] === 'You are helpful.'
                && $d['system'][0]['cache_control'] === ['type' => 'ephemeral']
                && ! isset($d['tools'][0]['cache_control'])
                && $d['tools'][1]['cache_control'] === ['type' => 'ephemeral'];
        });
    }

    public function test_caching_can_be_switched_off(): void
    {
        config(['ai.cache' => false]);
        Http::fake(['*/messages' => Http::response($this->ok())]);

        app(ClaudeGateway::class)->messages(['max_tokens' => 50, 'system' => 'Plain.', 'messages' => [['role' => 'user', 'content' => 'hi']]], 'unit_test');

        Http::assertSent(fn (Request $r) => $r->data()['system'] === 'Plain.');
    }

    public function test_without_a_key_nothing_is_sent_and_nothing_is_logged(): void
    {
        config(['services.anthropic.api_key' => '']);
        Http::fake();

        $this->assertFalse(app(ClaudeGateway::class)->enabled());
        $this->assertFalse(app(AnthropicClient::class)->enabled());
        try {
            app(ClaudeGateway::class)->messages(['max_tokens' => 5, 'messages' => []], 'unit_test');
            $this->fail('should throw');
        } catch (RuntimeException) {
        }

        Http::assertNothingSent();
        $this->assertSame(0, AiUsageLog::count());
    }

    public function test_the_existing_anthropic_client_now_goes_through_the_gateway_and_infers_the_feature(): void
    {
        Http::fake(['*/messages' => Http::response($this->ok())]);

        $out = app(AnthropicClient::class)->complete('sys', [['role' => 'user', 'content' => 'hello']], 100);

        $this->assertSame('{"ok":true}', $out);
        $this->assertSame(1, AiUsageLog::count());
        $this->assertNotSame('', AiUsageLog::first()->feature);
    }

    public function test_cache_tokens_are_priced_at_their_own_rates_and_admin_prices_override(): void
    {
        $g = app(ClaudeGateway::class);
        // sonnet: in 2, out 10, cache_write 2.50, cache_read 0.20 per million.
        $this->assertEqualsWithDelta(0.00652, $g->cost('claude-sonnet-5-5', 1000, 200, 1000, 100), 0.0000001);
        $this->assertSame(0.0, $g->cost('some-unknown-model', 1000, 1000));

        Setting::setValue('ai.price.claude-sonnet.in', 4.0, 'ai');
        $this->assertEqualsWithDelta(4.0, $g->cost('claude-sonnet-5-5', 1_000_000, 0), 0.000001);
    }

    public function test_model_classes_are_configurable(): void
    {
        config(['ai.models.fast' => 'claude-haiku-4-5-20251001', 'ai.models.top' => 'claude-opus-5-5']);
        $g = app(ClaudeGateway::class);

        $this->assertSame('claude-sonnet-5-5', $g->model());
        $this->assertSame('claude-haiku-4-5-20251001', $g->model('fast'));
        $this->assertSame('claude-opus-5-5', $g->model('top'));
    }

    public function test_the_feature_is_inferred_from_the_calling_class_when_none_is_set(): void
    {
        Http::fake(['*/messages' => Http::response($this->ok())]);

        (new class
        {
            public function go(): void
            {
                app(ClaudeGateway::class)->messages(['max_tokens' => 5, 'messages' => [['role' => 'user', 'content' => 'x']]]);
            }
        })->go();

        $this->assertNotSame('unknown', AiUsageLog::first()->feature);
        $this->assertNotSame('', AiUsageLog::first()->feature);
    }

    public function test_a_logging_failure_never_breaks_the_call(): void
    {
        Http::fake(['*/messages' => Http::response($this->ok())]);
        \Illuminate\Support\Facades\Schema::drop('ai_usage_log');

        $body = app(ClaudeGateway::class)->messages(['max_tokens' => 5, 'messages' => [['role' => 'user', 'content' => 'x']]], 'unit_test');

        $this->assertSame('{"ok":true}', ClaudeGateway::text($body));
    }

    public function test_prune_command_deletes_only_rows_past_retention(): void
    {
        config(['ai.retention_days' => 30]);
        AiUsageLog::create(['feature' => 'old', 'model' => 'm', 'status' => 'ok', 'created_at' => now()->subDays(40)]);
        AiUsageLog::create(['feature' => 'new', 'model' => 'm', 'status' => 'ok', 'created_at' => now()->subDays(5)]);

        $this->artisan('ai:prune-usage')->assertSuccessful();

        $this->assertSame(['new'], AiUsageLog::pluck('feature')->all());
    }

    // ---------------------------------------------------------------- admin cost view

    private function admin(): User
    {
        $this->seed(RoleSeeder::class);
        $u = User::factory()->create();
        $u->assignRole('super_admin');

        return $u;
    }

    public function test_the_cost_view_is_admin_only(): void
    {
        $this->seed(RoleSeeder::class);
        $this->actingAs(User::factory()->create());

        Livewire::test(AiCosts::class)->assertForbidden();
    }

    public function test_the_cost_view_aggregates_measured_rows_per_feature(): void
    {
        $this->actingAs($this->admin());
        AiUsageLog::create(['feature' => 'pricing_architect', 'model' => 'claude-sonnet-5-5', 'input_tokens' => 1000, 'output_tokens' => 100, 'cost_usd' => 0.003, 'duration_ms' => 800, 'attempts' => 1, 'status' => 'ok']);
        AiUsageLog::create(['feature' => 'pricing_architect', 'model' => 'claude-sonnet-5-5', 'input_tokens' => 1000, 'output_tokens' => 100, 'cost_usd' => 0.003, 'duration_ms' => 1200, 'attempts' => 1, 'status' => 'error']);
        AiUsageLog::create(['feature' => 'naaracare', 'model' => 'claude-haiku-4-5-20251001', 'input_tokens' => 500, 'output_tokens' => 50, 'cost_usd' => 0.001, 'duration_ms' => 400, 'attempts' => 1, 'status' => 'ok']);

        Livewire::test(AiCosts::class)
            ->assertSee('pricing_architect')->assertSee('naaracare')
            ->assertViewHas('total', fn ($t) => $t['calls'] === 3 && $t['errors'] === 1 && abs($t['cost'] - 0.007) < 0.000001);
    }

    public function test_admins_can_edit_and_reset_the_price_table(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(AiCosts::class)
            ->set('prices.claude-sonnet.in', '7.5')
            ->call('savePrices')->assertHasNoErrors();
        $this->assertEqualsWithDelta(7.5, (float) Setting::getValue('ai.price.claude-sonnet.in'), 0.0001);
        $this->assertEqualsWithDelta(7.5, app(ClaudeGateway::class)->cost('claude-sonnet-5-5', 1_000_000, 0), 0.0001);

        Livewire::test(AiCosts::class)->call('resetPrices');
        $this->assertEqualsWithDelta(2.0, app(ClaudeGateway::class)->cost('claude-sonnet-5-5', 1_000_000, 0), 0.0001);
    }

    public function test_the_route_exists_and_is_blocked_for_non_admins(): void
    {
        $this->seed(RoleSeeder::class);
        $this->actingAs($this->admin())->get(route('admin.ai-costs'))->assertOk();
        $this->actingAs(User::factory()->create())->get(route('admin.ai-costs'))->assertStatus(404);
    }
}
