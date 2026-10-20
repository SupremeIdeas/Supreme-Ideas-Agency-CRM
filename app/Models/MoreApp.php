<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A cross-promoted app shortcut shown in the mobile "More" sheet carousel
 * (master-only feature — see App\Support\FeatureEntitlements::isMaster()).
 */
class MoreApp extends Model
{
    protected $fillable = [
        'name',
        'icon_url',
        'link_url',
        'sort_order',
        'is_active',
        'is_system',
        'system_key',
        'route_name',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_system' => 'boolean',
        ];
    }

    public function scopeLive($query)
    {
        return $query->where('is_active', true);
    }

    /** Where the app goes: a named in-app route for built-in apps (so wire:navigate works), else the stored link. */
    public function href(): string
    {
        if ($this->route_name && \Illuminate\Support\Facades\Route::has($this->route_name)) {
            // The Business Suite tile goes straight to the dashboard for someone who already has a workspace (master only; the class is absent in a fork).
            $name = $this->route_name === 'business' && class_exists(\App\Support\Business\BusinessNav::class)
                ? \App\Support\Business\BusinessNav::entryRoute(auth()->user())
                : $this->route_name;

            return route($name);
        }

        return (string) $this->link_url;
    }

    /** Built-in apps are gated per person (Business Suite live, Brain allowed, Naara Chat on); admin-added apps are open to all. */
    public function visibleTo(?\App\Models\User $user): bool
    {
        if (! $this->is_system) {
            return true;
        }

        return class_exists(\App\Support\Business\SystemMoreApps::class) && \App\Support\Business\SystemMoreApps::visibleTo((string) $this->system_key, $user);
    }

    /** External links open in a new tab; internal paths navigate in-app. */
    public function isExternalLink(): bool
    {
        if ($this->route_name) {
            return false;
        }
        return preg_match('#^https?://#i', $this->link_url) === 1
            && ! str_starts_with($this->link_url, rtrim(config('app.url'), '/'));
    }
}
