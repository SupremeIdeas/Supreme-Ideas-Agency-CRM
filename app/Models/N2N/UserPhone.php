<?php

namespace App\Models\N2N;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A phone number a user has PROVEN they can receive an SMS on (N2N, MASTER-ONLY). */
class UserPhone extends Model
{
    protected $table = 'n2n_user_phones';

    protected $fillable = ['user_id', 'e164', 'country_code', 'verified_at', 'discoverable'];

    protected $hidden = ['e164'];

    protected function casts(): array
    {
        return ['verified_at' => 'datetime', 'discoverable' => 'boolean'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
