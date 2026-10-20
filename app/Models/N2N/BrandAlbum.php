<?php

namespace App\Models\N2N;

use App\Models\BrandPartner;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** An album / smart-link page: one public page listing where to stream a release (N2N, MASTER-ONLY). */
class BrandAlbum extends Model
{
    protected $table = 'n2n_brand_albums';

    protected $fillable = ['brand_partner_id', 'title', 'slug', 'description', 'cover_path', 'distributor', 'smart_url', 'release_date', 'published_at', 'sort_order', 'hidden_at'];

    protected function casts(): array
    {
        return ['hidden_at' => 'datetime', 'published_at' => 'datetime', 'release_date' => 'date'];
    }

    public function isPublished(): bool
    {
        return $this->published_at !== null && $this->hidden_at === null;
    }

    /** Live on the public profile: published by the artist and not hidden by an admin. */
    public function scopeLive($q)
    {
        return $q->whereNotNull('published_at')->whereNull('hidden_at');
    }

    public function links(): HasMany
    {
        return $this->hasMany(BrandAlbumLink::class, 'album_id')->orderBy('sort_order')->orderBy('id');
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(BrandPartner::class, 'brand_partner_id');
    }
}
