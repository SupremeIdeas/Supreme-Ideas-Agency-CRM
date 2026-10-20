<?php

namespace App\Models\Brain;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One turn in a NaaraBrain conversation (MASTER-ONLY). */
class BrainMessage extends Model
{
    protected $table = 'brain_messages';

    protected $fillable = ['thread_id', 'user_id', 'role', 'body', 'status', 'model'];

    public function thread(): BelongsTo
    {
        return $this->belongsTo(BrainThread::class, 'thread_id');
    }
}
