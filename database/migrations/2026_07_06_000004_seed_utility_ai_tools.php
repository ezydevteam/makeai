<?php

use App\Models\AiTool;
use App\Models\Category;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Str;

/**
 * Seed the four integration-backed utility tools (Grammar, AI Content Detector,
 * Plagiarism, Translation). Each runs on its integration when configured/enabled,
 * else falls back to the LLM using the tool's model_override or the global default.
 */
return new class extends Migration
{
    public function up(): void
    {
        $category = Category::updateOrCreate(
            ['slug' => 'writing-analysis', 'type' => 'ai_tool'],
            [
                'name' => 'Writing & Analysis',
                'description' => 'Grammar, translation, plagiarism, and AI-content analysis tools.',
                'icon' => 'ti ti-file-text',
                'color' => '#6366f1',
                'is_active' => true,
                'is_system' => true,
                'requires_pro' => false,
                'sort_order' => 27,
            ]
        );

        $textField = fn (string $label) => [[
            'key' => 'text', 'name' => 'text', 'type' => 'textarea',
            'label' => $label, 'required' => true, 'rows' => 8, 'max_length' => 20000,
        ]];

        $tools = [
            [
                'slug' => 'grammar-checker',
                'name' => 'Grammar Checker',
                'integration_slug' => 'grammar',
                'description' => 'Fix spelling and grammar mistakes and see every change.',
                'icon' => 'ti ti-spellcheck',
                'fields' => $textField('Your text'),
                'prompt_system' => 'You are a meticulous professional proofreader.',
                'prompt_user' => "Correct all spelling and grammar mistakes in the text below. Return the corrected text, then a short bullet list of the changes.\n\nText:\n{text}",
            ],
            [
                'slug' => 'ai-content-detector',
                'name' => 'AI Content Detector',
                'integration_slug' => 'ai_detector',
                'description' => 'Estimate how likely a piece of text was written by AI.',
                'icon' => 'ti ti-robot',
                'fields' => $textField('Text to analyze'),
                'prompt_system' => 'You are an AI-content analyst.',
                'prompt_user' => "Estimate how likely the following text was written by an AI. Give an approximate AI-likelihood percentage and a one-paragraph rationale. This is a heuristic estimate, not a definitive result.\n\nText:\n{text}",
            ],
            [
                'slug' => 'plagiarism-checker',
                'name' => 'Plagiarism Checker',
                'integration_slug' => 'plagiarism',
                'description' => 'Check text for copied or unoriginal passages.',
                'icon' => 'ti ti-copy-off',
                'fields' => $textField('Text to check'),
                'prompt_system' => 'You are a writing-originality analyst.',
                'prompt_user' => "Assess whether the following text appears original or derivative of common, publicly available content. You cannot access the web, so flag only phrasing that reads as unoriginal, and say so.\n\nText:\n{text}",
            ],
            [
                'slug' => 'translator',
                'name' => 'Translator',
                'integration_slug' => 'translation',
                'description' => 'Translate text into another language.',
                'icon' => 'ti ti-language',
                'fields' => [
                    ['key' => 'text', 'name' => 'text', 'type' => 'textarea', 'label' => 'Text to translate', 'required' => true, 'rows' => 8, 'max_length' => 20000],
                    ['key' => 'target_language', 'name' => 'target_language', 'type' => 'text', 'label' => 'Target language (e.g. DE, French, Spanish)', 'required' => true, 'max_length' => 40],
                ],
                'prompt_system' => 'You are a professional translator.',
                'prompt_user' => "Translate the following text into {target_language}. Return only the translation.\n\nText:\n{text}",
            ],
        ];

        foreach ($tools as $i => $tool) {
            AiTool::updateOrCreate(
                ['slug' => $tool['slug']],
                [
                    'ulid' => (string) Str::ulid(),
                    'name' => $tool['name'],
                    'type' => 'template',
                    'description' => $tool['description'],
                    'category_id' => $category->id,
                    'icon' => $tool['icon'],
                    'color' => '#6366f1',
                    // Pass the raw array — the AiTool `fields => 'array'` cast encodes
                    // it once. json_encode() here would double-encode and render zero inputs.
                    'fields' => $tool['fields'],
                    'prompt_system' => $tool['prompt_system'],
                    'prompt_user' => $tool['prompt_user'],
                    'output_type' => 'markdown',
                    'generation_mode' => 'integration_llm_fallback',
                    'integration_slug' => $tool['integration_slug'],
                    'access_level' => 'login',
                    'is_active' => true,
                    'is_featured' => false,
                    'requires_pro' => false,
                    'is_system' => true,
                    'sort_order' => $i + 1,
                ]
            );
        }
    }

    public function down(): void
    {
        AiTool::whereIn('slug', ['grammar-checker', 'ai-content-detector', 'plagiarism-checker', 'translator'])->delete();
        Category::where('slug', 'writing-analysis')->delete();
    }
};
