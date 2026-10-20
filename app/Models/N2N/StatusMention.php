<?php

namespace App\Models\N2N;

use Illuminate\Database\Eloquent\Model;

/** One member tagged in a status post (MASTER-ONLY). */
class StatusMention extends Model
{
    public $timestamps = false;

    protected $table = 'n2n_status_mentions';

    protected $fillable = ['status_id', 'user_id', 'created_at'];
}
