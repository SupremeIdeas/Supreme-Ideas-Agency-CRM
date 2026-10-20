<?php

namespace App\Http\Controllers;

use App\Models\WhiteLabelInstance;
use App\Models\WlFunnelManualPayment;
use App\Services\Updater\WhiteLabelProjectIntakeException;
use App\Services\Updater\WhiteLabelProjectIntakeService;
use App\Services\WhiteLabel\Funnel\WlFunnel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Setup details after a White Label payment is approved (Funnel Phase 6.4, MASTER-ONLY). A plain POST form on purpose: a server password never sits in client-side component state.
 * Own-server details are written to the encrypted intake columns; people who chose Supreme Ideas hosting are never asked for a login.
 */
class WlFunnelSetupController extends Controller
{
    private function context(Request $request): array
    {
        $inst = app(WlFunnel::class)->forUser($request->user());
        abort_if($inst === null, 404);
        $pay = WlFunnelManualPayment::query()->where('funnel_instance_id', $inst->id)->where('status', 'approved')->whereNotNull('white_label_instance_id')->latest('id')->first();
        $license = $pay ? WhiteLabelInstance::query()->find($pay->white_label_instance_id) : null;
        abort_if($license === null || ! $license->hasLiveLicense(), 404);

        return [$inst, $license];
    }

    public function show(Request $request)
    {
        [$inst, $license] = $this->context($request);
        $d = (array) $inst->draft;

        return view('wl-funnel.setup', ['inst' => $inst, 'license' => $license, 'd' => $d, 'intake' => $license->intake, 'own' => in_array($d['hosting_choice'] ?? '', [WhiteLabelInstance::HOSTING_OWN_VPS, WhiteLabelInstance::HOSTING_OWN_SHARED], true)]);
    }

    public function store(Request $request, WhiteLabelProjectIntakeService $intakes)
    {
        [$inst, $license] = $this->context($request);
        $d = (array) $inst->draft;
        $own = in_array($d['hosting_choice'] ?? '', [WhiteLabelInstance::HOSTING_OWN_VPS, WhiteLabelInstance::HOSTING_OWN_SHARED], true);
        $rules = ['whatsapp_number' => ['required', 'regex:/^\+[1-9][0-9]{7,14}$/'], 'additional_notes' => ['nullable', 'max:1000']];
        if ($own) {
            $rules += ['hosting_host' => ['required', 'max:190'], 'hosting_username' => ['required', 'max:120'], 'hosting_password' => ['required', 'max:300'], 'hosting_notes' => ['nullable', 'max:1000']];
        }
        $in = $request->validate($rules, ['whatsapp_number.regex' => 'Enter your WhatsApp number with the country code, for example +2348012345678.']);
        try {
            $intakes->submit($license, [
                'desired_brand_name' => (string) ($d['brand_name'] ?? $license->brand_name), 'whatsapp_number' => $in['whatsapp_number'], 'hosting_choice' => (string) ($d['hosting_choice'] ?? WhiteLabelInstance::HOSTING_SUPREME_IDEAS_SERVER),
                'brand_primary_color' => $d['primary'] ?? null, 'brand_accent_color' => $d['accent'] ?? null, 'hosting_disclaimer_acknowledged' => $own,
                'hosting_host' => $in['hosting_host'] ?? null, 'hosting_username' => $in['hosting_username'] ?? null, 'hosting_password' => $in['hosting_password'] ?? null, 'hosting_notes' => $in['hosting_notes'] ?? null,
                'additional_notes' => $in['additional_notes'] ?? null,
            ]);
        } catch (WhiteLabelProjectIntakeException $e) {
            return back()->withErrors(['whatsapp_number' => 'Your licence is not active yet. Please try again shortly.']);
        }
        app(WlFunnel::class)->transition($inst, \App\Services\WhiteLabel\Funnel\WlFunnelStates::SETUP, 'user', ['intake' => true]);

        return redirect()->route('wl-funnel.setup')->with('status', 'Thank you. We have your details and will start your setup.');
    }
}
