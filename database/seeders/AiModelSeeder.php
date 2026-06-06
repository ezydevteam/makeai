<?php

namespace Database\Seeders;

use App\Models\AiModel;
use Illuminate\Database\Seeder;

class AiModelSeeder extends Seeder
{
    /**
     * Accurate API pricing per 1K tokens (as of mid-2025).
     * Type: chat, embedding, reranking, audio
     */
    private const MODELS = [
        // OpenAI
        ['slug' => 'gpt-4o',            'input' => 0.00250, 'output' => 0.01000, 'credits' => 15,  'max_tokens' => 16384,  'type' => 'chat'],
        ['slug' => 'gpt-4o-mini',       'input' => 0.00015, 'output' => 0.00060, 'credits' => 1,   'max_tokens' => 16384,  'type' => 'chat'],
        ['slug' => 'gpt-4-turbo',       'input' => 0.01000, 'output' => 0.03000, 'credits' => 30,  'max_tokens' => 4096,   'type' => 'chat'],
        ['slug' => 'o1',                'input' => 0.01500, 'output' => 0.06000, 'credits' => 60,  'max_tokens' => 100000, 'type' => 'chat'],
        ['slug' => 'o3',                'input' => 0.01000, 'output' => 0.04000, 'credits' => 50,  'max_tokens' => 100000, 'type' => 'chat'],
        ['slug' => 'o4-mini',           'input' => 0.00110, 'output' => 0.00440, 'credits' => 5,   'max_tokens' => 100000, 'type' => 'chat'],
        // Anthropic
        ['slug' => 'claude-sonnet-4-20250514',   'input' => 0.00300, 'output' => 0.01500, 'credits' => 20,  'max_tokens' => 8192,  'type' => 'chat'],
        ['slug' => 'claude-opus-4-20250514',     'input' => 0.01500, 'output' => 0.07500, 'credits' => 75,  'max_tokens' => 8192,  'type' => 'chat'],
        ['slug' => 'claude-haiku-20250514',       'input' => 0.00080, 'output' => 0.00400, 'credits' => 2,   'max_tokens' => 8192,  'type' => 'chat'],
        // Google Gemini
        ['slug' => 'gemini-2.0-flash',  'input' => 0.00010, 'output' => 0.00040, 'credits' => 1,   'max_tokens' => 8192,  'type' => 'chat'],
        ['slug' => 'gemini-2.5-pro',    'input' => 0.00125, 'output' => 0.01000, 'credits' => 15,  'max_tokens' => 65536, 'type' => 'chat'],
        ['slug' => 'gemini-2.5-flash',  'input' => 0.00015, 'output' => 0.00060, 'credits' => 1,   'max_tokens' => 8192,  'type' => 'chat'],
        // xAI (Grok)
        ['slug' => 'grok-3',            'input' => 0.00300, 'output' => 0.01500, 'credits' => 20,  'max_tokens' => 16384, 'type' => 'chat'],
        ['slug' => 'grok-3-mini',       'input' => 0.00030, 'output' => 0.00050, 'credits' => 2,   'max_tokens' => 16384, 'type' => 'chat'],
        // DeepSeek
        ['slug' => 'deepseek-r1',       'input' => 0.00055, 'output' => 0.00219, 'credits' => 3,   'max_tokens' => 8192,  'type' => 'chat'],
        ['slug' => 'deepseek-v3',       'input' => 0.00027, 'output' => 0.00110, 'credits' => 2,   'max_tokens' => 8192,  'type' => 'chat'],
        // Perplexity
        ['slug' => 'sonar',             'input' => 0.00100, 'output' => 0.00100, 'credits' => 5,   'max_tokens' => 8192,  'type' => 'chat'],
        ['slug' => 'sonar-pro',         'input' => 0.00300, 'output' => 0.01500, 'credits' => 20,  'max_tokens' => 8192,  'type' => 'chat'],
        ['slug' => 'sonar-reasoning',   'input' => 0.00100, 'output' => 0.00500, 'credits' => 10,  'max_tokens' => 8192,  'type' => 'chat'],
        ['slug' => 'sonar-deep-research','input' => 0.00500, 'output' => 0.00500, 'credits' => 30, 'max_tokens' => 8192,  'type' => 'chat'],
        // Groq
        ['slug' => 'llama-3.3-70b',     'input' => 0.00059, 'output' => 0.00079, 'credits' => 2,   'max_tokens' => 8192,  'type' => 'chat'],
        ['slug' => 'mixtral-8x7b',      'input' => 0.00027, 'output' => 0.00027, 'credits' => 1,   'max_tokens' => 4096,  'type' => 'chat'],
        // Mistral
        ['slug' => 'mistral-large',     'input' => 0.00200, 'output' => 0.00600, 'credits' => 8,   'max_tokens' => 8192,  'type' => 'chat'],
        ['slug' => 'mistral-nemo',      'input' => 0.00015, 'output' => 0.00015, 'credits' => 1,   'max_tokens' => 4096,  'type' => 'chat'],
        // OpenRouter (prefixed slugs)
        ['slug' => 'openai/gpt-4o',                   'input' => 0.00250, 'output' => 0.01000, 'credits' => 15,  'max_tokens' => 16384,  'type' => 'chat', 'name' => 'OpenAI / GPT-4o'],
        ['slug' => 'openai/gpt-4o-mini',              'input' => 0.00015, 'output' => 0.00060, 'credits' => 1,   'max_tokens' => 16384,  'type' => 'chat', 'name' => 'OpenAI / GPT-4o Mini'],
        ['slug' => 'openai/o3-mini',                   'input' => 0.00110, 'output' => 0.00440, 'credits' => 5,   'max_tokens' => 100000, 'type' => 'chat', 'name' => 'OpenAI / o3-mini'],
        ['slug' => 'anthropic/claude-sonnet-4-20250514', 'input' => 0.00300, 'output' => 0.01500, 'credits' => 20,  'max_tokens' => 8192,  'type' => 'chat', 'name' => 'Anthropic / Claude Sonnet 4'],
        ['slug' => 'anthropic/claude-haiku-20250514',   'input' => 0.00080, 'output' => 0.00400, 'credits' => 2,   'max_tokens' => 8192,  'type' => 'chat', 'name' => 'Anthropic / Claude Haiku 3.5'],
        ['slug' => 'google/gemini-2.5-pro',            'input' => 0.00125, 'output' => 0.01000, 'credits' => 15,  'max_tokens' => 65536, 'type' => 'chat', 'name' => 'Google / Gemini 2.5 Pro'],
        ['slug' => 'google/gemini-2.0-flash',          'input' => 0.00010, 'output' => 0.00040, 'credits' => 1,   'max_tokens' => 8192,  'type' => 'chat', 'name' => 'Google / Gemini 2.0 Flash'],
        ['slug' => 'meta-llama/llama-4-maverick',      'input' => 0.00020, 'output' => 0.00090, 'credits' => 2,   'max_tokens' => 16384, 'type' => 'chat', 'name' => 'Meta / Llama 4 Maverick'],
        ['slug' => 'meta-llama/llama-4-scout',         'input' => 0.00010, 'output' => 0.00040, 'credits' => 1,   'max_tokens' => 16384, 'type' => 'chat', 'name' => 'Meta / Llama 4 Scout'],
        ['slug' => 'deepseek/deepseek-v3',             'input' => 0.00027, 'output' => 0.00110, 'credits' => 2,   'max_tokens' => 8192,  'type' => 'chat', 'name' => 'DeepSeek / DeepSeek V3'],
        ['slug' => 'deepseek/deepseek-r1',             'input' => 0.00055, 'output' => 0.00219, 'credits' => 3,   'max_tokens' => 8192,  'type' => 'chat', 'name' => 'DeepSeek / DeepSeek R1'],
        // Ollama
        ['slug' => 'llama3.2',    'input' => 0, 'output' => 0, 'credits' => 0, 'max_tokens' => 4096,  'type' => 'chat'],
        ['slug' => 'mistral',     'input' => 0, 'output' => 0, 'credits' => 0, 'max_tokens' => 4096,  'type' => 'chat'],
        ['slug' => 'gemma2',      'input' => 0, 'output' => 0, 'credits' => 0, 'max_tokens' => 4096,  'type' => 'chat'],
        // AWS Bedrock
        ['slug' => 'anthropic.claude-sonnet-4-20250514-v1:0',   'input' => 0.00300, 'output' => 0.01500, 'credits' => 20,  'max_tokens' => 8192,  'type' => 'chat', 'name' => 'Claude Sonnet 4 (Bedrock)'],
        ['slug' => 'anthropic.claude-haiku-20250514-v1:0',        'input' => 0.00080, 'output' => 0.00400, 'credits' => 2,   'max_tokens' => 8192,  'type' => 'chat', 'name' => 'Claude Haiku 3.5 (Bedrock)'],
        ['slug' => 'meta.llama4-maverick-17b-instruct-v1:0',     'input' => 0.00020, 'output' => 0.00090, 'credits' => 2,   'max_tokens' => 16384, 'type' => 'chat', 'name' => 'Llama 4 Maverick (Bedrock)'],
        // Cohere
        ['slug' => 'command-r-plus',   'input' => 0.00300, 'output' => 0.01500, 'credits' => 20,  'max_tokens' => 4096,  'type' => 'chat'],
        ['slug' => 'embed-v4.0',       'input' => 0.00010, 'output' => 0,        'credits' => 0,   'max_tokens' => 512,   'type' => 'embedding'],
        ['slug' => 'rerank-v3.5',      'input' => 0.00200, 'output' => 0,        'credits' => 0,   'max_tokens' => 512,   'type' => 'reranking'],
        // ElevenLabs
        ['slug' => 'eleven_multilingual_v2',  'input' => 0, 'output' => 0, 'credits' => 2,   'max_tokens' => 0,   'type' => 'audio'],
        ['slug' => 'eleven_turbo_v2_5',       'input' => 0, 'output' => 0, 'credits' => 1,   'max_tokens' => 0,   'type' => 'audio'],
        ['slug' => 'eleven_flash_v2_5',       'input' => 0, 'output' => 0, 'credits' => 1,   'max_tokens' => 0,   'type' => 'audio'],
        // Jina AI
        ['slug' => 'jina-embeddings-v3',  'input' => 0.00002, 'output' => 0, 'credits' => 0, 'max_tokens' => 8192, 'type' => 'embedding'],
        // Voyage AI
        ['slug' => 'voyage-3',        'input' => 0.00002, 'output' => 0, 'credits' => 0, 'max_tokens' => 4096, 'type' => 'embedding'],
        ['slug' => 'voyage-3-lite',   'input' => 0.00002, 'output' => 0, 'credits' => 0, 'max_tokens' => 2048, 'type' => 'embedding'],
        // Together AI
        ['slug' => 'meta-llama/Llama-4-Maverick-17B-128E-Instruct',    'input' => 0.00020, 'output' => 0.00090, 'credits' => 2,  'max_tokens' => 16384, 'type' => 'chat', 'name' => 'Llama 4 Maverick (Together)'],
        ['slug' => 'meta-llama/Llama-4-Scout-17B-16E-Instruct',         'input' => 0.00010, 'output' => 0.00040, 'credits' => 1,  'max_tokens' => 16384, 'type' => 'chat', 'name' => 'Llama 4 Scout (Together)'],
        ['slug' => 'meta-llama/Meta-Llama-3.3-70B-Instruct-Turbo',     'input' => 0.00088, 'output' => 0.00088, 'credits' => 2,  'max_tokens' => 16384, 'type' => 'chat', 'name' => 'Llama 3.3 70B (Together)'],
        ['slug' => 'mistralai/Mixtral-8x7B-Instruct-v0.1',             'input' => 0.00060, 'output' => 0.00060, 'credits' => 2,  'max_tokens' => 4096,  'type' => 'chat', 'name' => 'Mixtral 8x7B (Together)'],
        ['slug' => 'deepseek-ai/DeepSeek-V3',                           'input' => 0.00027, 'output' => 0.00110, 'credits' => 2,  'max_tokens' => 8192,  'type' => 'chat', 'name' => 'DeepSeek V3 (Together)'],
        // Replicate
        ['slug' => 'meta/meta-llama-3.3-70b-instruct',                 'input' => 0.00088, 'output' => 0.00088, 'credits' => 2,  'max_tokens' => 16384, 'type' => 'chat', 'name' => 'Llama 3.3 70B (Replicate)'],
        ['slug' => 'mistralai/mixtral-8x7b-instruct-v0.1',             'input' => 0.00060, 'output' => 0.00060, 'credits' => 2,  'max_tokens' => 4096,  'type' => 'chat', 'name' => 'Mixtral 8x7B (Replicate)'],
        ['slug' => 'stability-ai/stable-diffusion-3.5-large',          'input' => 0,        'output' => 0,        'credits' => 10, 'max_tokens' => 0,     'type' => 'image', 'name' => 'Stable Diffusion 3.5'],
        ['slug' => 'black-forest-labs/flux-1.1-pro',                   'input' => 0,        'output' => 0,        'credits' => 8,  'max_tokens' => 0,     'type' => 'image', 'name' => 'Flux 1.1 Pro'],
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
        // Bedrock ARN-style slugs: anthropic.claude-sonnet-4-20250514-v1:0
        if (str_contains($slug, '.') && ! str_contains($slug, '/')) {
            $parts = explode('.', $slug, 2);
            return ucfirst($parts[0]) . ' / ' . $this->formatName($parts[1]);
        }

        if (str_contains($slug, '/')) {
            $parts = explode('/', $slug);
            return ucfirst($parts[0]) . ' / ' . $this->formatName($parts[1]);
        }

        return str_replace(
            ['gpt', 'claude', 'gemini', 'deepseek', 'sonar', 'mistral', 'mixtral', 'llama', 'grok'],
            ['GPT', 'Claude', 'Gemini', 'DeepSeek', 'Sonar', 'Mistral', 'Mixtral', 'Llama', 'Grok'],
            ucwords(str_replace('-', ' ', $slug))
        );
    }

    public function run(): void
    {
        $this->up();
    }
}
