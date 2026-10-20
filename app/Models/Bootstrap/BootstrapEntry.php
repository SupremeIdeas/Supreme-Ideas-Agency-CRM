<?php

namespace App\Models\Bootstrap;

use Illuminate\Database\Eloquent\Model;

/** One thing `naara:bootstrap` created (see the bootstrap_manifest migration). */
class BootstrapEntry extends Model
{
    protected $table = 'bootstrap_manifest';

    protected $fillable = ['step', 'kind', 'item', 'checksum', 'admin_modified_at'];

    protected function casts(): array
    {
        return ['admin_modified_at' => 'datetime'];
    }

    public static function checksum(mixed $value): string
    {
        return sha1(json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '');
    }
}
