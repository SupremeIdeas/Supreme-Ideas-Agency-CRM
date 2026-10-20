<?php

namespace App\Livewire\Account;

use App\Models\UserSoundPref;
use App\Support\NotificationTones;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Account -> Sounds. A member's own alert tone and ringtone per event, plus the sound and vibration switches. Choices come only from the
 * admin's library (Admin -> Sounds & ringtones); "Platform default" follows whatever the admin marks as default for that event.
 * Foreground only, and the page says so: a closed tab uses the device's own notification sound.
 */
#[Layout('components.layouts.customer')]
class SoundSettings extends Component
{
    /** @var array<string, string> "category__slot" => tone id, or '' for "platform default" (no dots: Livewire would nest them) */
    public array $picks = [];

    public bool $sound = true;

    public bool $vibrate = true;

    public function mount(): void
    {
        $pref = UserSoundPref::where('user_id', Auth::id())->first();
        $this->sound = $pref?->sound_enabled ?? true;
        $this->vibrate = $pref?->vibrate_enabled ?? true;
        foreach (NotificationTones::keys() as $key) {
            $this->picks[str_replace('.', '__', $key)] = (string) (($pref?->tones ?? [])[$key] ?? '');
        }
    }

    /** One save path: every change persists immediately and refreshes the open page's sound config. */
    public function save(): void
    {
        $user = Auth::user();
        $picks = [];
        foreach ($this->picks as $k => $v) {
            $picks[str_replace('__', '.', (string) $k)] = $v;
        }
        NotificationTones::savePrefs($user, $picks, $this->sound, $this->vibrate);
        $this->dispatch('nx-sound-config', ...NotificationTones::clientConfig($user));
        $this->dispatch('nx-toast', type: 'success', message: (string) __('sounds.saved'));
    }

    public function updatedPicks(): void
    {
        $this->save();
    }

    public function updatedSound(): void
    {
        $this->save();
    }

    public function updatedVibrate(): void
    {
        $this->save();
    }

    public function resetAll(): void
    {
        foreach (array_keys($this->picks) as $k) {
            $this->picks[$k] = '';
        }
        $this->save();
    }

    public function render()
    {
        return view('livewire.account.sound-settings', [
            'categories' => NotificationTones::CATEGORIES,
            'library' => NotificationTones::library(),
        ]);
    }
}
