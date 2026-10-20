<?php

namespace App\Support\Verify\Checks;

use App\Services\Business\Softphone\SoftphoneAccess;
use App\Support\Business\BusinessSettings;
use App\Support\ProviderStatus;
use App\Support\Verify\Check;
use App\Support\Verify\CheckResult;

/**
 * MASTER-ONLY. `naara:verify softphone`: is everything the native softphone needs in place, and which part is still waiting? Pure reads, never a secret. A missing piece is a
 * warning (the module then simply does not offer itself), but a module switched ON with a missing piece is a failure, because a phone would be told it can ring when it cannot.
 */
class SoftphoneReadiness implements Check
{
    public function area(): string
    {
        return 'softphone';
    }

    public function name(): string
    {
        return 'Native softphone readiness (Twilio, push credentials, switch)';
    }

    public function run(): CheckResult
    {
        $on = BusinessSettings::softphoneLive();
        $missing = [];
        if (! ProviderStatus::isActive('twilio')) {
            $missing[] = 'Twilio is not active';
        }
        foreach (['api_key_sid' => 'TWILIO_API_KEY_SID', 'api_key_secret' => 'TWILIO_API_KEY_SECRET', 'twiml_app_sid' => 'TWILIO_TWIML_APP_SID'] as $k => $env) {
            if ((string) config('services.twilio.'.$k, '') === '') {
                $missing[] = $env.' is missing';
            }
        }
        $ios = SoftphoneAccess::pushCredentialFor('ios') !== null;
        $android = SoftphoneAccess::pushCredentialFor('android') !== null;
        if (! $ios) {
            $missing[] = 'no iOS push credential (calls will not wake an iPhone)';
        }
        if (! $android) {
            $missing[] = 'no Android push credential (calls will not wake an Android phone)';
        }
        $paired = \Illuminate\Support\Facades\Schema::hasTable('business_softphone_devices') ? \App\Models\BusinessSoftphoneDevice::query()->count() : 0;
        $detail = ($on ? 'Switched on' : 'Switched off').'; '.$paired.' paired phone'.($paired === 1 ? '' : 's').'.';
        if ($missing === []) {
            return CheckResult::pass($detail.' Twilio, the voice app and both push credentials are set. Real-phone tests (locked, backgrounded, Do Not Disturb, after restart) are still yours to run.');
        }
        $msg = $detail.' Missing: '.implode('; ', $missing).'.';

        return $on ? CheckResult::fail($msg, ['missing' => $missing]) : CheckResult::warn($msg, ['missing' => $missing]);
    }
}
