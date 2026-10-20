<?php

namespace App\Livewire\Handbooks;

use App\Exceptions\InsufficientBalanceException;
use App\Models\Handbook;
use App\Services\Handbooks\HandbookAccess;
use App\Services\Handbooks\HandbookException;
use App\Services\Handbooks\HandbookPurchaseService;
use App\Services\Handbooks\HandbookUnlockService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/** The access options on a book page (MASTER-ONLY): the tiles adapt to the viewer (read, buy, upgrade, use a Pro slot, audiobook). Money buttons disable while in flight. */
class BuyPanel extends Component
{
    public int $handbookId = 0;

    public ?string $notice = null;

    public ?string $error = null;

    public ?float $shortBy = null;

    public function mount(int $handbookId): void
    {
        $this->handbookId = $handbookId;
    }

    private function book(): Handbook
    {
        return Handbook::query()->published()->with('audiobook')->findOrFail($this->handbookId);
    }

    public function buy(HandbookPurchaseService $svc): void
    {
        $this->notice = $this->error = null;
        $this->shortBy = null;
        abort_unless(Auth::check(), 403);
        $b = $this->book();
        try {
            $q = $svc->quote(Auth::user(), $b);
            $svc->buy(Auth::user(), $b);
            $this->notice = $q['source'] === 'upgrade' ? 'Download unlocked. You can now save the EPUB and PDF from your Library.' : 'It is yours. Find it in your Library.';
        } catch (InsufficientBalanceException) {
            $this->shortBy = max(0.01, round($svc->quote(Auth::user(), $b)['price'] - (float) (Auth::user()->fresh()->wallet?->usd_balance ?? 0), 2));
            $this->error = 'Your wallet is short. Add funds, then come back here.';
        } catch (HandbookException $e) {
            $this->error = $e->getMessage();
        }
    }

    public function useSlot(HandbookUnlockService $svc): void
    {
        $this->notice = $this->error = null;
        abort_unless(Auth::check(), 403);
        try {
            $svc->useSlot(Auth::user(), $this->book());
            $this->notice = 'Unlocked with your Naara Pro. Read it offline from your Library.';
        } catch (HandbookException $e) {
            $this->error = $e->getMessage();
        }
    }

    public function buyAudio(HandbookPurchaseService $svc): void
    {
        $this->notice = $this->error = null;
        abort_unless(Auth::check(), 403);
        $a = $this->book()->audiobook;
        if (! $a) {
            return;
        }
        try {
            $svc->buyAudiobook(Auth::user(), $a);
            $this->notice = 'The audiobook is in your Library.';
        } catch (InsufficientBalanceException) {
            $this->error = 'Your wallet is short. Add funds, then come back here.';
            $this->shortBy = max(0.01, round((float) $a->price_usd - (float) (Auth::user()->fresh()->wallet?->usd_balance ?? 0), 2));
        } catch (HandbookException $e) {
            $this->error = $e->getMessage();
        }
    }

    public function render()
    {
        $b = $this->book();
        $user = Auth::user();
        if ($user) {
            app(HandbookUnlockService::class)->syncProLocks($user);
        }
        $a = $b->audiobook && $b->audiobook->is_enabled ? $b->audiobook : null;

        return view('livewire.handbooks.buy-panel', [
            'b' => $b, 'offers' => app(HandbookAccess::class)->offers($user, $b), 'audio' => $a,
            'ownsAudio' => $a && $user ? \App\Models\HandbookAudioEntitlement::query()->where('user_id', $user->id)->where('audiobook_id', $a->id)->exists() : false,
            'balance' => (float) ($user?->wallet?->usd_balance ?? 0),
        ]);
    }
}
