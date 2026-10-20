<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A topic on the dedicated /faq page (e.g. "eSIM Data & Naara Connect").
 * Ordered by `sort_order`; `tag_route` is an optional named route the
 * category header links out to (e.g. 'pricing').
 */
class FaqCategory extends Model
{
    protected $fillable = [
        'slug', 'title', 'eyebrow', 'blurb', 'tag_label', 'tag_route', 'sort_order', 'is_active',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function items(): HasMany
    {
        return $this->hasMany(FaqItem::class)->orderBy('sort_order');
    }
}
