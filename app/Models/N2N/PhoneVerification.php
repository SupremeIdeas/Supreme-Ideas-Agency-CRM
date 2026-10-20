<?php

namespace App\Models\N2N;

use Illuminate\Database\Eloquent\Model;

/** One OTP issued to prove ownership of a phone number (N2N, MASTER-ONLY). Only a keyed hash of the code is stored. */
class PhoneVerification extends Model
{
    protected $table = 'n2n_phone_verifications';

    protected $fillable = ['user_id', 'e164', 'code_hash', 'attempts', 'suppressed', 'ip', 'cost_usd', 'expires_at', 'verified_at'];

    protected $hidden = ['code_hash', 'e164'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'verified_at' => 'datetime', 'suppressed' => 'boolean', 'cost_usd' => 'float'];
    }
}
