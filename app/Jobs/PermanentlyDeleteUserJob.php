<?php

namespace App\Jobs;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

/**
 * Queued wrapper around a permanent user deletion.
 *
 * The cleanup itself lives in UserObserver::forceDeleting() so that every path to a permanent
 * deletion gets it — this job, and the admin trash screen, which deletes synchronously.
 * Keep it that way: work added here would silently not run for admin-initiated deletions.
 */
class PermanentlyDeleteUserJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(private readonly User $user) {}

    public function handle(): void
    {
        DB::transaction(fn () => $this->user->forceDelete());
    }
}
