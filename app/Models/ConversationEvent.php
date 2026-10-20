<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One line of a person's unified conversation timeline (MASTER-ONLY): a Naara chat message, an SMS or a call, in one ordered list per peer. */
class ConversationEvent extends Model
{
    protected $fillable = ['user_id', 'peer_key', 'channel', 'kind', 'direction', 'status', 'summary', 'source_type', 'source_id', 'occurred_at'];

    protected function casts(): array
    {
        return ['occurred_at' => 'datetime'];
    }
}
