<?php

namespace App\Livewire\Admin;

use App\Models\PayoutAccount;
use App\Services\Payouts\Crypto\CryptoWhitelist as Whitelist;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Admin -> Payouts -> Crypto wallet whitelist. NOWPayments only pays wallet addresses on its dashboard whitelist,
 * so every new address a user saves lands here as `pending`. The admin downloads the new ones as a CSV, adds
 * them in the NOWPayments dashboard (Settings -> Whitelist), then marks them whitelisted here. Until then the
 * Guardian waits (never refuses, never refunds) any payout to that address.
 */
#[Layout('components.layouts.admin')]
class CryptoWhitelist extends Component
{
    /** @var list<int> */
    public array $selected = [];

    public ?string $message = null;

    private function authorizeFinance(): void
    {
        $u = Auth::user();
        abort_unless($u?->hasAnyRole(['super_admin', 'admin']) || $u?->can('payouts.finance'), 403);
    }

    /** CSV of the NEW (pending) addresses. They move to "exported" so the next download is only what is new. */
    public function exportNew(Whitelist $wl): StreamedResponse
    {
        $this->authorizeFinance();
        $csv = $wl->exportCsv(Auth::user());

        return response()->streamDownload(fn () => print($csv), 'naara-crypto-whitelist-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv']);
    }

    /** Re-download everything still awaiting activation (pending + exported), without changing any status. */
    public function exportAll(Whitelist $wl): StreamedResponse
    {
        $this->authorizeFinance();
        $csv = $wl->exportCsv(Auth::user(), includeExported: true);

        return response()->streamDownload(fn () => print($csv), 'naara-crypto-whitelist-all-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv']);
    }

    public function markSelected(Whitelist $wl): void
    {
        $this->authorizeFinance();
        $n = $wl->markWhitelisted(Auth::user(), array_map('intval', $this->selected));
        $this->selected = [];
        $this->message = $n === 1 ? '1 address marked as whitelisted.' : "{$n} addresses marked as whitelisted.";
    }

    public function markAllExported(Whitelist $wl): void
    {
        $this->authorizeFinance();
        $ids = $wl->queue(PayoutAccount::WL_EXPORTED)->pluck('id')->all();
        $n = $wl->markWhitelisted(Auth::user(), $ids);
        $this->message = "{$n} exported addresses marked as whitelisted.";
    }

    public function render(Whitelist $wl)
    {
        $this->authorizeFinance();

        return view('livewire.admin.crypto-whitelist', [
            'counts' => $wl->counts(),
            'pending' => $wl->queue(PayoutAccount::WL_PENDING),
            'exported' => $wl->queue(PayoutAccount::WL_EXPORTED),
        ]);
    }
}
