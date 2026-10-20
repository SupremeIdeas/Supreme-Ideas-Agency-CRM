<?php

namespace App\Console\Commands;

use App\Services\JourneyEngine\Delivery;
use Illuminate\Console\Command;

/** Sends protective Journey alerts to users who opted in (J2). Hourly. A no-op while journey.delivery_enabled is off. */
class JourneyDeliverCommand extends Command
{
    protected $signature = 'naara:journey:deliver {--dry : Work out what would be sent, send nothing}';

    protected $description = 'Push/email protective Journey alerts to users who consented (off until journey.delivery_enabled)';

    public function handle(Delivery $delivery): int
    {
        $r = $delivery->run((bool) $this->option('dry'));
        $this->info("Users considered: {$r['users']}; messages ".($this->option('dry') ? 'that would be sent' : 'sent').": {$r['sent']}");
        foreach ($r['skipped'] as $why => $n) {
            $this->line("  skipped ({$why}): {$n}");
        }

        return self::SUCCESS;
    }
}
