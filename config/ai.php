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
            'models' => ['gpt-4o', 'gpt-4o-mini', 'gpt-4-turbo', 'o1', 'o3', 'o4-mini'],
        ],

        'anthropic' => [
            'name' => 'Anthropic',
            'driver' => 'anthropic',
            'key' => env('ANTHROPIC_API_KEY'),
            'url' => env('ANTHROPIC_URL', 'https://api.anthropic.com/v1'),
            'models' => ['claude-sonnet-4-20250514', 'claude-opus-4-20250514', 'claude-haiku-20250514'],
        ],

        'google' => [
            'name' => 'Google Gemini',
            'driver' => 'gemini',
            'key' => env('GEMINI_API_KEY'),
            'url' => env('GEMINI_URL', 'https://generativelanguage.googleapis.com/v1beta'),
            'models' => ['gemini-2.0-flash', 'gemini-2.5-pro', 'gemini-2.5-flash'],
        ],

        'xai' => [
            'name' => 'xAI (Grok)',
            'driver' => 'xai',
            'key' => env('XAI_API_KEY'),
            'url' => env('XAI_URL', 'https://api.x.ai/v1'),
            'models' => ['grok-3', 'grok-3-mini'],
        ],

        'deepseek' => [
            'name' => 'DeepSeek',
            'driver' => 'deepseek',
            'key' => env('DEEPSEEK_API_KEY'),
            'url' => env('DEEPSEEK_URL', 'https://api.deepseek.com/v1'),
            'models' => ['deepseek-r1', 'deepseek-v3'],
        ],

        'openrouter' => [
            'name' => 'OpenRouter',
            'driver' => 'openrouter',
            'key' => env('OPENROUTER_API_KEY'),
            'url' => env('OPENROUTER_URL', 'https://openrouter.ai/api/v1'),
            'models' => [], // dynamic, loaded from API
        ],

        'groq' => [
            'name' => 'Groq',
            'driver' => 'groq',
            'key' => env('GROQ_API_KEY'),
            'models' => ['llama-3.3-70b', 'mixtral-8x7b'],
        ],

        'mistral' => [
            'name' => 'Mistral AI',
            'driver' => 'mistral',
            'key' => env('MISTRAL_API_KEY'),
            'models' => ['mistral-large', 'mistral-nemo'],
        ],

        'ollama' => [
            'name' => 'Ollama (Local)',
            'driver' => 'ollama',
            'key' => env('OLLAMA_API_KEY', ''),
            'url' => env('OLLAMA_URL', 'http://localhost:11434'),
            'models' => [],
        ],

        'azure' => [
            'name' => 'Azure OpenAI',
            'driver' => 'azure',
            'key' => env('AZURE_OPENAI_API_KEY'),
            'url' => env('AZURE_OPENAI_URL'),
            'api_version' => env('AZURE_OPENAI_API_VERSION', '2025-04-01-preview'),
            'deployment' => env('AZURE_OPENAI_DEPLOYMENT', 'gpt-4o'),
            'embedding_deployment' => env('AZURE_OPENAI_EMBEDDING_DEPLOYMENT', 'text-embedding-3-small'),
            'image_deployment' => env('AZURE_OPENAI_IMAGE_DEPLOYMENT', 'gpt-image-1'),
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
        ],

        'cohere' => [
            'name' => 'Cohere',
            'driver' => 'cohere',
            'key' => env('COHERE_API_KEY'),
        ],

        'eleven' => [
            'name' => 'ElevenLabs',
            'driver' => 'eleven',
            'key' => env('ELEVENLABS_API_KEY'),
        ],

        'jina' => [
            'name' => 'Jina AI',
            'driver' => 'jina',
            'key' => env('JINA_API_KEY'),
        ],

        'voyageai' => [
            'name' => 'Voyage AI',
            'driver' => 'voyageai',
            'key' => env('VOYAGEAI_API_KEY'),
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
        'chunk_size' => (int) env('AI_CHUNK_SIZE', 1000),
        'chunk_overlap' => (int) env('AI_CHUNK_OVERLAP', 200),
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
];
