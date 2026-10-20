<?php

namespace App\Services\WhiteLabel\Funnel;

use App\Exceptions\InsufficientBalanceException;
use App\Jobs\AlertAdminJob;
use App\Models\User;
use App\Models\WlCustomOrder as O;
use App\Models\WlCustomOrderMessage;
use App\Services\Platform\PlatformEarningsService;
use App\Services\Wallet\WalletService;
use App\Support\FeatureFlags;
use App\Support\Notifications\Notify;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * White Label custom orders (Funnel Phase 8, MASTER-ONLY). A buyer describes a custom build, an admin prices it, the buyer accepts and pays.
 * Money rules: the total is computed from the line items on the SERVER (never taken from the client), a quote has an expiry, an order can be paid
 * once (atomic wallet debit idempotent on the order id, then the order is marked paid; if marking fails the debit is refunded and the admin alerted),
 * and revenue accrues to platform earnings once on the same reference. Paying by transfer is offline: an admin marks it paid with the bank or chain
 * reference, which is audited on the order.
 */
class WlCustomOrders
{
    public const MODULES = [
        'skins' => 'More skins',
        'theme' => 'A custom theme designed for my brand',
        'languages' => 'Extra languages and local copy',
        'providers' => 'Extra provider integrations',
        'gateways' => 'Extra payment gateways',
        'migration' => 'Migrating an existing platform or customers',
        'hosting' => 'Server setup and deployment help',
        'training' => 'Training for my team',
        'other' => 'Something else (describe below)',
    ];

    public const BUDGETS = ['under_1k' => 'Under $1,000', '1k_3k' => '$1,000 to $3,000', '3k_10k' => '$3,000 to $10,000', 'over_10k' => 'Over $10,000', 'unsure' => 'Not sure yet'];

    public const TIMELINES = ['asap' => 'As soon as possible', 'month' => 'Within a month', 'quarter' => 'Within three months', 'flexible' => 'Flexible'];

    public const MAX_OPEN_PER_USER = 3;

    public const MAX_TOTAL_USD = 100000;

    public function __construct(private readonly WalletService $wallet, private readonly PlatformEarningsService $earnings)
    {
    }

    public static function enabled(): bool
    {
        return FeatureFlags::enabled('wl_custom_orders');
    }

    /** @return array<string,mixed> the cleaned request @throws ValidationException */
    private function validateRequest(array $in): array
    {
        $v = validator($in, [
            'brand_name' => ['required', 'string', 'max:120'],
            'contact_email' => ['required', 'email', 'max:190'],
            'contact_whatsapp' => ['nullable', 'string', 'max:40', 'regex:/^[0-9+()\-\s]{6,40}$/'],
            'modules' => ['required', 'array', 'min:1'],
            'modules.*' => ['string', 'in:'.implode(',', array_keys(self::MODULES))],
            'budget_band' => ['nullable', 'in:'.implode(',', array_keys(self::BUDGETS))],
            'timeline' => ['nullable', 'in:'.implode(',', array_keys(self::TIMELINES))],
            'brief' => ['required', 'string', 'min:30', 'max:4000'],
            'reference_link' => ['nullable', 'url', 'max:300'],
        ], ['modules.required' => 'Tick at least one thing you need.', 'brief.min' => 'Tell us a little more (at least 30 characters) so we can price it properly.']);

        return $v->validate();
    }

    /** @throws ValidationException @throws WlCustomOrderException */
    public function request(User $user, array $in): O
    {
        if (! self::enabled()) {
            throw new WlCustomOrderException('Custom orders are not open right now.');
        }
        $data = $this->validateRequest($in);
        if (O::query()->where('user_id', $user->id)->whereIn('status', O::OPEN)->count() >= self::MAX_OPEN_PER_USER) {
            throw new WlCustomOrderException('You already have '.self::MAX_OPEN_PER_USER.' open custom requests. Finish or cancel one first.');
        }
        $order = O::query()->create($data + ['reference' => $this->newReference(), 'user_id' => $user->id, 'status' => O::REQUESTED, 'modules' => array_values(array_unique($data['modules']))]);
        AlertAdminJob::dispatch(code: 'wl_custom_order_new', message: "New White Label custom request {$order->reference} from {$order->brand_name}.", context: ['order_id' => $order->id]);

        return $order;
    }

    /**
     * Admin prices a request. Items are [{label, amount}]; the total is their sum, computed here.
     *
     * @param  list<array{label:string, amount:mixed}>  $items
     *
     * @throws ValidationException @throws WlCustomOrderException
     */
    public function quote(O $order, User $admin, array $items, ?string $note, int $validDays = 14): O
    {
        if (! in_array($order->status, [O::REQUESTED, O::QUOTED], true)) {
            throw new WlCustomOrderException('This order can no longer be quoted.');
        }
        $clean = [];
        foreach ($items as $i) {
            $label = trim((string) ($i['label'] ?? ''));
            $amount = is_numeric($i['amount'] ?? null) ? round((float) $i['amount'], 2) : null;
            if ($label === '' && $amount === null) {
                continue;
            }
            if ($label === '' || $amount === null || $amount <= 0) {
                throw ValidationException::withMessages(['items' => 'Every line needs a description and an amount above zero.']);
            }
            $clean[] = ['label' => mb_substr($label, 0, 160), 'amount' => $amount];
        }
        if ($clean === []) {
            throw ValidationException::withMessages(['items' => 'Add at least one priced line.']);
        }
        $total = round(array_sum(array_column($clean, 'amount')), 2);
        if ($total > self::MAX_TOTAL_USD) {
            throw ValidationException::withMessages(['items' => 'That total is above the allowed maximum of $'.number_format(self::MAX_TOTAL_USD).'.']);
        }
        $validDays = max(1, min(60, $validDays));
        $order->forceFill(['status' => O::QUOTED, 'quote_items' => $clean, 'quote_total_usd' => $total, 'quote_note' => $note ? mb_substr($note, 0, 2000) : null,
            'quote_valid_until' => now()->addDays($validDays), 'quoted_by' => $admin->id, 'quoted_at' => now(), 'accepted_at' => null])->save();
        Notify::event($order->user, 'wl_custom_order.quoted', ['reference' => $order->reference, 'total' => number_format($total, 2), 'until' => $order->quote_valid_until->format('M j, Y')], dedupe: 'wlco-q-'.$order->id.'-'.$order->quoted_at->timestamp);

        return $order;
    }

    /** @throws WlCustomOrderException */
    public function accept(O $order, User $buyer): O
    {
        $this->own($order, $buyer);
        if ($order->status === O::ACCEPTED) {
            return $order;   // a double click
        }
        if ($order->status !== O::QUOTED) {
            throw new WlCustomOrderException('There is no quote to accept.');
        }
        if ($order->quoteExpired()) {
            $order->forceFill(['status' => O::EXPIRED])->save();
            throw new WlCustomOrderException('That quote has expired. Message us and we will re-issue it.');
        }
        $order->forceFill(['status' => O::ACCEPTED, 'accepted_at' => now()])->save();
        AlertAdminJob::dispatch(code: 'wl_custom_order_accepted', message: "Quote on {$order->reference} accepted ($".number_format((float) $order->quote_total_usd, 2).').', context: ['order_id' => $order->id]);

        return $order;
    }

    public function decline(O $order, User $buyer, ?string $reason): O
    {
        $this->own($order, $buyer);
        if (! in_array($order->status, [O::REQUESTED, O::QUOTED, O::ACCEPTED], true)) {
            throw new WlCustomOrderException('This order is already closed.');
        }
        $order->forceFill(['status' => O::CANCELLED, 'decline_reason' => $reason ? mb_substr($reason, 0, 500) : null])->save();

        return $order;
    }

    /** Admin turns a request down. */
    public function reject(O $order, string $reason): O
    {
        if (! in_array($order->status, [O::REQUESTED, O::QUOTED, O::ACCEPTED], true)) {
            throw new WlCustomOrderException('This order is already closed.');
        }
        $order->forceFill(['status' => O::DECLINED, 'decline_reason' => mb_substr($reason, 0, 500)])->save();
        Notify::event($order->user, 'wl_custom_order.declined', ['reference' => $order->reference, 'reason' => mb_substr($reason, 0, 200)], dedupe: 'wlco-d-'.$order->id);

        return $order;
    }

    /**
     * Pay an accepted quote from the buyer's wallet. Atomic debit idempotent on this order and this buyer, then the order is marked paid under a row lock;
     * if that fails the debit is refunded (never charge without delivering) and the admin is alerted.
     *
     * @throws WlCustomOrderException @throws InsufficientBalanceException
     */
    public function payFromWallet(O $order, User $buyer): O
    {
        $this->own($order, $buyer);
        if ($order->status === O::PAID) {
            return $order;   // a double click: the first charge stands
        }
        if ($order->status !== O::ACCEPTED || (float) $order->quote_total_usd <= 0) {
            throw new WlCustomOrderException('Accept the quote before paying.');
        }
        if ($order->quote_valid_until !== null && $order->quote_valid_until->isPast()) {
            throw new WlCustomOrderException('That quote has expired. Message us and we will re-issue it.');
        }
        $amount = (float) $order->quote_total_usd;
        $ref = 'wlco:'.$order->id.':'.$buyer->id;
        $this->wallet->debit($buyer, $amount, 'USD', ['reference' => $ref, 'description' => 'White Label custom order '.$order->reference]);
        try {
            $paid = DB::transaction(function () use ($order, $ref, $amount) {
                $locked = O::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
                if ($locked->status === O::PAID) {
                    return null;
                }
                $locked->forceFill(['status' => O::PAID, 'paid_at' => now(), 'paid_via' => 'wallet', 'paid_reference' => $ref, 'paid_amount_usd' => $amount])->save();

                return $locked;
            });
        } catch (Throwable $e) {
            $this->wallet->refund($buyer, $amount, 'USD', ['reference' => 'refund:'.$ref, 'description' => 'Custom order could not be recorded', 'notify' => false]);
            Log::warning('WL custom order payment failed after debit: '.$e->getMessage());
            AlertAdminJob::dispatch(code: 'wl_custom_order_orphan_charge', message: "Custom order {$order->reference} charge {$ref} was refunded because the order could not be marked paid.", context: ['order_id' => $order->id]);
            throw new WlCustomOrderException('We could not record the payment, so you were not charged.');
        }
        if ($paid === null) {   // two requests raced: the other one won, give this debit back
            $this->wallet->refund($buyer, $amount, 'USD', ['reference' => 'refund-dup:'.$ref.':'.Str::random(6), 'description' => 'Duplicate custom order payment', 'notify' => false]);

            return $order->fresh();
        }
        $this->earnings->accrue($amount, 'wl_custom_order', 'earn:'.$ref, 'White Label custom order '.$order->reference);
        Notify::event($buyer, 'wl_custom_order.paid', ['reference' => $order->reference], dedupe: 'wlco-p-'.$order->id);
        AlertAdminJob::dispatch(code: 'wl_custom_order_paid', message: "Custom order {$order->reference} was paid from the wallet ($".number_format($amount, 2).').', context: ['order_id' => $order->id]);

        return $paid;
    }

    /** Admin records a payment that arrived outside the wallet (bank transfer or crypto), with the reference they checked it against. @throws WlCustomOrderException */
    public function markPaidOffline(O $order, User $admin, string $reference): O
    {
        if ($order->status !== O::ACCEPTED) {
            throw new WlCustomOrderException('Only an accepted quote can be marked paid.');
        }
        $reference = trim($reference);
        if ($reference === '') {
            throw new WlCustomOrderException('Enter the bank or transaction reference you checked.');
        }
        $amount = (float) $order->quote_total_usd;
        $ref = 'wlco-off:'.$order->id;
        DB::transaction(function () use ($order, $reference, $amount, $ref) {
            $locked = O::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== O::ACCEPTED) {
                throw new WlCustomOrderException('Only an accepted quote can be marked paid.');
            }
            $locked->forceFill(['status' => O::PAID, 'paid_at' => now(), 'paid_via' => 'offline', 'paid_reference' => mb_substr($reference, 0, 120), 'paid_amount_usd' => $amount])->save();
            $this->earnings->accrue($amount, 'wl_custom_order', 'earn:'.$ref, 'White Label custom order '.$locked->reference.' (transfer)');
        });
        \App\Support\Auditor::log('wl_custom_order.marked_paid', O::class, $order->id, ['reference' => $order->reference, 'amount' => $amount, 'bank_reference' => $reference, 'admin_id' => $admin->id]);
        Notify::event($order->user, 'wl_custom_order.paid', ['reference' => $order->reference], dedupe: 'wlco-p-'.$order->id);

        return $order->fresh();
    }

    public function post(O $order, User $author, string $body, bool $fromAdmin): WlCustomOrderMessage
    {
        $body = trim($body);
        if ($body === '' || mb_strlen($body) > 2000) {
            throw ValidationException::withMessages(['message' => 'Write a message of up to 2000 characters.']);
        }
        if (! $fromAdmin) {
            $this->own($order, $author);
        }
        $m = WlCustomOrderMessage::query()->create(['order_id' => $order->id, 'author_id' => $author->id, 'from_admin' => $fromAdmin, 'body' => $body, 'created_at' => now()]);
        if ($fromAdmin) {
            Notify::event($order->user, 'wl_custom_order.message', ['reference' => $order->reference, 'preview' => Str::limit($body, 120)]);
        } else {
            AlertAdminJob::dispatch(code: 'wl_custom_order_message', message: "New message on custom order {$order->reference}.", context: ['order_id' => $order->id]);
        }

        return $m;
    }

    /** Daily: a quote nobody answered stops being payable. */
    public function expireStale(): int
    {
        return O::query()->where('status', O::QUOTED)->whereNotNull('quote_valid_until')->where('quote_valid_until', '<', now())->update(['status' => O::EXPIRED]);
    }

    private function own(O $order, User $user): void
    {
        if ($order->user_id !== $user->id) {
            throw new WlCustomOrderException('That order is not yours.');
        }
    }

    private function newReference(): string
    {
        do {
            $ref = 'WLC-'.strtoupper(Str::random(6));
        } while (O::query()->where('reference', $ref)->exists());

        return $ref;
    }
}
