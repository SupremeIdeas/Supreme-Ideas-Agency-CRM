<?php

namespace App\Services\Brands;

use App\Exceptions\InsufficientBalanceException;
use App\Models\BrandPartner;
use App\Models\BrandSubscription;
use App\Models\BrandSubscriptionPlan;
use App\Models\Setting;
use App\Models\User;
use App\Services\Wallet\WalletService;
use App\Support\Auditor;
use App\Support\Notifications\Notify;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * The free Naara Pro trial (Brand-Growth Phase 4, MASTER-ONLY).
 *
 * A trial is the owner's BrandSubscription row in status 'trialing'. The monthly sweep (brand-subscriptions:bill) never touches it. At the end
 * the owner's wallet is charged ONCE for the plan they agreed to (reference brand-trial-convert:{id}, so no retry, double click or overlapping
 * run can ever charge twice). Short wallet: nothing is charged, the listing pauses, the owner is told, and we retry every few days a bounded
 * number of times before closing the trial. Everything is derived from timestamps passed in as "now", so tests drive it with a fake clock.
 */
class BrandTrialService
{
    public const NOTICE_VERSION = 1;

    public function __construct(private readonly WalletService $wallet, private readonly BrandSubscriptionService $subs)
    {
    }

    // ---- settings ---------------------------------------------------------------------------------------------------------------------------

    public static function enabled(): bool
    {
        return (bool) Setting::getValue('brand.trial.enabled', false);
    }

    public static function days(): int
    {
        return max(1, (int) Setting::getValue('brand.trial.days', 3));
    }

    public static function retryDays(): int
    {
        return max(1, (int) Setting::getValue('brand.trial.retry_days', 2));
    }

    public static function maxRetries(): int
    {
        return max(0, (int) Setting::getValue('brand.trial.max_retries', 3));
    }

    // ---- eligibility ------------------------------------------------------------------------------------------------------------------------

    /** Why this person cannot start a trial, or null when they can. One code, shown by the UI. */
    public function blocker(User $owner): ?string
    {
        if (! self::enabled()) {
            return 'off';
        }
        if (! $owner->hasVerifiedEmail()) {
            return 'email_unverified';
        }
        $brand = BrandPartner::where('owner_user_id', $owner->id)->first();
        if ($brand !== null) {
            if ($brand->hasProGrant()) {
                return 'already_pro';
            }
            $sub = $brand->subscription;
            if ($sub && in_array($sub->status, [BrandSubscription::ACTIVE, BrandSubscription::TRIALING, BrandSubscription::PAST_DUE], true)) {
                return 'already_subscribed';
            }
            if ($brand->subscriptions()->whereNotNull('trial_started_at')->exists()) {
                return 'trial_used';
            }
        }
        // Same person under a new account: the normalised email / phone seen on any earlier trial blocks another one.
        $keys = array_filter([$this->key('e', $owner->email), $this->key('p', $owner->phone)]);
        if ($keys !== [] && BrandSubscription::query()->whereNotNull('trial_started_at')
            ->where(function ($q) use ($keys) {
                foreach ($keys as $k) {
                    $q->orWhere('email_key', $k)->orWhere('phone_key', $k);
                }
            })->exists()) {
            return 'trial_used';
        }

        return null;
    }

    public function message(string $code): string
    {
        return match ($code) {
            'off' => 'Free trials are not open right now.',
            'email_unverified' => 'Confirm your email address first, then you can start your free trial.',
            'already_pro', 'already_subscribed' => 'You already have Naara Pro.',
            'trial_used' => 'The free trial has already been used on this account or email.',
            default => 'The free trial is not available.',
        };
    }

    // ---- lifecycle --------------------------------------------------------------------------------------------------------------------------

    /**
     * Start the trial. The owner must have ticked the notice (we store the moment and its version) and chosen the plan they will be charged
     * for when the trial ends. No money moves now.
     */
    public function start(User $owner, BrandSubscriptionPlan $convertTo, bool $noticeAccepted): BrandSubscription
    {
        if (! $noticeAccepted) {
            throw new \DomainException('Please confirm you have read how the trial ends.');
        }

        return DB::transaction(function () use ($owner, $convertTo) {
            $owner = User::whereKey($owner->id)->lockForUpdate()->firstOrFail();   // serialises two tabs
            if ($code = $this->blocker($owner)) {
                throw new \DomainException($this->message($code));
            }
            $brand = BrandPartner::where('owner_user_id', $owner->id)->lockForUpdate()->first();
            if ($brand === null) {
                $brand = BrandPartner::create([
                    'owner_user_id' => $owner->id,
                    'brand_name' => $owner->name ?: 'My brand',
                    'listing_status' => BrandPartner::STATUS_PENDING,
                    'is_featured' => false,
                    'background_color' => '#0A6E6E',
                    'current_plan_id' => $convertTo->id,
                    'is_active' => true,
                    'gate_version' => BrandVisibility::newBrandVersion(),
                ]);
            } else {
                $brand->forceFill([
                    'current_plan_id' => $convertTo->id,
                    'listing_status' => $this->subs->isListable($brand) ? BrandPartner::STATUS_ACTIVE : BrandPartner::STATUS_PENDING,
                ])->save();
            }

            $now = now();
            $sub = BrandSubscription::create([
                'brand_partner_id' => $brand->id,
                'plan_id' => $convertTo->id,
                'status' => BrandSubscription::TRIALING,
                'started_at' => $now,
                'trial_started_at' => $now,
                'trial_ends_at' => $now->copy()->addDays(self::days()),
                'convert_to_plan_id' => $convertTo->id,
                'locked_price_usd' => $convertTo->price_usd_per_month,
                'auto_convert' => true,
                'notice_ack_at' => $now,
                'email_key' => $this->key('e', $owner->email),
                'phone_key' => $this->key('p', $owner->phone),
            ]);

            Auditor::log('brand.trial_started', 'BrandPartner', $brand->id, ['owner' => $owner->id, 'plan' => $convertTo->name, 'ends' => $sub->trial_ends_at->toIso8601String(), 'notice' => self::NOTICE_VERSION]);
            Notify::event($owner, 'brand.trial_started', ['plan' => $convertTo->name, 'date' => $sub->trial_ends_at->format('j M Y, H:i'), 'amount' => '$'.number_format((float) $convertTo->price_usd_per_month, 2)], 'trial-start-'.$sub->id);

            return $sub;
        });
    }

    /** Change which plan the trial turns into (price is re-locked to the new plan; the owner sees it before the end). */
    public function changePlan(BrandSubscription $sub, BrandSubscriptionPlan $plan): BrandSubscription
    {
        if ($sub->status !== BrandSubscription::TRIALING) {
            throw new \DomainException('Only a running trial can change plan.');
        }
        $sub->forceFill(['convert_to_plan_id' => $plan->id, 'plan_id' => $plan->id, 'locked_price_usd' => $plan->price_usd_per_month])->save();
        $sub->brandPartner?->forceFill(['current_plan_id' => $plan->id])->save();

        return $sub->refresh();
    }

    /** Owner turns off auto-conversion (the trial then simply ends and the listing closes). */
    public function setAutoConvert(BrandSubscription $sub, bool $on): void
    {
        if ($sub->status === BrandSubscription::TRIALING) {
            $sub->forceFill(['auto_convert' => $on])->save();
        }
    }

    /** Charge now instead of waiting for the end (the owner is happy with the trial). Same single idempotent debit. */
    public function convertNow(BrandSubscription $sub): bool
    {
        return $this->convert($sub, now(), true);
    }

    /**
     * Convert one trial (or retry one that could not be charged). Returns true when the owner is now paying. Safe to call repeatedly:
     * a locked row re-read plus one fixed wallet reference means the charge happens at most once.
     */
    public function convert(BrandSubscription $sub, \DateTimeInterface $now, bool $early = false): bool
    {
        return (bool) DB::transaction(function () use ($sub, $now, $early) {
            $sub = BrandSubscription::whereKey($sub->id)->lockForUpdate()->first();
            if (! $sub) {
                return false;
            }
            $unfunded = $sub->status === BrandSubscription::PAST_DUE && $sub->trial_started_at !== null && $sub->converted_at === null;
            if ($sub->status !== BrandSubscription::TRIALING && ! $unfunded) {
                return false;
            }
            $brand = $sub->brandPartner;
            $owner = $brand?->owner;
            $plan = $sub->convertTo ?? $sub->plan;
            if (! $brand || ! $owner || ! $plan) {
                return false;
            }
            if (! $early && ! $unfunded && ! $sub->auto_convert) {
                return $this->end($sub, $brand, $owner, 'cancelled') && false;
            }
            $price = (float) ($sub->locked_price_usd ?? $plan->price_usd_per_month);

            try {
                $this->wallet->debit($owner, $price, 'USD', [
                    'reference' => 'brand-trial-convert:'.$sub->id,
                    'description' => 'Naara Pro — '.$plan->name.' (after free trial)',
                ]);
            } catch (InsufficientBalanceException) {
                $this->unfunded($sub, $brand, $owner, $plan, $now);

                return false;
            }

            $sub->forceFill([
                'status' => BrandSubscription::ACTIVE,
                'plan_id' => $plan->id,
                'last_charged_at' => $now,
                'next_billing_at' => \Illuminate\Support\Carbon::instance($now)->addMonthNoOverflow(),
                'converted_at' => $now,
                'end_reason' => 'converted',
                'unfunded_since' => null,
                'next_retry_at' => null,
                'grace_reminders_sent' => 0,
            ])->save();
            $brand->forceFill(['current_plan_id' => $plan->id, 'listing_status' => $this->subs->isListable($brand) ? BrandPartner::STATUS_ACTIVE : BrandPartner::STATUS_PENDING])->save();
            Auditor::log('brand.trial_converted', 'BrandPartner', $brand->id, ['owner' => $owner->id, 'plan' => $plan->name, 'early' => $early]);
            Notify::event($owner, 'brand.trial_converted', ['plan' => $plan->name, 'amount' => '$'.number_format($price, 2)], 'trial-conv-'.$sub->id);

            return true;
        });
    }

    /** The owner funded the wallet and presses Reactivate. Throttled (one try a minute) so a held button cannot hammer the wallet. */
    public function reactivate(BrandSubscription $sub): bool
    {
        if (! Cache::add('brand-trial-react:'.$sub->id, 1, 60)) {
            throw new \DomainException('Please wait a minute before trying again.');
        }

        return $this->convert($sub, now(), true);
    }

    /** Owner cancels during the trial: no charge, ever. */
    public function cancel(BrandSubscription $sub): void
    {
        DB::transaction(function () use ($sub) {
            $sub = BrandSubscription::whereKey($sub->id)->lockForUpdate()->first();
            if ($sub && in_array($sub->status, [BrandSubscription::TRIALING, BrandSubscription::PAST_DUE], true) && $sub->converted_at === null && $sub->trial_started_at !== null) {
                $this->end($sub, $sub->brandPartner, $sub->brandPartner?->owner, 'cancelled');
            }
        });
    }

    /** Admin gave this brand complimentary Pro: the trial must not convert into a charge. */
    public function stopForGrant(BrandPartner $brand): void
    {
        $sub = $brand->subscription;
        if ($sub && $sub->trial_started_at !== null && $sub->converted_at === null && in_array($sub->status, [BrandSubscription::TRIALING, BrandSubscription::PAST_DUE], true)) {
            $sub->forceFill(['status' => BrandSubscription::CANCELLED, 'cancelled_at' => now(), 'end_reason' => 'admin_grant', 'next_retry_at' => null, 'unfunded_since' => null])->save();
        }
    }

    /** Admin lets this person take another trial (wipes only the abuse keys, keeps history). */
    public function resetEligibility(BrandPartner $brand, User $admin): void
    {
        $brand->subscriptions()->whereNotNull('trial_started_at')->update(['email_key' => null, 'phone_key' => null, 'trial_started_at' => null]);
        Auditor::log('brand.trial_reset', 'BrandPartner', $brand->id, ['by' => $admin->id]);
    }

    // ---- the sweep --------------------------------------------------------------------------------------------------------------------------

    /** @return array{converted:int,unfunded:int,closed:int,reminded:int} */
    public function sweep(\DateTimeInterface $now): array
    {
        $out = ['converted' => 0, 'unfunded' => 0, 'closed' => 0, 'reminded' => 0];

        BrandSubscription::where('status', BrandSubscription::TRIALING)->where('trial_ends_at', '<=', $now)->orderBy('id')->each(function ($sub) use ($now, &$out) {
            $this->convert($sub, $now) ? $out['converted']++ : $out['unfunded']++;
        });
        BrandSubscription::where('status', BrandSubscription::PAST_DUE)->whereNotNull('trial_started_at')->whereNull('converted_at')
            ->whereNotNull('next_retry_at')->where('next_retry_at', '<=', $now)->orderBy('id')->each(function ($sub) use ($now, &$out) {
                if ($this->convert($sub, $now)) {
                    $out['converted']++;
                } elseif (BrandSubscription::whereKey($sub->id)->value('status') === BrandSubscription::CANCELLED) {
                    $out['closed']++;
                }
            });
        // Heads-up before the charge: 48 h, 24 h and 6 h out. Each stage once; a late run sends only the most urgent stage, never a burst.
        BrandSubscription::where('status', BrandSubscription::TRIALING)->where('auto_convert', true)->where('reminders_sent', '<', 3)
            ->where('trial_ends_at', '<=', \Illuminate\Support\Carbon::instance($now)->addHours(48))->where('trial_ends_at', '>', $now)->orderBy('id')
            ->each(function ($sub) use ($now, &$out) {
                $left = $sub->trial_ends_at->diffInMinutes($now, true);
                $stage = $left <= 360 ? 3 : ($left <= 1440 ? 2 : 1);
                $owner = $sub->brandPartner?->owner;
                if ($stage <= (int) $sub->reminders_sent || ! $owner) {
                    return;
                }
                $sub->forceFill(['reminders_sent' => $stage])->save();
                Notify::event($owner, 'brand.trial_ending', ['plan' => ($sub->convertTo ?? $sub->plan)?->name ?? 'Naara Pro', 'amount' => '$'.number_format((float) $sub->locked_price_usd, 2), 'date' => $sub->trial_ends_at->format('j M Y, H:i')], 'trial-end-'.$sub->id.'-'.$stage);
                $out['reminded']++;
            });

        return $out;
    }

    // ---- internals --------------------------------------------------------------------------------------------------------------------------

    private function unfunded(BrandSubscription $sub, BrandPartner $brand, User $owner, BrandSubscriptionPlan $plan, \DateTimeInterface $now): void
    {
        $tries = (int) $sub->retry_count + ($sub->status === BrandSubscription::PAST_DUE ? 1 : 0);
        if ($sub->status === BrandSubscription::PAST_DUE && $tries >= self::maxRetries()) {
            $this->end($sub, $brand, $owner, 'unfunded');

            return;
        }
        $first = $sub->status !== BrandSubscription::PAST_DUE;
        $sub->forceFill([
            'status' => BrandSubscription::PAST_DUE,
            'next_billing_at' => null,   // the monthly sweep must not also try
            'unfunded_since' => $sub->unfunded_since ?? $now,
            'retry_count' => $first ? 0 : $tries,
            'next_retry_at' => \Illuminate\Support\Carbon::instance($now)->addDays(self::retryDays()),
        ])->save();
        $brand->forceFill(['listing_status' => BrandPartner::STATUS_PAUSED])->save();
        Notify::event($owner, 'brand.trial_unfunded', ['plan' => $plan->name, 'amount' => '$'.number_format((float) ($sub->locked_price_usd ?? $plan->price_usd_per_month), 2), 'date' => $sub->next_retry_at->format('j M Y')], 'trial-unf-'.$sub->id.'-'.$sub->retry_count);
        Auditor::log('brand.trial_unfunded', 'BrandPartner', $brand->id, ['owner' => $owner->id, 'retry' => $sub->retry_count]);
    }

    /** Close the trial without a charge. Returns true so callers can chain. */
    private function end(BrandSubscription $sub, ?BrandPartner $brand, ?User $owner, string $reason): bool
    {
        $sub->forceFill(['status' => BrandSubscription::CANCELLED, 'cancelled_at' => now(), 'end_reason' => $reason, 'next_retry_at' => null, 'next_billing_at' => null])->save();
        $brand?->forceFill(['listing_status' => BrandPartner::STATUS_DISABLED])->save();
        if ($brand) {
            Auditor::log('brand.trial_ended', 'BrandPartner', $brand->id, ['reason' => $reason]);
        }
        if ($owner) {
            Notify::event($owner, 'brand.trial_ended', ['reason' => $reason === 'unfunded' ? 'The wallet could not cover the plan.' : 'The trial was closed.'], 'trial-end-final-'.$sub->id);
        }

        return true;
    }

    private function key(string $kind, ?string $value): ?string
    {
        $v = $kind === 'e' ? $this->normEmail((string) $value) : preg_replace('/\D+/', '', (string) $value);

        return $v === '' || $v === null ? null : hash('sha256', $kind.':'.$v);
    }

    /** gmail-style: dots and +tags in the local part do not make a new person. */
    private function normEmail(string $email): string
    {
        $email = strtolower(trim($email));
        if (! str_contains($email, '@')) {
            return $email;
        }
        [$local, $domain] = explode('@', $email, 2);
        $local = explode('+', $local, 2)[0];
        if (in_array($domain, ['gmail.com', 'googlemail.com'], true)) {
            $local = str_replace('.', '', $local);
            $domain = 'gmail.com';
        }

        return $local.'@'.$domain;
    }
}
