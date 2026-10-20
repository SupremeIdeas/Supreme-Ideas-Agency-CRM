<?php

namespace App\Support\Verify;

final class CheckResult
{
    public const PASS = 'pass';

    public const WARN = 'warn';

    public const FAIL = 'fail';

    /** @param array<string, mixed> $evidence never contains a secret, a balance or personal data */
    private function __construct(public readonly string $status, public readonly string $detail, public readonly array $evidence = [])
    {
    }

    public static function pass(string $detail, array $evidence = []): self
    {
        return new self(self::PASS, $detail, $evidence);
    }

    public static function warn(string $detail, array $evidence = []): self
    {
        return new self(self::WARN, $detail, $evidence);
    }

    public static function fail(string $detail, array $evidence = []): self
    {
        return new self(self::FAIL, $detail, $evidence);
    }

    /** An area whose real checks arrive in a later phase: honest "not built yet", never a fake pass. */
    public static function notBuilt(string $phase): self
    {
        return new self(self::WARN, "No automated check yet ({$phase}).");
    }
}
