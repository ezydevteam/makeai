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
