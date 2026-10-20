<?php

namespace App\Livewire\Admin;

use App\Support\Auditor;
use App\Support\IconOverrides;
use App\Support\MediaStorage;
use App\Support\PaymentBrandIcons;
use App\Support\PaymentGatewayConfig;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Admin → Brand icons (UX/localization/platform-reach blueprint Phase C).
 * `IconOverrides` (Admin custom-icon overrides, blueprint Section 16.3)
 * existed with zero UI anywhere — every payment-gateway logo rendered via
 * `<x-payment-icon>` (`resources/views/components/payment-icon.blade.php`)
 * already checked `IconOverrides::for('pay.<slug>')` first, but nothing let
 * an admin actually set one. This page is that missing screen: one row per
 * payment gateway (plus the card marks shown on the wallet top-up screen),
 * upload replaces the bundled/lettered fallback via the exact same
 * `IconOverrides` storage this codebase already reads from — no second
 * mechanism.
 *
 * Social sign-in logos are a SEPARATE, already-solved case: they render via
 * <x-service-icon>, which already checks ServiceIcons::overrides() first —
 * that override already has its own admin screen (Admin → Service icons).
 * This page does not duplicate it.
 */
#[Layout('components.layouts.admin')]
class BrandIcons extends Component
{
    use WithFileUploads;

    /** Card marks shown alongside the gateway list on the wallet top-up screen. */
    private const CARD_MARKS = ['visa', 'mastercard', 'googlepay', 'applepay'];

    public $upload = null;

    public string $uploadSlug = '';

    public ?string $saved = null;

    public function mount(): void
    {
        abort_unless(Auth::user()?->hasAnyRole(['super_admin', 'admin']), 403);
    }

    public function save(): void
    {
        $this->validate([
            'upload' => 'required|file|mimes:webp,svg,png,jpg,jpeg|max:1024',
            'uploadSlug' => 'required|string',
        ]);

        $url = MediaStorage::storePublic($this->upload, 'brand-icons');
        IconOverrides::set('pay.'.$this->uploadSlug, $url);
        Auditor::log('brand_icons.updated', null, null, ['slug' => $this->uploadSlug]);

        $this->reset('upload', 'uploadSlug');
        $this->saved = 'Logo saved.';
        $this->dispatch('nx-toast', type: 'success', message: 'Brand icon saved.');
    }

    public function remove(string $slug): void
    {
        abort_unless(Auth::user()?->hasAnyRole(['super_admin', 'admin']), 403);
        IconOverrides::remove('pay.'.$slug);
        Auditor::log('brand_icons.removed', null, null, ['slug' => $slug]);
        $this->saved = 'Override removed — back to the built-in mark.';
    }

    public function render()
    {
        $slugs = collect(array_keys(PaymentGatewayConfig::GATEWAYS))
            ->merge(self::CARD_MARKS)
            ->unique()->values();

        $rows = $slugs->map(fn ($slug) => [
            'slug' => $slug,
            'override' => IconOverrides::for('pay.'.$slug),
            'state' => IconOverrides::for('pay.'.$slug) ? 'custom'
                : (PaymentBrandIcons::has($slug) ? 'bundled' : 'missing'),
        ]);

        return view('livewire.admin.brand-icons', ['rows' => $rows]);
    }
}
