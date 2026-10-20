<?php

namespace App\Models\N2N;

use Illuminate\Database\Eloquent\Model;

/** A message queued for later delivery (N2N Composer suite, MASTER-ONLY). */
class ScheduledMessage extends Model
{
    public const PENDING = 'pending';

    public const SENDING = 'sending';

    public const SENT = 'sent';

    public const FAILED = 'failed';

    public const CANCELLED = 'cancelled';

    protected $table = 'n2n_scheduled_messages';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['send_at' => 'datetime', 'claimed_at' => 'datetime', 'attachment_ids' => 'array'];
    }
}
