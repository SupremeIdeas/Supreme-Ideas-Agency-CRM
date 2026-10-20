<?php

namespace App\Livewire\N2N;

use App\Models\N2N\ProfilePicture;
use App\Services\N2N\Brands\BrandLimitException;
use App\Services\N2N\Profiles\ProfileService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

/** Account > Naara profile (N2N Batch 8, MASTER-ONLY): visibility, per-field reveal, birthday year, bio and two pictures. */
#[Layout('components.layouts.n2n')]
class ProfileSettings extends Component
{
    use WithFileUploads;

    public string $visibility = 'connections';

    public array $reveal = [];

    public bool $showYear = false;

    public string $bio = '';

    public $pic1 = null;

    public $pic2 = null;

    public ?string $message = null;

    public ?string $error = null;

    public function mount(): void
    {
        $u = Auth::user();
        $this->visibility = (string) ($u->n2n_profile_visibility ?: 'connections');
        $this->reveal = array_values((array) ($u->n2n_profile_reveal ?? []));
        $this->showYear = (bool) $u->n2n_dob_show_year;
        $this->bio = (string) $u->bio;
    }

    public function save(ProfileService $svc): void
    {
        $this->reset('message', 'error');
        try {
            $svc->save(Auth::user(), $this->visibility, $this->reveal, $this->showYear, $this->bio);
            $this->message = 'Profile saved.';
        } catch (BrandLimitException $e) {
            $this->error = $e->getMessage();
        }
    }

    public function savePicture(int $slot, ProfileService $svc): void
    {
        $this->reset('message', 'error');
        $file = $slot === 1 ? $this->pic1 : $this->pic2;
        if ($file === null) {
            return;
        }
        try {
            $svc->setPicture(Auth::user(), $slot, $file);
            $this->message = 'Picture saved.';
        } catch (BrandLimitException $e) {
            $this->error = $e->getMessage();
        }
        $this->pic1 = $this->pic2 = null;
    }

    public function removePicture(int $slot, ProfileService $svc): void
    {
        $svc->removePicture(Auth::user(), $slot);
        $this->message = 'Picture removed.';
    }

    public function render()
    {
        return view('livewire.n2n.profile-settings', ['slots' => ProfilePicture::query()->where('user_id', Auth::id())->pluck('slot')->all(), 'profileUrl' => Auth::user()->username ? route('n2n.profile', ['handle' => Auth::user()->username]) : null]);
    }
}
