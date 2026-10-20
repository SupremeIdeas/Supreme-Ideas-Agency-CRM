<?php

namespace App\Services\BrandLinks;

/** The full answer of the policy for one link (Brand-Growth Phase 2, MASTER-ONLY): a status, a score, and every check with its plain-language result. */
final class LinkVerdict
{
    /** @param list<array{key: string, ok: bool, message: string}> $checks */
    public function __construct(
        public string $status,                 // approved | pending | rejected
        public string $url = '',
        public string $host = '',
        public string $registrable = '',
        public string $tld = '',
        public string $subdomain = '',
        public int $score = 0,
        public array $checks = [],
        public ?string $reason = null,         // the one sentence the owner reads
        public ?string $finalHost = null,
        public ?int $httpStatus = null,
        public ?string $safeBrowsing = null,   // clean | flagged | skipped
        public bool $canRequestReview = false,
        public bool $canRetry = false,
    ) {}

    public function approved(): bool
    {
        return $this->status === 'approved';
    }

    /** @return list<string> every failed check's message */
    public function failures(): array
    {
        return array_values(array_map(fn ($c) => $c['message'], array_filter($this->checks, fn ($c) => ! $c['ok'])));
    }
}
