<?php

namespace App\Services\Bootstrap\Steps;

use App\Models\Bootstrap\BootstrapEntry;
use App\Models\Coupon;
use App\Services\Bootstrap\BootstrapStep;
use App\Support\MarketingCoupons;
use Illuminate\Support\Carbon;

/**
 * Bootstrap Phase 3 (coupons). Keeps the two existing evergreen coupons (WELCOME10, COMEBACK15, owned by MarketingCouponsSeeder) and adds a small set of DRAFT coupons
 * (inactive, so nothing is offered until the owner turns them on: owner decision 4). Every discount is clamped by CouponEngine/DiscountMarginGuard at checkout whatever
 * the percentage says, so a draft cannot sell below cost + the minimum profit.
 */
class PromotionsStep implements BootstrapStep
{
    public function name(): string
    {
        return 'promotions';
    }

    /** @return list<array{code: string, percent: float, applies: string, max: int|null, starts: ?Carbon, ends: ?Carbon, note: string}> */
    private function drafts(): array
    {
        $next = fn (int $m, int $d, int $len) => [Carbon::create(now()->year, $m, $d)->isPast() ? Carbon::create(now()->year + 1, $m, $d)->startOfDay() : Carbon::create(now()->year, $m, $d)->startOfDay(), $len];
        $row = function (string $code, float $pct, string $applies, ?int $max, array $win, string $note) {
            return ['code' => $code, 'percent' => $pct, 'applies' => $applies, 'max' => $max, 'starts' => $win[0], 'ends' => $win[0]->copy()->addDays($win[1])->endOfDay(), 'note' => $note];
        };

        return [
            ['code' => 'FIRSTESIM', 'percent' => 10.0, 'applies' => 'esim', 'max' => 1000, 'starts' => null, 'ends' => null, 'note' => 'First eSIM only (per-user limit 1)'],
            $row('BLACKFRIDAY', 15.0, 'all', 500, $next(11, 24, 5), 'Seasonal draft, dated window'),
            $row('NEWYEAR', 10.0, 'all', 500, $next(12, 30, 4), 'Seasonal draft, dated window'),
            $row('EASTER', 10.0, 'all', 300, $next(4, 3, 4), 'Seasonal draft, dated window (check the year\'s Easter date)'),
            $row('INDEPENDENCE', 10.0, 'all', 300, $next(9, 29, 4), 'Seasonal draft for 1 October (Nigeria)'),
        ];
    }

    public function plan(string $profile): array
    {
        $rows = [];
        foreach ($this->drafts() as $d) {
            if (! Coupon::where('code', $d['code'])->exists()) {
                $rows[] = ['kind' => 'coupon', 'item' => $d['code'], 'note' => "{$d['percent']}% {$d['applies']} DRAFT (inactive): {$d['note']}"];
            }
        }

        return $rows;
    }

    public function apply(string $profile): array
    {
        $created = $preserved = 0;
        foreach ($this->drafts() as $d) {
            if (Coupon::where('code', $d['code'])->exists()) {
                $preserved++;

                continue;
            }
            Coupon::create([
                'code' => $d['code'], 'percent_off' => $d['percent'], 'applies_to' => $d['applies'], 'max_redemptions' => $d['max'], 'per_user_limit' => 1,
                'starts_at' => $d['starts'], 'expires_at' => $d['ends'], 'is_active' => false,
            ]);
            BootstrapEntry::updateOrCreate(['step' => $this->name(), 'kind' => 'coupon', 'item' => $d['code']], ['checksum' => BootstrapEntry::checksum([$d['percent'], $d['applies']])]);
            $created++;
        }

        return ['created' => $created, 'preserved' => $preserved, 'skipped' => 0];
    }

    public function verify(string $profile): array
    {
        $fail = [];
        foreach ([MarketingCoupons::WELCOME_CODE, MarketingCoupons::COMEBACK_CODE] as $code) {
            if (! Coupon::where('code', $code)->exists()) {
                $fail[] = "evergreen coupon {$code} is missing (php artisan db:seed --class=MarketingCouponsSeeder)";
            }
        }
        foreach ($this->drafts() as $d) {
            if (! Coupon::where('code', $d['code'])->exists()) {
                $fail[] = "draft coupon {$d['code']} is missing (run naara:bootstrap)";
            }
        }

        return $fail;
    }
}
