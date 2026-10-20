<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** An email address offered in the "which email did I sign up with" dropdown of the provider login manager (MASTER-ONLY). */
class ProviderLoginEmail extends Model
{
    protected $fillable = ['email', 'label'];
}
