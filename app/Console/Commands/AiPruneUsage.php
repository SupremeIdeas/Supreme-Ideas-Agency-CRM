<?php

namespace App\Console\Commands;

use App\Models\AiUsageLog;
use Illuminate\Console\Command;

/** Deletes AI usage rows older than the retention window (NaaraBrain Sprint 0, MASTER-ONLY). The log holds numbers only, so this is housekeeping, not privacy. */
class AiPruneUsage extends Command
{
    protected $signature = 'ai:prune-usage';

    protected $description = 'Delete ai_usage_log rows older than ai.retention_days';

    public function handle(): int
    {
        $n = AiUsageLog::query()->where('created_at', '<', now()->subDays(max(7, (int) config('ai.retention_days', 180))))->delete();
        $this->info("Pruned {$n} row(s).");

        return self::SUCCESS;
    }
}
