<?php

namespace App\Models\N2N;

use Illuminate\Database\Eloquent\Model;

/** A member's abuse report on another member, with an excerpt captured at report time (N2N, MASTER-ONLY). Reviewed by staff. */
class Report extends Model
{
    protected $table = 'n2n_reports';

    protected $fillable = ['reporter_id', 'reported_id', 'conversation_id', 'message_id', 'reason', 'note', 'excerpt', 'status', 'reviewed_by', 'reviewed_at', 'staff_note', 'action'];

    protected function casts(): array
    {
        return ['excerpt' => 'array', 'reviewed_at' => 'datetime'];
    }
}
