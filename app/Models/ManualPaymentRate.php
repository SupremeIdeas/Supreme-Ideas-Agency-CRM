<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ManualPaymentRate extends Model
{
    public const SOURCE_GREY = 'grey_live';

    public const SOURCE_MANUAL = 'manual';

    protected $fillable = ['from_currency', 'to_currency', 'rate', 'source', 'as_of', 'note', 'updated_by'];

    protected function casts(): array
    {
        return ['rate' => 'decimal:8', 'as_of' => 'datetime'];
    }
}
