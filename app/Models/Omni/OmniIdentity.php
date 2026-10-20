<?php

namespace App\Models\Omni;

use Illuminate\Database\Eloquent\Model;

/** The customer's id on one channel, owned by one contact (MASTER-ONLY). */
class OmniIdentity extends Model
{
    protected $table = 'omni_identities';

    protected $guarded = [];
}
