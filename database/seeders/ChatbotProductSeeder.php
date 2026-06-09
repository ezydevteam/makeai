<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\ChatbotProduct;
use Illuminate\Database\Seeder;

class ChatbotProductSeeder extends Seeder
{
    public function run(): void
    {
        $products = [
            [
                'slug' => 'chat-code',
                'name' => 'Code',
                'icon' => 'ti ti-code',
                'color_hex' => '#1F75FE',
                'system_prompt' => 'You are an expert software engineer. Help the user write, fix, explain, and document code. Always provide code blocks with proper syntax highlighting. Be concise but thorough.',
                'preferred_models' => ['deepseek-v3', 'gpt-4o', 'claude-sonnet-4-5', 'gemini-2-0-flash'],
                'default_model' => 'gpt-4o',
                'starter_prompts' => ['Fix a bug in my code', 'Write a Python script', 'Explain this regex', 'Add TypeScript types', 'Write unit tests for this'],
                'sort_order' => 1,
                'is_active' => true,
            ],
            [
                'slug' => 'chat-write',
                'name' => 'Write',
                'icon' => 'ti ti-pencil',
                'color_hex' => '#16a34a',
                'system_prompt' => 'You are a professional writer. Help the user craft blogs, emails, essays, reports, creative fiction, and copywriting. Focus on clarity, tone, and structure.',
                'preferred_models' => ['gpt-4o', 'claude-sonnet-4-5', 'gemini-2-5-pro', 'mistral-large'],
                'default_model' => 'gpt-4o',
                'starter_prompts' => ['Write a blog post about...', 'Improve my email draft', 'Write a product description', 'Summarize this text', 'Proofread and fix grammar'],
                'sort_order' => 2,
                'is_active' => true,
            ],
            [
                'slug' => 'chat-design',
                'name' => 'Design',
                'icon' => 'ti ti-palette',
                'color_hex' => '#9333ea',
                'system_prompt' => 'You are a UI/UX design expert. Help with design briefs, brand guidelines, design feedback, component specs, and Figma prompts. Think about user experience, accessibility, and visual hierarchy.',
                'preferred_models' => ['claude-sonnet-4-5', 'gpt-4o', 'gemini-2-5-pro'],
                'default_model' => 'claude-sonnet-4-5',
                'starter_prompts' => ['Review my UI design', 'Create a brand color palette', 'Write a design brief', 'Suggest UI improvements', 'Generate Figma component ideas'],
                'sort_order' => 3,
                'is_active' => true,
            ],
            [
                'slug' => 'chat-marketing',
                'name' => 'Marketing',
                'icon' => 'ti ti-speakerphone',
                'color_hex' => '#ea580c',
                'system_prompt' => 'You are a marketing strategist. Help with ad copy, funnels, GTM strategy, positioning, competitor analysis, and SEO content. Think about audience, conversion, and ROI.',
                'preferred_models' => ['gpt-4o', 'claude-sonnet-4-5', 'gemini-2-5-pro'],
                'default_model' => 'gpt-4o',
                'starter_prompts' => ['Write Facebook ad copy', 'Create a go-to-market plan', 'Write a value proposition', 'Plan a product launch', 'Analyze this landing page'],
                'sort_order' => 4,
                'is_active' => true,
            ],
            [
                'slug' => 'chat-social',
                'name' => 'Social Media',
                'icon' => 'ti ti-brand-instagram',
                'color_hex' => '#db2777',
                'system_prompt' => "You are a social media expert. Help create platform-aware captions, threads, hooks, hashtags, and content calendars. Tailor content for each platform's audience.",
                'preferred_models' => ['gpt-4o-mini', 'claude-haiku-4-5', 'gemini-2-0-flash'],
                'default_model' => 'gpt-4o-mini',
                'starter_prompts' => ['Write 5 Instagram captions', 'Create a Twitter thread', 'Suggest trending hashtags', 'Write a LinkedIn post', 'Create a content calendar'],
                'sort_order' => 5,
                'is_active' => true,
            ],
            [
                'slug' => 'chat-analyze',
                'name' => 'Analyze',
                'icon' => 'ti ti-chart-bar',
                'color_hex' => '#ca8a04',
                'system_prompt' => 'You are a data analyst. Help interpret data, summarize reports, perform research, make comparisons, and extract insights. Be data-driven and cite reasoning.',
                'preferred_models' => ['gpt-4o', 'claude-sonnet-4-5', 'gemini-2-5-pro'],
                'default_model' => 'gpt-4o',
                'starter_prompts' => ['Analyze this data', 'Summarize this report', 'Compare these two options', 'Explain this concept simply', 'Extract key insights'],
                'sort_order' => 6,
                'is_active' => true,
            ],
            [
                'slug' => 'chat-image',
                'name' => 'Image',
                'icon' => 'ti ti-photo',
                'color_hex' => '#0891b2',
                'system_prompt' => 'You are an AI image generation assistant. Help the user craft effective image generation prompts. Provide detailed visual descriptions for text-to-image models.',
                'preferred_models' => ['dall-e-3', 'flux-pro', 'ideogram', 'stability-sd3'],
                'default_model' => 'dall-e-3',
                'starter_prompts' => ['Generate a product banner', 'Create a logo concept', 'Design a social media graphic', 'Illustrate this scene', 'Create a thumbnail'],
                'sort_order' => 7,
                'is_active' => true,
            ],
            [
                'slug' => 'chat-research',
                'name' => 'Research',
                'icon' => 'ti ti-world-www',
                'color_hex' => '#4f46e5',
                'system_prompt' => 'You are a research assistant. Provide web-aware answers with cited sources. Summarize recent developments and find current information. Always cite your sources.',
                'preferred_models' => ['perplexity-sonar', 'gpt-4o', 'gemini-2-5-pro'],
                'default_model' => 'gpt-4o',
                'starter_prompts' => ['Latest news about...', 'Research competitors in...', 'Find statistics on...', 'Summarize recent developments in...', 'What is the current status of...'],
                'sort_order' => 8,
                'is_active' => true,
            ],
            [
                'slug' => 'chat-mentor',
                'name' => 'Mentor',
                'icon' => 'ti ti-school',
                'color_hex' => '#7c3aed',
                'system_prompt' => 'You are an expert AI mentor and coach. Help the user think clearly, improve their skills, and make better decisions. Ask clarifying questions when needed, explain the reasoning behind recommendations, challenge weak assumptions respectfully, and focus on practical steps that build long-term understanding.',
                'preferred_models' => ['claude-sonnet-4-5', 'gpt-4o', 'gemini-2-5-pro'],
                'default_model' => 'claude-sonnet-4-5',
                'starter_prompts' => ['Mentor me on this idea', 'Review my approach and tell me what I am missing', 'Teach me this step by step', 'Give me feedback like a coach', 'Help me improve this workflow'],
                'sort_order' => 9,
                'is_active' => true,
            ],
        ];

        foreach ($products as $product) {
            ChatbotProduct::updateOrCreate(
                ['slug' => $product['slug']],
                $product
            );
        }
    }
}
