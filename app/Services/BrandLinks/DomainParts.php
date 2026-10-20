<?php

namespace App\Services\BrandLinks;

/** A hostname split by the Public Suffix List (Brand-Growth Phase 2, MASTER-ONLY). */
final class DomainParts
{
    public function __construct(
        public readonly string $host,
        public readonly string $suffix,          // com.ng, co.uk, blogspot.com (a private-section suffix)
        public readonly string $registrable,     // example.com.ng
        public readonly string $label,           // example  (the one label to the left of the suffix)
        public readonly string $subdomain,       // portfolio.shop (may be empty)
        public readonly string $tld,             // ng
        public readonly bool $privateSuffix,     // shared host: every customer gets a subdomain
        public readonly bool $icann,             // the suffix is an ICANN-delegated one
    ) {}

    /** @return list<string> */
    public function subdomainLabels(): array
    {
        return $this->subdomain === '' ? [] : explode('.', $this->subdomain);
    }
}
