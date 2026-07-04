<?php

/**
 * MakeAI — AI Configuration
 *
 * Merges custom MakeAI settings with Laravel AI SDK configuration.
 * All API keys, limits, and model settings are overridable from admin panel via settings().
 *
 * @see https://laravel.com/docs/13.x/ai-sdk
 */

return [

    /*
    |--------------------------------------------------------------------------
    | Default AI Provider
    |--------------------------------------------------------------------------
    |
    | These control which AI provider is used by default for various operations.
    | All values are overridable from the admin panel via the settings() helper.
    |
    */

    'default' => env('AI_DEFAULT_PROVIDER', 'openai'),
    'default_for_images' => env('AI_DEFAULT_IMAGE_PROVIDER', 'openai'),
    'default_for_audio' => env('AI_DEFAULT_AUDIO_PROVIDER', 'openai'),
    'default_for_transcription' => env('AI_DEFAULT_TRANSCRIPTION_PROVIDER', 'openai'),
    'default_for_embeddings' => env('AI_DEFAULT_EMBEDDING_PROVIDER', 'openai'),
    'default_for_reranking' => env('AI_DEFAULT_RERANKING_PROVIDER', 'cohere'),

    /*
    |--------------------------------------------------------------------------
    | Caching
    |--------------------------------------------------------------------------
    |
    | Embedding and AI operation caching strategies.
    |
    */

    'caching' => [
        'embeddings' => [
            'cache' => false,
            'store' => env('CACHE_STORE', 'database'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | AI Providers
    |--------------------------------------------------------------------------
    |
    | Each provider defines its driver, API key, and base URL.
    | API keys are resolved dynamically at runtime from the ai_keys DB table
    | via ProviderRegistry (round-robin load balancing). Personal user API keys
    | take priority when set on the user's profile.
    |
    | To add a new provider: 1) Add entry below, 2) Register in ProviderRegistry.
    |
    */

    'providers' => [
        'openai' => [
            'name' => 'OpenAI',
            'driver' => 'openai',
            'key' => env('OPENAI_API_KEY'),
            'url' => env('OPENAI_URL', 'https://api.openai.com/v1'),
            'models' => ['gpt-5.5', 'gpt-5.5-mini', 'gpt-5.4', 'gpt-4o', 'gpt-4o-mini', 'o3', 'o4-mini'],
        ],

        'anthropic' => [
            'name' => 'Anthropic',
            'driver' => 'anthropic',
            'key' => env('ANTHROPIC_API_KEY'),
            'url' => env('ANTHROPIC_URL', 'https://api.anthropic.com/v1'),
            'models' => ['claude-opus-4-8', 'claude-sonnet-4-6', 'claude-haiku-4-5'],
        ],

        'google' => [
            'name' => 'Google Gemini',
            'driver' => 'gemini',
            'key' => env('GEMINI_API_KEY'),
            'url' => env('GEMINI_URL', 'https://generativelanguage.googleapis.com/v1beta'),
            'models' => ['gemini-3.5-flash', 'gemini-3.1-pro', 'gemini-3.1-flash-lite', 'gemini-2.5-pro', 'gemini-2.5-flash', 'gemini-2.0-flash'],
        ],

        'xai' => [
            'name' => 'xAI (Grok)',
            'driver' => 'xai',
            'key' => env('XAI_API_KEY'),
            'url' => env('XAI_URL', 'https://api.x.ai/v1'),
            'models' => ['grok-4.3', 'grok-4.1-fast', 'grok-3', 'grok-3-mini'],
        ],

        'deepseek' => [
            'name' => 'DeepSeek',
            'driver' => 'deepseek',
            'key' => env('DEEPSEEK_API_KEY'),
            'url' => env('DEEPSEEK_URL', 'https://api.deepseek.com/v1'),
            'models' => ['deepseek-v4-pro', 'deepseek-v4-flash', 'deepseek-r1', 'deepseek-v3'],
        ],

        'openrouter' => [
            'name' => 'OpenRouter',
            'driver' => 'openrouter',
            'key' => env('OPENROUTER_API_KEY'),
            'url' => env('OPENROUTER_URL', 'https://openrouter.ai/api/v1'),
            'models' => [
                'openai/gpt-5.5', 'openai/gpt-5.4', 'openai/gpt-4o', 'openai/gpt-4o-mini', 'openai/o4-mini',
                'anthropic/claude-opus-4-8', 'anthropic/claude-sonnet-4-6', 'anthropic/claude-haiku-4-5',
                'google/gemini-3.1-pro', 'google/gemini-3.5-flash', 'google/gemini-2.5-pro', 'google/gemini-2.0-flash',
                'meta-llama/llama-4-maverick', 'meta-llama/llama-4-scout',
                'deepseek/deepseek-v4-pro', 'deepseek/deepseek-v4-flash', 'deepseek/deepseek-r1',
            ],
        ],

        'groq' => [
            'name' => 'Groq',
            'driver' => 'groq',
            'key' => env('GROQ_API_KEY'),
            'models' => ['llama-4-scout-17b', 'llama-3.3-70b', 'mixtral-8x7b'],
        ],

        'mistral' => [
            'name' => 'Mistral AI',
            'driver' => 'mistral',
            'key' => env('MISTRAL_API_KEY'),
            'models' => ['mistral-large-latest', 'mistral-medium-latest', 'mistral-small-latest'],
        ],

        'ollama' => [
            'name' => 'Ollama (Local)',
            'driver' => 'ollama',
            'key' => env('OLLAMA_API_KEY', ''),
            'url' => env('OLLAMA_URL', 'http://localhost:11434'),
            'models' => ['llama3.2', 'mistral', 'gemma2'],
        ],

        'bedrock' => [
            'name' => 'AWS Bedrock',
            'driver' => 'bedrock',
            'region' => env('AWS_BEDROCK_REGION', 'us-east-1'),
            'key' => env('AWS_BEARER_TOKEN_BEDROCK'),
            'access_key_id' => env('AWS_ACCESS_KEY_ID'),
            'secret_access_key' => env('AWS_SECRET_ACCESS_KEY'),
            'session_token' => env('AWS_SESSION_TOKEN'),
            'use_default_credential_provider' => env('AWS_USE_DEFAULT_CREDENTIALS', true),
            'models' => [
                'anthropic.claude-opus-4-8-v1:0',
                'anthropic.claude-sonnet-4-6-v1:0',
                'anthropic.claude-haiku-4-5-v1:0',
                'meta.llama4-maverick-17b-instruct-v1:0',
            ],
        ],

        'cohere' => [
            'name' => 'Cohere',
            'driver' => 'cohere',
            'key' => env('COHERE_API_KEY'),
            'models' => ['command-r-plus', 'embed-v4.0', 'rerank-v3.5'],
        ],

        'eleven' => [
            'name' => 'ElevenLabs',
            'driver' => 'eleven',
            'key' => env('ELEVENLABS_API_KEY'),
            'models' => ['eleven_multilingual_v2', 'eleven_turbo_v2_5', 'eleven_flash_v2_5'],
        ],

        'jina' => [
            'name' => 'Jina AI',
            'driver' => 'jina',
            'key' => env('JINA_API_KEY'),
            'models' => ['jina-embeddings-v3'],
        ],

        'voyageai' => [
            'name' => 'Voyage AI',
            'driver' => 'voyageai',
            'key' => env('VOYAGEAI_API_KEY'),
            'models' => ['voyage-3', 'voyage-3-lite'],
        ],

        'perplexity' => [
            'name' => 'Perplexity',
            'driver' => 'perplexity',
            'key' => env('PERPLEXITY_API_KEY'),
            'url' => env('PERPLEXITY_URL', 'https://api.perplexity.ai'),
            'models' => ['sonar', 'sonar-pro', 'sonar-reasoning', 'sonar-deep-research'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Token Limits
    |--------------------------------------------------------------------------
    |
    | Default token limits per request and per user. These are overridable
    | from the admin panel via settings(). The global_daily_budget_usd is
    | enforced by TokenGuard before every AI request.
    |
    */

    'limits' => [
        'max_tokens_per_request' => 4096,
        'daily_limit_per_user' => 50000,
        'monthly_limit_per_user' => 1000000,
        'global_daily_budget_usd' => 100.00,
        'soft_limit_percentage' => 80,
    ],

    /*
    |--------------------------------------------------------------------------
    | Image Generation
    |--------------------------------------------------------------------------
    |
    | Default settings for image generation. Overridable from admin panel.
    |
    */

    'image' => [
        'providers' => ['dall-e-3', 'stable-diffusion-3', 'flux-pro'],
        'default_size' => '1024x1024',
        'max_per_day' => 50,
    ],

    /*
    |--------------------------------------------------------------------------
    | RAG / Knowledge Base
    |--------------------------------------------------------------------------
    |
    | Default settings for document ingestion, chunking, and vector storage.
    |
    */

    'rag' => [
        'chunk_size' => (int) env('AI_CHUNK_SIZE', 800),
        'chunk_overlap' => (int) env('AI_CHUNK_OVERLAP', 100),
        'max_context_chunks' => (int) env('AI_MAX_CONTEXT_CHUNKS', 10),
        'vector_store_driver' => env('AI_VECTOR_STORE_DRIVER', 'database'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Queues
    |--------------------------------------------------------------------------
    */

    'queues' => [
        'default' => 'default',
        'ai' => 'ai',
        'emails' => 'emails',
        'webhooks' => 'webhooks',
    ],

    /*
    |--------------------------------------------------------------------------
    | Unified Friendly Names & Provider Map
    |--------------------------------------------------------------------------
    |
    | Maps raw model slugs to readable labels, and raw provider slugs
    | to unified provider titles displayed across the application.
    |
    */

    'model_names' => [
        // OpenAI
        'gpt-5.5' => 'GPT-5.5 (Most Powerful)',
        'gpt-5.5-mini' => 'GPT-5.5 Mini (Fast & Affordable)',
        'gpt-5.4' => 'GPT-5.4 (Advanced)',
        'gpt-4o' => 'GPT-4o (Great All-Rounder)',
        'gpt-4o-mini' => 'GPT-4o Mini (Budget-Friendly)',
        'gpt-4-turbo' => 'GPT-4 Turbo',
        'gpt-4' => 'GPT-4',
        'gpt-3.5-turbo' => 'GPT-3.5 Turbo',
        'o3' => 'o3 (Deep Reasoning)',
        'o3-mini' => 'o3 Mini (Efficient Reasoning)',
        'o4-mini' => 'o4 Mini (Efficient Reasoning)',
        'o1' => 'o1 (Deep Reasoning)',
        'o1-mini' => 'o1 Mini',
        // Anthropic
        'claude-opus-4-8' => 'Claude Opus 4.8 (Most Capable)',
        'claude-sonnet-4-6' => 'Claude Sonnet 4.6 (Best Balance)',
        'claude-sonnet-4-5' => 'Claude Sonnet 4.5 (Best Balance)',
        'claude-haiku-4-5' => 'Claude Haiku 4.5 (Fast & Lightweight)',
        'claude-3-opus' => 'Claude 3 Opus',
        'claude-3-sonnet' => 'Claude 3.5 Sonnet',
        'claude-3-haiku' => 'Claude 3 Haiku',
        'claude-3-5-sonnet' => 'Claude 3.5 Sonnet',
        'claude-3-5-haiku' => 'Claude 3.5 Haiku',
        // Google Gemini
        'gemini-3.5-flash' => 'Gemini 3.5 Flash (Ultra Fast)',
        'gemini-3.1-pro' => 'Gemini 3.1 Pro (High Performance)',
        'gemini-3.1-flash-lite' => 'Gemini 3.1 Flash Lite (Lightweight)',
        'gemini-2.5-pro' => 'Gemini 2.5 Pro (Reliable)',
        'gemini-2.5-flash' => 'Gemini 2.5 Flash (Quick)',
        'gemini-2.0-flash' => 'Gemini 2.0 Flash (Budget)',
        'gemini-1.5-pro' => 'Gemini 1.5 Pro',
        'gemini-1.5-flash' => 'Gemini 1.5 Flash',
        // xAI Grok
        'grok-4.3' => 'Grok 4.3 (Latest & Smartest)',
        'grok-4.1-fast' => 'Grok 4.1 Fast (Speedy)',
        'grok-3' => 'Grok 3 (Previous Gen)',
        'grok-3-mini' => 'Grok 3 Mini (Compact)',
        // DeepSeek
        'deepseek-v4-pro' => 'DeepSeek V4 Pro (Best Quality)',
        'deepseek-v4-flash' => 'DeepSeek V4 Flash (Fast & Cheap)',
        'deepseek-r1' => 'DeepSeek R1 (Reasoning & Code)',
        'deepseek-v3' => 'DeepSeek V3 (Balanced)',
        // Perplexity
        'sonar' => 'Sonar (Web Search)',
        'sonar-pro' => 'Sonar Pro (Advanced Search)',
        'sonar-reasoning' => 'Sonar Reasoning (Search + Think)',
        'sonar-deep-research' => 'Sonar Deep Research (In-Depth)',
        'perplexity-sonar' => 'Sonar (Web Search)',
        // Groq
        'llama-4-scout-17b' => 'Llama 4 Scout (Ultra Fast)',
        'llama-3.3-70b' => 'Llama 3.3 70B (Powerful Open-Source)',
        'mixtral-8x7b' => 'Mixtral 8x7B (Fast MoE)',
        // Mistral
        'mistral-large-latest' => 'Mistral Large (Top Tier)',
        'mistral-medium-latest' => 'Mistral Medium (Balanced)',
        'mistral-small-latest' => 'Mistral Small (Lightweight)',
        'mistral-large' => 'Mistral Large (Top Tier)',
        // Image
        'dall-e-3' => 'DALL-E 3 (Image Gen)',
        'flux-pro' => 'Flux Pro (Image Gen)',
        'ideogram' => 'Ideogram (Image Gen)',
        'stability-sd3' => 'Stable Diffusion 3 (Image Gen)',
    ],

    'provider_names' => [
        'openai' => 'OpenAI',
        'anthropic' => 'Anthropic',
        'google' => 'Google Gemini',
        'xai' => 'xAI (Grok)',
        'deepseek' => 'DeepSeek',
        'openrouter' => 'OpenRouter',
        'groq' => 'Groq',
        'mistral' => 'Mistral AI',
        'ollama' => 'Ollama (Local)',
        'bedrock' => 'AWS Bedrock',
        'cohere' => 'Cohere',
        'eleven' => 'ElevenLabs',
        'jina' => 'Jina AI',
        'voyageai' => 'Voyage AI',
        'perplexity' => 'Perplexity',
    ],
];
