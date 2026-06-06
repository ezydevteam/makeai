<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            FoundationSeeder::class,
            AdminSeeder::class,
            AiModelSeeder::class,
            AiToolCategorySeeder::class,
            AiTemplateSeeder::class,
            ChatbotProductSeeder::class,
            SiteTemplateSeeder::class,
            PlanSeeder::class,
            PaymentGatewaySeeder::class,
            PageSeeder::class,
            MailTemplateSeeder::class,
            SupportSeeder::class,
        ]);
    }
}
