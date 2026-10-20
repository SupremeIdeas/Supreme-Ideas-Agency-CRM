<?php

namespace App\Services\Handbooks;

use App\Models\Handbook;
use App\Models\HandbookEntitlement;
use App\Models\HandbookRefundRequest;
use App\Models\User;
use App\Services\Platform\PlatformEarningsService;
use App\Services\Wallet\WalletService;
use App\Support\Auditor;
use App\Support\Notifications\Notify;
use Illuminate\Support\Facades\DB;

/**
 * Handbook refund requests (MASTER-ONLY). Decision D7: refunds are never automatic. A buyer asks within the window; a person on the team approves or declines. Approving
 * returns the exact price paid to the wallet (idempotent on the request, so a double click never refunds twice), takes the same amount back out of the platform earnings,
 * and ends the access that money bought (a bought book is removed; a download upgrade drops back to the unlocked read-only level the person still holds).
 */
class HandbookRefundService
{
    public function __construct(private readonly WalletService $wallet, private readonly PlatformEarningsService $earnings)
    {
    }

    /** Why this person cannot ask for a refund on this entitlement right now, or null when they can. */
    public function problem(HandbookEntitlement $e): ?string
    {
        if (! in_array($e->source, ['purchase', 'upgrade'], true) || (float) $e->price_paid_usd <= 0) {
            return 'Only a handbook you paid for can be refunded.';
        }
        if (HandbookRefundRequest::query()->where('user_id', $e->user_id)->where('handbook_id', $e->handbook_id)->where('status', HandbookRefundRequest::PENDING)->exists()) {
            return 'You already have a refund request waiting for this handbook.';
        }
        if (HandbookRefundRequest::query()->where('entitlement_id', $e->id)->where('status', HandbookRefundRequest::APPROVED)->exists()) {
            return 'This purchase was already refunded.';
        }
        $days = (int) config('handbooks.refund_window_days', 7);
        if ($e->updated_at && $e->updated_at->lt(now()->subDays($days))) {
            return "Refund requests are open for {$days} days after you pay.";
        }

        return null;
    }

    /** @throws HandbookException */
    public function request(User $user, HandbookEntitlement $e, ?string $reason): HandbookRefundRequest
    {
        if ($e->user_id !== $user->id) {
            throw new HandbookException('That handbook is not in your library.');
        }
        if (($why = $this->problem($e)) !== null) {
            throw new HandbookException($why);
        }

        return HandbookRefundRequest::query()->create([
            'user_id' => $user->id, 'handbook_id' => $e->handbook_id, 'entitlement_id' => $e->id, 'source' => (string) $e->source,
            'amount_usd' => $e->price_paid_usd, 'reason' => $reason !== null ? mb_substr(trim($reason), 0, 1000) : null, 'status' => HandbookRefundRequest::PENDING,
        ]);
    }

    /** @throws HandbookException */
    public function approve(HandbookRefundRequest $req, User $by, ?string $note = null): HandbookRefundRequest
    {
        $req = DB::transaction(function () use ($req, $by, $note) {
            $r = HandbookRefundRequest::query()->lockForUpdate()->findOrFail($req->id);
            if ($r->status !== HandbookRefundRequest::PENDING) {
                throw new HandbookException('This request has already been decided.');
            }
            $ref = 'hbrefund:'.$r->id;
            $user = User::query()->findOrFail($r->user_id);
            $this->wallet->refund($user, (float) $r->amount_usd, 'USD', ['reference' => $ref, 'description' => 'Handbook refund', 'notify' => false]);
            $this->earnings->reverse((float) $r->amount_usd, 'handbook', 'rev:'.$ref, 'Handbook refund #'.$r->id);
            $e = HandbookEntitlement::query()->find($r->entitlement_id);
            if ($e) {
                if ($r->source === 'upgrade') {
                    $e->forceFill(['level' => HandbookEntitlement::UNLOCKED, 'source' => 'refunded_upgrade', 'price_paid_usd' => 0])->save();
                } else {
                    $e->delete();
                }
            }
            $r->forceFill(['status' => HandbookRefundRequest::APPROVED, 'decided_by' => $by->id, 'decided_at' => now(), 'decision_note' => $note])->save();
            Auditor::log('handbook.refund_approved', Handbook::class, $r->handbook_id, ['request' => $r->id, 'amount' => (string) $r->amount_usd, 'by' => $by->id]);

            return $r;
        });
        $this->tell($req, 'approved');

        return $req;
    }

    /** @throws HandbookException */
    public function decline(HandbookRefundRequest $req, User $by, ?string $note = null): HandbookRefundRequest
    {
        $req = DB::transaction(function () use ($req, $by, $note) {
            $r = HandbookRefundRequest::query()->lockForUpdate()->findOrFail($req->id);
            if ($r->status !== HandbookRefundRequest::PENDING) {
                throw new HandbookException('This request has already been decided.');
            }
            $r->forceFill(['status' => HandbookRefundRequest::DECLINED, 'decided_by' => $by->id, 'decided_at' => now(), 'decision_note' => $note])->save();
            Auditor::log('handbook.refund_declined', Handbook::class, $r->handbook_id, ['request' => $r->id, 'by' => $by->id]);

            return $r;
        });
        $this->tell($req, 'declined');

        return $req;
    }

    private function tell(HandbookRefundRequest $r, string $outcome): void
    {
        $user = User::query()->find($r->user_id);
        $b = Handbook::query()->find($r->handbook_id);
        if ($user && $b) {
            Notify::event($user, 'handbook.refund_decided', ['title' => $b->title, 'outcome' => $outcome, 'note' => (string) $r->decision_note], dedupe: 'hbrefund-'.$r->id);
        }
    }
}
