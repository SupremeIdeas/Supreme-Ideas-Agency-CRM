<?php

namespace App\Models\N2N;

use Illuminate\Database\Eloquent\Model;

/** One member's reaction to one message (N2N, MASTER-ONLY). */
class MessageReaction extends Model
{
    protected $table = 'n2n_message_reactions';

    protected $guarded = [];
}
