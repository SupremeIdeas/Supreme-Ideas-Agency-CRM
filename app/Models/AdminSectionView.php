<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Content/billing/hot-menu blueprint Phase G — see the migration for the shape. */
class AdminSectionView extends Model
{
    protected $fillable = ['user_id', 'section_key', 'last_viewed_at'];

    protected function casts(): array
    {
        return [
            'last_viewed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
