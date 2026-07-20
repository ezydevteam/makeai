<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Reference seeder.
 *
 * MakeAI does NOT populate a new installation from seeders. The installation
 * wizard imports database/data/data.sql — a data-only export of the fully
 * prepared database (settings, AI models, tools, pages, mail templates, plans,
 * …) — immediately after running the migrations. The product's own seeder
 * classes are therefore not part of this package.
 *
 * This file exists so that `php artisan db:seed` remains a working entry point
 * for your own data. Add your seeders below and run:
 *
 *   php artisan db:seed
 *
 * Do NOT run `php artisan migrate:fresh --seed` on a live installation: it
 * drops every table, and the data this product needs lives in data.sql, not
 * here. To rebuild from scratch, reinstall through the wizard instead.
 */
class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Register your own seeders here, for example:
        //
        // $this->call([
        //     MyCustomSeeder::class,
        // ]);
        //
        // Factories are available too — see database/factories/UserFactory.php:
        //
        // \App\Models\User::factory(10)->create();
    }
}
