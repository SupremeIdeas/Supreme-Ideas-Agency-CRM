<?php

namespace App\Models\N2N;

use Illuminate\Database\Eloquent\Model;

/** An invite sent to someone not yet on Naara (N2N Batch 9, MASTER-ONLY). Keyed hash only: never the address. */
class Invite extends Model
{
    public $timestamps = false;

    protected $table = 'n2n_invites';

    protected $fillable = ['inviter_id', 'kind', 'target_hash', 'created_at'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }
}
