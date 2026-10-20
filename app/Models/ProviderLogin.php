<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One third-party dashboard the owner signs in to (MASTER-ONLY). No password is ever stored here. */
class ProviderLogin extends Model
{
    protected $fillable = ['provider_key', 'name', 'category', 'login_url', 'login_method', 'email_hint', 'username_hint', 'notes', 'is_custom', 'confirmed_at'];

    protected $casts = ['is_custom' => 'boolean', 'confirmed_at' => 'datetime'];
}
