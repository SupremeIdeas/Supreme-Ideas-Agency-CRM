<?php

namespace App\Services\Business\Brain;

use App\Models\AiUsageLog;
use App\Models\BrandPartner;
use App\Models\BrandSubscription;
use App\Models\BusinessBrainRun;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * What the AI costs against what Naara Pro earns (MASTER-ONLY, admin only). Everything comes from measured rows: revenue from active paid subscriptions (their monthly
 * price, prorated to the window), cost from ai_usage_log (the real tokens the provider billed) for NaaraBrain chat and the inbox assistant, usage from the run table.
 * Admin-granted Pro brands earn nothing and cost something, so they get their own line instead of hiding inside a plan's average. Margin here is revenue minus AI cost
 * only: it is the AI's contribution, not the whole business's profit (telephony, SMS and hosting costs are tracked elsewhere).
 */
class BusinessBrainEconomics
{
    public const FEATURES = ['brain_chat', 'business_brain_draft', 'business_brain_summary', 'business_brain_tasks', 'respond_agent'];

    /**
     * @return array{days: int, rows: list<array<string, mixed>>, totals: array<string, float|int>, top: list<array<string, mixed>>}
     */
    public function report(int $days = 30): array
    {
        $days = max(1, min($days, 365));
        $since = now()->subDays($days);
        $factor = $days / 30;

        // Every Naara Pro brand owner -> their plan (a paying subscription's plan, else the admin-granted plan, else none) and whether they pay.
        $owners = [];
        BrandPartner::query()->whereNotNull('owner_user_id')->where('is_active', true)->with(['subscription.plan', 'plan'])->get()->each(function (BrandPartner $b) use (&$owners) {
            if (! $b->isNaaraPro()) {
                return;
            }
            $paying = $b->subscription?->status === BrandSubscription::ACTIVE;
            $plan = ($paying ? $b->subscription->plan : null) ?? $b->plan;
            $owners[(int) $b->owner_user_id] = ['plan' => $plan?->name ?? 'Naara Pro (no plan set)', 'price' => $paying ? (float) ($plan?->price_usd_per_month ?? 0) : 0.0, 'paying' => $paying];
        });

        $cost = AiUsageLog::query()->whereIn('feature', self::FEATURES)->where('created_at', '>=', $since)->whereNotNull('user_id')
            ->selectRaw('user_id, feature, SUM(cost_usd) as c, COUNT(*) as n')->groupBy('user_id', 'feature')->get();
        $runs = BusinessBrainRun::query()->where('created_at', '>=', $since)->where('status', 'done')->selectRaw('owner_user_id, COUNT(*) as n')->groupBy('owner_user_id')->pluck('n', 'owner_user_id');

        $rows = [];
        $perOwner = [];
        foreach ($owners as $uid => $o) {
            $key = $o['paying'] ? $o['plan'] : 'Complimentary (admin-granted)';
            $rows[$key] ??= ['plan' => $key, 'subscribers' => 0, 'revenue' => 0.0, 'chat_cost' => 0.0, 'assist_cost' => 0.0, 'actions' => 0, 'complimentary' => ! $o['paying']];
            $rows[$key]['subscribers']++;
            $rows[$key]['revenue'] += $o['price'] * $factor;
            $rows[$key]['actions'] += (int) ($runs[$uid] ?? 0);
            $perOwner[$uid] = ['plan' => $key, 'chat' => 0.0, 'assist' => 0.0, 'price' => $o['price'] * $factor];
        }
        $unmatched = ['plan' => 'Not on Naara Pro (free members, admins)', 'subscribers' => 0, 'revenue' => 0.0, 'chat_cost' => 0.0, 'assist_cost' => 0.0, 'actions' => 0, 'complimentary' => true];
        foreach ($cost as $r) {
            $uid = (int) $r->user_id;
            $isChat = $r->feature === 'brain_chat';
            if (isset($perOwner[$uid])) {
                $rows[$perOwner[$uid]['plan']][$isChat ? 'chat_cost' : 'assist_cost'] += (float) $r->c;
                $perOwner[$uid][$isChat ? 'chat' : 'assist'] += (float) $r->c;
            } else {
                $unmatched[$isChat ? 'chat_cost' : 'assist_cost'] += (float) $r->c;
            }
        }
        if ($unmatched['chat_cost'] + $unmatched['assist_cost'] > 0) {
            $rows[$unmatched['plan']] = $unmatched;
        }

        $out = [];
        $tot = ['subscribers' => 0, 'revenue' => 0.0, 'cost' => 0.0, 'margin' => 0.0, 'actions' => 0];
        foreach ($rows as $r) {
            $total = $r['chat_cost'] + $r['assist_cost'];
            $margin = $r['revenue'] - $total;
            $out[] = $r + ['cost' => round($total, 4), 'margin' => round($margin, 4), 'margin_pct' => $r['revenue'] > 0 ? round($margin / $r['revenue'] * 100, 1) : null,
                'cost_per_sub' => $r['subscribers'] > 0 ? round($total / $r['subscribers'], 4) : null];
            $tot['subscribers'] += $r['subscribers'];
            $tot['revenue'] += $r['revenue'];
            $tot['cost'] += $total;
            $tot['margin'] += $margin;
            $tot['actions'] += $r['actions'];
        }
        usort($out, fn ($a, $b) => $b['revenue'] <=> $a['revenue'] ?: $b['cost'] <=> $a['cost']);

        // The heaviest users: where the cost is, and whether a payer covers it.
        $top = [];
        foreach ($perOwner as $uid => $p) {
            $c = $p['chat'] + $p['assist'];
            if ($c > 0) {
                $top[] = ['user_id' => $uid, 'plan' => $p['plan'], 'cost' => round($c, 4), 'revenue' => round($p['price'], 4), 'covered' => $p['price'] >= $c];
            }
        }
        usort($top, fn ($a, $b) => $b['cost'] <=> $a['cost']);
        $names = User::query()->whereIn('id', array_column(array_slice($top, 0, 10), 'user_id'))->pluck('name', 'id');
        $top = array_map(fn ($t) => $t + ['name' => (string) ($names[$t['user_id']] ?? '#'.$t['user_id'])], array_slice($top, 0, 10));

        return ['days' => $days, 'rows' => $out, 'totals' => array_map(fn ($v) => is_float($v) ? round($v, 4) : $v, $tot), 'top' => $top];
    }
}
