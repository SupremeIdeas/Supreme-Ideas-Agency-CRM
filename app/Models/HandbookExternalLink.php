<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** An outside marketplace link (MASTER-ONLY, Naara Travel Handbook Store). */
class HandbookExternalLink extends Model
{
    protected $table = 'handbook_external_links';

    protected $fillable = ['handbook_id', 'marketplace', 'url', 'is_enabled', 'availability'];

    protected function casts(): array
    {
        return ['is_enabled' => 'boolean'];
    }

    public const MARKETPLACES = ['amazon_kindle' => 'Kindle', 'amazon_paperback' => 'Paperback on Amazon', 'amazon_hardcover' => 'Hardcover on Amazon', 'apple_books' => 'Apple Books', 'google_play_books' => 'Google Play Books', 'other' => 'Other store'];

    public function handbook(): BelongsTo
    {
        return $this->belongsTo(Handbook::class);
    }

    /** A button only shows while it is switched on, live, and has an https link. */
    public function isShown(): bool
    {
        return $this->is_enabled && $this->availability === 'live' && is_string($this->url) && str_starts_with($this->url, 'https://');
    }
}
