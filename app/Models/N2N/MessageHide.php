<?php

namespace App\Models\N2N;

use Illuminate\Database\Eloquent\Model;

/** A message one member removed from THEIR OWN view ("Delete for me"). N2N, MASTER-ONLY. Never affects what anyone else sees. */
class MessageHide extends Model
{
    public $timestamps = false;

    protected $table = 'n2n_message_hides';

    protected $fillable = ['user_id', 'message_id', 'created_at'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }
}
