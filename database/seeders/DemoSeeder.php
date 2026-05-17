<?php

namespace Database\Seeders;

use App\Models\Ad;
use App\Models\MenuItem;
use App\Models\Page;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Create Demo Admin
        User::updateOrCreate(['email' => 'admin@demo.com'], [
            'name' => 'Demo Administrator',
            'password' => Hash::make('demo123'),
            'role' => 'admin',
        ]);

        // 2. Create Demo User
        User::updateOrCreate(['email' => 'user@demo.com'], [
            'name' => 'Demo User',
            'password' => Hash::make('demo123'),
            'role' => 'user',
        ]);

        // 3. Sample Pages
        Page::updateOrCreate(['slug' => 'about-us'], [
            'title' => 'About MakeAI',
            'content' => '<h1>Empowering Creativity with AI</h1><p>MakeAI is the next generation platform for content creators.</p>',
            'is_active' => true,
        ]);

        // 4. Sample Ads
        Ad::updateOrCreate(['zone' => 'sidebar_top'], [
            'type' => 'image',
            'provider' => 'internal',
            'config' => [
                'image_url' => 'https://via.placeholder.com/300x250',
                'target_url' => 'https://envato.com',
            ],
            'is_active' => true,
        ]);

        // 5. Sample Menu Items
        MenuItem::updateOrCreate(['label' => 'Home'], [
            'type' => 'route',
            'value' => 'home',
            'order' => 1,
        ]);

        MenuItem::updateOrCreate(['label' => 'About'], [
            'type' => 'page',
            'value' => 'about-us',
            'order' => 2,
        ]);
    }
}
