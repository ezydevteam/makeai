<?php

namespace App\Console\Commands;

use App\Models\DataExportRequest;
use Illuminate\Console\Command;

class CleanupExpiredExports extends Command
{
    protected $signature = 'gdpr:cleanup-exports {--prune-days=30 : Delete expired records older than this many days}';

    protected $description = 'Transition expired GDPR data-export requests to the expired state, remove their files, and prune old records.';

    public function handle(): int
    {
        // 1) Expire ready/downloaded requests whose retention window has closed.
        //    The file is normally already gone (exports:cleanup deletes it at
        //    24h); unlink defensively in case timings drift, then mark expired.
        $expired = 0;
        DataExportRequest::query()
            ->whereIn('status', ['ready', 'downloaded'])
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->each(function (DataExportRequest $request) use (&$expired) {
                if ($request->file_path) {
                    $path = storage_path('app/' . $request->file_path);
                    if (is_file($path)) {
                        @unlink($path);
                    }
                }

                $request->update(['status' => 'expired', 'file_path' => null]);
                $expired++;
            });

        // 2) Prune long-expired records so the table stays lean.
        $pruned = DataExportRequest::query()
            ->where('status', 'expired')
            ->where('updated_at', '<', now()->subDays((int) $this->option('prune-days')))
            ->delete();

        // 3) Report how many exports are still downloadable (uses both scopes).
        $downloadable = DataExportRequest::query()->ready()->notExpired()->count();

        $this->info("Expired {$expired} export(s); pruned {$pruned} old record(s); {$downloadable} still downloadable.");

        return self::SUCCESS;
    }
}
