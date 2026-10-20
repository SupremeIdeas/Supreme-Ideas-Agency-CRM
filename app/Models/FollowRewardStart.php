<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Brand-Growth Phase 7 (MASTER-ONLY): a person pressed Follow on a handle (the clock for the return gate). */
class FollowRewardStart extends Model
{
    protected $fillable = ['user_id', 'handle_id', 'brand_partner_handle_id', 'started_at', 'ip_hash', 'ua_hash'];

    protected $casts = ['started_at' => 'datetime'];
}
