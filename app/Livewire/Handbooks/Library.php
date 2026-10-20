<?php

namespace App\Livewire\Handbooks;

use App\Models\HandbookAudioEntitlement;
use App\Models\Handbook;
use App\Models\HandbookEntitlement;
use App\Models\HandbookEsimPick;
use App\Models\HandbookReadingState;
use App\Services\Handbooks\HandbookEsimUnlockService;
use App\Services\Handbooks\HandbookException;
use App\Services\Handbooks\HandbookRefundService;
use App\Services\Handbooks\HandbookUnlockService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/** The member's handbook Library (MASTER-ONLY): everything they can read, with progress, continue-reading, a country filter, download badges and the audiobook shelf. */
#[Layout('components.layouts.customer')]
class Library extends Component
{
    #[Url(as: 'country', except: '')]
    public string $country = '';

    public ?string $notice = null;

    public ?string $error = null;

    public ?int $refundFor = null;

    public string $refundReason = '';

    /** Choose the free handbook a regional or global eSIM earned. */
    public function choosePick(int $pickId, int $handbookId): void
    {
        $this->reset('notice', 'error');
        try {
            $b = app(HandbookEsimUnlockService::class)->choose(Auth::user(), $pickId, $handbookId);
            $this->notice = $b->title.' is unlocked. Happy reading.';
        } catch (HandbookException $e) {
            $this->error = $e->getMessage();
        }
    }

    public function openRefund(int $entitlementId): void
    {
        $this->reset('notice', 'error', 'refundReason');
        $this->refundFor = HandbookEntitlement::query()->where('user_id', Auth::id())->whereKey($entitlementId)->exists() ? $entitlementId : null;
    }

    public function cancelRefund(): void
    {
        $this->reset('refundFor', 'refundReason');
    }

    /** Ask for a refund. A person on the team decides; nothing is refunded automatically. */
    public function requestRefund(HandbookRefundService $svc): void
    {
        $this->reset('notice', 'error');
        $this->validate(['refundReason' => ['nullable', 'string', 'max:1000']]);
        try {
            $e = HandbookEntitlement::query()->where('user_id', Auth::id())->findOrFail($this->refundFor);
            $svc->request(Auth::user(), $e, $this->refundReason);
            $this->notice = 'Your refund request is with our team. We will email you the answer.';
            $this->reset('refundFor', 'refundReason');
        } catch (HandbookException $ex) {
            $this->error = $ex->getMessage();
        }
    }

    /** Spend a Naara Pro slot. */
    public function useSlot(int $handbookId): void
    {
        $this->reset('notice', 'error');
        try {
            $b = Handbook::query()->published()->findOrFail($handbookId);
            app(HandbookUnlockService::class)->useSlot(Auth::user(), $b);
            $this->notice = $b->title.' is unlocked with your Pro slot.';
        } catch (HandbookException $e) {
            $this->error = $e->getMessage();
        }
    }

    public function render()
    {
        $user = Auth::user();
        app(HandbookUnlockService::class)->syncProLocks($user);
        $rows = HandbookEntitlement::query()->where('user_id', $user->id)->with('handbook')->latest('id')->get()->filter(fn ($e) => $e->handbook && $e->handbook->status !== 'draft');
        $progress = HandbookReadingState::query()->where('user_id', $user->id)->get()->keyBy('handbook_id');
        $countries = $rows->pluck('handbook.country_code')->filter()->unique()->sort()->values();
        if ($this->country !== '') {
            $rows = $rows->filter(fn ($e) => strtoupper((string) $e->handbook->country_code) === strtoupper($this->country));
        }
        $continue = $rows->filter(fn ($e) => $e->locked_at === null && ($progress[$e->handbook_id]->percent ?? 0) > 0 && ($progress[$e->handbook_id]->percent ?? 0) < 99)
            ->sortByDesc(fn ($e) => $progress[$e->handbook_id]->updated_at)->first();
        $audio = HandbookAudioEntitlement::query()->where('user_id', $user->id)->with('audiobook.handbook')->latest('id')->get()->filter(fn ($a) => $a->audiobook && $a->audiobook->handbook);

        $svc = app(HandbookUnlockService::class);
        $picks = HandbookEsimPick::query()->where('user_id', $user->id)->whereNull('picked_at')->get()->map(fn ($p) => ['id' => $p->id, 'left' => $p->picksLeft(), 'options' => app(HandbookEsimUnlockService::class)->candidates((array) $p->country_codes)->whereNotIn('id', (array) $p->chosen_ids ?: [0])->orderBy('country_code')->get(['id', 'title', 'country_code'])]);
        $slotsLeft = $svc->slotsLeft($user);
        $held = HandbookEntitlement::query()->where('user_id', $user->id)->whereNull('locked_at')->pluck('handbook_id');
        $slotOptions = $slotsLeft > 0 ? Handbook::query()->published()->where('unlock_via_pro', true)->whereNotIn('id', $held)->where('series_slot', 'S1')->orderBy('country_code')->limit(60)->get(['id', 'title', 'country_code']) : collect();

        $refunds = \App\Models\HandbookRefundRequest::query()->where('user_id', $user->id)->where('status', 'pending')->pluck('handbook_id')->all();
        $refundable = $rows->filter(fn ($e) => $e->locked_at === null && app(HandbookRefundService::class)->problem($e) === null)->pluck('id')->all();

        return view('livewire.handbooks.library', ['refunds' => $refunds, 'refundable' => $refundable, 'picks' => $picks, 'slotsLeft' => $slotsLeft, 'slotOptions' => $slotOptions, 'slotsTotal' => \App\Support\Handbooks\HandbookSettings::proSlots(), 'isPro' => $svc->proTerm($user) !== null,'rows' => $rows->values(), 'progress' => $progress, 'countries' => $countries, 'continue' => $continue, 'audio' => $audio]);
    }
}
