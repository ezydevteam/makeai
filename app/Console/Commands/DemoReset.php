<?php

namespace App\Console\Commands;

use App\Services\AddonService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
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

        // fresh, NOT refresh. `migrate:refresh` rolls back by running every migration's
        // down(), including the bundled addons' — and an addon down() only has to be
        // imperfect once to break the reset forever after. ai-chatbot's
        // make_user_id_nullable_and_add_session_id migration drops a foreign key that is
        // already gone by the time rollback reaches it, which killed the reset with
        // "Can't DROP 'conversations_user_id_foreign'" on the SECOND and every later run:
        // the first reset has no addon migrations to roll back yet, so this stayed hidden
        // until the demo had been up for six hours. `migrate:fresh` drops the tables
        // outright and never calls down(), so no addon can poison the reset. The addon
        // schemas are rebuilt below by activateBundledAddons() → installAddonSchema().
        $this->warn('Dropping and rebuilding schema...');
        Artisan::call('migrate:fresh', ['--force' => true]);

        $this->warn('Seeding base data...');
        Artisan::call('db:seed', ['--force' => true]);

        // migrate:fresh wiped the license row and the addons table, and FoundationSeeder
        // reseeds license_status='invalid'. Everything below depends on that being fixed
        // first: AddonService::activate() refuses on checkLicenseRequirement(), and
        // DemoSeeder writes into addon tables that only activation creates.
        $this->warn('Restoring demo license...');
        $this->activateDemoLicense();

        $this->warn('Activating bundled addons...');
        $this->activateBundledAddons();

        $this->warn('Seeding demo data...');
        Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\DemoSeeder', '--force' => true]);

        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        $this->info('Demo reset complete!');
    }

    /**
     * Mark the license valid so the demo boots as an Extended install.
     *
     * This writes an activated state without contacting the license server, which is only
     * defensible because it cannot run outside demo mode: the config('demo.enabled') gate at
     * the top of handle() has already returned. Demo mode is self-limiting as a piracy route —
     * it blocks every write verb, publishes its own login credentials and wipes the database
     * every six hours — so a build that reaches this code is not a usable unlicensed install.
     */
    private function activateDemoLicense(): void
    {
        settings_set('license_status', 'valid', 'string', 'license');
        settings_set('license_type', '2', 'integer', 'license');
        settings_set('license_buyer', 'demo', 'string', 'license');
        settings_set('license_verified_at', now()->toDateTimeString(), 'string', 'license');
        settings_set('license_domain', hash('sha256', (string) parse_url((string) config('app.url'), PHP_URL_HOST)), 'string', 'license');

        // license_verified() memoises for an hour; without this the activations below
        // still read the pre-seed 'invalid'.
        Cache::forget('license.status');
    }

    /**
     * Activate every addon shipped in addons/.
     *
     * The demo package bundles a fixed set, so "everything on disk" is the showcase set by
     * construction. activate() runs each addon's migrations, seeders and permissions, which
     * is what puts the tables DemoSeeder writes into place.
     */
    private function activateBundledAddons(): void
    {
        $addons = app(AddonService::class);

        $addons->syncFromFilesystem();

        foreach (\App\Models\Addon::orderBy('slug')->pluck('slug') as $slug) {
            if ($addons->activate($slug)) {
                $this->line("  activated {$slug}");
                continue;
            }

            // Not fatal: a demo missing one addon is better than a demo with no data at all.
            $this->warn("  could not activate {$slug}");
        }

        Cache::forget('active_addons_list');
    }
}
