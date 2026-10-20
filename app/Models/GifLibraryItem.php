<?php

namespace App\Models;

use App\Support\MediaStorage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/** One GIF or sticker on a shelf of the composer library. */
class GifLibraryItem extends Model
{
    protected $fillable = ['category', 'name', 'path', 'disk', 'source', 'source_url', 'license', 'width', 'height', 'bytes', 'sort', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function url(): string
    {
        return $this->disk === null ? asset('images/gifs/'.$this->path) : Storage::disk($this->disk)->url($this->path);
    }

    /** Remove the stored file of an admin-added item (shipped files are never deleted from disk). */
    protected static function booted(): void
    {
        static::deleting(function (self $i) {
            if ($i->disk !== null && $i->source !== 'seed') {
                Storage::disk($i->disk)->delete($i->path);
            }
        });
    }

    public static function mediaDisk(): string
    {
        return MediaStorage::disk();
    }
}
