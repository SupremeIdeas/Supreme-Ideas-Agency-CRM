<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single question/answer pair on the dedicated /faq page. `answer` is
 * admin-authored HTML (a handful of inline `<strong>`/`<a>` tags for
 * emphasis and cross-links) rendered raw, the same trust level this
 * codebase already gives `CustomPage::html` and `LegalContent` bodies —
 * never end-user input, so this is not an XSS surface.
 */
class FaqItem extends Model
{
    protected $fillable = [
        'faq_category_id', 'question', 'answer', 'sort_order', 'is_active',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(FaqCategory::class, 'faq_category_id');
    }
}
