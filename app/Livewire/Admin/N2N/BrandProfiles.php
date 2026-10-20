<?php

namespace App\Livewire\Admin\N2N;

use App\Models\BrandPartner;
use App\Models\N2N\BrandAlbum;
use App\Models\N2N\BrandEmbed;
use App\Models\N2N\BrandMusicLink;
use App\Models\N2N\BrandSocialLink;
use App\Models\Setting;
use App\Services\N2N\Brands\BrandExtras;
use App\Services\N2N\Brands\BrandLimits;
use App\Support\Auditor;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Admin > N2N > Brand profiles (MASTER-ONLY): confirm or revoke music profiles, edit the profile limits, and hide/restore any link, player,
 * album or social link (report -> hide). Hiding never deletes the owner's data.
 */
#[Layout('components.layouts.admin')]
class BrandProfiles extends Component
{
    public array $limits = [];

    public ?string $message = null;

    public function mount(): void
    {
        foreach (BrandLimits::DEFAULTS as $tier => $row) {
            foreach ($row as $k => $default) {
                $this->limits["{$tier}.{$k}"] = \App\Support\N2N\N2NSettings::int("brand_limits.{$tier}.{$k}", $default, 0);
            }
        }
    }

    public function saveLimits(): void
    {
        foreach ($this->limits as $key => $v) {
            if (array_key_exists($key, $this->flatDefaults())) {
                Setting::setValue('n2n.brand_limits.'.$key, max(0, min(200, (int) $v)));
            }
        }
        Auditor::log('n2n.brand.limits_changed', Setting::class, null, ['limits' => $this->limits]);
        $this->message = 'Limits saved.';
    }

    private function flatDefaults(): array
    {
        $out = [];
        foreach (BrandLimits::DEFAULTS as $tier => $row) {
            foreach ($row as $k => $d) {
                $out["{$tier}.{$k}"] = $d;
            }
        }

        return $out;
    }

    public function confirmMusic(int $brandId, BrandExtras $x): void
    {
        $b = BrandPartner::query()->whereNotNull('music_requested_at')->find($brandId);
        if ($b) {
            $x->confirmMusic($b, (int) Auth::id());
        }
    }

    public function revokeMusic(int $brandId, BrandExtras $x): void
    {
        $b = BrandPartner::query()->find($brandId);
        if ($b) {
            $x->revokeMusic($b, (int) Auth::id());
        }
    }

    public function toggleHidden(string $type, int $id, bool $hide, BrandExtras $x): void
    {
        $x->setHidden($type, $id, $hide, (int) Auth::id());
    }

    public function render()
    {
        return view('livewire.admin.n2n.brand-profiles', [
            'requests' => BrandPartner::query()->whereNotNull('music_requested_at')->whereNull('music_verified_at')->orderBy('music_requested_at')->get(),
            'music' => BrandPartner::query()->whereNotNull('music_verified_at')->orderByDesc('music_verified_at')->limit(50)->get(),
            'items' => collect()
                ->concat(BrandMusicLink::query()->latest('id')->limit(30)->get()->map(fn ($r) => ['type' => 'music', 'row' => $r, 'text' => $r->platform.': '.$r->url]))
                ->concat(BrandEmbed::query()->latest('id')->limit(30)->get()->map(fn ($r) => ['type' => 'embed', 'row' => $r, 'text' => $r->provider.': '.$r->provider_id]))
                ->concat(BrandSocialLink::query()->latest('id')->limit(30)->get()->map(fn ($r) => ['type' => 'social', 'row' => $r, 'text' => $r->platform.': '.$r->url]))
                ->concat(BrandAlbum::query()->latest('id')->limit(30)->get()->map(fn ($r) => ['type' => 'album', 'row' => $r, 'text' => 'Album: '.$r->title])),
        ]);
    }
}
