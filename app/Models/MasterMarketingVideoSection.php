<?php

namespace App\Models;

use App\Support\MasterMarketing\VideoSlots;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** MASTER-ONLY. One admin-managed video shown in a named slot of a master marketing page. */
class MasterMarketingVideoSection extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['autoplay' => 'boolean', 'loop' => 'boolean', 'is_visible' => 'boolean', 'protected' => 'boolean', 'wave' => 'array', 'starts_at' => 'datetime', 'ends_at' => 'datetime'];
    }

    /** Visible now: switched on and inside its optional schedule window. */
    public function scopeLive(Builder $q): Builder
    {
        return $q->where('is_visible', true)
            ->where(fn ($w) => $w->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($w) => $w->whereNull('ends_at')->orWhere('ends_at', '>=', now()));
    }

    protected static function booted(): void
    {
        $bust = fn () => VideoSlots::flush();
        static::saved($bust);
        static::deleted($bust);
    }
}
