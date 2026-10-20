<?php

namespace App\Services\MerchantWidgets;

use App\Models\MerchantWidgetEvent;
use App\Models\MerchantWidgetKey;
use App\Models\MerchantWidgetOrder;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

/**
 * Read-only analytics for one widget key (MASTER-ONLY): the view-to-order funnel, a daily series, how orders ended, what sold, where
 * the traffic came from, the latest orders, and the change against the period before. Pure reads: it never writes and never
 * returns a cost price or a full buyer email.
 */
class MerchantWidgetAnalytics
{
    public const RANGES = [7, 30, 90];

    /** @return array<string,mixed> */
    public function forKey(MerchantWidgetKey $key, int $days = 30): array
    {
        $days = in_array($days, self::RANGES, true) ? $days : 30;
        $end = CarbonImmutable::now()->endOfDay();
        $start = $end->subDays($days - 1)->startOfDay();
        $prevStart = $start->subDays($days);

        $cur = $this->totals($key, $start, $end);
        $prev = $this->totals($key, $prevStart, $start->subSecond());

        // daily series, zero-filled so the chart has no gaps
        $events = MerchantWidgetEvent::query()->where('merchant_widget_key_id', $key->id)->whereBetween('created_at', [$start, $end])
            ->selectRaw('date(created_at) as d, event_type, count(*) as n')->groupBy('d', 'event_type')->get();
        $orders = MerchantWidgetOrder::query()->where('merchant_widget_key_id', $key->id)->where('status', MerchantWidgetOrder::COMPLETED)
            ->whereBetween('created_at', [$start, $end])->selectRaw('date(created_at) as d, count(*) as n, sum(merchant_price_usd) as v')->groupBy('d')->get()->keyBy('d');
        $series = [];
        for ($i = 0; $i < $days; $i++) {
            $d = $start->addDays($i)->toDateString();
            $series[$d] = ['date' => $d, 'views' => 0, 'checkouts' => 0, 'orders' => (int) ($orders[$d]->n ?? 0), 'revenue' => round((float) ($orders[$d]->v ?? 0), 2)];
        }
        foreach ($events as $e) {
            $d = (string) $e->d;
            if (! isset($series[$d])) {
                continue;
            }
            if ($e->event_type === 'impression') {
                $series[$d]['views'] = (int) $e->n;
            } elseif ($e->event_type === 'checkout_opened') {
                $series[$d]['checkouts'] = (int) $e->n;
            }
        }

        $all = MerchantWidgetOrder::query()->where('merchant_widget_key_id', $key->id)->whereBetween('created_at', [$start, $end])->get(['status', 'kind', 'origin_domain', 'merchant_price_usd']);
        $byStatus = $all->groupBy('status')->map->count()->all();
        $byKind = $all->where('status', MerchantWidgetOrder::COMPLETED)->groupBy('kind')
            ->map(fn ($g) => ['orders' => $g->count(), 'revenue' => round((float) $g->sum('merchant_price_usd'), 2)])->all();
        $origins = $all->whereNotNull('origin_domain')->groupBy('origin_domain')->map->count()->sortDesc()->take(5)->all();

        $recent = MerchantWidgetOrder::query()->where('merchant_widget_key_id', $key->id)->latest('id')->limit(10)
            ->get(['id', 'kind', 'status', 'merchant_price_usd', 'buyer_email', 'created_at', 'failure_reason'])
            ->map(fn ($o) => [
                'id' => $o->id, 'kind' => $o->kind, 'status' => $o->status, 'value' => round((float) $o->merchant_price_usd, 2),
                'buyer' => $this->maskEmail($o->buyer_email), 'at' => $o->created_at, 'note' => $o->failure_reason ? Str::limit($o->failure_reason, 60) : null,
            ])->all();

        $views = max(1, $cur['views']);

        return [
            'days' => $days, 'from' => $start->toDateString(), 'to' => $end->toDateString(),
            'totals' => $cur, 'previous' => $prev,
            'delta' => [
                'views' => $this->delta($cur['views'], $prev['views']),
                'orders' => $this->delta($cur['orders'], $prev['orders']),
                'revenue' => $this->delta($cur['revenue'], $prev['revenue']),
            ],
            'funnel' => [
                ['label' => 'Widget views', 'value' => $cur['views'], 'pct' => 100],
                ['label' => 'Catalogue browsed', 'value' => $cur['catalogue'], 'pct' => min(100, round($cur['catalogue'] / $views * 100, 1))],
                ['label' => 'Checkout opened', 'value' => $cur['checkouts'], 'pct' => min(100, round($cur['checkouts'] / $views * 100, 1))],
                ['label' => 'Order completed', 'value' => $cur['orders'], 'pct' => min(100, round($cur['orders'] / $views * 100, 1))],
            ],
            'series' => array_values($series),
            'by_status' => $byStatus, 'by_kind' => $byKind, 'origins' => $origins, 'recent' => $recent,
            'has_data' => $cur['views'] + $cur['checkouts'] + $cur['orders'] + array_sum($byStatus) > 0,
        ];
    }

    /** @return array{views:int,catalogue:int,checkouts:int,orders:int,revenue:float,conversion:?float,aov:?float} */
    private function totals(MerchantWidgetKey $key, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $ev = MerchantWidgetEvent::query()->where('merchant_widget_key_id', $key->id)->whereBetween('created_at', [$from, $to])
            ->selectRaw('event_type, count(*) as n')->groupBy('event_type')->pluck('n', 'event_type');
        $done = MerchantWidgetOrder::query()->where('merchant_widget_key_id', $key->id)->where('status', MerchantWidgetOrder::COMPLETED)
            ->whereBetween('created_at', [$from, $to])->selectRaw('count(*) as n, coalesce(sum(merchant_price_usd),0) as v')->first();
        $orders = (int) ($done->n ?? 0);
        $revenue = round((float) ($done->v ?? 0), 2);
        $checkouts = (int) ($ev['checkout_opened'] ?? 0);

        return [
            'views' => (int) ($ev['impression'] ?? 0), 'catalogue' => (int) ($ev['catalogue_view'] ?? 0), 'checkouts' => $checkouts,
            'orders' => $orders, 'revenue' => $revenue,
            'conversion' => $checkouts > 0 ? round($orders / $checkouts * 100, 1) : null,
            'aov' => $orders > 0 ? round($revenue / $orders, 2) : null,
        ];
    }

    /** Percentage change against the previous period; null when there is nothing to compare with. */
    private function delta(float|int $now, float|int $before): ?float
    {
        return $before > 0 ? max(-100.0, min(999.0, round(($now - $before) / $before * 100, 1))) : null;
    }

    private function maskEmail(?string $email): string
    {
        if (! $email || ! str_contains($email, '@')) {
            return 'Guest';
        }
        [$u, $d] = explode('@', $email, 2);

        return mb_substr($u, 0, 1).str_repeat('*', max(2, min(5, mb_strlen($u) - 1))).'@'.$d;
    }
}
