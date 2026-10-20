<?php

namespace App\Models\N2N;

use Illuminate\Database\Eloquent\Model;

/** An admin-written notice at the top of Naara chat (N2N, MASTER-ONLY). */
class ChatNotice extends Model
{
    protected $table = 'n2n_chat_notices';

    protected $fillable = ['title', 'body', 'tone', 'active', 'dismissible', 'starts_at', 'ends_at', 'created_by'];

    protected $casts = ['active' => 'bool', 'dismissible' => 'bool', 'starts_at' => 'datetime', 'ends_at' => 'datetime'];

    public function scopeLive($q)
    {
        return $q->where('active', true)
            ->where(fn ($w) => $w->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($w) => $w->whereNull('ends_at')->orWhere('ends_at', '>', now()));
    }
}
