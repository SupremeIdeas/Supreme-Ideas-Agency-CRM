<?php

namespace App\Livewire\Admin;

use App\Models\ProviderLogin;
use App\Models\ProviderLoginEmail;
use App\Support\Auditor;
use App\Support\ProviderLogins as Catalogue;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Provider login manager (MASTER-ONLY): where and how the owner signs in to each third-party dashboard. A reminder map, not a vault: no password field exists,
 * and a note that looks like a password is refused. Providers whose API keys are saved here are listed first.
 */
#[Layout('components.layouts.admin')]
class ProviderLogins extends Component
{
    public string $search = '';

    public string $filter = 'all';   // all | credentials | unconfirmed

    public ?int $editingId = null;

    public string $login_url = '';

    public string $login_method = 'unconfirmed';

    public string $email_hint = '';

    public string $username_hint = '';

    public string $notes = '';

    public string $newEmail = '';

    public string $newEmailLabel = '';

    public string $customName = '';

    public string $customCategory = 'other';

    public ?string $saved = null;

    /** Authorization runs on every Livewire request, not only the first load. */
    public function booted(): void
    {
        abort_unless(auth()->user()?->hasAnyRole(['super_admin', 'admin']), 403);
    }

    public function edit(int $id): void
    {
        $row = ProviderLogin::findOrFail($id);
        $this->editingId = $row->id;
        $this->login_url = (string) $row->login_url;
        $this->login_method = $row->login_method;
        $this->email_hint = (string) $row->email_hint;
        $this->username_hint = (string) $row->username_hint;
        $this->notes = (string) $row->notes;
        $this->resetErrorBag();
    }

    public function cancel(): void
    {
        $this->editingId = null;
        $this->resetErrorBag();
    }

    public function save(): void
    {
        $row = ProviderLogin::findOrFail((int) $this->editingId);
        $emails = ProviderLoginEmail::pluck('email')->all();

        $this->validate([
            'login_url' => ['nullable', 'url:https', 'max:300'],
            'login_method' => ['required', Rule::in(array_keys(Catalogue::METHODS))],
            'email_hint' => ['nullable', 'email', 'max:190', Rule::in($emails)],
            'username_hint' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:300', function ($attr, $value, $fail) {
                if (preg_match('/\b(pass(word|wd|code)?|pwd|pin|secret)\b\s*[:=\-]/i', (string) $value)) {
                    $fail('Please do not type a password here. This page only remembers where and how you sign in.');
                }
            }],
        ], [
            'login_url.url' => 'Use a full https:// address.',
            'email_hint.in' => 'Pick one of the saved emails, or add it in the Email hints box first.',
        ]);

        $row->update([
            'login_url' => $this->login_url !== '' ? $this->login_url : null,
            'login_method' => $this->login_method,
            'email_hint' => $this->email_hint !== '' ? $this->email_hint : null,
            'username_hint' => $this->login_method === 'username_password' && $this->username_hint !== '' ? $this->username_hint : null,
            'notes' => $this->notes !== '' ? $this->notes : null,
            'confirmed_at' => now(),
        ]);
        Auditor::log('provider_login.updated', 'ProviderLogin', $row->id, ['provider' => $row->provider_key, 'method' => $row->login_method]);

        $this->editingId = null;
        $this->saved = "{$row->name} saved.";
    }

    public function addEmail(): void
    {
        $this->validate([
            'newEmail' => ['required', 'email', 'max:190', Rule::unique('provider_login_emails', 'email')],
            'newEmailLabel' => ['nullable', 'string', 'max:80'],
        ]);
        ProviderLoginEmail::create(['email' => mb_strtolower(trim($this->newEmail)), 'label' => $this->newEmailLabel !== '' ? $this->newEmailLabel : null]);
        $this->reset('newEmail', 'newEmailLabel');
        $this->saved = 'Email added to the dropdown.';
    }

    /** Removing an email only takes it out of the dropdown; rows that already show it keep their text. */
    public function removeEmail(int $id): void
    {
        ProviderLoginEmail::where('id', $id)->delete();
    }

    public function addCustom(): void
    {
        $this->validate([
            'customName' => ['required', 'string', 'max:120'],
            'customCategory' => ['required', Rule::in(array_keys(Catalogue::CATEGORIES))],
        ]);
        $key = 'custom-'.Str::slug($this->customName);
        $n = 1;
        while (ProviderLogin::where('provider_key', $key)->exists()) {
            $key = 'custom-'.Str::slug($this->customName).'-'.(++$n);
        }
        ProviderLogin::create(['provider_key' => $key, 'name' => trim($this->customName), 'category' => $this->customCategory, 'is_custom' => true]);
        $this->reset('customName');
        $this->saved = 'Provider added. Fill in its login details.';
    }

    public function deleteCustom(int $id): void
    {
        ProviderLogin::where('id', $id)->where('is_custom', true)->delete();
    }

    /** @return \Illuminate\Support\Collection<int, array{row: ProviderLogin, keys: bool}> */
    private function rows()
    {
        $cats = array_flip(array_keys(Catalogue::CATEGORIES));
        $needle = mb_strtolower(trim($this->search));

        return ProviderLogin::all()
            ->map(fn (ProviderLogin $r) => ['row' => $r, 'keys' => Catalogue::credentialsFor($r)])
            ->filter(fn ($x) => $needle === '' || str_contains(mb_strtolower($x['row']->name.' '.$x['row']->email_hint.' '.$x['row']->provider_key), $needle))
            ->filter(fn ($x) => match ($this->filter) {
                'credentials' => $x['keys'],
                'unconfirmed' => $x['row']->confirmed_at === null,
                default => true,
            })
            // Providers whose API keys we hold come first, then by category, then A-Z.
            ->sortBy(fn ($x) => [$x['keys'] ? 0 : 1, $cats[$x['row']->category] ?? 99, mb_strtolower($x['row']->name)])
            ->values();
    }

    public function render()
    {
        return view('livewire.admin.provider-logins', [
            'rows' => $this->rows(),
            'emails' => ProviderLoginEmail::orderBy('email')->get(),
            'methods' => Catalogue::METHODS,
            'categories' => Catalogue::CATEGORIES,
        ]);
    }
}
