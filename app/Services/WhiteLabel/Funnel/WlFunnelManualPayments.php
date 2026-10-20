<?php

namespace App\Services\WhiteLabel\Funnel;

use App\Models\ManualPaymentAccount;
use App\Models\User;
use App\Models\WhiteLabelInstance;
use App\Models\WhiteLabelLicensePayment;
use App\Models\WhiteLabelLicensePlan;
use App\Models\WlFunnelInstance;
use App\Models\WlFunnelManualPayment as P;
use App\Services\Updater\WhiteLabelLicenseService;
use App\Services\WhiteLabel\Payments\ManualPaymentException;
use App\Services\WhiteLabel\Payments\WlRateQuoter;
use App\Support\Auditor;
use App\Support\MediaStorage;
use App\Support\Notifications\Notify;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Manual payments for the White Label funnel (Funnel Phases 4, 5 and 6.1, MASTER-ONLY). The buyer gets a locked quote and the receiving details,
 * pays outside the app, uploads proof; an admin approves, and ONLY an approval issues the licence (once, idempotently). Nothing here trusts the
 * browser: price comes from the plan row, amount from WlRateQuoter, accounts from admin data, and proof files are sniffed and hashed.
 */
class WlFunnelManualPayments
{
    public const CURRENCIES = ['USD', 'GBP', 'EUR', 'USDT', 'USDC'];

    public const MAX_FILES = 3;

    public const MAX_FILE_BYTES = 8 * 1048576;

    public const MAX_ATTEMPTS = 5;

    /** Accept a transfer that arrives at this share of the expected amount or more (bank-fee shortfalls). */
    public const TOLERANCE = 0.995;

    public function __construct(private readonly WlRateQuoter $quoter, private readonly WlFunnel $funnel, private readonly WhiteLabelLicenseService $licenses) {}

    public static function enabled(): bool
    {
        return \App\Support\FeatureFlags::adminEnabled('wl_manual_payments');
    }

    /** The buyer's current payment, if any. */
    public function current(WlFunnelInstance $inst): ?P
    {
        return P::query()->where('funnel_instance_id', $inst->id)->where('purpose', P::PURPOSE_LICENSE)->whereIn('status', P::OPEN)->latest('id')->first();
    }

    /** The buyer's current payment for the balance to Extended, if one is open. */
    public function currentBalance(WlFunnelInstance $inst): ?P
    {
        return P::query()->where('funnel_instance_id', $inst->id)->where('purpose', P::PURPOSE_BALANCE)->whereIn('status', P::OPEN)->latest('id')->first();
    }

    /** The licence and unpaid balance invoice this buyer could settle now, or null. @return array{instance: WhiteLabelInstance, invoice: \App\Models\WlInvoice, plan: WhiteLabelLicensePlan}|null */
    public function balanceDue(WlFunnelInstance $inst): ?array
    {
        $licensePay = P::query()->where('funnel_instance_id', $inst->id)->where('purpose', P::PURPOSE_LICENSE)->where('status', P::APPROVED)->whereNotNull('white_label_instance_id')->latest('id')->first();
        $instance = $licensePay ? WhiteLabelInstance::query()->find($licensePay->white_label_instance_id) : null;
        if (! $instance || ! $instance->hasLiveLicense() || $instance->tier !== WhiteLabelInstance::TIER_NORMAL) {
            return null;
        }
        $invoice = \App\Models\WlInvoice::query()->where('source_key', 'mp:'.$licensePay->id.':balance')->where('status', 'issued')->where('balance_due', '>', 0)->first();
        $plan = WhiteLabelLicensePlan::query()->where('key', $licensePay->plan_key)->first();

        return $invoice && $plan ? ['instance' => $instance, 'invoice' => $invoice, 'plan' => $plan] : null;
    }

    /**
     * Start (or reuse) a payment of the balance to Extended by transfer. The amount is the balance invoice's own balance due, quoted at the locked rate; the licence itself is never touched until an admin approves.
     *
     * @throws ManualPaymentException
     */
    public function beginBalance(WlFunnelInstance $inst, string $currency, ?string $network = null): P
    {
        $currency = strtoupper($currency);
        if (! self::enabled()) {
            throw new ManualPaymentException('Manual payments are not available right now.');
        }
        if (! in_array($currency, self::CURRENCIES, true)) {
            throw new ManualPaymentException('Choose one of the listed currencies.');
        }
        $due = $this->balanceDue($inst) ?? throw new ManualPaymentException('There is no balance to pay right now.');
        $account = $this->accountsFor($currency)->first(fn (ManualPaymentAccount $a) => ! $a->isCrypto() || $a->network === $network)
            ?? throw new ManualPaymentException($currency.' payments are not available right now.');
        if ($account->isCrypto() && ($network === null || $account->network !== $network)) {
            throw new ManualPaymentException('Choose the network you will send on.');
        }

        return DB::transaction(function () use ($inst, $currency, $network, $account, $due) {
            $existing = P::query()->where('funnel_instance_id', $inst->id)->where('purpose', P::PURPOSE_BALANCE)->whereIn('status', [P::AWAITING, P::REVIEW, P::UNDERPAID])->lockForUpdate()->latest('id')->first();
            if ($existing && $existing->status !== P::AWAITING) {
                return $existing;   // proof already sent: never replace a payment under review
            }
            if ($existing && $existing->currency === $currency && $existing->network === $network && $existing->isLocked()) {
                return $existing;
            }
            $existing?->forceFill(['status' => P::CANCELLED])->save();
            $usd = (float) $due['invoice']->balance_due;
            $q = $this->quoter->amountToSend($currency, $usd);

            return P::query()->create([
                'funnel_instance_id' => $inst->id, 'purpose' => P::PURPOSE_BALANCE, 'wl_invoice_id' => $due['invoice']->id, 'account_id' => $account->id, 'plan_key' => $due['plan']->key, 'currency' => $currency,
                'network' => $account->isCrypto() ? $network : null, 'price_usd' => $usd, 'expected_amount' => $q['amount'], 'rate' => $q['rate'], 'rate_source' => $q['source'], 'rate_as_of' => $q['as_of'],
                'locked_until' => $q['locked_until'], 'reference_code' => $this->reference($inst), 'status' => P::AWAITING,
            ]);
        });
    }

    /** Currencies the buyer may choose now, each with a reason when it cannot be used. @return array<string, string|null> */
    public function availableCurrencies(): array
    {
        $out = [];
        foreach (self::CURRENCIES as $c) {
            $out[$c] = $this->accountsFor($c)->isEmpty() ? 'Not available right now.' : null;
        }

        return $out;
    }

    /** Crypto networks with a usable (enabled AND test-confirmed) account for a stablecoin. @return list<string> */
    public function networksFor(string $currency): array
    {
        return $this->accountsFor($currency)->pluck('network')->filter()->unique()->values()->all();
    }

    private function accountsFor(string $currency)
    {
        return ManualPaymentAccount::query()->where('currency', strtoupper($currency))->where('enabled', true)->orderBy('sort')->get()
            ->filter(fn (ManualPaymentAccount $a) => ! $a->isCrypto() || $a->test_confirmed_at !== null);
    }

    /**
     * Start (or re-quote) a payment for the buyer's chosen currency. A still-locked identical quote is reused; an expired or different one is cancelled and replaced.
     *
     * @throws ManualPaymentException
     */
    public function begin(WlFunnelInstance $inst, string $currency, ?string $network = null): P
    {
        $currency = strtoupper($currency);
        if (! self::enabled()) {
            throw new ManualPaymentException('Manual payments are not available right now.');
        }
        if (! in_array($currency, self::CURRENCIES, true)) {
            throw new ManualPaymentException('Choose one of the listed currencies.');
        }
        if ($this->funnel->missing($inst) !== []) {
            throw new ManualPaymentException('Finish every step of your purchase before paying.');
        }
        if (in_array($inst->status, [WlFunnelStates::PAID, WlFunnelStates::LICENSE_ISSUED, WlFunnelStates::SETUP, WlFunnelStates::LIVE], true)) {
            throw new ManualPaymentException('This purchase is already paid.');
        }
        $plan = $this->plan($inst);
        $account = $this->accountsFor($currency)->first(fn (ManualPaymentAccount $a) => ! $a->isCrypto() || $a->network === $network)
            ?? throw new ManualPaymentException($currency.' payments are not available right now.');
        if ($account->isCrypto() && ($network === null || $account->network !== $network)) {
            throw new ManualPaymentException('Choose the network you will send on.');
        }

        return DB::transaction(function () use ($inst, $currency, $network, $plan, $account) {
            $existing = P::query()->where('funnel_instance_id', $inst->id)->where('purpose', P::PURPOSE_LICENSE)->whereIn('status', [P::AWAITING, P::REVIEW, P::UNDERPAID])->lockForUpdate()->latest('id')->first();
            if ($existing && $existing->status !== P::AWAITING) {
                return $existing;   // proof already sent: never replace a payment under review
            }
            if ($existing && $existing->currency === $currency && $existing->network === $network && $existing->plan_key === $plan->key && $existing->isLocked()) {
                return $existing;
            }
            $existing?->forceFill(['status' => P::CANCELLED])->save();
            $q = $this->quoter->amountToSend($currency, (float) $plan->price_usd);
            $pay = P::query()->create([
                'funnel_instance_id' => $inst->id, 'account_id' => $account->id, 'plan_key' => $plan->key, 'currency' => $currency, 'network' => $account->isCrypto() ? $network : null,
                'price_usd' => $plan->price_usd, 'expected_amount' => $q['amount'], 'rate' => $q['rate'], 'rate_source' => $q['source'], 'rate_as_of' => $q['as_of'], 'locked_until' => $q['locked_until'],
                'reference_code' => $this->reference($inst), 'status' => P::AWAITING,
            ]);
            if (in_array($inst->status, [WlFunnelStates::WIZARD, WlFunnelStates::DRAFT_SAVED, WlFunnelStates::REJECTED], true)) {
                $this->funnel->transition($inst, WlFunnelStates::AWAITING_PAYMENT, 'user', ['payment' => $pay->id]);
            }

            return $pay;
        });
    }

    /** The receiving details the buyer is shown, from admin data (never from the request). @return list<array{label:string,value:string}> */
    public function instructions(P $pay): array
    {
        return array_values(array_map(fn ($r) => ['label' => (string) ($r['label'] ?? ''), 'value' => (string) ($r['value'] ?? '')], (array) $pay->account?->fields));
    }

    /**
     * The buyer says "I've paid" and sends proof.
     *
     * @param  list<UploadedFile>  $files
     *
     * @throws ManualPaymentException
     */
    public function submitProof(P $pay, User $user, array $files, string $payerName, ?string $payerBank, ?string $paidAt, ?string $txHash): P
    {
        $inst = $pay->instance;
        if ((int) $inst->user_id !== (int) $user->id) {
            throw new ManualPaymentException('This payment is not yours.');
        }
        if (! in_array($pay->status, [P::AWAITING, P::UNDERPAID], true)) {
            throw new ManualPaymentException('This payment cannot take new proof right now.');
        }
        if ($pay->proof_attempts >= self::MAX_ATTEMPTS) {
            throw new ManualPaymentException('Too many proofs were sent for this payment. Please contact support.');
        }
        $payerName = trim(strip_tags($payerName));
        if ($payerName === '' || mb_strlen($payerName) > 120) {
            throw new ManualPaymentException('Enter the name on the paying account.');
        }
        $isCrypto = in_array($pay->currency, ['USDT', 'USDC'], true);
        $txHash = $txHash !== null ? trim($txHash) : null;
        if ($isCrypto) {
            if (! self::validTxHash((string) $pay->network, (string) $txHash)) {
                throw new ManualPaymentException('Enter the transaction hash exactly as your wallet shows it.');
            }
            if (P::query()->where('tx_hash', $txHash)->where('id', '!=', $pay->id)->exists()) {
                throw new ManualPaymentException('That transaction hash was already used.');
            }
        } else {
            $txHash = null;
        }
        $files = array_values(array_filter($files));
        if ($files === [] || count($files) > self::MAX_FILES) {
            throw new ManualPaymentException('Attach 1 to '.self::MAX_FILES.' files (screenshot or PDF).');
        }

        $stored = [];
        $disk = MediaStorage::privateDisk();
        try {
            foreach ($files as $f) {
                $raw = (string) file_get_contents($f->getRealPath());
                $kind = self::sniffProof($raw);
                if ($kind === null) {
                    throw new ManualPaymentException('Only JPG, PNG, WebP or PDF files are accepted.');
                }
                if (strlen($raw) > self::MAX_FILE_BYTES) {
                    throw new ManualPaymentException('Each file must be 8 MB or smaller.');
                }
                $key = 'wl-funnel/proofs/'.now()->format('Y/m').'/'.Str::random(40).'.'.$kind['ext'];
                Storage::disk($disk)->put($key, $raw, ['visibility' => 'private']);
                $stored[] = ['disk' => $disk, 'key' => $key, 'sha256' => hash('sha256', $raw), 'mime' => $kind['mime'], 'bytes' => strlen($raw)];
            }
        } catch (\Throwable $e) {
            foreach ($stored as $s) {
                Storage::disk($s['disk'])->delete($s['key']);   // a half-accepted batch leaves nothing behind
            }
            throw $e;
        }

        $flags = [];
        $hashes = array_column($stored, 'sha256');
        $dupes = P::query()->where('id', '!=', $pay->id)->whereNotNull('proof')->get(['id', 'proof'])->contains(fn (P $o) => array_intersect($hashes, array_column((array) $o->proof, 'sha256')) !== []);
        if ($dupes) {
            $flags[] = 'duplicate_image';
        }
        if ($pay->locked_until->isPast()) {
            $flags[] = 'quote_expired_when_submitted';
        }
        $old = (array) $pay->proof;
        $pay->forceFill([
            'status' => P::REVIEW, 'payer_name' => $payerName, 'payer_bank' => $payerBank !== null ? mb_substr(trim(strip_tags($payerBank)), 0, 120) : null,
            'paid_at_claimed' => $paidAt ? \Carbon\CarbonImmutable::parse($paidAt) : null, 'tx_hash' => $txHash, 'proof' => array_merge($old, $stored), 'flags' => $flags, 'proof_attempts' => $pay->proof_attempts + 1,
            'reject_reason' => null,
        ])->save();
        if ($inst->status === WlFunnelStates::AWAITING_PAYMENT) {
            $this->funnel->transition($inst, WlFunnelStates::PAYMENT_REVIEW, 'user', ['payment' => $pay->id]);
        }
        Auditor::log('wl.funnel_payment_proof_submitted', P::class, $pay->id, ['reference' => $pay->reference_code, 'files' => count($stored)]);
        Notify::event($user, 'wl.payment_review', ['reference' => $pay->reference_code], 'wl-pay-review-'.$pay->id.'-'.$pay->proof_attempts);

        return $pay->fresh();
    }

    /** The reviewer approves: confirm the amount that arrived (USD value is computed from the locked rate). A short amount is refused here, so use underpaid(). */
    public function approve(P $pay, User $admin, float $receivedAmount): WhiteLabelInstance
    {
        $this->guardAdmin($admin);
        if ($receivedAmount + 0.00001 < (float) $pay->expected_amount * self::TOLERANCE) {
            throw new ManualPaymentException('The amount is below the accepted tolerance. Mark it underpaid instead.');
        }

        $instance = DB::transaction(function () use ($pay, $admin, $receivedAmount) {
            $locked = P::query()->whereKey($pay->id)->lockForUpdate()->firstOrFail();
            if ($locked->white_label_instance_id !== null) {
                return WhiteLabelInstance::query()->findOrFail($locked->white_label_instance_id);   // already issued: never twice
            }
            if (! in_array($locked->status, [P::REVIEW, P::UNDERPAID], true)) {
                throw new ManualPaymentException('Only a payment under review can be approved.');
            }
            $locked->forceFill(['status' => P::APPROVED, 'approved_amount' => round($receivedAmount, 2), 'reviewer_id' => $admin->id, 'reviewed_at' => now()])->save();
            $instance = $locked->isBalance() ? $this->settleBalance($locked, $admin) : $this->issue($locked, $admin);
            Auditor::log('wl.funnel_payment_approved', P::class, $locked->id, ['reference' => $locked->reference_code, 'received' => $receivedAmount, 'instance' => $instance->id]);

            return $instance;
        });

        // Documents come AFTER the approval has committed and can never undo it; a failure here is retried by wl-invoices:retry and flagged by naara:verify wl.
        try {
            $pay->refresh()->isBalance() ? app(WlInvoiceIssuer::class)->forBalancePayment($pay) : app(WlInvoiceIssuer::class)->forApprovedPayment($pay);
        } catch (\Throwable $e) {
            report($e);
        }

        return $instance;
    }

    public function reject(P $pay, User $admin, string $reason): P
    {
        $this->guardAdmin($admin);
        $reason = trim(strip_tags($reason));
        if ($reason === '') {
            throw new ManualPaymentException('Give the buyer a reason.');
        }
        if ($pay->status !== P::REVIEW) {
            throw new ManualPaymentException('Only a payment under review can be rejected.');
        }
        $pay->forceFill(['status' => P::AWAITING, 'reject_reason' => mb_substr($reason, 0, 300), 'reviewer_id' => $admin->id, 'reviewed_at' => now()])->save();
        $inst = $pay->instance;
        if ($inst->status === WlFunnelStates::PAYMENT_REVIEW) {
            $this->funnel->transition($inst, WlFunnelStates::AWAITING_PAYMENT, 'admin', ['payment' => $pay->id, 'rejected' => true]);
        }
        Auditor::log('wl.funnel_payment_rejected', P::class, $pay->id, ['reference' => $pay->reference_code, 'reason' => $reason]);
        if ($inst->user) {
            Notify::event($inst->user, 'wl.payment_rejected', ['reference' => $pay->reference_code, 'reason' => $reason], 'wl-pay-rej-'.$pay->id.'-'.$pay->proof_attempts);
        }

        return $pay->fresh();
    }

    /** Less than the accepted amount arrived: record it, tell the buyer the exact shortfall, and let them send the difference with new proof. */
    public function underpaid(P $pay, User $admin, float $receivedAmount): P
    {
        $this->guardAdmin($admin);
        if ($pay->status !== P::REVIEW) {
            throw new ManualPaymentException('Only a payment under review can be marked underpaid.');
        }
        $pay->forceFill(['status' => P::UNDERPAID, 'approved_amount' => round($receivedAmount, 2), 'reviewer_id' => $admin->id, 'reviewed_at' => now()])->save();
        $inst = $pay->instance;
        if ($inst->status === WlFunnelStates::PAYMENT_REVIEW) {
            $this->funnel->transition($inst, WlFunnelStates::AWAITING_PAYMENT, 'admin', ['payment' => $pay->id, 'underpaid' => true]);
        }
        Auditor::log('wl.funnel_payment_underpaid', P::class, $pay->id, ['reference' => $pay->reference_code, 'received' => $receivedAmount, 'expected' => (string) $pay->expected_amount]);
        if ($inst->user) {
            Notify::event($inst->user, 'wl.payment_underpaid', ['reference' => $pay->reference_code, 'received' => number_format($receivedAmount, 2).' '.$pay->currency, 'expected' => number_format((float) $pay->expected_amount, 2).' '.$pay->currency], 'wl-pay-under-'.$pay->id.'-'.$pay->proof_attempts);
        }

        return $pay->fresh();
    }

    /** Balance to Extended (Phase 7): upgrade the licence's tier, record the payment and earning, once per payment. */
    private function settleBalance(P $pay, ?User $admin): WhiteLabelInstance
    {
        $due = $this->balanceDue($pay->instance);
        $invoice = \App\Models\WlInvoice::query()->find($pay->wl_invoice_id);
        if (! $due || ! $invoice || (int) $due['invoice']->id !== (int) $invoice->id) {
            throw new ManualPaymentException('This balance is no longer payable. Reject the payment instead.');
        }
        $usd = round((float) $pay->approved_amount * (float) $pay->rate, 2);
        $instance = $this->licenses->completeBalanceExternally($due['instance'], min($usd, (float) $invoice->balance_due), (float) (WhiteLabelLicensePlan::balanceToExtended($due['plan']) ?? 0), 'wlfunnel-mp:'.$pay->id);
        $pay->forceFill(['white_label_instance_id' => $instance->id, 'issued_at' => now()])->save();
        if ($pay->instance->user) {
            Notify::event($pay->instance->user, 'wl.payment_approved', ['reference' => $pay->reference_code, 'brand' => $instance->brand_name], 'wl-pay-ok-'.$pay->id);
        }

        return $instance;
    }

    /** Licence issuance (Phase 6.1): idempotent per payment. Creates the instance, issues the key, records the payment, moves the funnel on. */
    private function issue(P $pay, ?User $admin): WhiteLabelInstance
    {
        $inst = $pay->instance;
        $d = (array) $inst->draft;
        $plan = $this->plan($inst, $pay->plan_key);
        $instance = $this->licenses->register([
            'brand_name' => $d['brand_name'] ?? 'White-label brand', 'contact_email' => $d['billing_email'] ?? ($inst->user?->email ?? ''), 'owner_user_id' => $inst->user_id,
            'license_plan_id' => $plan->id, 'acquisition_method' => WhiteLabelInstance::ACQUISITION_LANDING_FUNNEL, 'hosting_preference' => $d['hosting_choice'] ?? $inst->hosting_choice,
            'hosting_disclaimer_acknowledged' => ! empty($d['hosting_disclaimer_ack']) || ($d['hosting_choice'] ?? '') === 'supreme_ideas_server', 'note' => 'White Label funnel '.$inst->public_code.', payment '.$pay->reference_code,
        ]);
        $this->licenses->priceForPayment($instance, (float) $pay->price_usd, $admin?->id);
        $this->licenses->issueLicense($instance, (string) $plan->tier, $admin?->id);
        WhiteLabelLicensePayment::query()->firstOrCreate(['payment_reference' => 'wlfunnel-mp:'.$pay->id], ['white_label_instance_id' => $instance->id, 'amount_usd' => $pay->price_usd, 'kind' => WhiteLabelLicensePayment::KIND_INITIAL]);
        $pay->forceFill(['white_label_instance_id' => $instance->id, 'issued_at' => now()])->save();
        if (in_array($inst->status, [WlFunnelStates::WIZARD, WlFunnelStates::DRAFT_SAVED, WlFunnelStates::REJECTED], true)) {
            $this->funnel->transition($inst, WlFunnelStates::AWAITING_PAYMENT, $admin ? 'admin' : 'system', ['payment' => $pay->id]);
        }
        if (in_array($inst->status, [WlFunnelStates::PAYMENT_REVIEW, WlFunnelStates::AWAITING_PAYMENT], true)) {
            $this->funnel->transition($inst, WlFunnelStates::PAID, $admin ? 'admin' : 'system', ['payment' => $pay->id]);
        }
        $this->funnel->transition($inst, WlFunnelStates::LICENSE_ISSUED, 'system', ['instance' => $instance->id]);
        if ($inst->user) {
            Notify::event($inst->user, 'wl.payment_approved', ['reference' => $pay->reference_code, 'brand' => $instance->brand_name], 'wl-pay-ok-'.$pay->id);
        }

        return $instance;
    }

    /**
     * Pay from the Naara wallet (Funnel Phase 4b). One atomic debit with an idempotent reference; the licence is issued inside the charge, so a failed issuance auto-refunds (orphan-charge guard).
     *
     * @throws ManualPaymentException|\App\Exceptions\InsufficientBalanceException
     */
    public function payWithWallet(WlFunnelInstance $inst, User $user): WhiteLabelInstance
    {
        if (! WlFunnel::enabled()) {
            throw new ManualPaymentException('Paying from the wallet is not available right now.');
        }
        if ((int) $inst->user_id !== (int) $user->id) {
            throw new ManualPaymentException('This purchase is not yours.');
        }
        if ($this->funnel->missing($inst) !== []) {
            throw new ManualPaymentException('Finish every step of your purchase before paying.');
        }
        $plan = $this->plan($inst);
        $existing = P::query()->where('funnel_instance_id', $inst->id)->where('method', 'wallet')->where('status', P::APPROVED)->first();
        if ($existing?->white_label_instance_id) {
            return WhiteLabelInstance::query()->findOrFail($existing->white_label_instance_id);   // paid already: never charged twice
        }
        $ref = 'wlfunnel-wallet:'.$inst->id;
        $instance = app(\App\Services\Wallet\WalletService::class)->charge($user, (float) $plan->price_usd, 'USD', function () use ($inst, $plan, $ref) {
            $pay = P::query()->create([
                'funnel_instance_id' => $inst->id, 'method' => 'wallet', 'plan_key' => $plan->key, 'currency' => 'USD', 'price_usd' => $plan->price_usd, 'expected_amount' => $plan->price_usd, 'rate' => 1, 'rate_source' => 'none',
                'rate_as_of' => now(), 'locked_until' => now(), 'reference_code' => $this->reference($inst), 'status' => P::APPROVED, 'approved_amount' => $plan->price_usd, 'reviewed_at' => now(),
            ]);

            return $this->issue($pay, null);
        }, ['reference' => $ref, 'description' => 'White Label licence: '.$plan->name]);
        $pay = P::query()->where('funnel_instance_id', $inst->id)->where('method', 'wallet')->latest('id')->first();
        Auditor::log('wl.funnel_wallet_payment', P::class, $pay?->id, ['instance' => $instance->id, 'amount' => (string) $plan->price_usd]);
        try {
            app(WlInvoiceIssuer::class)->forApprovedPayment($pay->fresh());
        } catch (\Throwable $e) {
            report($e);
        }

        return $instance;
    }

    /**
     * Pay the balance to Extended from the wallet: one atomic debit with an idempotent reference, the upgrade happens inside the charge (a failed upgrade auto-refunds), documents follow.
     *
     * @throws ManualPaymentException|\App\Exceptions\InsufficientBalanceException
     */
    public function payBalanceWithWallet(WlFunnelInstance $inst, User $user): WhiteLabelInstance
    {
        if (! WlFunnel::enabled()) {
            throw new ManualPaymentException('Paying from the wallet is not available right now.');
        }
        if ((int) $inst->user_id !== (int) $user->id) {
            throw new ManualPaymentException('This purchase is not yours.');
        }
        $due = $this->balanceDue($inst) ?? throw new ManualPaymentException('There is no balance to pay right now.');
        $invoice = $due['invoice'];
        $usd = (float) $invoice->balance_due;
        $ref = 'wlfunnel-wallet-bal:'.$invoice->id;
        $instance = app(\App\Services\Wallet\WalletService::class)->charge($user, $usd, 'USD', function () use ($inst, $due, $invoice, $usd) {
            $pay = P::query()->create([
                'funnel_instance_id' => $inst->id, 'method' => 'wallet', 'purpose' => P::PURPOSE_BALANCE, 'wl_invoice_id' => $invoice->id, 'plan_key' => $due['plan']->key, 'currency' => 'USD', 'price_usd' => $usd, 'expected_amount' => $usd,
                'rate' => 1, 'rate_source' => 'none', 'rate_as_of' => now(), 'locked_until' => now(), 'reference_code' => $this->reference($inst), 'status' => P::APPROVED, 'approved_amount' => $usd, 'reviewed_at' => now(),
            ]);

            return $this->settleBalance($pay, null);
        }, ['reference' => $ref, 'description' => 'White Label balance to Extended: '.$due['instance']->brand_name]);
        $pay = P::query()->where('funnel_instance_id', $inst->id)->where('method', 'wallet')->where('purpose', P::PURPOSE_BALANCE)->latest('id')->first();
        Auditor::log('wl.funnel_wallet_balance_payment', P::class, $pay?->id, ['instance' => $instance->id, 'amount' => (string) $usd]);
        try {
            app(WlInvoiceIssuer::class)->forBalancePayment($pay->fresh());
        } catch (\Throwable $e) {
            report($e);
        }

        return $instance;
    }

    /** A short, unique, typo-safe reference to put in the transfer memo: WL-<funnel code>-<4 chars>. */
    private function reference(WlFunnelInstance $inst): string
    {
        $alphabet = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
        do {
            $tail = '';
            for ($i = 0; $i < 4; $i++) {
                $tail .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
            $ref = 'WL-'.$inst->public_code.'-'.$tail;
        } while (P::query()->where('reference_code', $ref)->exists());

        return $ref;
    }

    private function plan(WlFunnelInstance $inst, ?string $key = null): WhiteLabelLicensePlan
    {
        $d = (array) $inst->draft;
        $plan = WhiteLabelLicensePlan::query()->where('key', $key ?? ($d['plan_key'] ?? $inst->plan_key_at_landing))->where('is_active', true)->first();

        return $plan ?? throw new ManualPaymentException('That plan is not available.');
    }

    private function guardAdmin(User $admin): void
    {
        abort_unless($admin->hasAnyRole(['super_admin', 'admin']), 403);
    }

    /** Transaction hash shapes per network (format only; the reviewer still checks the chain). */
    public static function validTxHash(string $network, string $hash): bool
    {
        return match ($network) {
            'TRC20' => preg_match('/^[0-9a-fA-F]{64}$/', $hash) === 1,
            'BEP20' => preg_match('/^0x[0-9a-fA-F]{64}$/', $hash) === 1,
            'Solana' => preg_match('/^[1-9A-HJ-NP-Za-km-z]{80,90}$/', $hash) === 1,
            default => false,
        };
    }

    /** @return array{mime:string,ext:string}|null JPG, PNG, WebP or PDF, by the bytes. */
    public static function sniffProof(string $raw): ?array
    {
        if (str_starts_with($raw, '%PDF-')) {
            return ['mime' => 'application/pdf', 'ext' => 'pdf'];
        }
        $s = \App\Services\N2N\Media\MediaSniffer::sniff(substr($raw, 0, 64));

        return $s !== null && $s['kind'] === 'image' && $s['mime'] !== 'image/gif' ? ['mime' => $s['mime'], 'ext' => $s['ext']] : null;
    }

    /** Short-lived signed access for the admin viewer is served by the controller; this returns the stored descriptor only. */
    public function proofDescriptor(P $pay, int $i): ?array
    {
        return ((array) $pay->proof)[$i] ?? null;
    }
}
