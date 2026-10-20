<?php

namespace App\Livewire;

use App\Models\BrandPartner;
use App\Services\Brands\BusinessCardShareService;
use App\Services\N2N\Identity\IdentityResolver;
use App\Services\N2N\Identity\ResolveThrottled;
use App\Services\N2N\Messaging\MessagingException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Component;

/** "Send in Naara Chat" on a Business Card page (Brand-Growth Phase 5, MASTER-ONLY): look the person up by Naara ID, @username or phone, then send the card as a chat card. */
class BusinessCardShare extends Component
{
    public int $brandId = 0;

    public string $to = '';

    public ?array $hit = null;

    public ?string $notice = null;

    public ?string $error = null;

    public string $key = '';

    public function mount(int $brandId): void
    {
        $this->brandId = $brandId;
        $this->key = Str::random(24);
    }

    public function updatedTo(IdentityResolver $resolver): void
    {
        $this->hit = null;
        $this->notice = $this->error = null;
        if (strlen(trim($this->to)) < 3) {
            return;
        }
        try {
            $r = $resolver->resolve($this->to, 'search', Auth::user(), request()->ip());
        } catch (ResolveThrottled $e) {
            $this->error = $e->getMessage();

            return;
        }
        $this->hit = $r->found && (int) $r->userId !== (int) Auth::id() ? ['name' => $r->name, 'naara_id' => $r->naaraId] : ['name' => null];
    }

    public function send(BusinessCardShareService $svc): void
    {
        $this->notice = $this->error = null;
        if (empty($this->hit['naara_id'])) {
            $this->error = 'We could not find that person on Naara.';

            return;
        }
        try {
            $svc->share(Auth::user(), (string) $this->hit['naara_id'], $this->brandId, 'bcs'.substr(sha1($this->key.$this->hit['naara_id'].$this->brandId), 0, 24), request()->ip());
            $this->notice = 'Sent to '.($this->hit['name'] ?: 'them').'.';
            $this->reset('to', 'hit');
            $this->key = Str::random(24);   // a second share is a new message
        } catch (MessagingException $e) {
            $this->error = $e->getMessage();
        }
    }

    public function render()
    {
        return view('livewire.business-card-share', ['listed' => BrandPartner::listed()->whereKey($this->brandId)->exists()]);
    }
}
