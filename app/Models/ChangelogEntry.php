<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One shipped version's user-facing release notes (content/billing/hot-menu
 * blueprint Phase E). Append-only: the admin UI only ever creates a new row,
 * never edits or deletes one — the "What's New" screen is a permanent record.
 */
class ChangelogEntry extends Model
{
    protected $fillable = ['version', 'released_at', 'summary', 'notes', 'created_by'];

    protected function casts(): array
    {
        return ['released_at' => 'date'];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
