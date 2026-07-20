<?php

namespace App\Console\Commands;

use App\Services\AI\ToolViewTrackingService;
use Illuminate\Console\Command;

class FlushToolViews extends Command
{
    protected $signature = 'tools:flush-views';
    protected $description = 'Flush Redis tool page view counters to the database.';

    public function handle(ToolViewTrackingService $viewTracker): int
    {
        $flushed = $viewTracker->flushToDatabase();

        $this->info("Flushed view counters for {$flushed} tools.");

        return self::SUCCESS;
    }
}
