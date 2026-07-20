<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PruneUsageLogs extends Command
{
    protected $signature = 'usage:prune
        {--days= : Override the retention window (in days); falls back to the ai_usage_log_retention_days setting}
        {--dry-run : Report how many rows would be deleted without deleting anything}';

    protected $description = 'Prune aggregated ai_usage_logs rows older than the retention window so the table does not grow unbounded.';

    /**
     * Default retention window used when neither --days nor the
     * ai_usage_log_retention_days setting provides a usable value.
     */
    private const DEFAULT_RETENTION_DAYS = 90;

    /**
     * Rows deleted per iteration. Chunking avoids a single giant DELETE that
     * would hold a long table lock on busy installs.
     */
    private const CHUNK_SIZE = 1000;

    public function handle(): int
    {
        $days = $this->resolveRetentionDays();
        $cutoff = now()->subDays($days);

        // SAFETY: only aggregated rows are eligible. Un-aggregated rows still
        // feed analytics:aggregate, so deleting them would silently lose data.
        $base = fn () => DB::table('ai_usage_logs')
            ->whereNotNull('aggregated_at')
            ->where('created_at', '<', $cutoff);

        if ($this->option('dry-run')) {
            $count = $base()->count();

            $this->info("[dry-run] {$count} aggregated usage log row(s) older than {$days} day(s) would be pruned.");

            return self::SUCCESS;
        }

        $totalDeleted = 0;
        do {
            $deleted = $base()->limit(self::CHUNK_SIZE)->delete();
            $totalDeleted += $deleted;
        } while ($deleted > 0);

        $this->info("Pruned {$totalDeleted} aggregated usage log row(s) older than {$days} day(s).");

        return self::SUCCESS;
    }

    /**
     * Resolve the retention window, preferring the --days flag, then the
     * configurable setting, then the hard default. Non-positive values are
     * treated as unset so a misconfigured setting can never wipe the table.
     */
    private function resolveRetentionDays(): int
    {
        $override = $this->option('days');
        if ($override !== null && (int) $override > 0) {
            return (int) $override;
        }

        $configured = (int) settings('ai_usage_log_retention_days', self::DEFAULT_RETENTION_DAYS);

        return $configured > 0 ? $configured : self::DEFAULT_RETENTION_DAYS;
    }
}
