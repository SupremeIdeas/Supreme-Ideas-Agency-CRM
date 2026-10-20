<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A receiving account for White Label manual payments. Admin-editable; values encrypted at rest. */
class ManualPaymentAccount extends Model
{
    protected $fillable = ['currency', 'kind', 'network', 'label', 'payee_note', 'fields', 'enabled', 'sort', 'test_confirmed_at', 'test_note', 'updated_by'];

    protected function casts(): array
    {
        return ['fields' => 'encrypted:array', 'enabled' => 'boolean', 'test_confirmed_at' => 'datetime'];
    }

    public function changes(): HasMany
    {
        return $this->hasMany(ManualPaymentAccountChange::class, 'account_id');
    }

    /** Stable fingerprint of the payable details (never the details themselves). */
    public function fingerprint(?array $fields = null): string
    {
        return hash('sha256', json_encode(array_map(fn ($r) => [trim((string) ($r['label'] ?? '')), trim((string) ($r['value'] ?? ''))], $fields ?? (array) $this->fields)));
    }

    public function isCrypto(): bool
    {
        return $this->kind === 'crypto';
    }
}
