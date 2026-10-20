<?php

namespace App\Models\N2N;

use Illuminate\Database\Eloquent\Model;

/** A member an owner hides their status from (MASTER-ONLY). Never visible to that member. */
class StatusHidden extends Model
{
    public $timestamps = false;

    protected $table = 'n2n_status_hidden';

    protected $fillable = ['owner_id', 'member_id'];
}
