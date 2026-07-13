<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Core no longer ships the media/generation/search integrations or the Image
 * Editor tool — those become addon-provided. This removes:
 *   - the Image Editor tool row (was seeded by an earlier migration), and
 *   - orphaned `external_{slug}_*` settings for the 14 dropped integrations.
 * Kept integrations: plagiarism, ai_detector, grammar, translation (+ system
 * connectors). Safe no-op on fresh installs.
 */
return new class extends Migration
{
    /** Integrations removed from config/external-tools.php. */
    private array $removed = [
        'image_generation', 'stock_image', 'background_remover', 'image_upscaler',
        'text_to_speech', 'speech_to_text', 'ai_music', 'voice_separator',
        'video_generation', 'avatar_video', 'web_search', 'url_scraper',
        'youtube_transcript', 'gamma',
    ];

    public function up(): void
    {
        if (Schema::hasTable('ai_tools')) {
            DB::table('ai_tools')->where('slug', 'image-editor')->delete();
        }

        // Drop the now-empty "Design & Graphics" category the Image Editor created.
        if (Schema::hasTable('categories') && Schema::hasTable('ai_tools')) {
            $categoryId = DB::table('categories')->where('slug', 'design-graphics')->value('id');
            if ($categoryId && ! DB::table('ai_tools')->where('category_id', $categoryId)->exists()) {
                DB::table('categories')->where('id', $categoryId)->delete();
            }
        }

        if (Schema::hasTable('settings')) {
            foreach ($this->removed as $slug) {
                DB::table('settings')->where('key', 'like', "external_{$slug}_%")->delete();
            }
        }
    }

    public function down(): void
    {
        // Irreversible: these integrations and the Image Editor tool are now
        // addon-provided; there is nothing meaningful to restore here.
    }
};
