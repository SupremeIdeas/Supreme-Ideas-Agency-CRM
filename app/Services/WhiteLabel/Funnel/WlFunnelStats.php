<?php

namespace App\Services\WhiteLabel\Funnel;

use App\Models\WlFunnelInstance;
use App\Models\WlInvoice;

/** The numbers on the admin Funnel tab (MASTER-ONLY): where buyers are, what was earned and what is still owed. Pure reads. */
final class WlFunnelStats
{
    /** @return array{started: int, saved: int, review: int, paid: int, live: int, conversion: float, revenue: float, outstanding: float, overdue: int, started_30: int, paid_30: int} */
    public static function summary(): array
    {
        $paidStates = [WlFunnelStates::PAID, WlFunnelStates::LICENSE_ISSUED, WlFunnelStates::SETUP, WlFunnelStates::LIVE];
        $started = WlFunnelInstance::query()->count();
        $paid = WlFunnelInstance::query()->whereIn('status', $paidStates)->count();
        $unpaid = WlInvoice::query()->where('doc_type', WlInvoice::INVOICE)->where('status', 'issued')->where('balance_due', '>', 0);

        return [
            'started' => $started,
            'saved' => WlFunnelInstance::query()->whereNotIn('status', [WlFunnelStates::LANDING, WlFunnelStates::REGISTERING])->count(),
            'review' => WlFunnelInstance::query()->where('status', WlFunnelStates::PAYMENT_REVIEW)->count(),
            'paid' => $paid,
            'live' => WlFunnelInstance::query()->where('status', WlFunnelStates::LIVE)->count(),
            'conversion' => $started > 0 ? round($paid / $started * 100, 1) : 0.0,
            'revenue' => round((float) WlInvoice::query()->where('doc_type', WlInvoice::INVOICE)->where('status', 'paid')->sum('total'), 2),
            'outstanding' => round((float) (clone $unpaid)->sum('balance_due'), 2),
            'overdue' => (clone $unpaid)->whereNotNull('due_at')->where('due_at', '<', now())->count(),
            'started_30' => WlFunnelInstance::query()->where('created_at', '>=', now()->subDays(30))->count(),
            'paid_30' => WlFunnelInstance::query()->whereIn('status', $paidStates)->where('updated_at', '>=', now()->subDays(30))->count(),
        ];
    }
}
