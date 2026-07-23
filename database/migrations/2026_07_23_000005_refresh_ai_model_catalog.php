<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Refreshes the AI model catalog to the July 2026 provider lineups.
 *
 * Removed models are deactivated, never deleted — generation_history and usage
 * logs reference ai_models by slug, and a hard delete would orphan them. Any
 * default/fallback setting pointing at a removed slug is repointed to that
 * provider's current equivalent so no tool is left calling a dead model.
 *
 * New rows are upserted by re-running AiModelSeeder (dev/checkout only — the
 * shipped package seeds ai_models from data.sql, so the seeder is absent on a
 * buyer install and this step is skipped there; data.sql is regenerated
 * separately from the refreshed seeder).
 */
return new class extends Migration
{
    /**
     * Removed slug => the active slug a stored default/fallback should move to.
     * The keys are the full set of models this release retires.
     */
    private const REPLACEMENTS = [
        // OpenAI — two generations behind, plus the dropped 5.5-mini tier.
        'gpt-4o' => 'gpt-5.6-terra',
        'gpt-4o-mini' => 'gpt-5.4-mini',
        'o3' => 'gpt-5.6-sol',
        'o4-mini' => 'gpt-5.4-mini',
        'gpt-5.5-mini' => 'gpt-5.4-mini',
        // Google — 2.0 Flash was shut down by Google on 2026-06-01.
        'gemini-2.0-flash' => 'gemini-3.6-flash',
        // xAI.
        'grok-4.3' => 'grok-4.5',
        'grok-3' => 'grok-4.5',
        'grok-3-mini' => 'grok-4.1-fast',
        // DeepSeek — legacy names deprecated 2026-07-24.
        'deepseek-r1' => 'deepseek-v4-pro',
        'deepseek-v3' => 'deepseek-v4-pro',
        // Groq — llama-4 deprecated on Groq; wrong 70b id; mixtral retired.
        'llama-4-scout-17b' => 'llama-3.3-70b-versatile',
        'llama-3.3-70b' => 'llama-3.3-70b-versatile',
        'mixtral-8x7b' => 'llama-3.1-8b-instant',
        // Mistral — no current "medium" tier.
        'mistral-medium-latest' => 'mistral-large-latest',
        // OpenRouter prefixed mirrors.
        'openai/gpt-4o' => 'openai/gpt-5.6-terra',
        'openai/gpt-4o-mini' => 'openai/gpt-5.4',
        'openai/o4-mini' => 'openai/gpt-5.4',
        'google/gemini-2.0-flash' => 'google/gemini-3.6-flash',
        'deepseek/deepseek-r1' => 'deepseek/deepseek-v4-pro',
    ];

    public function up(): void
    {
        $removed = array_keys(self::REPLACEMENTS);

        // 1. Repoint the default/fallback model settings before deactivating, so a
        //    site whose default points at a removed model keeps a working one.
        //    These live in the `ai` settings blob (group:ai), not flat rows.
        $this->repointSetting('default_ai_model', $removed);
        $this->repointSetting('fallback_ai_model', $removed);

        // 2. Deactivate the removed models. whereIn is a no-op for any slug that
        //    was never seeded on this install.
        DB::table('ai_models')->whereIn('slug', $removed)->update(['is_active' => false]);

        // 3. Upsert the new lineup. Only present in a dev checkout.
        if (class_exists(\Database\Seeders\AiModelSeeder::class)) {
            (new \Database\Seeders\AiModelSeeder)->run();
        }
    }

    public function down(): void
    {
        // Reactivate what was deactivated. The added rows are left in place —
        // they are harmless when inactive and re-seeding is idempotent.
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
