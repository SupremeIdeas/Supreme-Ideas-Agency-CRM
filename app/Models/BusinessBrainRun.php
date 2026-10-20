<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One NaaraBrain inbox-assistant action (MASTER-ONLY). */
class BusinessBrainRun extends Model
{
    public const ACTIONS = ['draft', 'summary', 'tasks'];

    protected $fillable = ['wallet_group_id', 'user_id', 'owner_user_id', 'channel', 'counterpart', 'action', 'status', 'output', 'model', 'input_tokens', 'output_tokens', 'cost_usd'];

    protected $casts = ['cost_usd' => 'decimal:6', 'input_tokens' => 'integer', 'output_tokens' => 'integer'];

    /** What the member may see: never the cost. */
    protected $hidden = ['cost_usd', 'input_tokens', 'output_tokens', 'model'];
}
