<?php

/**
 * AI gateway settings (NaaraBrain Sprint 0, MASTER-ONLY). Every Claude call in the platform goes through App\Services\AI\ClaudeGateway, which reads this file.
 * Model IDs are configuration, never constants. The price table is what the admin cost view multiplies tokens by: the figures are third-party list prices and
 * UNVERIFIED until checked against the real Anthropic invoice; override any of them in Admin > AI costs (stored as settings `ai.price.<family>.<field>`).
 */
return [
    // Model classes the router will use (Sprint 1+). "standard" is the platform's existing model (services.anthropic.model).
    'models' => [
        'fast' => env('AI_MODEL_FAST', 'claude-haiku-4-5-20251001'),
        'standard' => env('AI_MODEL_STANDARD', env('ANTHROPIC_MODEL', 'claude-sonnet-5-5')),
        'top' => env('AI_MODEL_TOP', 'claude-opus-5-5'),
    ],

    // USD per 1,000,000 tokens, matched by model-id prefix. cache_read / cache_write are the prompt-cache read and 5-minute write rates.
    'prices' => [
        'claude-haiku' => ['in' => 1.00, 'out' => 5.00, 'cache_read' => 0.10, 'cache_write' => 1.25],
        'claude-sonnet' => ['in' => 2.00, 'out' => 10.00, 'cache_read' => 0.20, 'cache_write' => 2.50],
        'claude-opus' => ['in' => 4.00, 'out' => 20.00, 'cache_read' => 0.40, 'cache_write' => 5.00],
        'claude-fable' => ['in' => 4.00, 'out' => 20.00, 'cache_read' => 0.40, 'cache_write' => 5.00],
    ],

    'timeout' => (int) env('AI_TIMEOUT', 120),
    'retries' => (int) env('AI_RETRIES', 2),          // extra attempts on 429 / 5xx / 529 / connection errors
    'backoff_ms' => (int) env('AI_BACKOFF_MS', 700),   // doubled each retry
    'cache' => (bool) env('AI_PROMPT_CACHE', true),    // cache_control on the system prompt and the tool list
    'retention_days' => (int) env('AI_USAGE_RETENTION_DAYS', 180),
];
