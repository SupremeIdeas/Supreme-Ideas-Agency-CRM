<?php

namespace App\Models\N2N;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One member's star on a message (N2N Batch 12, MASTER-ONLY). Private: the other person is never told. */
class Star extends Model
{
    public $timestamps = false;

    protected $table = 'n2n_stars';

    protected $fillable = ['user_id', 'message_id', 'created_at'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class, 'message_id');
    }
}
