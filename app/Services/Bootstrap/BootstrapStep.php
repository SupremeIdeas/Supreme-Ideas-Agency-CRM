<?php

namespace App\Services\Bootstrap;

/**
 * One ordered, independent bootstrap step (Bootstrap blueprint section 4). plan() says what apply() WOULD create (for --dry-run), apply() creates only what is
 * missing and never overwrites an existing or admin-edited value, verify() returns a list of failures (empty = healthy) and is safe to run anywhere, any time.
 */
interface BootstrapStep
{
    public function name(): string;

    /** @return list<array{kind: string, item: string, note: string}> */
    public function plan(string $profile): array;

    /** @return array{created: int, preserved: int, skipped: int} */
    public function apply(string $profile): array;

    /** @return list<string> */
    public function verify(string $profile): array;
}
