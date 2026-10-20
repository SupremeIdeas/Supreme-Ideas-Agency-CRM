<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One shelf of the composer's GIF and sticker library. `surfaces` null means every chat shows it; otherwise only the listed ones (see config gif_library.surfaces). */
class GifCategory extends Model
{
    protected $fillable = ['slug', 'name', 'sort', 'is_active', 'is_builtin', 'surfaces'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'is_builtin' => 'boolean', 'surfaces' => 'array'];
    }

    /** The name people see. A shipped shelf the admin never renamed follows the member's language. */
    public function label(): string
    {
        if ($this->is_builtin) {
            $key = 'gifs.cat.'.$this->slug;
            $english = trans($key, [], 'en');
            if ($this->name === $english) {
                $t = __($key);

                return $t !== $key ? $t : $this->name;
            }
        }

        return $this->name;
    }

    public function showsOn(?string $surface): bool
    {
        $s = $this->surfaces;
        if (! is_array($s) || $s === [] || $surface === null || $surface === '') {
            return true;
        }

        return in_array($surface, $s, true);
    }
}
