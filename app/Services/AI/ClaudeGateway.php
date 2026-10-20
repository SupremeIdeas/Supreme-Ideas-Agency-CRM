<?php

namespace App\Services\AI;

use App\Models\AiUsageLog;
use App\Models\Setting;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * The ONE door to Claude (NaaraBrain Sprint 0, MASTER-ONLY). Every AI call in the platform (pricing, KYC cross-check, support agent, voice agent, blog, translation,
 * maintenance, Brain) goes through here, so every call gets the same: configured model and API version, a timeout, bounded retries with backoff on rate-limit and
 * server errors, prompt caching on the system prompt and tool list, and a measured row in ai_usage_log (tokens in/out, cache read/write, cost, latency). The key is
 * the admin-managed `services.anthropic.api_key`; it is sent only from here and never logged. A failure to log can never break the call.
 */
class ClaudeGateway
{
    private const RETRYABLE = [408, 409, 429, 500, 502, 503, 504, 529];

    public function enabled(): bool
    {
        return filled(config('services.anthropic.api_key'));
    }

    /** "standard" is the platform's long-standing model; "fast" and "top" are for routing (Sprint 1+). */
    public function model(string $class = 'standard'): string
    {
        return $class === 'standard'
            ? (string) config('services.anthropic.model', config('ai.models.standard'))
            : (string) config("ai.models.$class", config('services.anthropic.model'));
    }

    /**
     * One Messages API request. $payload takes the API's own keys (system, messages, tools, max_tokens, temperature, model). Returns the decoded response body.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     *
     * @throws RuntimeException when the key is missing or the call fails after its retries
     */
    public function messages(array $payload, ?string $feature = null, ?int $timeout = null): array
    {
        if (! $this->enabled()) {
            throw new RuntimeException('Anthropic API key is not configured.');
        }
        $feature = $feature ?? AiUsage::feature() ?? AiUsage::inferFeature();
        $payload['model'] = $payload['model'] ?? $this->model();
        $payload = $this->withCaching($payload);

        $base = rtrim((string) config('services.anthropic.base_url', 'https://api.anthropic.com/v1'), '/');
        $retries = max(0, (int) config('ai.retries', 2));
        $started = microtime(true);
        $attempt = 0;
        $status = null;
        $error = null;
        $body = null;
        $requestId = null;

        while (true) {
            $attempt++;
            try {
                $response = Http::withHeaders([
                    'x-api-key' => (string) config('services.anthropic.api_key'),
                    'anthropic-version' => (string) config('services.anthropic.version', '2023-06-01'),
                    'content-type' => 'application/json',
                ])->timeout($timeout ?? (int) config('ai.timeout', 120))->post($base.'/messages', $payload);
            } catch (ConnectionException $e) {
                $status = null;
                $error = 'connection: '.mb_substr($e->getMessage(), 0, 120);
                if ($attempt <= $retries) {
                    $this->backoff($attempt);

                    continue;
                }
                break;
            }

            $status = $response->status();
            $requestId = $response->header('request-id') ?: null;
            if ($response->successful()) {
                $body = (array) $response->json();
                $error = null;
                break;
            }
            $error = 'http '.$status;
            if (in_array($status, self::RETRYABLE, true) && $attempt <= $retries) {
                $this->backoff($attempt);

                continue;
            }
            break;
        }

        $this->log($feature, (string) $payload['model'], $body, $attempt, (int) round((microtime(true) - $started) * 1000), $error === null ? 'ok' : 'error', $status, $error, $requestId);

        if ($body === null) {
            throw new RuntimeException('Anthropic API error: '.($status !== null ? 'HTTP '.$status : $error));
        }

        return $body;
    }

    /** The concatenated text of a response. */
    public static function text(array $body): string
    {
        return collect($body['content'] ?? [])->where('type', 'text')->pluck('text')->implode('');
    }

    /** USD for one call's token counts at the configured (or admin-overridden) per-million prices. */
    public function cost(string $model, int $in, int $out, int $cacheWrite = 0, int $cacheRead = 0): float
    {
        $family = null;
        foreach (array_keys((array) config('ai.prices')) as $prefix) {
            if (str_starts_with($model, $prefix)) {
                $family = $prefix;
                break;
            }
        }
        if ($family === null) {
            return 0.0;
        }
        $price = fn (string $field): float => (float) Setting::getValue("ai.price.$family.$field", config("ai.prices.$family.$field"));

        return round(($in * $price('in') + $out * $price('out') + $cacheWrite * $price('cache_write') + $cacheRead * $price('cache_read')) / 1_000_000, 6);
    }

    /** Turn the system prompt into a cacheable block and mark the end of the tool list, so a repeated prefix is billed at the cache-read rate. Harmless below the provider's minimum size. */
    private function withCaching(array $payload): array
    {
        if (! config('ai.cache', true)) {
            return $payload;
        }
        if (isset($payload['system']) && is_string($payload['system']) && trim($payload['system']) !== '') {
            $payload['system'] = [['type' => 'text', 'text' => $payload['system'], 'cache_control' => ['type' => 'ephemeral']]];
        }
        if (! empty($payload['tools']) && is_array($payload['tools'])) {
            $last = array_key_last($payload['tools']);
            $payload['tools'][$last]['cache_control'] = ['type' => 'ephemeral'];
        }

        return $payload;
    }

    private function backoff(int $attempt): void
    {
        $ms = (int) config('ai.backoff_ms', 700) * (2 ** ($attempt - 1));
        if ($ms > 0) {
            usleep(min($ms, 8000) * 1000);
        }
    }

    private function log(string $feature, string $model, ?array $body, int $attempts, int $ms, string $status, ?int $http, ?string $error, ?string $requestId): void
    {
        try {
            $u = (array) ($body['usage'] ?? []);
            $in = (int) ($u['input_tokens'] ?? 0);
            $out = (int) ($u['output_tokens'] ?? 0);
            $cw = (int) ($u['cache_creation_input_tokens'] ?? 0);
            $cr = (int) ($u['cache_read_input_tokens'] ?? 0);
            AiUsageLog::create([
                'user_id' => AiUsage::userId(), 'feature' => mb_substr($feature, 0, 80), 'model' => mb_substr((string) ($body['model'] ?? $model), 0, 80),
                'input_tokens' => $in, 'output_tokens' => $out, 'cache_write_tokens' => $cw, 'cache_read_tokens' => $cr,
                'cost_usd' => $this->cost((string) ($body['model'] ?? $model), $in, $out, $cw, $cr), 'duration_ms' => $ms, 'attempts' => min($attempts, 255),
                'status' => $status, 'http_status' => $http, 'error' => $error, 'request_id' => $requestId,
            ]);
        } catch (Throwable) {
            // Measuring must never break the call it measures.
        }
    }
}
