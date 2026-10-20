<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** MASTER-ONLY. One member's saved Widget Dock positions: {widgetId: {side: left|right, y: 0..1}}. */
class MasterMarketingDockPosition extends Model
{
    protected $fillable = ['user_id', 'positions', 'stamp'];

    protected $casts = ['positions' => 'array', 'stamp' => 'integer'];
}
