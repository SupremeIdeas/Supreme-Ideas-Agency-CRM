<?php

namespace App\Livewire\Admin\N2N;

use App\Models\N2N\UserPhone;
use App\Models\User;
use App\Services\N2N\Identity\IdentityResolver;
use App\Services\N2N\Identity\PhoneLinkService;
use App\Services\N2N\Identity\UsernamePolicy;
use App\Support\Auditor;
use App\Support\N2N\N2NSettings;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/** Admin > Users: look up a person by Naara ID, username or phone; rename or release a phone (N2N, MASTER-ONLY). Every action is audited. */
class IdentityLookup extends Component
{
    public string $q = '';

    public string $newUsername = '';

    public ?int $foundId = null;

    public ?string $message = null;

    private function admin(): User
    {
        $u = Auth::user();
        abort_unless($u && $u->hasAnyRole(['super_admin', 'admin']) && N2NSettings::enabled(), 403);

        return $u;
    }

    public function find(IdentityResolver $resolver): void
    {
        $admin = $this->admin();
        $this->message = null;
        $r = $resolver->resolve($this->q, 'deliver', $admin);
        $this->foundId = $r->found ? $r->userId : null;
        Auditor::log('n2n.admin_lookup', User::class, $this->foundId, ['by' => $admin->id, 'hit' => $r->found]);
        if (! $r->found) {
            $this->message = 'No match.';
        }
    }

    public function rename(UsernamePolicy $policy): void
    {
        $admin = $this->admin();
        $user = User::query()->findOrFail($this->foundId);
        $this->message = $policy->forceRename($user, $this->newUsername, $admin)['message'];
    }

    public function releasePhone(PhoneLinkService $svc): void
    {
        $admin = $this->admin();
        $svc->unlink(User::query()->findOrFail($this->foundId), $admin);
        $this->message = 'Phone released.';
    }

    public function render()
    {
        $user = $this->foundId ? User::query()->find($this->foundId) : null;
        $phone = $user ? UserPhone::query()->where('user_id', $user->id)->value('e164') : null;

        return view('livewire.admin.n2n.identity-lookup', [
            'user' => $user,
            'maskedPhone' => $phone ? substr($phone, 0, 4).str_repeat('•', max(0, strlen($phone) - 7)).substr($phone, -3) : null,
        ]);
    }
}
