<?php

namespace App\Console\Commands;

use App\Models\AiUsageLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AggregateAnalytics extends Command
{
    protected $signature = 'analytics:aggregate';

    protected $description = 'Aggregate AI usage logs into daily summary records for dashboards.';

    public function handle(): int
    {
        $cutoff = now()->subMinutes(65)->startOfMinute();

        // Aggregate unprocessed logs into daily summaries
        $aggregated = DB::table('ai_usage_logs')
            ->where('created_at', '>=', now()->subMinutes(65)->startOfHour())
            ->where('created_at', '<', $cutoff)
            ->whereNull('aggregated_at')
            ->where('status', 'success')
            ->groupBy(DB::raw('DATE(created_at)'), 'provider', 'model', 'type')
            ->selectRaw('
                DATE(created_at) as date,
                provider,
                model,
                type,
                COUNT(*) as total_requests,
                SUM(input_tokens) as total_input_tokens,
                SUM(output_tokens) as total_output_tokens,
                SUM(cost_usd) as total_cost_usd,
                SUM(credits_used) as total_credits
            ')
            ->get();

        if ($aggregated->isEmpty()) {
            $this->info('No new logs to aggregate.');

            return self::SUCCESS;
        }

        $count = 0;
        foreach ($aggregated as $row) {
            DB::table('analytics_daily')->upsert(
                [
                    'date' => $row->date,
                    'provider' => $row->provider,
                    'model' => $row->model,
                    'type' => $row->type,
                    'total_requests' => $row->total_requests,
                    'total_input_tokens' => $row->total_input_tokens,
                    'total_output_tokens' => $row->total_output_tokens,
                    'total_cost_usd' => $row->total_cost_usd,
                    'total_credits' => $row->total_credits,
                    'updated_at' => now(),
                ],
                ['date', 'provider', 'model', 'type'],
                ['total_requests', 'total_input_tokens', 'total_output_tokens', 'total_cost_usd', 'total_credits', 'updated_at']
            );
            $count++;
        }

        // Mark aggregated
        DB::table('ai_usage_logs')
            ->where('created_at', '>=', now()->subMinutes(65)->startOfHour())
            ->where('created_at', '<', $cutoff)
            ->whereNull('aggregated_at')
            ->where('status', 'success')
            ->update(['aggregated_at' => now()]);

        $this->info("Aggregated {$count} daily summary row(s) from usage logs.");

        return self::SUCCESS;
    }
}
