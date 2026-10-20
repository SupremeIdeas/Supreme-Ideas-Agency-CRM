<?php

namespace App\Support\Verify\Checks;

use App\Models\WhiteLabelLicensePlan;
use App\Models\WlFunnelInstance;
use App\Services\WhiteLabel\Funnel\WlFunnelCode;
use App\Services\WhiteLabel\Funnel\WlFunnelStates;
use App\Support\Verify\Check;
use App\Support\Verify\CheckResult;
use Throwable;

/** MASTER-ONLY. `naara:verify wl`: the funnel's state machine is closed, codes round-trip, every plan maps to a prefix, no instance sits in an unknown state or lacks its code. */
class WlFunnelSound implements Check
{
    public function area(): string
    {
        return 'wl';
    }

    public function name(): string
    {
        return 'White Label funnel: state machine, codes and instances are consistent';
    }

    public function run(): CheckResult
    {
        try {
            $bad = [];
            foreach (WlFunnelStates::TRANSITIONS as $from => $tos) {
                foreach ($tos as $to) {
                    if (! array_key_exists($to, WlFunnelStates::TRANSITIONS)) {
                        $bad[] = "$from leads to an unknown state $to";
                    }
                }
            }
            foreach ([WhiteLabelLicensePlan::KEY_BASIC, WhiteLabelLicensePlan::KEY_MEDIUM, WhiteLabelLicensePlan::KEY_EXTENDED, WhiteLabelLicensePlan::KEY_EXTENDED_V2] as $k) {
                if (! WlFunnelCode::valid(WlFunnelCode::generate($k))) {
                    $bad[] = "a code for plan $k does not validate";
                }
            }
            $unknown = WlFunnelInstance::query()->whereNotIn('status', WlFunnelStates::all())->count();
            if ($unknown > 0) {
                $bad[] = "$unknown instance(s) are in an unknown state";
            }
            $badCode = 0;
            WlFunnelInstance::query()->select('id', 'public_code')->orderBy('id')->chunk(500, function ($rows) use (&$badCode) {
                foreach ($rows as $r) {
                    if (! WlFunnelCode::valid($r->public_code)) {
                        $badCode++;
                    }
                }
            });
            if ($badCode > 0) {
                $bad[] = "$badCode instance(s) have a code that does not validate";
            }
            if (\Illuminate\Support\Facades\Schema::hasTable('wl_invoices') && \Illuminate\Support\Facades\Schema::hasTable('wl_funnel_manual_payments')) {
                $missing = \App\Models\WlFunnelManualPayment::query()->where('status', 'approved')->whereNotNull('white_label_instance_id')->get(['id', 'purpose'])->filter(fn ($p) => ! \App\Models\WlInvoice::query()->where('source_key', 'mp:'.$p->id.($p->purpose === 'balance' ? ':receipt' : ':invoice'))->exists())->count();
                if ($missing > 0) {
                    $bad[] = "$missing approved payment(s) have no invoice yet (wl-invoices:retry will issue them)";
                }
                foreach (\App\Models\WlInvoice::query()->select('id', 'number', 'subtotal', 'tax_total', 'total', 'lines')->limit(2000)->get() as $inv) {
                    if (abs(array_sum(array_column((array) $inv->lines, 'amount')) - (float) $inv->subtotal) > 0.005 || abs((float) $inv->subtotal + (float) $inv->tax_total - (float) $inv->total) > 0.005) {
                        $bad[] = "document {$inv->number} does not add up";
                    }
                }
                $dupes = \Illuminate\Support\Facades\DB::table('wl_invoices')->select('number')->groupBy('number')->havingRaw('count(*) > 1')->count();
                if ($dupes > 0) {
                    $bad[] = "$dupes duplicate document number(s)";
                }
                if (\App\Services\WhiteLabel\Funnel\WlInvoiceSettings::sellerName() === '' && \App\Models\WlInvoice::query()->exists()) {
                    $bad[] = 'the legal seller name is not set in Admin > White Label invoices';
                }
            }
            if (\Illuminate\Support\Facades\Schema::hasTable('wl_funnel_manual_payments')) {
                $noLicence = \App\Models\WlFunnelManualPayment::query()->where('status', 'approved')->whereNull('white_label_instance_id')->count();
                if ($noLicence > 0) {
                    $bad[] = "$noLicence approved payment(s) have no licence";
                }
                $twice = \App\Models\WlFunnelManualPayment::query()->where('purpose', 'license')->whereNotNull('white_label_instance_id')->selectRaw('white_label_instance_id')->groupBy('white_label_instance_id')->havingRaw('count(*) > 1')->count();
                if ($twice > 0) {
                    $bad[] = "$twice licence(s) are tied to more than one payment";
                }
            }
        } catch (Throwable $e) {
            return CheckResult::fail('The funnel could not be checked: '.$e->getMessage());
        }

        return $bad === [] ? CheckResult::pass('The funnel state machine is closed and every code validates.') : CheckResult::fail(implode('; ', $bad).'.', ['problems' => $bad]);
    }
}
