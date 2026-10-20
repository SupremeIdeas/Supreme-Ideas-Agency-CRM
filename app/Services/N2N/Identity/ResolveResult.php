<?php

namespace App\Services\N2N\Identity;

/** What the resolver returns (N2N, MASTER-ONLY). Never carries an email or a phone number; the public shape never carries a database id. */
final class ResolveResult
{
    public function __construct(
        public readonly bool $found,
        public readonly ?int $userId = null,
        public readonly ?string $naaraId = null,
        public readonly ?string $username = null,
        public readonly ?string $name = null,
        public readonly ?string $avatar = null,
        public readonly ?string $match = null,
    ) {}

    public static function miss(): self
    {
        return new self(false);
    }

    /** The uniform shape a browser may see: identical keys for a hit and a miss. */
    public function toPublic(): array
    {
        return [
            'found' => $this->found,
            'naara_id' => $this->naaraId,
            'username' => $this->username,
            'name' => $this->name,
            'avatar' => $this->avatar,
            'match' => $this->match,
        ];
    }
}
