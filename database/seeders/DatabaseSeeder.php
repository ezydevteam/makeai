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

        // Optional bundled-addon seeder. The ai-chatbot addon can be removed by the
        // operator (AddonService::delete() deletes its directory), which leaves the
        // PSR-4-mapped class file gone. Guard so a stale `use` reference never fatals
        // a core `db:seed` / DemoReset. Addon seeding otherwise runs on activation.
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
