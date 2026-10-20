<?php

namespace App\Support\Verify\Checks;

use App\Models\Omni\OmniRawEvent;
use App\Services\Omni\AdapterRegistry;
use App\Support\Verify\Check;
use App\Support\Verify\CheckResult;
use Illuminate\Support\Facades\Schema;
use Throwable;

/** MASTER-ONLY. `naara:verify omni`: the Naara Respond core is installed, nothing is stuck in the webhook inbox, and no connection lacks its webhook address. Pure reads. */
class OmniCoreSound implements Check
{
    public function area(): string
    {
        return 'omni';
    }

    public function name(): string
    {
        return 'Omni core tables, webhook inbox and connections';
    }

    public function run(): CheckResult
    {
        try {
            foreach (['omni_contacts', 'omni_identities', 'omni_conversations', 'omni_messages', 'omni_raw_events'] as $t) {
                if (! Schema::hasTable($t)) {
                    return CheckResult::fail("Table {$t} is missing: run the migrations.");
                }
            }
            $stuck = OmniRawEvent::query()->where('status', 'pending')->where('received_at', '<', now()->subMinutes(15))->count();
            $failed = OmniRawEvent::query()->where('status', 'failed')->where('received_at', '>=', now()->subDay())->count();
            $noAddress = Schema::hasColumn('channel_connections', 'public_id')
                ? \App\Models\ChannelConnection::query()->where('status', 'active')->whereNull('public_id')->whereIn('channel', app(AdapterRegistry::class)->channels())->count()
                : 0;
        } catch (Throwable $e) {
            return CheckResult::fail('The omni core could not be checked: '.$e->getMessage());
        }
        if ($stuck > 0) {
            return CheckResult::fail("{$stuck} webhook event(s) have waited over 15 minutes: the queue worker or the cron is not draining.", ['stuck' => $stuck]);
        }
        if ($failed > 0 || $noAddress > 0) {
            return CheckResult::warn("{$failed} event(s) failed in the last day, {$noAddress} active connection(s) have no webhook address.", ['failed' => $failed, 'no_address' => $noAddress]);
        }

        return CheckResult::pass('Omni tables present, webhook inbox drained, every connection has its address.');
    }
}
