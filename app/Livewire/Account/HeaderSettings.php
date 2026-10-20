<?php

namespace App\Livewire\Account;

use App\Support\Header\HeaderItems;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Account > Header. A member chooses which shortcuts sit in their dashboard header, at most three, so nobody is forced into a crowded header. Every change
 * saves at once (same pattern as Account > Sounds). The server clamps to three and to what this install offers, whatever the browser sends.
 */
#[Layout('components.layouts.customer')]
class HeaderSettings extends Component
{
    /** @var list<string> */
    public array $picked = [];

    public bool $merchantButton = true;

    public ?string $notice = null;

    public function mount(): void
    {
        $this->picked = HeaderItems::chosen(Auth::user());
        $this->merchantButton = HeaderItems::showMerchantButton(Auth::user()) || ! HeaderItems::isMerchant(Auth::user());
    }

    public function toggle(string $key): void
    {
        $this->notice = null;
        if (! isset(HeaderItems::available(Auth::user())[$key])) {
            return;
        }
        if (in_array($key, $this->picked, true)) {
            $this->picked = array_values(array_diff($this->picked, [$key]));
        } elseif (count($this->picked) >= HeaderItems::MAX) {
            $this->notice = (string) __('header.max_reached', ['max' => HeaderItems::MAX]);

            return;
        } else {
            $this->picked[] = $key;
        }
        $this->persist();
    }

    public function updatedMerchantButton(): void
    {
        if (HeaderItems::isMerchant(Auth::user())) {
            $this->persist();
        }
    }

    public function resetToDefault(): void
    {
        $this->notice = null;
        $this->picked = array_slice(array_values(array_filter(HeaderItems::DEFAULTS, fn ($k) => HeaderItems::exists($k, Auth::user()))), 0, HeaderItems::MAX);
        $this->persist();
    }

    private function persist(): void
    {
        $this->picked = HeaderItems::save(Auth::user(), $this->picked, HeaderItems::isMerchant(Auth::user()) ? $this->merchantButton : null);
        $this->dispatch('nx-toast', type: 'success', message: (string) __('header.saved'));
    }

    public function render()
    {
        return view('livewire.account.header-settings', [
            'available' => HeaderItems::available(Auth::user()),
            'max' => HeaderItems::MAX,
            'isMerchant' => HeaderItems::isMerchant(Auth::user()),
        ]);
    }
}
