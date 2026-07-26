<?php

namespace Addons\SocialScheduler\Services;

use App\Models\User;
use App\Services\AI\AiService;
use App\Services\AI\ProviderRegistry;
use Generator;

class AiCaptionService
{
    private const PLATFORM_GUIDES = [
        'instagram' => 'Engaging, visual storytelling, 2200 char max, heavy emoji, 20–30 hashtags at end, newlines for readability. Focus on emotions and aesthetics.',
        'twitter' => 'Punchy, conversational, 280 chars MAX per tweet. If thread, clearly mark "1/" "2/" etc. No hashtag spam — max 2 relevant hashtags.',
        'linkedin' => 'Professional, insightful, thought leadership tone. 700 char sweet spot. 3–5 relevant hashtags. Use line breaks. No excessive emoji.',
        'facebook' => 'Friendly, community-oriented, storytelling. 500–1000 chars optimal. 2–3 hashtags. Ask a question to drive comments.',
        'tiktok' => 'Trend-aware, casual, Gen-Z friendly. Under 2200 chars. 3–5 trending hashtags. Direct hook in first line. CTA at end.',
        'pinterest' => 'Keyword-rich for search, inspirational, descriptive. 500 char max. Focus on value and discovery.',
        'youtube' => 'SEO-optimized description. First 2–3 lines most important (shown before "show more"). Include timestamps if long-form. 5000 char limit.',
    ];

    public function __construct(private AiService $ai) {}

    public function streamCaption(
        string $topic,
        string $platform,
        string $tone,
        ?string $additionalContext,
        ?User $user,
    ): Generator {
        $guide = self::PLATFORM_GUIDES[$platform] ?? 'Write an engaging social media caption.';
        $appName = settings('app_name', 'MakeAI');

        $systemPrompt = "You are a social media expert writing a caption for {$platform}.\nPlatform guidelines: {$guide}\nTone: {$tone}.\nWrite ONLY the caption text — no preamble, no explanation, no quotes around it.\nApp context: {$appName}";

        $userPrompt = "Topic: {$topic}" . ($additionalContext ? "\nAdditional context: {$additionalContext}" : '');

        $providerName = addon_setting('social-scheduler', 'provider');
        if (empty($providerName)) {
            $providerName = settings('default_ai_provider', 'openai');
        }

        $modelName = addon_setting('social-scheduler', 'ai_model');
        if (empty($modelName)) {
            $modelName = settings('default_ai_model', config('ai.fallback_model'));
        }

        $adapter = ProviderRegistry::resolve($providerName);

        $stream = $adapter->streamChatCompletion([
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $userPrompt],
        ], $modelName, [
            'max_tokens' => 1024,
        ]);

        foreach ($stream as $chunk) {
            if (is_string($chunk)) {
                yield $chunk;
            }
        }
    }

    public function adaptCaption(string $originalCaption, string $targetPlatform): string
    {
        $guide = self::PLATFORM_GUIDES[$targetPlatform] ?? 'Write an engaging social media caption.';

        $providerName = addon_setting('social-scheduler', 'provider');
        if (empty($providerName)) {
            $providerName = settings('default_ai_provider', 'openai');
        }

        $modelName = addon_setting('social-scheduler', 'ai_model');
        if (empty($modelName)) {
            $modelName = settings('default_ai_model', config('ai.fallback_model'));
        }

        $adapter = ProviderRegistry::resolve($providerName);

        $systemPrompt = "Rewrite this social media caption for {$targetPlatform}.\nPlatform guidelines: {$guide}\nReturn ONLY the rewritten caption text.";

        try {
            $response = $adapter->chatCompletion([
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $originalCaption],
            ], $modelName, ['max_tokens' => 800]);

            return $response['content'] ?? $originalCaption;
        } catch (\Throwable) {
            return $originalCaption;
        }
    }
}
