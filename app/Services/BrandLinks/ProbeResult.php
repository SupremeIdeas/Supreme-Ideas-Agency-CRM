<?php

namespace App\Services\BrandLinks;

/** What a server-side look at a URL found (Brand-Growth Phase 2, MASTER-ONLY). Never carries a page body. */
final class ProbeResult
{
    public function __construct(
        public readonly bool $reachable,
        public readonly ?int $status = null,
        public readonly ?string $finalUrl = null,
        public readonly int $redirects = 0,
        public readonly ?string $error = null,      // a stable code: dns | blocked_ip | tls | timeout | too_many_redirects | scheme | port | http
        public readonly bool $upgradedFromHttp = false,
    ) {}

    public function finalHost(): ?string
    {
        return $this->finalUrl ? strtolower((string) parse_url($this->finalUrl, PHP_URL_HOST)) : null;
    }
}
