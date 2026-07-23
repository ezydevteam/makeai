<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Refreshes the embedding / audio / reranking / image side of the AI catalog to
 * the July 2026 lineups — the companion to the chat-model refresh in
 * 2026_07_23_000005.
 *
 * Same contract: removed models are deactivated (never deleted, to preserve
 * ingest/usage-log references), and any RAG embedding/reranking setting pointing
 * at a removed slug is repointed. New rows are upserted by re-running the core
 * AiModelSeeder; the image row (stable-diffusion-3) belongs to the ai-image-pro
 * addon and is refreshed by that addon's own seeder on re-enable — here we only
 * deactivate the retired slug so it doesn't linger active.
 */
return new class extends Migration
{
    /** Removed slug => the active slug a stored setting should move to. */
    private const REPLACEMENTS = [
        'command-r-plus' => 'command-a-03-2025',
        'rerank-v3.5' => 'rerank-v4.0-fast',
        'voyage-3' => 'voyage-4',
        'voyage-3-lite' => 'voyage-4-lite',
        // ai-image-pro addon model.
        'stable-diffusion-3' => 'stable-diffusion-3.5',
    ];

    public function up(): void
    {
        $removed = array_keys(self::REPLACEMENTS);

        $this->repointSetting('rag_embedding_model', $removed);
        $this->repointSetting('rag_reranking_model', $removed);

        DB::table('ai_models')->whereIn('slug', $removed)->update(['is_active' => false]);

        if (class_exists(\Database\Seeders\AiModelSeeder::class)) {
            (new \Database\Seeders\AiModelSeeder)->run();
        }
    }

    public function down(): void
    {
        DB::table('ai_models')->whereIn('slug', array_keys(self::REPLACEMENTS))->update(['is_active' => true]);
    }

    private function repointSetting(string $key, array $removed): void
    {
        $current = settings($key);

        if (is_string($current) && in_array($current, $removed, true)) {
            settings_set($key, self::REPLACEMENTS[$current], 'string', 'ai');
        }
    }
};
