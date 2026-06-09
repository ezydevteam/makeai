<?php

namespace Database\Seeders;

use App\Models\AiModel;
use Illuminate\Database\Seeder;

class AiModelSeeder extends Seeder
{
    /**
     * Accurate API pricing per 1K tokens (as of June 2026).
     * Type: chat, embedding, reranking, audio
     */
    private const MODELS = [
        // ─── OpenAI ──────────────────────────────────────────────────────
        ['slug' => 'gpt-5.5',           'input' => 0.00500, 'output' => 0.03000, 'credits' => 40,  'max_tokens' => 32768,  'type' => 'chat'],
        ['slug' => 'gpt-5.5-mini',      'input' => 0.00100, 'output' => 0.00400, 'credits' => 5,   'max_tokens' => 32768,  'type' => 'chat'],
        ['slug' => 'gpt-5.4',           'input' => 0.00250, 'output' => 0.01500, 'credits' => 20,  'max_tokens' => 32768,  'type' => 'chat'],
        ['slug' => 'gpt-4o',            'input' => 0.00250, 'output' => 0.01000, 'credits' => 15,  'max_tokens' => 16384,  'type' => 'chat'],
        ['slug' => 'gpt-4o-mini',       'input' => 0.00015, 'output' => 0.00060, 'credits' => 1,   'max_tokens' => 16384,  'type' => 'chat'],
        ['slug' => 'o3',                'input' => 0.01000, 'output' => 0.04000, 'credits' => 50,  'max_tokens' => 100000, 'type' => 'chat'],
        ['slug' => 'o4-mini',           'input' => 0.00110, 'output' => 0.00440, 'credits' => 5,   'max_tokens' => 100000, 'type' => 'chat'],

        // ─── Anthropic ──────────────────────────────────────────────────
        ['slug' => 'claude-opus-4-8',    'input' => 0.00500, 'output' => 0.02500, 'credits' => 80,  'max_tokens' => 16384, 'type' => 'chat'],
        ['slug' => 'claude-sonnet-4-6',  'input' => 0.00300, 'output' => 0.01500, 'credits' => 20,  'max_tokens' => 16384, 'type' => 'chat'],
        ['slug' => 'claude-haiku-4-5',   'input' => 0.00100, 'output' => 0.00500, 'credits' => 3,   'max_tokens' => 8192,  'type' => 'chat'],

        // ─── Google Gemini ──────────────────────────────────────────────
        ['slug' => 'gemini-3.5-flash',      'input' => 0.00020, 'output' => 0.00080, 'credits' => 1,   'max_tokens' => 16384, 'type' => 'chat'],
        ['slug' => 'gemini-3.1-pro',        'input' => 0.00200, 'output' => 0.01200, 'credits' => 18,  'max_tokens' => 65536, 'type' => 'chat'],
        ['slug' => 'gemini-3.1-flash-lite', 'input' => 0.00025, 'output' => 0.00150, 'credits' => 2,   'max_tokens' => 8192,  'type' => 'chat'],
        ['slug' => 'gemini-2.5-pro',        'input' => 0.00125, 'output' => 0.01000, 'credits' => 15,  'max_tokens' => 65536, 'type' => 'chat'],
        ['slug' => 'gemini-2.5-flash',      'input' => 0.00015, 'output' => 0.00060, 'credits' => 1,   'max_tokens' => 8192,  'type' => 'chat'],
        ['slug' => 'gemini-2.0-flash',      'input' => 0.00010, 'output' => 0.00040, 'credits' => 1,   'max_tokens' => 8192,  'type' => 'chat'],

        // ─── xAI (Grok) ────────────────────────────────────────────────
        ['slug' => 'grok-4.3',          'input' => 0.00125, 'output' => 0.00250, 'credits' => 10,  'max_tokens' => 32768, 'type' => 'chat'],
        ['slug' => 'grok-4.1-fast',     'input' => 0.00020, 'output' => 0.00050, 'credits' => 2,   'max_tokens' => 32768, 'type' => 'chat'],
        ['slug' => 'grok-3',            'input' => 0.00300, 'output' => 0.01500, 'credits' => 20,  'max_tokens' => 16384, 'type' => 'chat'],
        ['slug' => 'grok-3-mini',       'input' => 0.00030, 'output' => 0.00050, 'credits' => 2,   'max_tokens' => 16384, 'type' => 'chat'],

        // ─── DeepSeek ───────────────────────────────────────────────────
        ['slug' => 'deepseek-v4-pro',   'input' => 0.000435, 'output' => 0.00087, 'credits' => 3,  'max_tokens' => 16384, 'type' => 'chat'],
        ['slug' => 'deepseek-v4-flash', 'input' => 0.000140, 'output' => 0.00028, 'credits' => 1,  'max_tokens' => 16384, 'type' => 'chat'],
        ['slug' => 'deepseek-r1',       'input' => 0.00055,  'output' => 0.00219, 'credits' => 3,  'max_tokens' => 8192,  'type' => 'chat'],
        ['slug' => 'deepseek-v3',       'input' => 0.00027,  'output' => 0.00110, 'credits' => 2,  'max_tokens' => 8192,  'type' => 'chat'],

        // ─── Perplexity ─────────────────────────────────────────────────
        ['slug' => 'sonar',              'input' => 0.00100, 'output' => 0.00100, 'credits' => 5,   'max_tokens' => 8192, 'type' => 'chat'],
        ['slug' => 'sonar-pro',          'input' => 0.00300, 'output' => 0.01500, 'credits' => 20,  'max_tokens' => 8192, 'type' => 'chat'],
        ['slug' => 'sonar-reasoning',    'input' => 0.00100, 'output' => 0.00500, 'credits' => 10,  'max_tokens' => 8192, 'type' => 'chat'],
        ['slug' => 'sonar-deep-research','input' => 0.00500, 'output' => 0.00500, 'credits' => 30,  'max_tokens' => 8192, 'type' => 'chat'],

        // ─── Groq ───────────────────────────────────────────────────────
        ['slug' => 'llama-4-scout-17b',  'input' => 0.00011, 'output' => 0.00034, 'credits' => 1,  'max_tokens' => 16384, 'type' => 'chat'],
        ['slug' => 'llama-3.3-70b',      'input' => 0.00059, 'output' => 0.00079, 'credits' => 2,  'max_tokens' => 8192,  'type' => 'chat'],
        ['slug' => 'mixtral-8x7b',       'input' => 0.00027, 'output' => 0.00027, 'credits' => 1,  'max_tokens' => 4096,  'type' => 'chat'],

        // ─── Mistral ────────────────────────────────────────────────────
        ['slug' => 'mistral-large-latest',  'input' => 0.00200, 'output' => 0.00600, 'credits' => 8,  'max_tokens' => 8192, 'type' => 'chat'],
        ['slug' => 'mistral-medium-latest', 'input' => 0.00100, 'output' => 0.00300, 'credits' => 4,  'max_tokens' => 8192, 'type' => 'chat'],
        ['slug' => 'mistral-small-latest',  'input' => 0.00020, 'output' => 0.00060, 'credits' => 1,  'max_tokens' => 8192, 'type' => 'chat'],

        // ─── OpenRouter (prefixed slugs) ────────────────────────────────
        ['slug' => 'openai/gpt-5.5',                     'input' => 0.00500, 'output' => 0.03000, 'credits' => 40,  'max_tokens' => 32768,  'type' => 'chat', 'name' => 'OpenAI / GPT-5.5'],
        ['slug' => 'openai/gpt-5.4',                     'input' => 0.00250, 'output' => 0.01500, 'credits' => 20,  'max_tokens' => 32768,  'type' => 'chat', 'name' => 'OpenAI / GPT-5.4'],
        ['slug' => 'openai/gpt-4o',                      'input' => 0.00250, 'output' => 0.01000, 'credits' => 15,  'max_tokens' => 16384,  'type' => 'chat', 'name' => 'OpenAI / GPT-4o'],
        ['slug' => 'openai/gpt-4o-mini',                 'input' => 0.00015, 'output' => 0.00060, 'credits' => 1,   'max_tokens' => 16384,  'type' => 'chat', 'name' => 'OpenAI / GPT-4o Mini'],
        ['slug' => 'openai/o4-mini',                     'input' => 0.00110, 'output' => 0.00440, 'credits' => 5,   'max_tokens' => 100000, 'type' => 'chat', 'name' => 'OpenAI / o4-mini'],
        ['slug' => 'anthropic/claude-opus-4-8',          'input' => 0.00500, 'output' => 0.02500, 'credits' => 80,  'max_tokens' => 16384,  'type' => 'chat', 'name' => 'Anthropic / Claude Opus 4.8'],
        ['slug' => 'anthropic/claude-sonnet-4-6',        'input' => 0.00300, 'output' => 0.01500, 'credits' => 20,  'max_tokens' => 16384,  'type' => 'chat', 'name' => 'Anthropic / Claude Sonnet 4.6'],
        ['slug' => 'anthropic/claude-haiku-4-5',         'input' => 0.00100, 'output' => 0.00500, 'credits' => 3,   'max_tokens' => 8192,   'type' => 'chat', 'name' => 'Anthropic / Claude Haiku 4.5'],
        ['slug' => 'google/gemini-3.1-pro',              'input' => 0.00200, 'output' => 0.01200, 'credits' => 18,  'max_tokens' => 65536,  'type' => 'chat', 'name' => 'Google / Gemini 3.1 Pro'],
        ['slug' => 'google/gemini-3.5-flash',            'input' => 0.00020, 'output' => 0.00080, 'credits' => 1,   'max_tokens' => 16384,  'type' => 'chat', 'name' => 'Google / Gemini 3.5 Flash'],
        ['slug' => 'google/gemini-2.5-pro',              'input' => 0.00125, 'output' => 0.01000, 'credits' => 15,  'max_tokens' => 65536,  'type' => 'chat', 'name' => 'Google / Gemini 2.5 Pro'],
        ['slug' => 'google/gemini-2.0-flash',            'input' => 0.00010, 'output' => 0.00040, 'credits' => 1,   'max_tokens' => 8192,   'type' => 'chat', 'name' => 'Google / Gemini 2.0 Flash'],
        ['slug' => 'meta-llama/llama-4-maverick',        'input' => 0.00020, 'output' => 0.00090, 'credits' => 2,   'max_tokens' => 16384,  'type' => 'chat', 'name' => 'Meta / Llama 4 Maverick'],
        ['slug' => 'meta-llama/llama-4-scout',           'input' => 0.00010, 'output' => 0.00040, 'credits' => 1,   'max_tokens' => 16384,  'type' => 'chat', 'name' => 'Meta / Llama 4 Scout'],
        ['slug' => 'deepseek/deepseek-v4-pro',           'input' => 0.000435, 'output' => 0.00087, 'credits' => 3,  'max_tokens' => 16384,  'type' => 'chat', 'name' => 'DeepSeek / V4 Pro'],
        ['slug' => 'deepseek/deepseek-v4-flash',         'input' => 0.000140, 'output' => 0.00028, 'credits' => 1,  'max_tokens' => 16384,  'type' => 'chat', 'name' => 'DeepSeek / V4 Flash'],
        ['slug' => 'deepseek/deepseek-r1',               'input' => 0.00055,  'output' => 0.00219, 'credits' => 3,  'max_tokens' => 8192,   'type' => 'chat', 'name' => 'DeepSeek / R1'],

        // ─── Ollama (local — free) ──────────────────────────────────────
        ['slug' => 'llama3.2',    'input' => 0, 'output' => 0, 'credits' => 0, 'max_tokens' => 4096,  'type' => 'chat'],
        ['slug' => 'mistral',     'input' => 0, 'output' => 0, 'credits' => 0, 'max_tokens' => 4096,  'type' => 'chat'],
        ['slug' => 'gemma2',      'input' => 0, 'output' => 0, 'credits' => 0, 'max_tokens' => 4096,  'type' => 'chat'],

        // ─── AWS Bedrock ────────────────────────────────────────────────
        ['slug' => 'anthropic.claude-opus-4-8-v1:0',            'input' => 0.00500, 'output' => 0.02500, 'credits' => 80,  'max_tokens' => 16384, 'type' => 'chat', 'name' => 'Claude Opus 4.8 (Bedrock)'],
        ['slug' => 'anthropic.claude-sonnet-4-6-v1:0',          'input' => 0.00300, 'output' => 0.01500, 'credits' => 20,  'max_tokens' => 16384, 'type' => 'chat', 'name' => 'Claude Sonnet 4.6 (Bedrock)'],
        ['slug' => 'anthropic.claude-haiku-4-5-v1:0',           'input' => 0.00100, 'output' => 0.00500, 'credits' => 3,   'max_tokens' => 8192,  'type' => 'chat', 'name' => 'Claude Haiku 4.5 (Bedrock)'],
        ['slug' => 'meta.llama4-maverick-17b-instruct-v1:0',    'input' => 0.00020, 'output' => 0.00090, 'credits' => 2,   'max_tokens' => 16384, 'type' => 'chat', 'name' => 'Llama 4 Maverick (Bedrock)'],

        // ─── Cohere ─────────────────────────────────────────────────────
        ['slug' => 'command-r-plus',   'input' => 0.00300, 'output' => 0.01500, 'credits' => 20,  'max_tokens' => 4096,  'type' => 'chat'],
        ['slug' => 'embed-v4.0',       'input' => 0.00010, 'output' => 0,        'credits' => 0,   'max_tokens' => 512,   'type' => 'embedding'],
        ['slug' => 'rerank-v3.5',      'input' => 0.00200, 'output' => 0,        'credits' => 0,   'max_tokens' => 512,   'type' => 'reranking'],

        // ─── ElevenLabs ─────────────────────────────────────────────────
        ['slug' => 'eleven_multilingual_v2',  'input' => 0, 'output' => 0, 'credits' => 2, 'max_tokens' => 0, 'type' => 'audio'],
        ['slug' => 'eleven_turbo_v2_5',       'input' => 0, 'output' => 0, 'credits' => 1, 'max_tokens' => 0, 'type' => 'audio'],
        ['slug' => 'eleven_flash_v2_5',       'input' => 0, 'output' => 0, 'credits' => 1, 'max_tokens' => 0, 'type' => 'audio'],

        // ─── Jina AI ────────────────────────────────────────────────────
        ['slug' => 'jina-embeddings-v3',  'input' => 0.00002, 'output' => 0, 'credits' => 0, 'max_tokens' => 8192, 'type' => 'embedding'],

        // ─── Voyage AI ──────────────────────────────────────────────────
        ['slug' => 'voyage-3',        'input' => 0.00002, 'output' => 0, 'credits' => 0, 'max_tokens' => 4096, 'type' => 'embedding'],
        ['slug' => 'voyage-3-lite',   'input' => 0.00002, 'output' => 0, 'credits' => 0, 'max_tokens' => 2048, 'type' => 'embedding'],

        // ─── Together AI ────────────────────────────────────────────────
        ['slug' => 'meta-llama/Llama-4-Maverick-17B-128E-Instruct',  'input' => 0.00020, 'output' => 0.00090, 'credits' => 2,  'max_tokens' => 16384, 'type' => 'chat', 'name' => 'Llama 4 Maverick (Together)'],
        ['slug' => 'meta-llama/Llama-4-Scout-17B-16E-Instruct',      'input' => 0.00010, 'output' => 0.00040, 'credits' => 1,  'max_tokens' => 16384, 'type' => 'chat', 'name' => 'Llama 4 Scout (Together)'],
        ['slug' => 'meta-llama/Meta-Llama-3.3-70B-Instruct-Turbo',   'input' => 0.00088, 'output' => 0.00088, 'credits' => 2,  'max_tokens' => 16384, 'type' => 'chat', 'name' => 'Llama 3.3 70B (Together)'],
        ['slug' => 'deepseek-ai/DeepSeek-V4-Pro',                    'input' => 0.000435, 'output' => 0.00087, 'credits' => 3, 'max_tokens' => 16384, 'type' => 'chat', 'name' => 'DeepSeek V4 Pro (Together)'],
        ['slug' => 'deepseek-ai/DeepSeek-V4-Flash',                  'input' => 0.000140, 'output' => 0.00028, 'credits' => 1, 'max_tokens' => 16384, 'type' => 'chat', 'name' => 'DeepSeek V4 Flash (Together)'],
        ['slug' => 'mistralai/Mixtral-8x7B-Instruct-v0.1',           'input' => 0.00060,  'output' => 0.00060, 'credits' => 2, 'max_tokens' => 4096,  'type' => 'chat', 'name' => 'Mixtral 8x7B (Together)'],

        // ─── Replicate ──────────────────────────────────────────────────
        ['slug' => 'meta/meta-llama-3.3-70b-instruct',              'input' => 0.00088, 'output' => 0.00088, 'credits' => 2,  'max_tokens' => 16384, 'type' => 'chat',  'name' => 'Llama 3.3 70B (Replicate)'],
        ['slug' => 'mistralai/mixtral-8x7b-instruct-v0.1',          'input' => 0.00060, 'output' => 0.00060, 'credits' => 2,  'max_tokens' => 4096,  'type' => 'chat',  'name' => 'Mixtral 8x7B (Replicate)'],
        ['slug' => 'stability-ai/stable-diffusion-3.5-large',       'input' => 0,       'output' => 0,       'credits' => 10, 'max_tokens' => 0,     'type' => 'image', 'name' => 'Stable Diffusion 3.5'],
        ['slug' => 'black-forest-labs/flux-1.1-pro',                 'input' => 0,       'output' => 0,       'credits' => 8,  'max_tokens' => 0,     'type' => 'image', 'name' => 'Flux 1.1 Pro'],
    ];

    public function up(): void
    {
        $providers = config('ai.providers', []);

        foreach ($providers as $providerSlug => $providerInfo) {
            $models = $providerInfo['models'] ?? [];
            foreach ($models as $modelSlug) {
                $costs = $this->findCosts($modelSlug);

                AiModel::updateOrCreate(
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
