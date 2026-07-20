<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class DemoReset extends Command
{
    protected $signature = 'demo:reset {--force}';

    protected $description = 'Reset the database to demo state';

    public function handle(): void
    {
        // Hard gate: this command runs migrate:refresh, which destroys every row
        // in the database. The scheduler passes --force (the demo site itself runs
        // APP_ENV=production), so the isProduction() check below cannot be the
        // only guard — --force would wave the wipe straight through on a real
        // install. Demo mode being explicitly on is the one signal that means
        // "this database is disposable".
        if (! config('demo.enabled')) {
            $this->error('demo:reset is refused: demo mode is not enabled (DEMO_ENABLED).');
            $this->line('This command runs migrate:refresh and would erase all data.');

            return;
        }

        if (app()->isProduction() && ! $this->option('force')) {
            $this->error('Cannot reset in production without --force');

            return;
        }

        $this->info('Resetting demo environment...');

        // Clear sensitive data but keep structures
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        $this->warn('Refreshing migrations...');
        Artisan::call('migrate:refresh', ['--force' => true]);

        $this->warn('Seeding base data...');
        Artisan::call('db:seed', ['--force' => true]);

        $this->warn('Seeding demo data...');
        Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\DemoSeeder', '--force' => true]);

        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        $this->info('Demo reset complete!');
    }
}
