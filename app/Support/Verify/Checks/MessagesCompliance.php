<?php

namespace App\Support\Verify\Checks;

use App\Models\VirtualNumber;
use App\Services\SMS\SmsOptOuts;
use App\Support\Verify\Check;
use App\Support\Verify\CheckResult;
use Illuminate\Support\Facades\Schema;

/**
 * Blueprint 5.5, the parts provable without a live provider: STOP/START is understood and persisted, the inbound and delivery webhooks verify a
 * secret with hash_equals BEFORE reading the payload, and every provider that owns a Naara Line has a webhook secret configured.
 */
class MessagesCompliance implements Check
{
    private const PROVIDERS = ['twilio', 'telnyx', 'plivo', 'vonage', 'sinch', 'getatext', 'fivesim', 'herosms'];

    public function area(): string
    {
        return 'messages';
    }

    public function name(): string
    {
        return 'STOP handling, verified webhooks and provider secrets';
    }

    public function run(): CheckResult
    {
        if (! Schema::hasTable('sms_opt_outs')) {
            return CheckResult::fail('The STOP opt-out table is missing; run the migrations.');
        }
        if (SmsOptOuts::keyword('STOP') !== 'stop' || SmsOptOuts::keyword('start') !== 'start' || SmsOptOuts::keyword('please do not stop') !== null) {
            return CheckResult::fail('STOP/START keyword detection is wrong.');
        }
        foreach (['SmsInboundWebhookController', 'SmsDeliveryWebhookController'] as $c) {
            $src = (string) @file_get_contents(app_path("Http/Controllers/Webhooks/{$c}.php"));
            $verify = strpos($src, 'hash_equals');
            $read = strpos($src, '$request->input(');
            if ($verify === false || ($read !== false && $read < $verify)) {
                return CheckResult::fail("{$c} must verify the webhook secret (hash_equals) before reading the payload.");
            }
        }
        $inUse = VirtualNumber::query()->where('status', 'active')->distinct()->pluck('provider')->filter(fn ($p) => in_array($p, self::PROVIDERS, true))->all();
        $missing = array_values(array_filter($inUse, fn ($p) => trim((string) config("services.$p.webhook_token", config("services.$p.inbound_token", ''))) === ''));
        if ($missing !== []) {
            return CheckResult::fail('Active Naara Lines use providers with no inbound webhook secret, so replies cannot be received: '.implode(', ', $missing), ['providers' => $missing]);
        }

        if (in_array('telnyx', $inUse, true) && trim((string) config('services.telnyx.messaging_profile_id', '')) === '') {
            return CheckResult::fail('Active Naara Lines use Telnyx but no Messaging Profile ID is set (Admin > Provider keys), so those numbers cannot send or receive SMS.', ['providers' => ['telnyx']]);
        }

        return CheckResult::pass('STOP is honoured, webhooks verify before trusting, and every provider in use has a secret.', ['providers_in_use' => $inUse]);
    }
}
