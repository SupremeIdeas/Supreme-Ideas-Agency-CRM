<?php

namespace App\Models\N2N;

use Illuminate\Database\Eloquent\Model;

/** A 24-hour Naara Chat status (MASTER-ONLY). */
class StatusPost extends Model
{
    public const KINDS = ['text', 'image', 'video', 'voice'];

    public $timestamps = false;

    protected $table = 'n2n_status_posts';

    protected $fillable = ['user_id', 'kind', 'body', 'bg', 'font', 'disk', 'storage_key', 'mime', 'bytes', 'duration_s', 'expires_at', 'purge_failed_at', 'created_at'];

    protected $hidden = ['disk', 'storage_key'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'created_at' => 'datetime', 'purge_failed_at' => 'datetime'];
    }

    public function scopeActive($q)
    {
        return $q->where('expires_at', '>', now());
    }
}
