<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Addons\AiChatbot\Database\Seeders\ChatbotModeSeeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $seeders = [
            FoundationSeeder::class,
            AdminSeeder::class,
            AiModelSeeder::class,
            AiToolCategorySeeder::class,
            AiToolSeeder::class,
        ];

        if (class_exists(ChatbotModeSeeder::class)) {
            $seeders[] = ChatbotModeSeeder::class;
        }

        $seeders = array_merge($seeders, [
            RagToolSeeder::class,
            PlanSeeder::class,
            PaymentGatewaySeeder::class,
            PageSeeder::class,
            MailTemplateSeeder::class,
            SupportSeeder::class,
            ContactSeeder::class,
        ]);

        $this->call($seeders);
    }
}
