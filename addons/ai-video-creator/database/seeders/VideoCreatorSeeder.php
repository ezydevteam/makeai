<?php

namespace Addons\AiVideoCreator\Database\Seeders;

use Addons\AiVideoCreator\Models\VcFolder;
use Addons\AiVideoCreator\Models\VcProject;
use Addons\AiVideoCreator\Models\VcRender;
use App\Models\User;
use Illuminate\Database\Seeder;

class VideoCreatorSeeder extends Seeder
{
    public function run(): void
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('vc_projects')) {
            return;
        }

        if (VcProject::count() > 0) {
            return;
        }

        $user = User::first();
        if (! $user) {
            return;
        }

        $marketingFolder = VcFolder::create([
            'user_id' => $user->id,
            'name' => 'Marketing Videos',
            'color' => '#6366f1',
            'sort_order' => 1,
        ]);

        $personalFolder = VcFolder::create([
            'user_id' => $user->id,
            'name' => 'Personal',
            'color' => '#10b981',
            'sort_order' => 2,
        ]);

        $project1 = VcProject::create([
            'user_id' => $user->id,
            'name' => 'Product Demos',
            'folder_id' => $marketingFolder->id,
            'color' => '#6366f1',
        ]);

        $project2 = VcProject::create([
            'user_id' => $user->id,
            'name' => 'Travel Memories',
            'folder_id' => $personalFolder->id,
            'color' => '#10b981',
        ]);

        VcRender::create([
            'user_id' => $user->id,
            'vc_project_id' => $project1->id,
            'type' => 'text_to_video',
            'status' => 'completed',
            'provider' => 'kling',
            'title' => 'Demo: Mountain Sunset',
            'prompt' => 'A serene mountain landscape at sunset with golden light and drifting clouds',
            'duration_seconds' => 5,
            'aspect_ratio' => '16:9',
            'duration_actual' => 5,
            'credits_deducted' => 50,
            'completed_at' => now(),
        ]);

        VcRender::create([
            'user_id' => $user->id,
            'vc_project_id' => $project2->id,
            'type' => 'image_to_video',
            'status' => 'completed',
            'provider' => 'kling',
            'title' => 'Demo: Beach Waves',
            'prompt' => 'Gentle waves on a tropical beach',
            'duration_seconds' => 5,
            'aspect_ratio' => '16:9',
            'duration_actual' => 5,
            'credits_deducted' => 40,
            'completed_at' => now(),
        ]);

        // Update denormalized counts
        $project1->update([
            'render_count' => $project1->renders()->count(),
            'total_duration' => $project1->renders()->sum('duration_actual'),
        ]);
        $project2->update([
            'render_count' => $project2->renders()->count(),
            'total_duration' => $project2->renders()->sum('duration_actual'),
        ]);
    }
}
