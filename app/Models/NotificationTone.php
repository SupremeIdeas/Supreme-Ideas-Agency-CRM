<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/** One tone in the admin's library (ringtone, message, credit alert, push or reward). See App\Support\NotificationTones. */
class NotificationTone extends Model
{
    protected $fillable = ['category', 'slot', 'label', 'credit', 'file_path', 'disk', 'mime', 'size_bytes', 'duration_ms', 'is_default', 'is_builtin', 'uploaded_by'];

    protected function casts(): array
    {
        return ['is_default' => 'boolean', 'is_builtin' => 'boolean'];
    }

    /** The URL a browser plays it from (same-origin route, so the CSP `media-src 'self'` and Web Audio fetch both work). */
    public function url(): string
    {
        return route('sounds.tone', ['tone' => $this->id]);
    }

    public function key(): string
    {
        return $this->category.'.'.$this->slot;
    }

    public function contents(): ?string
    {
        try {
            return Storage::disk($this->disk)->get($this->file_path);
        } catch (\Throwable) {
            return null;
        }
    }
}
