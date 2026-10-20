<?php

namespace App\Models\Brain;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** One NaaraBrain conversation (MASTER-ONLY). Always reached through the signed-in person's own relation, never by a bare id. */
class BrainThread extends Model
{
    protected $table = 'brain_threads';

    protected $fillable = ['user_id', 'title', 'depth', 'length', 'pinned', 'archived_at', 'last_message_at'];

    protected $casts = ['pinned' => 'boolean', 'archived_at' => 'datetime', 'last_message_at' => 'datetime'];

    /** The only way to reach threads: scoped to one person, so a bare id can never read someone else's. */
    public function scopeOwnedBy(Builder $q, int $userId): Builder
    {
        return $q->where('user_id', $userId);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(BrainMessage::class, 'thread_id')->orderBy('id');
    }
}
