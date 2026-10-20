<?php

namespace App\Models\N2N;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A private follow-up reminder (Naara Planner Phase C, MASTER-ONLY). */
class Followup extends Model
{
    protected $table = 'n2n_followups';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['due_at' => 'datetime', 'done_at' => 'datetime', 'reminded_at' => 'datetime', 'starred' => 'boolean'];
    }

    public function about(): BelongsTo
    {
        return $this->belongsTo(User::class, 'about_user_id');
    }
}
