<?php

namespace App\Models\N2N;

use Illuminate\Database\Eloquent\Model;

/** "Delete for me" on a media item (N2N Batch 12, MASTER-ONLY): hides it from ONE member's thread and gallery. */
class AttachmentHide extends Model
{
    public $timestamps = false;

    protected $table = 'n2n_attachment_hides';

    protected $fillable = ['user_id', 'attachment_id', 'created_at'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }
}
