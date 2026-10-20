<?php

namespace App\Services\Handbooks;

use App\Exceptions\InsufficientBalanceException;
use App\Jobs\AlertAdminJob;
use App\Models\Handbook;
use App\Models\HandbookAudioEntitlement;
use App\Models\HandbookAudiobook;
use App\Models\HandbookEntitlement;
use App\Models\User;
use App\Services\Platform\PlatformEarningsService;
use App\Services\Wallet\WalletService;
use App\Support\Handbooks\HandbookPricing;
use App\Support\Notifications\Notify;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Buying a handbook, its download upgrade, or its audiobook (MASTER-ONLY). Money rules: a live published price, an atomic wallet debit idempotent on a reference that
 * identifies THIS person, THIS book and THIS target level (so a double click or a retry can never charge twice), the entitlement written right after, and an automatic
 * refund if the entitlement cannot be written (never charge without delivering). The price paid is snapshotted on the entitlement. Revenue is the admin's own income:
 * accrued once to the platform earnings bucket on the same reference. Refund requests from buyers are human-reviewed only (never automatic here).
 */
class HandbookPurchaseService
{
    public function __construct(private readonly WalletService $wallet, private readonly PlatformEarningsService $earnings)
    {
    }

    /** What buying this book would cost this person right now, and what it grants. @return array{price:float, target:string, source:string} @throws HandbookException */
    public function quote(User $user, Handbook $b): array
    {
        if ($b->status !== Handbook::PUBLISHED) {
            throw new HandbookException('That handbook is not available.');
        }
        if (blank($b->epub_path)) {
            throw new HandbookException('That handbook is not available.');   // defence in depth: an unfinished title can never be bought, whatever its status says
        }
        $level = app(HandbookAccess::class)->levelFor($user, $b);
        if ($level === HandbookEntitlement::OWNED) {
            throw new HandbookException('You already own this handbook.');
        }
        if (! \App\Support\Handbooks\HandbookSettings::get('purchase_enabled') || ! $b->allow_purchase) {
            throw new HandbookException('This handbook cannot be bought right now.');
        }
        if ($level === HandbookEntitlement::UNLOCKED && ! \App\Support\Handbooks\HandbookSettings::get('upgrade_enabled')) {
            throw new HandbookException('Download upgrades are not available right now.');
        }

        return $level === HandbookEntitlement::UNLOCKED
            ? ['price' => HandbookPricing::upgradeUsd($b->list_price_usd), 'target' => HandbookEntitlement::OWNED, 'source' => 'upgrade']
            : ['price' => HandbookPricing::fullUsd($b->list_price_usd), 'target' => HandbookEntitlement::OWNED, 'source' => 'purchase'];
    }

    /**
     * @throws HandbookException            not available, or already owned
     * @throws InsufficientBalanceException the caller sends the person through the normal top-up flow and back to the book
     */
    public function buy(User $user, Handbook $b): HandbookEntitlement
    {
        $q = $this->quote($user, $b);
        if ($q['price'] <= 0) {
            throw new HandbookException('This handbook has no price set yet.');
        }
        $ref = 'handbook:'.$user->id.':'.$b->id.':'.$q['source'];
        $label = $q['source'] === 'upgrade' ? 'Handbook download upgrade' : 'Handbook purchase';

        $this->wallet->debit($user, $q['price'], 'USD', ['reference' => $ref, 'description' => $label.': '.$b->title]);
        try {
            $e = HandbookEntitlement::query()->updateOrCreate(
                ['user_id' => $user->id, 'handbook_id' => $b->id],
                ['level' => HandbookEntitlement::OWNED, 'source' => $q['source'], 'source_ref' => $ref, 'price_paid_usd' => $q['price'], 'locked_at' => null],
            );
        } catch (Throwable $ex) {
            $this->wallet->refund($user, $q['price'], 'USD', ['reference' => 'refund:'.$ref, 'description' => 'Handbook could not be delivered', 'notify' => false]);
            Log::warning('Handbook entitlement failed after debit: '.$ex->getMessage());
            AlertAdminJob::dispatch(code: 'handbook_orphan_charge', message: "Handbook {$b->id} charge {$ref} was refunded because the entitlement could not be written.", context: ['user_id' => $user->id, 'handbook_id' => $b->id]);
            throw new HandbookException('We could not unlock the handbook, so you were not charged.');
        }
        $this->earnings->accrue($q['price'], 'handbook', 'earn:'.$ref, $label.' #'.$b->id);
        Notify::event($user, $q['source'] === 'upgrade' ? 'handbook.upgraded' : 'handbook.purchased', ['title' => $b->title, 'verified' => $b->last_verified_on?->format('M j, Y') ?? 'recently'], dedupe: 'hb-'.$ref);

        return $e;
    }

    /** @throws HandbookException @throws InsufficientBalanceException */
    public function buyAudiobook(User $user, HandbookAudiobook $a): HandbookAudioEntitlement
    {
        $b = $a->handbook;
        if (! $a->is_enabled || ! $b || $b->status !== Handbook::PUBLISHED || (float) $a->price_usd <= 0) {
            throw new HandbookException('That audiobook is not available.');
        }
        if (HandbookAudioEntitlement::query()->where('user_id', $user->id)->where('audiobook_id', $a->id)->exists()) {
            throw new HandbookException('You already own this audiobook.');
        }
        $price = HandbookPricing::fullUsd($a->price_usd);
        $ref = 'handbookaudio:'.$user->id.':'.$a->id;
        $this->wallet->debit($user, $price, 'USD', ['reference' => $ref, 'description' => 'Audiobook: '.$b->title]);
        try {
            $e = HandbookAudioEntitlement::query()->create(['user_id' => $user->id, 'audiobook_id' => $a->id, 'source_ref' => $ref, 'price_paid_usd' => $price]);
        } catch (UniqueConstraintViolationException) {
            return HandbookAudioEntitlement::query()->where('user_id', $user->id)->where('audiobook_id', $a->id)->firstOrFail();   // a double click: the first charge stands, nothing more
        } catch (Throwable $ex) {
            $this->wallet->refund($user, $price, 'USD', ['reference' => 'refund:'.$ref, 'description' => 'Audiobook could not be delivered', 'notify' => false]);
            AlertAdminJob::dispatch(code: 'handbook_orphan_charge', message: "Audiobook {$a->id} charge {$ref} was refunded because the entitlement could not be written.", context: ['user_id' => $user->id]);
            throw new HandbookException('We could not unlock the audiobook, so you were not charged.');
        }
        $this->earnings->accrue($price, 'handbook', 'earn:'.$ref, 'Audiobook sale #'.$a->id);
        Notify::event($user, 'handbook.audiobook_purchased', ['title' => $b->title], dedupe: 'hb-'.$ref);

        return $e;
    }
}
