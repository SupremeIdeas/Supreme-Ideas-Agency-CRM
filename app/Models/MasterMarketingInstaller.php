<?php

namespace App\Models;

use App\Support\MediaStorage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/** One downloadable Naara installer per platform (MASTER ONLY). Either an uploaded file or an external link. */
class MasterMarketingInstaller extends Model
{
    protected $fillable = ['platform', 'version', 'file_name', 'file_path', 'external_url', 'size_bytes', 'sha256', 'checks', 'min_os', 'notes', 'is_published', 'downloads'];

    protected function casts(): array
    {
        return ['is_published' => 'boolean', 'checks' => 'array', 'size_bytes' => 'integer', 'downloads' => 'integer'];
    }

    /** Where the person's browser should go. Null when nothing is attached yet. */
    public function targetUrl(): ?string
    {
        if ($this->file_path) {
            return Storage::disk(MediaStorage::disk())->url($this->file_path);
        }

        return $this->external_url ?: null;
    }

    public function isLive(): bool
    {
        return $this->is_published && $this->targetUrl() !== null;
    }

    public function humanSize(): ?string
    {
        if (! $this->size_bytes) {
            return null;
        }

        return $this->size_bytes >= 1048576 ? number_format($this->size_bytes / 1048576, 1).' MB' : number_format($this->size_bytes / 1024, 0).' KB';
    }
}
