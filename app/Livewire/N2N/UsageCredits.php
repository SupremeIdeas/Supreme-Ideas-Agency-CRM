<?php

namespace App\Livewire\N2N;

use App\Models\N2N\CreditGift;
use App\Models\N2N\UsageLedger;
use App\Services\Wallet\WalletService;
use App\Services\N2N\Credit\CreditGifts;
use App\Services\N2N\Credit\Money;
use App\Services\N2N\Credit\UsageCredit;
use App\Services\N2N\Identity\IdentityResolver;
use App\Services\N2N\Identity\ResolveThrottled;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * NaaraCredit for N2N (MASTER-ONLY): both balances, buy with wallet money, share, gift to someone not on Naara yet (24h escrow), pending
 * gifts with a countdown, and history. Every money button carries a per-form idempotency key and disables itself while in flight.
 */
#[Layout('components.layouts.n2n')]
class UsageCredits extends Component
{
    public string $buyUsd = '10';

    public string $buyKey = '';

    public string $shareTo = '';

    public string $shareAmount = '';

    public string $shareKey = '';

    public string $giftTo = '';

    public string $giftAmount = '';

    public string $giftKey = '';

    public ?array $recipient = null;

    public ?string $message = null;

    public ?string $error = null;

    public bool $needMore = false;

    public function mount(): void
    {
        $this->rotateKeys();
    }

    private function rotateKeys(): void
    {
        $this->buyKey = (string) Str::uuid();
        $this->shareKey = (string) Str::uuid();
        $this->giftKey = (string) Str::uuid();
    }

    private function say(bool $ok, string $text): void
    {
        $this->message = $ok ? $text : null;
        $this->error = $ok ? null : $text;
    }

    /** Live, debounced recipient check (rate-limited by the resolver). Shows a name, never an email or number. */
    public function updatedShareTo(IdentityResolver $resolver): void
    {
        $this->recipient = null;
        if (strlen(trim($this->shareTo)) < 3) {
            return;
        }
        try {
            $hit = $resolver->resolve($this->shareTo, 'search', Auth::user(), request()->ip());
        } catch (ResolveThrottled $e) {
            $this->say(false, $e->getMessage());

            return;
        }
        $this->recipient = $hit->found ? ['name' => $hit->name, 'username' => $hit->username, 'naara_id' => $hit->naaraId] : ['name' => null];
    }

    public function buy(UsageCredit $credit): void
    {
        $this->needMore = false;
        if (! $credit->purchaseAllowed(request()->userAgent())) {
            $this->say(false, 'Buying credit is not available in this app yet. Use the website.');

            return;
        }
        try {
            $r = $credit->purchase(Auth::user(), (float) $this->buyUsd, $this->buyKey);
        } catch (\App\Exceptions\InsufficientBalanceException) {
            $this->say(false, 'Your wallet balance is too low. Top up your wallet first.');

            return;
        } catch (\InvalidArgumentException $e) {
            $this->say(false, $e->getMessage());

            return;
        } catch (\Throwable $e) {
            report($e);
            $this->say(false, 'We could not complete that. You were not charged.');

            return;
        }
        $this->buyKey = (string) Str::uuid();
        $this->say(true, 'Added '.$r['credits'].' credits.');
    }

    public function share(UsageCredit $credit): void
    {
        try {
            $r = $credit->share(Auth::user(), $this->shareTo, (float) $this->shareAmount, $this->shareKey, request()->ip());
        } catch (ResolveThrottled $e) {
            $this->say(false, $e->getMessage());

            return;
        }
        if ($r['ok']) {
            $this->reset('shareTo', 'shareAmount', 'recipient');
            $this->shareKey = (string) Str::uuid();
        }
        $this->say($r['ok'], $r['message']);
    }

    public function gift(CreditGifts $gifts): void
    {
        $r = $gifts->create(Auth::user(), $this->giftTo, (float) $this->giftAmount, $this->giftKey, request()->ip());
        if ($r['ok']) {
            $this->reset('giftTo', 'giftAmount');
            $this->giftKey = (string) Str::uuid();
        }
        $this->say($r['ok'], $r['message']);
    }

    public function render(UsageCredit $credit)
    {
        $user = Auth::user();
        $b = $credit->balances($user);

        return view('livewire.n2n.usage-credits', [
            'bal' => array_map(fn ($c) => Money::credits($c), $b),
            'canBuy' => $credit->purchaseAllowed(request()->userAgent()),
            'walletUsd' => number_format((float) optional($user->wallet)->usd_balance, 2),
            'pending' => CreditGift::query()->where('sender_id', $user->id)->where('status', CreditGift::PENDING)->orderBy('expires_at')->get(),
            'history' => UsageLedger::query()->where('user_id', $user->id)->orderByDesc('id')->limit(20)->get(),
        ]);
    }
}
