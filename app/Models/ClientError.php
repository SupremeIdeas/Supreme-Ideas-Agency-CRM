<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One browser-side failure reported by the beacon in resources/js/client-errors.js. No IP address and no query string is stored. */
class ClientError extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['user_id', 'kind', 'fingerprint', 'route', 'component', 'message', 'stack', 'user_agent', 'viewport_w'];
}
