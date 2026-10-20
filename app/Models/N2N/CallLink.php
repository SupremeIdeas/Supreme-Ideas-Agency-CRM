<?php

namespace App\Models\N2N;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

/** A Naara-issued group-call link (N2N Batch 11, MASTER-ONLY). The secret is only ever stored hashed (lookup) and encrypted (host copy). */
class CallLink extends Model
{
    protected $table = 'n2n_call_links';

    protected $guarded = [];

    protected $hidden = ['token_hash', 'token_enc'];

    protected function casts(): array
    {
        return ['requires_approval' => 'boolean', 'expires_at' => 'datetime', 'revoked_at' => 'datetime'];
    }

    public function call(): BelongsTo
    {
        return $this->belongsTo(GroupCall::class, 'group_call_id');
    }

    public static function hash(string $token): string
    {
        return hash('sha256', $token);
    }

    public function token(): string
    {
        return Crypt::decryptString($this->token_enc);
    }

    public function usable(): bool
    {
        return $this->revoked_at === null && $this->expires_at->isFuture();
    }
}
