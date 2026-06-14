<?php

declare(strict_types=1);

namespace Addons\AiVoiceover\Database\Seeders;

use App\Models\AiTool;
use App\Models\Category;
use App\Services\AI\ToolCatalogCacheService;
use Illuminate\Database\Seeder;

class VoiceoverToolSeeder extends Seeder
{
    public function run(): void
    {
        $category = Category::updateOrCreate(
            ['slug' => 'audio-media', 'type' => 'ai_tool'],
            [
                'name' => '🎙️ Audio & Media',
                'description' => 'Generate voiceovers, produce podcasts, transcribe audio, and publish RSS feeds.',
                'icon' => 'ti ti-microphone-2',
                'color' => '#7c3aed',
                'is_active' => true,
                'is_system' => false,
                'requires_pro' => false,
                'sort_order' => 25,
            ]
        );

        AiTool::updateOrCreate(
            ['slug' => 'voiceover-studio'],
            [
                'name' => 'Voiceover Studio',
                'type' => 'template',
                'description' => 'Generate AI voiceovers from scripts, produce multi-speaker podcast episodes with background music, auto-transcribe audio to text, and publish podcast RSS feeds.',
                'category_id' => $category->id,
                'icon' => 'ti ti-microphone',
                'color' => '#7c3aed',
                'fields' => [],
                'output_type' => 'markdown',
                'access_level' => 'login_required',
                'is_active' => true,
                'is_featured' => true,
                'requires_pro' => false,
                'is_system' => false,
                'supports_brand_voice' => false,
                'avg_output_tokens' => 0,
                'sort_order' => 1,
                'about_content' => 'The Voiceover Studio lets you create professional-quality audio content using AI text-to-speech. Generate voiceovers, produce multi-speaker podcast episodes, add background music, transcribe audio, and publish podcast RSS feeds.',
                'how_it_works' => [
                    ['step' => 1, 'title' => 'Create a project', 'description' => 'Start a voiceover or podcast project.'],
                    ['step' => 2, 'title' => 'Write your script', 'description' => 'Enter text or multi-speaker dialogue.'],
                    ['step' => 3, 'title' => 'Choose voices', 'description' => 'Pick from AI voices across ElevenLabs, OpenAI, Murf, and PlayHT.'],
                    ['step' => 4, 'title' => 'Generate & publish', 'description' => 'Generate audio, add music, transcribe, and publish to RSS.'],
                ],
                'meta_title' => 'AI Voiceover Studio — Generate Podcasts & Voiceovers',
                'meta_description' => 'Create AI voiceovers and podcast episodes with multi-speaker support, background music, transcription, and RSS publishing.',
            ]
        );

        $category->updateToolsCount();
        ToolCatalogCacheService::invalidateAll();
    }
}
