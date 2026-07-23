<?php

namespace Database\Seeders;

use App\Models\AiModel;
use App\Services\AI\CreditPricingService;
use Illuminate\Database\Seeder;

class AiModelSeeder extends Seeder
{
    /**
     * Accurate API pricing per 1K tokens (as of July 2026).
     * Type: chat, embedding, reranking, audio
     */
    private const MODELS = [
        // ─── OpenAI ──────────────────────────────────────────────────────
        ['slug' => 'gpt-5.6-sol',       'input' => 0.00500, 'output' => 0.03000, 'credits' => 40,  'max_tokens' => 32768,  'type' => 'chat', 'name' => 'GPT-5.6 Sol (Most Powerful)'],
        ['slug' => 'gpt-5.6-terra',     'input' => 0.00250, 'output' => 0.01500, 'credits' => 20,  'max_tokens' => 32768,  'type' => 'chat', 'name' => 'GPT-5.6 Terra (Balanced)'],
        ['slug' => 'gpt-5.6-luna',      'input' => 0.00100, 'output' => 0.00600, 'credits' => 5,   'max_tokens' => 32768,  'type' => 'chat', 'name' => 'GPT-5.6 Luna (Fast & Affordable)'],
        ['slug' => 'gpt-5.5',           'input' => 0.00500, 'output' => 0.03000, 'credits' => 40,  'max_tokens' => 32768,  'type' => 'chat', 'name' => 'GPT-5.5 (Powerful)'],
        ['slug' => 'gpt-5.4',           'input' => 0.00250, 'output' => 0.01500, 'credits' => 20,  'max_tokens' => 32768,  'type' => 'chat', 'name' => 'GPT-5.4 (Advanced)'],
        ['slug' => 'gpt-5.4-mini',      'input' => 0.00075, 'output' => 0.00450, 'credits' => 4,   'max_tokens' => 32768,  'type' => 'chat', 'name' => 'GPT-5.4 Mini (Efficient)'],
        ['slug' => 'gpt-5.4-nano',      'input' => 0.00020, 'output' => 0.00125, 'credits' => 1,   'max_tokens' => 32768,  'type' => 'chat', 'name' => 'GPT-5.4 Nano (Cheapest)'],
        // OpenAI embeddings — the default embedding provider, previously unseeded.
        ['slug' => 'text-embedding-3-small', 'input' => 0.00002, 'output' => 0, 'credits' => 0, 'max_tokens' => 8191, 'type' => 'embedding', 'name' => 'OpenAI Embedding 3 Small (Text Embedding)'],
        ['slug' => 'text-embedding-3-large', 'input' => 0.00013, 'output' => 0, 'credits' => 0, 'max_tokens' => 8191, 'type' => 'embedding', 'name' => 'OpenAI Embedding 3 Large (Text Embedding)'],

        // ─── Anthropic ──────────────────────────────────────────────────
        ['slug' => 'claude-fable-5',     'input' => 0.01000, 'output' => 0.05000, 'credits' => 160, 'max_tokens' => 16384, 'type' => 'chat', 'name' => 'Claude Fable 5 (Most Capable)'],
        ['slug' => 'claude-opus-4-8',    'input' => 0.00500, 'output' => 0.02500, 'credits' => 80,  'max_tokens' => 16384, 'type' => 'chat', 'name' => 'Claude Opus 4.8 (Highly Capable)'],
        ['slug' => 'claude-sonnet-5',    'input' => 0.00300, 'output' => 0.01500, 'credits' => 20,  'max_tokens' => 16384, 'type' => 'chat', 'name' => 'Claude Sonnet 5 (Best Balance)'],
        ['slug' => 'claude-sonnet-4-6',  'input' => 0.00300, 'output' => 0.01500, 'credits' => 20,  'max_tokens' => 16384, 'type' => 'chat', 'name' => 'Claude Sonnet 4.6 (Balanced)'],
        ['slug' => 'claude-haiku-4-5',   'input' => 0.00100, 'output' => 0.00500, 'credits' => 3,   'max_tokens' => 8192,  'type' => 'chat', 'name' => 'Claude Haiku 4.5 (Fast & Lightweight)'],

        // ─── Google Gemini ──────────────────────────────────────────────
        ['slug' => 'gemini-3.6-flash',      'input' => 0.00150, 'output' => 0.00750, 'credits' => 8,   'max_tokens' => 65536, 'type' => 'chat', 'name' => 'Gemini 3.6 Flash (Latest & Fast)'],
        ['slug' => 'gemini-3.5-flash',      'input' => 0.00150, 'output' => 0.00900, 'credits' => 10,  'max_tokens' => 16384, 'type' => 'chat', 'name' => 'Gemini 3.5 Flash (Ultra Fast)'],
        ['slug' => 'gemini-3.1-pro',        'input' => 0.00200, 'output' => 0.01200, 'credits' => 18,  'max_tokens' => 65536, 'type' => 'chat', 'name' => 'Gemini 3.1 Pro (High Performance)'],
        ['slug' => 'gemini-3.1-flash-lite', 'input' => 0.00010, 'output' => 0.00040, 'credits' => 1,   'max_tokens' => 8192,  'type' => 'chat', 'name' => 'Gemini 3.1 Flash Lite (Lightweight)'],
        ['slug' => 'gemini-2.5-pro',        'input' => 0.00125, 'output' => 0.01000, 'credits' => 15,  'max_tokens' => 65536, 'type' => 'chat', 'name' => 'Gemini 2.5 Pro (Reliable)'],
        ['slug' => 'gemini-2.5-flash',      'input' => 0.00015, 'output' => 0.00060, 'credits' => 1,   'max_tokens' => 8192,  'type' => 'chat', 'name' => 'Gemini 2.5 Flash (Quick)'],
        ['slug' => 'text-embedding-004',    'input' => 0.00001, 'output' => 0, 'credits' => 0, 'max_tokens' => 2048, 'type' => 'embedding', 'name' => 'Gemini Text Embedding 004 (Text Embedding)'],

        // ─── xAI (Grok) ────────────────────────────────────────────────
        ['slug' => 'grok-4.5',          'input' => 0.00200, 'output' => 0.00600, 'credits' => 8,   'max_tokens' => 32768, 'type' => 'chat', 'name' => 'Grok 4.5 (Latest & Smartest)'],
        ['slug' => 'grok-4.1-fast',     'input' => 0.00020, 'output' => 0.00050, 'credits' => 2,   'max_tokens' => 32768, 'type' => 'chat', 'name' => 'Grok 4.1 Fast (Speedy)'],

        // ─── DeepSeek ───────────────────────────────────────────────────
        ['slug' => 'deepseek-v4-pro',   'input' => 0.000435, 'output' => 0.00087, 'credits' => 3,  'max_tokens' => 16384, 'type' => 'chat', 'name' => 'DeepSeek V4 Pro (Best Quality)'],
        ['slug' => 'deepseek-v4-flash', 'input' => 0.000140, 'output' => 0.00028, 'credits' => 1,  'max_tokens' => 16384, 'type' => 'chat', 'name' => 'DeepSeek V4 Flash (Fast & Cheap)'],

        // ─── Perplexity ─────────────────────────────────────────────────
        ['slug' => 'sonar',              'input' => 0.00100, 'output' => 0.00100, 'credits' => 5,   'max_tokens' => 8192, 'type' => 'chat', 'name' => 'Sonar (Web Search)'],
        ['slug' => 'sonar-pro',          'input' => 0.00300, 'output' => 0.01500, 'credits' => 20,  'max_tokens' => 8192, 'type' => 'chat', 'name' => 'Sonar Pro (Advanced Search)'],
        ['slug' => 'sonar-reasoning',    'input' => 0.00100, 'output' => 0.00500, 'credits' => 10,  'max_tokens' => 8192, 'type' => 'chat', 'name' => 'Sonar Reasoning (Search + Think)'],
        ['slug' => 'sonar-deep-research','input' => 0.00500, 'output' => 0.00500, 'credits' => 30,  'max_tokens' => 8192, 'type' => 'chat', 'name' => 'Sonar Deep Research (In-Depth)'],

        // ─── Groq ───────────────────────────────────────────────────────
        ['slug' => 'llama-3.3-70b-versatile', 'input' => 0.00059,  'output' => 0.00079, 'credits' => 2,  'max_tokens' => 32768, 'type' => 'chat', 'name' => 'Llama 3.3 70B (Powerful Open-Source)'],
        ['slug' => 'llama-3.1-8b-instant',    'input' => 0.00005,  'output' => 0.00008, 'credits' => 1,  'max_tokens' => 8192,  'type' => 'chat', 'name' => 'Llama 3.1 8B (Ultra Fast)'],
        ['slug' => 'openai/gpt-oss-120b',     'input' => 0.00015,  'output' => 0.00060, 'credits' => 1,  'max_tokens' => 32768, 'type' => 'chat', 'name' => 'GPT-OSS 120B (Open-Source Top Tier)'],
        ['slug' => 'openai/gpt-oss-20b',      'input' => 0.000075, 'output' => 0.00030, 'credits' => 1,  'max_tokens' => 32768, 'type' => 'chat', 'name' => 'GPT-OSS 20B (Open-Source Fast)'],

        // ─── Mistral ────────────────────────────────────────────────────
        ['slug' => 'mistral-large-latest',  'input' => 0.00050, 'output' => 0.00150, 'credits' => 2,  'max_tokens' => 8192, 'type' => 'chat', 'name' => 'Mistral Large (Top Tier)'],
        ['slug' => 'mistral-small-latest',  'input' => 0.00010, 'output' => 0.00030, 'credits' => 1,  'max_tokens' => 8192, 'type' => 'chat', 'name' => 'Mistral Small (Lightweight)'],

        // ─── OpenRouter (prefixed slugs) ────────────────────────────────
        ['slug' => 'openai/gpt-5.6-sol',                 'input' => 0.00500, 'output' => 0.03000, 'credits' => 40,  'max_tokens' => 32768,  'type' => 'chat', 'name' => 'GPT-5.6 Sol (Most Powerful)'],
        ['slug' => 'openai/gpt-5.6-terra',               'input' => 0.00250, 'output' => 0.01500, 'credits' => 20,  'max_tokens' => 32768,  'type' => 'chat', 'name' => 'GPT-5.6 Terra (Balanced)'],
        ['slug' => 'openai/gpt-5.6-luna',                'input' => 0.00100, 'output' => 0.00600, 'credits' => 5,   'max_tokens' => 32768,  'type' => 'chat', 'name' => 'GPT-5.6 Luna (Fast & Affordable)'],
        ['slug' => 'openai/gpt-5.5',                     'input' => 0.00500, 'output' => 0.03000, 'credits' => 40,  'max_tokens' => 32768,  'type' => 'chat', 'name' => 'GPT-5.5 (Powerful)'],
        ['slug' => 'openai/gpt-5.4',                     'input' => 0.00250, 'output' => 0.01500, 'credits' => 20,  'max_tokens' => 32768,  'type' => 'chat', 'name' => 'GPT-5.4 (Advanced)'],
        ['slug' => 'anthropic/claude-fable-5',           'input' => 0.01000, 'output' => 0.05000, 'credits' => 160, 'max_tokens' => 16384,  'type' => 'chat', 'name' => 'Claude Fable 5 (Most Capable)'],
        ['slug' => 'anthropic/claude-opus-4-8',          'input' => 0.00500, 'output' => 0.02500, 'credits' => 80,  'max_tokens' => 16384,  'type' => 'chat', 'name' => 'Claude Opus 4.8 (Highly Capable)'],
        ['slug' => 'anthropic/claude-sonnet-5',          'input' => 0.00300, 'output' => 0.01500, 'credits' => 20,  'max_tokens' => 16384,  'type' => 'chat', 'name' => 'Claude Sonnet 5 (Best Balance)'],
        ['slug' => 'anthropic/claude-sonnet-4-6',        'input' => 0.00300, 'output' => 0.01500, 'credits' => 20,  'max_tokens' => 16384,  'type' => 'chat', 'name' => 'Claude Sonnet 4.6 (Balanced)'],
        ['slug' => 'anthropic/claude-haiku-4-5',         'input' => 0.00100, 'output' => 0.00500, 'credits' => 3,   'max_tokens' => 8192,   'type' => 'chat', 'name' => 'Claude Haiku 4.5 (Fast & Lightweight)'],
        ['slug' => 'google/gemini-3.6-flash',            'input' => 0.00150, 'output' => 0.00750, 'credits' => 8,   'max_tokens' => 65536,  'type' => 'chat', 'name' => 'Gemini 3.6 Flash (Latest & Fast)'],
        ['slug' => 'google/gemini-3.1-pro',              'input' => 0.00200, 'output' => 0.01200, 'credits' => 18,  'max_tokens' => 65536,  'type' => 'chat', 'name' => 'Gemini 3.1 Pro (High Performance)'],
        ['slug' => 'google/gemini-3.5-flash',            'input' => 0.00150, 'output' => 0.00900, 'credits' => 10,  'max_tokens' => 16384,  'type' => 'chat', 'name' => 'Gemini 3.5 Flash (Ultra Fast)'],
        ['slug' => 'google/gemini-2.5-pro',              'input' => 0.00125, 'output' => 0.01000, 'credits' => 15,  'max_tokens' => 65536,  'type' => 'chat', 'name' => 'Gemini 2.5 Pro (Reliable)'],
        ['slug' => 'meta-llama/llama-4-maverick',        'input' => 0.00020, 'output' => 0.00090, 'credits' => 2,   'max_tokens' => 16384,  'type' => 'chat', 'name' => 'Llama 4 Maverick (Open-Source Top Tier)'],
        ['slug' => 'meta-llama/llama-4-scout',           'input' => 0.00010, 'output' => 0.00040, 'credits' => 1,   'max_tokens' => 16384,  'type' => 'chat', 'name' => 'Llama 4 Scout (Open-Source Fast)'],
        ['slug' => 'deepseek/deepseek-v4-pro',           'input' => 0.000435, 'output' => 0.00087, 'credits' => 3,  'max_tokens' => 16384,  'type' => 'chat', 'name' => 'DeepSeek V4 Pro (Best Quality)'],
        ['slug' => 'deepseek/deepseek-v4-flash',         'input' => 0.000140, 'output' => 0.00028, 'credits' => 1,  'max_tokens' => 16384,  'type' => 'chat', 'name' => 'DeepSeek V4 Flash (Fast & Cheap)'],

        // ─── Ollama (local — free) ──────────────────────────────────────
        ['slug' => 'llama3.2',    'input' => 0, 'output' => 0, 'credits' => 0, 'max_tokens' => 4096,  'type' => 'chat', 'name' => 'Llama 3.2 (Local - Free)'],
        ['slug' => 'mistral',     'input' => 0, 'output' => 0, 'credits' => 0, 'max_tokens' => 4096,  'type' => 'chat', 'name' => 'Mistral (Local - Free)'],
        ['slug' => 'gemma2',      'input' => 0, 'output' => 0, 'credits' => 0, 'max_tokens' => 4096,  'type' => 'chat', 'name' => 'Gemma 2 (Local - Free)'],

        // ─── AWS Bedrock ────────────────────────────────────────────────
        ['slug' => 'anthropic.claude-opus-4-8-v1:0',            'input' => 0.00500, 'output' => 0.02500, 'credits' => 80,  'max_tokens' => 16384, 'type' => 'chat', 'name' => 'Claude Opus 4.8 (AWS Bedrock)'],
        ['slug' => 'anthropic.claude-sonnet-5-v1:0',            'input' => 0.00300, 'output' => 0.01500, 'credits' => 20,  'max_tokens' => 16384, 'type' => 'chat', 'name' => 'Claude Sonnet 5 (AWS Bedrock)'],
        ['slug' => 'anthropic.claude-sonnet-4-6-v1:0',          'input' => 0.00300, 'output' => 0.01500, 'credits' => 20,  'max_tokens' => 16384, 'type' => 'chat', 'name' => 'Claude Sonnet 4.6 (AWS Bedrock)'],
        ['slug' => 'anthropic.claude-haiku-4-5-v1:0',           'input' => 0.00100, 'output' => 0.00500, 'credits' => 3,   'max_tokens' => 8192,  'type' => 'chat', 'name' => 'Claude Haiku 4.5 (AWS Bedrock)'],
        ['slug' => 'meta.llama4-maverick-17b-instruct-v1:0',    'input' => 0.00020, 'output' => 0.00090, 'credits' => 2,   'max_tokens' => 16384, 'type' => 'chat', 'name' => 'Llama 4 Maverick (AWS Bedrock)'],

        // ─── Cohere ─────────────────────────────────────────────────────
        ['slug' => 'command-a-03-2025', 'input' => 0.00250, 'output' => 0.01000, 'credits' => 15,  'max_tokens' => 8192,  'type' => 'chat', 'name' => 'Cohere Command A (Enterprise RAG)'],
        ['slug' => 'embed-v4.0',        'input' => 0.00010, 'output' => 0,        'credits' => 0,   'max_tokens' => 512,   'type' => 'embedding', 'name' => 'Cohere Embed v4 (Text Embedding)'],
        ['slug' => 'rerank-v4.0-fast',  'input' => 0.00200, 'output' => 0,        'credits' => 0,   'max_tokens' => 512,   'type' => 'reranking', 'name' => 'Cohere Rerank v4 Fast (Search Reranking)'],

        // ─── ElevenLabs ─────────────────────────────────────────────────
        ['slug' => 'eleven_v3',               'input' => 0, 'output' => 0, 'credits' => 2, 'max_tokens' => 0, 'type' => 'audio', 'name' => 'ElevenLabs v3 (Most Expressive)'],
        ['slug' => 'eleven_multilingual_v2',  'input' => 0, 'output' => 0, 'credits' => 2, 'max_tokens' => 0, 'type' => 'audio', 'name' => 'ElevenLabs Multilingual v2 (Best Quality)'],
        ['slug' => 'eleven_turbo_v2_5',       'input' => 0, 'output' => 0, 'credits' => 1, 'max_tokens' => 0, 'type' => 'audio', 'name' => 'ElevenLabs Turbo v2.5 (Fast)'],
        ['slug' => 'eleven_flash_v2_5',       'input' => 0, 'output' => 0, 'credits' => 1, 'max_tokens' => 0, 'type' => 'audio', 'name' => 'ElevenLabs Flash v2.5 (Low Latency)'],

        // ─── Jina AI ────────────────────────────────────────────────────
        ['slug' => 'jina-embeddings-v3',  'input' => 0.00002, 'output' => 0, 'credits' => 0, 'max_tokens' => 8192, 'type' => 'embedding', 'name' => 'Jina Embeddings v3 (Text Embedding)'],

        // ─── Voyage AI ──────────────────────────────────────────────────
        ['slug' => 'voyage-4',        'input' => 0.00018, 'output' => 0, 'credits' => 0, 'max_tokens' => 32768, 'type' => 'embedding', 'name' => 'Voyage 4 (Text Embedding)'],
        ['slug' => 'voyage-4-lite',   'input' => 0.00007, 'output' => 0, 'credits' => 0, 'max_tokens' => 32768, 'type' => 'embedding', 'name' => 'Voyage 4 Lite (Lightweight Embedding)'],

        // NOTE: Together AI, Replicate, and image-generation models are intentionally
        // omitted — no such provider is defined in config/ai.php, so the seeder (which
        // iterates configured providers) never created them. Media pricing is anchored
        // via config('ai.media_costs'); addons that add those providers seed their own.
    ];

    public function up(): void
    {
        $providers = config('ai.providers', []);

        foreach ($providers as $providerSlug => $providerInfo) {
            $models = $providerInfo['models'] ?? [];
            foreach ($models as $modelSlug) {
                $costs = $this->findCosts($modelSlug);

                $model = AiModel::updateOrCreate(
                    ['slug' => $modelSlug],
                    [
                        'name' => $costs['name'],
                        'provider' => $providerSlug,
                        'is_active' => true,
                        'type' => $costs['type'],
                        'cost_input_1k' => $costs['input'],
                        'cost_output_1k' => $costs['output'],
                        'credits_per_1k' => $costs['credits'],
                        'max_tokens' => $costs['max_tokens'],
                    ]
                );

                // Chat models are token-billed: their credits derive from real
                // provider cost via CreditPricingService (single source of truth).
                // Media/embedding/reranking bill per-unit or are free, so keep
                // their curated credits and exclude them from auto-recalc.
                if ($costs['type'] === 'chat') {
                    if ($model->wasRecentlyCreated || $model->credits_auto) {
                        $model->credits_per_1k = CreditPricingService::deriveCreditsPer1k($model);
                        $model->credits_auto = true;
                        $model->save();
                    }
                } else {
                    // Media (audio/image/transcription) is billed PER UNIT, not per
                    // token. Seed the curated per-unit credit cost into meta so the
                    // charge (TokenGuard::mediaCreditCost) reflects the intended value
                    // instead of the flat config default. Only seed on create — never
                    // overwrite an admin's manual meta.credits_per_unit on re-seed.
                    $dirty = false;
                    if ($model->credits_auto) {
                        $model->credits_auto = false;
                        $dirty = true;
                    }
                    if ($model->wasRecentlyCreated
                        && in_array($costs['type'], ['audio', 'image', 'transcription'], true)
                        && (int) $costs['credits'] > 0) {
                        $meta = $model->meta ?? [];
                        $meta['credits_per_unit'] = (float) $costs['credits'];
                        $model->meta = $meta;
                        $dirty = true;
                    }
                    if ($dirty) {
                        $model->save();
                    }
                }
            }
        }
    }

    private function findCosts(string $slug): array
    {
        foreach (self::MODELS as $m) {
            if ($m['slug'] === $slug) {
                return [
                    'name' => $m['name'] ?? $this->formatName($m['slug']),
                    'input' => $m['input'],
                    'output' => $m['output'],
                    'credits' => $m['credits'],
                    'max_tokens' => $m['max_tokens'],
                    'type' => $m['type'],
                ];
            }
        }

        return [
            'name' => $this->formatName($slug),
            'input' => 0.001,
            'output' => 0.003,
            'credits' => 5,
            'max_tokens' => 4096,
            'type' => 'chat',
        ];
    }

    private function formatName(string $slug): string
    {
        // Bedrock ARN-style slugs: anthropic.claude-opus-4-8-v1:0, meta.llama4-*
        // Only split on dot if the prefix is a known Bedrock vendor
        $bedrockVendors = ['anthropic', 'meta', 'amazon', 'cohere', 'ai21', 'stability'];
        if (str_contains($slug, '.') && ! str_contains($slug, '/')) {
            $parts = explode('.', $slug, 2);
            if (in_array($parts[0], $bedrockVendors, true)) {
                return ucfirst($parts[0]).' / '.$this->formatName($parts[1]);
            }
        }

        if (str_contains($slug, '/')) {
            $parts = explode('/', $slug);

            return ucfirst($parts[0]).' / '.$this->formatName($parts[1]);
        }

        $name = ucwords(str_replace('-', ' ', $slug));

        return str_ireplace(
            ['gpt', 'claude', 'gemini', 'deepseek', 'sonar', 'mistral', 'mixtral', 'llama', 'grok'],
            ['GPT', 'Claude', 'Gemini', 'DeepSeek', 'Sonar', 'Mistral', 'Mixtral', 'Llama', 'Grok'],
            $name
        );
    }

    public function run(): void
    {
        $this->up();
    }
}
