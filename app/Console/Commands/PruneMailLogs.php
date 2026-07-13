<?php

namespace App\Console\Commands;

use App\Models\MailLog;
use Illuminate\Console\Command;

class PruneMailLogs extends Command
{
    protected $signature = 'mail:prune-logs {--days=90 : Delete mail log rows older than this many days}';

    protected $description = 'Prune old mail_logs rows so the table does not grow unbounded (e.g. after newsletter sends).';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $cutoff = now()->subDays($days);

        $deleted = MailLog::where('created_at', '<', $cutoff)->delete();

        $this->info("Pruned {$deleted} mail log row(s) older than {$days} day(s).");

        return self::SUCCESS;
    }
}
