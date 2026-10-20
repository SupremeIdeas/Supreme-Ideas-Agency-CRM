<?php

namespace App\Models\N2N;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One uploaded image, voice note or video on an N2N message (MASTER-ONLY). */
class Attachment extends Model
{
    public const STAGED = 'staged';

    public const ATTACHED = 'attached';

    public const DELETED = 'deleted';

    protected $table = 'n2n_attachments';

    protected $fillable = ['message_id', 'uploader_id', 'kind', 'disk', 'storage_key', 'thumb_key', 'mime', 'bytes', 'duration_s', 'width', 'height', 'status', 'meta', 'expires_at'];

    protected $hidden = ['storage_key', 'thumb_key', 'disk'];

    protected function casts(): array
    {
        return ['meta' => 'array', 'expires_at' => 'datetime', 'bytes' => 'integer'];
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class, 'message_id');
    }
}
