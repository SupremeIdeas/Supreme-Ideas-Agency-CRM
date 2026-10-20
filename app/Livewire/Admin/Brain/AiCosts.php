<?php

namespace App\Livewire\Admin\Brain;

use App\Models\AiUsageLog;
use App\Models\Setting;
use App\Support\Auditor;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

/**
 * Admin > AI costs (NaaraBrain Sprint 0, MASTER-ONLY): what every Claude call really cost, per feature, from the measured ai_usage_log (never from estimates), plus
 * the per-million-token price table the cost is computed from. Numbers only: no prompt or reply text is ever stored or shown here.
 */
class AiCosts extends Component
{
    public int $days = 30;

    /** @var array<string, array<string, string>> family => field => value */
    public array $prices = [];

    public ?string $saved = null;


    private const FIELDS = ['in', 'out', 'cache_write', 'cache_read'];

    public function booted(): void
    {
        abort_unless(Auth::user()?->hasAnyRole(['super_admin', 'admin']), 403);
    }

    public function mount(): void
    {
        $this->loadPrices();
    }

    public function setDays(int $d): void
    {
        $this->days = in_array($d, [1, 7, 30, 90], true) ? $d : 30;
    }

    public function savePrices(): void
    {
        $this->validate(['prices.*.*' => 'required|numeric|min:0|max:1000']);
        foreach ($this->prices as $family => $row) {
            if (! array_key_exists($family, (array) config('ai.prices'))) {
                continue;
            }
            foreach (self::FIELDS as $f) {
                Setting::setValue("ai.price.$family.$f", (float) $row[$f], 'ai');
            }
        }
        Auditor::log('ai.prices_updated', 'Setting', 0, ['families' => array_keys($this->prices)]);
        $this->saved = 'Prices saved. New calls use them; past rows keep the cost they were logged with.';
    }

    public function resetPrices(): void
    {
        foreach (array_keys((array) config('ai.prices')) as $family) {
            foreach (self::FIELDS as $f) {
                Setting::query()->where('key', "ai.price.$family.$f")->get()->each->delete(); // per-model so the settings cache is cleared
            }
        }
        $this->loadPrices();
        $this->saved = 'Prices reset to the shipped list prices.';
    }

    private function loadPrices(): void
    {
        foreach ((array) config('ai.prices') as $family => $defaults) {
            foreach (self::FIELDS as $f) {
                $this->prices[$family][$f] = (string) Setting::getValue("ai.price.$family.$f", $defaults[$f]);
            }
        }
    }

    /** @param  list<int>  $values */
    private static function percentile(array $values, float $p): int
    {
        if ($values === []) {
            return 0;
        }
        sort($values);

        return $values[(int) min(count($values) - 1, floor($p * count($values)))];
    }

    public function render()
    {
        $since = now()->subDays($this->days);
        $q = fn () => AiUsageLog::query()->where('created_at', '>=', $since);

        $features = $q()->select('feature', DB::raw('count(*) as calls'), DB::raw("sum(case when status = 'error' then 1 else 0 end) as errors"),
            DB::raw('sum(input_tokens) as tin'), DB::raw('sum(output_tokens) as tout'), DB::raw('sum(cache_read_tokens) as tcr'), DB::raw('sum(cache_write_tokens) as tcw'),
            DB::raw('sum(cost_usd) as cost'), DB::raw('avg(duration_ms) as avg_ms'), DB::raw('avg(attempts) as attempts'))
            ->groupBy('feature')->orderByDesc('cost')->get()->map(function ($r) {
                $ctx = (int) $r->tin + (int) $r->tcr + (int) $r->tcw;
                $durations = AiUsageLog::query()->where('feature', $r->feature)->where('created_at', '>=', now()->subDays($this->days))->orderByDesc('id')->limit(5000)->pluck('duration_ms')->map(fn ($v) => (int) $v)->all();

                return [
                    'feature' => $r->feature, 'calls' => (int) $r->calls, 'errors' => (int) $r->errors,
                    'in' => (int) $r->tin, 'out' => (int) $r->tout, 'cache_read' => (int) $r->tcr,
                    'cache_hit' => $ctx > 0 ? round(100 * (int) $r->tcr / $ctx, 1) : 0.0,
                    'cost' => (float) $r->cost, 'per_call' => (int) $r->calls > 0 ? (float) $r->cost / (int) $r->calls : 0.0,
                    'p50' => self::percentile($durations, 0.5), 'p95' => self::percentile($durations, 0.95), 'attempts' => round((float) $r->attempts, 2),
                ];
            });

        $models = $q()->select('model', DB::raw('count(*) as calls'), DB::raw('sum(cost_usd) as cost'))->groupBy('model')->orderByDesc('cost')->get();
        $daily = $q()->select(DB::raw('date(created_at) as d'), DB::raw('count(*) as calls'), DB::raw('sum(cost_usd) as cost'))->groupBy('d')->orderBy('d')->get();
        $total = ['calls' => $features->sum('calls'), 'cost' => $features->sum('cost'), 'errors' => $features->sum('errors'), 'tokens' => $features->sum('in') + $features->sum('out')];

        return view('livewire.admin.brain.ai-costs', compact('features', 'models', 'daily', 'total'));
    }
}
