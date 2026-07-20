<?php

namespace App\Console\Commands;

use App\Jobs\PermanentlyDeleteUserJob;
use App\Models\User;
use Illuminate\Console\Command;

class AccountsPurgeDeleted extends Command
{
    protected $signature = 'accounts:purge-deleted';

    protected $description = 'Permanently delete user accounts whose scheduled deletion date has passed.';

    public function handle(): int
    {
        $users = User::where('is_active', false)
            ->whereNotNull('scheduled_deletion_at')
            ->where('scheduled_deletion_at', '<=', now())
            ->get();

        if ($users->isEmpty()) {
            $this->info('No accounts pending permanent deletion.');

            return self::SUCCESS;
        }

        $count = $users->count();
        $this->info("Dispatching permanent deletion for {$count} account(s)...");

        $bar = $this->output->createProgressBar($count);
        $bar->start();

        foreach ($users as $user) {
            PermanentlyDeleteUserJob::dispatch($user);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("{$count} deletion job(s) dispatched.");

        return self::SUCCESS;
    }
}
