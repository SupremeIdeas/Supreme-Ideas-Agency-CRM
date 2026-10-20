<?php

namespace App\Models\N2N;

use Illuminate\Database\Eloquent\Model;

/** One of a member's two lightweight profile pictures (N2N, MASTER-ONLY). Private disk; served only through a visibility check. */
class ProfilePicture extends Model
{
    protected $table = 'n2n_profile_pictures';

    protected $fillable = ['user_id', 'slot', 'disk', 'storage_key', 'bytes'];

    protected $hidden = ['storage_key', 'disk'];
}
