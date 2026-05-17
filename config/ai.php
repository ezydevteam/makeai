<?php

/**
 * MakeAI — AI Configuration
 *
 * Default AI provider settings, model costs, and limits.
 * All values are overridable from the admin panel via settings().
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Default Provider
    |--------------------------------------------------------------------------
    */
    'default_provider' => env('AI_DEFAULT_PROVIDER', 'openai'),

    /*
    |--------------------------------------------------------------------------
    | Supported Providers
    |--------------------------------------------------------------------------
    */
    'providers' => [
        'openai' => [
            'name' => 'OpenAI',
            'base_url' => 'https://api.openai.com/v1',
            'models' => ['gpt-4o', 'gpt-4o-mini', 'gpt-4-turbo', 'o1', 'o3', 'o4-mini'],
        ],
        'anthropic' => [
            'name' => 'Anthropic',
            'base_url' => 'https://api.anthropic.com/v1',
            'models' => ['claude-sonnet-4-5', 'claude-opus-4', 'claude-haiku'],
        ],
        'google' => [
            'name' => 'Google',
            'base_url' => 'https://generativelanguage.googleapis.com/v1beta',
            'models' => ['gemini-2.0-flash', 'gemini-2.5-pro', 'gemini-2.5-flash'],
        ],
        'xai' => [
            'name' => 'xAI',
            'base_url' => 'https://api.x.ai/v1',
            'models' => ['grok-3', 'grok-3-mini'],
        ],
        'deepseek' => [
            'name' => 'DeepSeek',
            'base_url' => 'https://api.deepseek.com/v1',
            'models' => ['deepseek-r1', 'deepseek-v3'],
        ],
        'openrouter' => [
            'name' => 'OpenRouter',
            'base_url' => 'https://openrouter.ai/api/v1',
            'models' => [], // dynamic, loaded from API
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Token Limits
    |--------------------------------------------------------------------------
    */
    'limits' => [
        'max_tokens_per_request' => 4096,
        'daily_limit_per_user' => 50000,      // tokens
        'monthly_limit_per_user' => 1000000,
        'global_daily_budget_usd' => 100.00,
        'soft_limit_percentage' => 80,         // warn at 80%
    ],

    /*
    |--------------------------------------------------------------------------
    | Image Generation
    |--------------------------------------------------------------------------
    */
    'image' => [
        'providers' => ['dall-e-3', 'stable-diffusion-3', 'flux-pro'],
        'default_size' => '1024x1024',
        'max_per_day' => 50,
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
