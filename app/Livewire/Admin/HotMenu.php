<?php

namespace App\Livewire\Admin;

use App\Support\AdminHotMenu;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Content/billing/hot-menu blueprint Phase G — header-triggered "Hot Menu"
 * modal listing every admin section the signed-in admin/staff member may
 * reach, each with a live badge count. Mirrors `NotificationCenter`'s own
 * bell-and-sheet shape (badge poll + `<x-ui.modal>`), scoped to the admin
 * panel only.
 */
class HotMenu extends Component
{
    public function totalBadge(): int
    {
        $admin = Auth::user();

        return $admin ? AdminHotMenu::totalBadge($admin) : 0;
    }

    /** Mark a section viewed (clears its badge) and hand back its destination, mirroring NotificationCenter::go(). */
    public function open(string $sectionKey, string $route): void
    {
        $admin = Auth::user();
        if ($admin) {
            AdminHotMenu::markViewed($admin, $sectionKey);
        }

        $this->redirect(route($route), navigate: true);
    }

    public function render()
    {
        $admin = Auth::user();
        $sections = $admin ? AdminHotMenu::for($admin) : [];

        return view('livewire.admin.hot-menu', [
            'sections' => $sections,
            'total' => collect($sections)->sum(fn (array $s) => ($s['count'] ?? 0) + ($s['escalation'] ?? 0)),
        ]);
    }
}
