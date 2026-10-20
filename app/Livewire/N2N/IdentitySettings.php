<?php

namespace App\Livewire\N2N;

use App\Models\Contact;
use App\Models\N2N\UserPhone;
use App\Services\N2N\Identity\ContactImporter;
use App\Services\N2N\Identity\NaaraId;
use App\Services\N2N\Identity\PhoneLinkService;
use App\Services\N2N\Identity\UsernamePolicy;
use App\Support\N2N\N2NSettings;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

/** Account > Naara ID and discovery (N2N, MASTER-ONLY): username, Naara ID, verified phone, discovery switches, contact import. */
#[Layout('components.layouts.n2n')]
class IdentitySettings extends Component
{
    use WithFileUploads;

    public string $username = '';

    public string $phone = '';

    public string $code = '';

    public bool $codeSent = false;

    public bool $byPhone = true;

    public bool $byEmail = false;

    public bool $hideMe = false;

    public bool $consent = false;

    public string $whoCanMessage = 'everyone';

    public bool $readReceipts = true;

    public bool $privateVisits = false;

    public bool $typingIndicator = true;

    public bool $hidePresence = false;

    public $contactsFile = null;

    public ?string $message = null;

    public ?string $error = null;

    public function mount(): void
    {
        $user = Auth::user();
        $this->ensureIdentity();
        $this->username = (string) $user->fresh()->username;
        $this->byEmail = (bool) $user->n2n_find_by_email;
        $this->hideMe = (bool) $user->n2n_hide_me;
        $this->whoCanMessage = (string) ($user->n2n_who_can_message ?: 'everyone');
        $this->readReceipts = (bool) $user->n2n_read_receipts;
        $this->privateVisits = (bool) $user->n2n_private_visits;
        $this->typingIndicator = (bool) $user->n2n_typing_indicator;
        $this->hidePresence = (bool) $user->n2n_hide_presence;
        $this->byPhone = (bool) (UserPhone::query()->where('user_id', $user->id)->value('discoverable') ?? false);
        if ($this->username === '') {
            $this->username = app(UsernamePolicy::class)->suggest($user);
        }
    }

    /** Every account gets its Naara ID the first time it opens N2N (the backfill command covers the rest). */
    private function ensureIdentity(): void
    {
        $user = Auth::user();
        NaaraId::for($user);
    }

    private function say(array $r): void
    {
        $this->message = $r['ok'] ? $r['message'] : null;
        $this->error = $r['ok'] ? null : $r['message'];
    }

    public function saveUsername(UsernamePolicy $policy): void
    {
        $this->say($policy->claim(Auth::user(), $this->username));
    }

    public function sendCode(PhoneLinkService $svc): void
    {
        $r = $svc->start(Auth::user(), $this->phone, request()->ip());
        $this->codeSent = $r['ok'];
        $this->say($r);
    }

    public function verifyCode(PhoneLinkService $svc): void
    {
        $r = $svc->verify(Auth::user(), $this->code);
        if ($r['ok']) {
            $this->reset('phone', 'code', 'codeSent');
        }
        $this->say($r);
    }

    public function unlinkPhone(PhoneLinkService $svc): void
    {
        $svc->unlink(Auth::user());
        $this->say(['ok' => true, 'message' => 'Number removed.']);
    }

    public function saveDiscovery(PhoneLinkService $svc): void
    {
        $user = Auth::user();
        $user->forceFill(['n2n_find_by_email' => $this->byEmail, 'n2n_hide_me' => $this->hideMe])->save();
        $svc->setDiscoverable($user, $this->byPhone);
        \App\Jobs\N2N\LinkContactsJob::dispatch(null, (string) UserPhone::query()->where('user_id', $user->id)->value('e164'));
        $this->say(['ok' => true, 'message' => 'Discovery settings saved.']);
    }

    public function savePrivacy(): void
    {
        $this->validate(['whoCanMessage' => 'required|in:everyone,connections,nobody']);
        Auth::user()->forceFill([
            'n2n_who_can_message' => $this->whoCanMessage, 'n2n_read_receipts' => $this->readReceipts,
            'n2n_typing_indicator' => $this->typingIndicator, 'n2n_hide_presence' => $this->hidePresence, 'n2n_private_visits' => $this->privateVisits,
        ])->save();
        $this->say(['ok' => true, 'message' => 'Privacy settings saved.']);
    }

    public function importContacts(ContactImporter $importer): void
    {
        $this->validate(['contactsFile' => 'required|file|max:2048|mimes:vcf,csv,txt']);
        $rows = $importer->parse((string) file_get_contents($this->contactsFile->getRealPath()));
        $this->say($importer->import(Auth::user(), $rows, $this->consent));
        $this->reset('contactsFile');
    }

    public function deleteImported(ContactImporter $importer): void
    {
        $this->say(['ok' => true, 'message' => 'Deleted '.$importer->deleteImported(Auth::user()).' imported contact(s).']);
    }

    public function render()
    {
        $user = Auth::user()->fresh();

        return view('livewire.n2n.identity-settings', [
            'naaraId' => $user->naara_id,
            'linked' => UserPhone::query()->where('user_id', $user->id)->exists(),
            'importedCount' => Contact::query()->where('user_id', $user->id)->whereNotNull('imported_at')->count(),
            'knownCount' => Contact::query()->where('user_id', $user->id)->whereNotNull('linked_user_id')->count(),
            'importMax' => N2NSettings::importMaxContacts(),
        ]);
    }
}
