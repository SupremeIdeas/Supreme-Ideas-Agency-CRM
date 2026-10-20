<?php

namespace App\Services\AI;

use Closure;
use Illuminate\Support\Str;

/**
 * Who and what a Claude call is for (MASTER-ONLY). The gateway reads this when it logs usage. Wrap a block in AiUsage::as(...) to name the feature and the person
 * the cost belongs to; anywhere nothing is set, the feature is inferred from the calling class (PricingArchitect -> pricing_architect) and the signed-in user is used.
 */
class AiUsage
{
    /** @var list<array{feature: ?string, user: ?int}> */
    private static array $stack = [];

    public static function as(?string $feature, ?int $userId, Closure $fn): mixed
    {
        self::$stack[] = ['feature' => $feature, 'user' => $userId];
        try {
            return $fn();
        } finally {
            array_pop(self::$stack);
        }
    }

    public static function feature(): ?string
    {
        return self::top()['feature'] ?? null;
    }

    public static function userId(): ?int
    {
        return self::top()['user'] ?? (auth()->check() ? (int) auth()->id() : null);
    }

    /** The first calling class outside the AI plumbing, as a snake_case feature name. */
    public static function inferFeature(): string
    {
        foreach (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 14) as $frame) {
            $class = $frame['class'] ?? null;
            if ($class === null || str_starts_with($class, 'App\\Services\\AI\\') || in_array($class, [\App\Services\Support\ClaudeChatModel::class, 'Illuminate\\Support\\Facades\\Facade'], true) || str_starts_with($class, 'Illuminate\\') || str_starts_with($class, 'Tests\\Support\\')) {
                continue;
            }

            return Str::snake(class_basename($class));
        }

        return 'unknown';
    }

    /** @return array{feature: ?string, user: ?int}|array{} */
    private static function top(): array
    {
        return self::$stack === [] ? [] : self::$stack[array_key_last(self::$stack)];
    }
}
