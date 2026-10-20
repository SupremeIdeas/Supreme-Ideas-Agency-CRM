<?php

namespace App\Models\N2N;

use Illuminate\Database\Eloquent\Model;

/** A viewer's one reaction to a status post (MASTER-ONLY). */
class StatusReaction extends Model
{
    public $timestamps = false;

    protected $table = 'n2n_status_reactions';

    protected $fillable = ['status_id', 'viewer_id', 'emoji', 'created_at'];
}
