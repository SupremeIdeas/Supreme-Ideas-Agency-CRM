<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/** One travel handbook (MASTER-ONLY, Naara Travel Handbook Store). Files live on the private disk only; nothing here is ever a public URL. */
class Handbook extends Model
{
    public const DRAFT = 'draft';

    public const REVIEW = 'review';

    public const PUBLISHED = 'published';

    public const ARCHIVED = 'archived';

    public const STATUSES = ['draft' => 'Draft', 'review' => 'In review', 'published' => 'Published', 'archived' => 'Archived'];

    /** In-memory defaults that mirror the column defaults, so a freshly created model behaves like a reloaded one. */
    protected $attributes = ['edition' => 1, 'is_featured' => false, 'unlock_via_esim' => true, 'unlock_via_pro' => true, 'allow_purchase' => true];

    protected $fillable = [
        'slug', 'catalogue_id', 'country_code', 'series_slot', 'series_id', 'title', 'subtitle', 'description', 'language', 'cover_path', 'back_cover_path', 'epub_path', 'pdf_path', 'page_count', 'list_price_usd', 'status',
        'last_verified_on', 'edition', 'published_at', 'isbn_ebook', 'isbn_paperback', 'isbn_hardcover', 'sample_chapter_ref', 'toc', 'sort_order', 'is_featured', 'unlock_via_esim', 'unlock_via_pro', 'allow_purchase', 'extra_unlock_countries',
    ];

    protected function casts(): array
    {
        return ['list_price_usd' => 'decimal:2', 'last_verified_on' => 'date', 'published_at' => 'datetime', 'toc' => 'array', 'edition' => 'integer', 'page_count' => 'integer', 'is_featured' => 'boolean', 'unlock_via_esim' => 'boolean', 'unlock_via_pro' => 'boolean', 'allow_purchase' => 'boolean', 'extra_unlock_countries' => 'array'];
    }

    public function scopePublished(Builder $q): Builder
    {
        return $q->where('status', self::PUBLISHED);
    }

    public function series(): BelongsTo
    {
        return $this->belongsTo(HandbookSeries::class, 'series_id');
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(HandbookCategory::class, 'handbook_category_handbook');
    }

    public function moods(): BelongsToMany
    {
        return $this->belongsToMany(HandbookMood::class, 'handbook_handbook_mood');
    }

    public function links(): HasMany
    {
        return $this->hasMany(HandbookExternalLink::class);
    }

    public function audiobook(): HasOne
    {
        return $this->hasOne(HandbookAudiobook::class);
    }

    public function editions(): HasMany
    {
        return $this->hasMany(HandbookEdition::class)->orderByDesc('edition');
    }

    public function entitlements(): HasMany
    {
        return $this->hasMany(HandbookEntitlement::class);
    }

    /** What stops this title being published: the publish checklist (cover, EPUB, PDF, description, price, last-verified date, a category). @return list<string> */
    public function publishProblems(): array
    {
        $p = [];
        foreach ([['cover_path', 'a cover'], ['epub_path', 'the manuscript as an EPUB file'], ['pdf_path', 'the PDF for buyers to download'], ['description', 'a description']] as [$col, $label]) {
            if (blank($this->{$col})) {
                $p[] = 'Add '.$label.'.';
            }
        }
        if ((float) $this->list_price_usd <= 0) {
            $p[] = 'Set a price above zero.';
        }
        if ($this->last_verified_on === null) {
            $p[] = 'Set the "last verified" date.';
        }
        if ($this->exists && $this->categories()->count() === 0) {
            $p[] = 'Choose at least one category.';
        }

        return $p;
    }
}
