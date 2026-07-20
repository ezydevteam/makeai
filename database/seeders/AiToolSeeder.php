<?php

namespace Database\Seeders;

use App\Models\AiTool;
use App\Models\Category;
use App\Services\AI\ToolCatalogCacheService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Seeds the 402 predefined AI tools from AI_SaaS_Master_Prompt Part 14.7.
 */
class AiToolSeeder extends Seeder
{
    public function run(): void
    {
        $categories = Category::aiTools()->where('is_active', true)->get()->keyBy('slug');
        $catalog = $this->catalog();
        $expectedTotal = 402;
        $actualTotal = collect($catalog)->flatten(1)->count();

        if ($actualTotal !== $expectedTotal) {
            throw new RuntimeException("AI tool catalog must contain {$expectedTotal} tools; {$actualTotal} found.");
        }

        $sortOrder = 1;
        $activeSlugs = [];

        foreach ($catalog as $categorySlug => $tools) {
            $category = $categories->get($categorySlug);

            if (! $category) {
                throw new RuntimeException("Missing AI tool category: {$categorySlug}");
            }

            foreach ($tools as $tool) {
                $activeSlugs[] = $tool['slug'];

                // Look up with trashed so a previously soft-deleted canonical tool is
                // restored and updated in place, rather than triggering a unique-slug
                // insert collision (the unique index spans soft-deleted rows).
                $model = AiTool::withTrashed()->firstOrNew(['slug' => $tool['slug']]);
                $model->fill($this->payload($tool, $category, $sortOrder++));
                if ($model->trashed()) {
                    $model->deleted_at = null;
                }
                $model->save();
            }

            $category->updateToolsCount();
        }

        AiTool::where('type', 'text')->whereNotIn('slug', $activeSlugs)->update(['is_active' => false]);

        // Assign marketing funnel stage tags for Marketing Suite template
        foreach ($this->stageTags() as $slug => $tags) {
            AiTool::where('slug', $slug)->update(['tags' => json_encode($tags)]);
        }

        foreach ($categories as $category) {
            $category->updateToolsCount();
        }

        ToolCatalogCacheService::invalidateAll();
    }

    private function payload(array $tool, Category $category, int $sortOrder): array
    {
        $name = $tool['name'] ?? Str::headline($tool['slug']);
        $description = $tool['description'] ?? $this->description($name, $category->name, $category->slug);
        $outputType = $tool['output_type'] ?? $this->outputType($tool['slug'], $category->slug);

        return [
            'name' => $name,
            'description' => $description,
            'prompt_system' => $this->flagshipSystemPrompt($tool['slug'])
                ?: $this->promptSystem($name, $category->name, $category->slug, $outputType),
            'prompt_user' => $this->promptUser($name),
            'category_id' => $category->id,
            'icon' => $tool['icon'] ?? $category->icon,
            'color' => $tool['color'] ?? $category->color,
            'fields' => $this->fieldsFor($category->slug, $tool['slug']),
            'tags' => $tool['tags'] ?? null,
            'output_type' => $outputType,
            'model_override' => null,
            'temperature' => $this->temperatureFor($category->slug),
            'max_tokens_override' => $this->maxTokens($category->slug, $tool['slug']),
            'access_level' => in_array($category->slug, ['legal-finance', 'business', 'development'], true)
                && str_contains($tool['slug'], 'analysis')
                ? 'pro_plan'
                : 'inherit',
            'is_active' => true,
            'is_featured' => in_array($tool['slug'], $this->featuredSlugs(), true),
            'supports_brand_voice' => ! in_array($category->slug, ['development', 'academic', 'language'], true),
            'avg_output_tokens' => $this->avgOutputTokens($category->slug, $tool['slug']),
            'sort_order' => $sortOrder,
            'about_content' => "{$name} is an AI-powered {$category->name} tool that turns a few guided inputs into {$this->categoryValueProp($category->slug)}. Set the tone, format, and language to fit your needs, then copy, export, or refine the result — no writing or design skills required.",
            'how_it_works' => [
                ['step' => 1, 'title' => 'Add details', 'description' => 'Fill in the main topic, audience, and any required context.'],
                ['step' => 2, 'title' => 'Choose style', 'description' => 'Select tone, language, format, and output length where needed.'],
                ['step' => 3, 'title' => 'Generate', 'description' => 'Review the AI output, then copy, export, save, or regenerate it.'],
            ],
            'usage_examples' => [$this->usageExample($category->slug, $category->name)],
            'faq_items' => [
                ['question' => "Is {$name} free to use?", 'answer' => 'It runs on your account plan. Generate as many results as your plan allows.'],
                ['question' => "Can I edit the {$name} output?", 'answer' => 'Yes. Every result is an editable draft — copy it, save it, export it, or regenerate for a fresh take.'],
                ['question' => 'What languages are supported?', 'answer' => 'Choose your output language in the form and the result is written in that language.'],
            ],
            'show_about' => true,
            'show_how_it_works' => true,
            'show_usage_examples' => true,
            'show_faqs' => true,
            'show_reviews' => true,
            'show_related_tools' => true,
            'meta_title' => "{$name} - Free AI Tool",
            'meta_description' => $description,
            'og_image' => null,
        ];
    }

    /**
     * Bespoke, hand-tuned system prompts for the flagship tools (the featured,
     * highest-traffic ones). These override the tiered generator with task-specific
     * craft — subject-line limits, hook rules, section structure — that a category
     * rule set cannot express. Returns '' for every other tool, which then falls
     * back to promptSystem(). {language} stays a runtime placeholder.
     */
    private function flagshipSystemPrompt(string $slug): string
    {
        return match ($slug) {
            'blog-article' => <<<'PROMPT'
                You are a senior content writer and SEO strategist producing a complete, publish-ready long-form article.

                - Open with a hook that names the reader's problem and the payoff of reading on — no "In today's world" throat-clearing.
                - Structure the piece with logical, descriptive, search-friendly H2/H3 headings.
                - Develop each section with specific detail, examples, and concrete takeaways; never filler or repetition.
                - Weave the target keywords in naturally — never keyword-stuff.
                - Close with a tight conclusion and, where it fits, a clear call to action.
                - Be accurate: no invented statistics, studies, or quotes; mark any needed citation as a [placeholder].

                Return clean Markdown. Always respond in {language}.
                PROMPT,

            'instagram-caption' => <<<'PROMPT'
                You are a social media copywriter crafting scroll-stopping Instagram captions.

                - Open with a strong first line — the only part shown before "more" — that hooks or intrigues.
                - Write like a real person, on-brand for the requested tone; never a stiff brand memo.
                - Add a natural question or light call to action to drive comments where it fits.
                - End with 5-15 relevant, non-spammy hashtags on their own line, mixing broad and niche.
                - Use Instagram's style: warm emojis where they help, short punchy lines, generous spacing.

                Return the caption(s) ready to paste. Always respond in {language}.
                PROMPT,

            'cold-email' => <<<'PROMPT'
                You are a B2B outbound specialist writing cold emails that get replies.

                - Write a specific, curiosity-driven subject line under ~50 characters — no clickbait, no fake "Re:".
                - Open with a personalized line about the RECIPIENT, not the sender.
                - State the value in one or two sentences, tied to a likely pain point.
                - End with a single low-friction call to action (a question or a small specific ask), never "let me know".
                - Keep it under ~120 words, plain text, no jargon, no spam-trigger phrasing.

                Return the subject line and body, labeled. Always respond in {language}.
                PROMPT,

            'product-description' => <<<'PROMPT'
                You are an e-commerce copywriter writing high-converting product descriptions.

                - Lead with the core benefit and who it is for, then turn the key features into benefits.
                - Stay strictly accurate to the provided details — never invent materials, dimensions, or claims.
                - Use scannable structure: a short persuasive paragraph plus a feature/benefit bullet list.
                - Anticipate and answer the top buyer objection.
                - Match the requested tone and include a subtle call to action where relevant.

                Return clean Markdown. Always respond in {language}.
                PROMPT,

            'business-plan' => <<<'PROMPT'
                You are a startup advisor and financial analyst drafting a structured, investor-ready business plan.

                - Cover the relevant sections: executive summary, problem and solution, market and audience, business/revenue model, go-to-market, competition, team, and high-level financials.
                - Be specific and realistic; base any numbers on stated assumptions and label them as assumptions.
                - Never fabricate market sizes or figures — give reasoned ranges or clearly marked [placeholders] to fill.
                - Use clear headings and prioritized, actionable points; avoid generic strategy filler.

                Return clean Markdown with headings. Always respond in {language}.
                PROMPT,

            'code-generator' => <<<'PROMPT'
                You are a senior software engineer generating correct, production-quality code.

                - Return code that runs as written for the requested language and task.
                - Use a fenced code block tagged with the language. Put any explanation AFTER the block, never inside it.
                - Never invent APIs, libraries, functions, or flags; if a detail is unknown, pick a sensible standard and note the assumption in a brief comment.
                - Handle the obvious edge cases and errors; prefer clear, idiomatic, secure patterns over cleverness.
                - Stay focused on what was asked — no unrelated scaffolding.

                Write any prose in {language}.
                PROMPT,

            'meta-seo' => <<<'PROMPT'
                You are an SEO specialist writing search-optimized page metadata.

                - Write a meta title under ~60 characters and a meta description under ~155 characters, so neither truncates in search results.
                - Match the page's search intent, lead with the primary keyword naturally, and make the description compelling enough to earn the click.
                - Never keyword-stuff or promise something the page does not offer.
                - Return the title and description, clearly labeled.

                Always respond in {language}.
                PROMPT,

            'translator' => <<<'PROMPT'
                You are a professional translator.

                - Translate the input into the target language accurately and naturally, the way a native speaker would phrase it — not word for word.
                - Preserve meaning, tone, register, and formatting (lists, line breaks, placeholders, markup) exactly.
                - Leave proper nouns, code, URLs, and genuinely untranslatable terms intact.
                - Return only the translation, with no commentary unless asked.

                Always respond in {language}.
                PROMPT,

            default => '',
        };
    }

    /**
     * Compose a tiered system prompt: a universal base, a per-category expertise
     * block, and a per-output-type format contract. One category rule set covers
     * every tool in that category, so all 402 tools gain domain-specific guidance
     * and guardrails without 402 hand-written prompts.
     */
    private function promptSystem(string $name, string $categoryName, string $categorySlug, string $outputType): string
    {
        $base = <<<PROMPT
You are an expert {$categoryName} specialist operating the "{$name}" tool.

Core rules:
- Produce practical, specific, ready-to-use output that directly serves the request.
- Honor the requested tone, audience, language, length, and format exactly.
- Be accurate. Never invent facts, statistics, names, quotes, or sources; if something is unknown, say so or leave a clearly marked placeholder.
- Make reasonable assumptions when the input is thin, and keep the result easy to edit.
- Return only the requested content — no preamble, apologies, or meta-commentary.
PROMPT;

        $parts = array_filter([
            $base,
            $this->categoryGuidance($categorySlug),
            $this->outputContract($outputType),
        ]);

        return implode("\n\n", $parts)."\n\nAlways respond in {language}.";
    }

    /**
     * Domain expertise and guardrails per tool category. Kept tight (a few lines
     * each) so the added tokens stay small relative to the quality gain.
     */
    private function categoryGuidance(string $slug): string
    {
        return match ($slug) {
            'development' => "Development rules:\n"
                ."- Return correct, runnable code in fenced blocks tagged with the language.\n"
                ."- Never invent APIs, libraries, or signatures; if unsure, state the assumption.\n"
                ."- Prefer idiomatic, secure patterns and note important edge cases briefly.",
            'legal-finance' => "Domain rules:\n"
                ."- Give practical, well-structured guidance, but state plainly this is not legal or financial advice and that rules vary by jurisdiction.\n"
                ."- Never guarantee an outcome; flag where a qualified professional should be consulted.",
            'health-fitness' => "Domain rules:\n"
                ."- Give evidence-based, general guidance and note this is not medical advice; advise consulting a professional for personal conditions.\n"
                ."- Avoid diagnoses and specific medical dosages.",
            'academic' => "Academic rules:\n"
                ."- Keep a formal, objective register with a clear thesis, argument, and supporting evidence.\n"
                ."- Do not fabricate sources or quotes; where a citation is needed, insert a clearly marked placeholder.",
            'social-media' => "Social rules:\n"
                ."- Match the platform's conventions and length limits, and open with a scroll-stopping hook.\n"
                ."- Use natural, human language and only genuinely relevant hashtags.",
            'advertising' => "Ad-copy rules:\n"
                ."- Lead with the benefit and end with one clear call to action, within any stated character limit.\n"
                ."- Avoid unverifiable claims and banned superlatives.",
            'email-marketing' => "Email rules:\n"
                ."- Craft a compelling subject line, a skimmable body, and a single clear call to action.\n"
                ."- Keep the tone personal and avoid spam-trigger phrasing that hurts deliverability.",
            'ecommerce' => "Product-copy rules:\n"
                ."- Sell on benefits and answer buyer objections, staying accurate to the given details.\n"
                ."- Never invent specifications or features; use scannable structure.",
            'business' => "Business rules:\n"
                ."- Be structured, data-aware, and actionable, with prioritized recommendations.\n"
                ."- State assumptions; skip vague strategy filler.",
            'sales' => "Sales rules:\n"
                ."- Be persuasive and value-led, tuned to the buyer's stage; address the likely objection and end with a concrete next step.",
            'marketing-strategy' => "Strategy rules:\n"
                ."- Tie every recommendation to goals, audience, channels, and measurable metrics.\n"
                ."- Be specific to the input, not generic best-practice.",
            'customer-support' => "Support rules:\n"
                ."- Be clear, empathetic, and solution-first, with concrete steps in plain language.\n"
                ."- Match the brand tone and de-escalate where needed.",
            'hr-recruiting' => "HR rules:\n"
                ."- Be professional, inclusive, and bias-aware.\n"
                ."- Avoid discriminatory language and any unlawful questions or requirements.",
            'creative-writing' => "Craft rules:\n"
                ."- Prioritize voice, imagery, and pacing; show rather than tell.\n"
                ."- Honor the requested genre, point of view, and tone.",
            'entertainment' => "Style rules:\n"
                ."- Be engaging, original, and appropriate for the audience; keep it fresh.",
            'blog-content' => "Article rules:\n"
                ."- Write a strong intro, scannable headings, and a natural conclusion.\n"
                ."- Be accurate and readable; never keyword-stuff.",
            'website-seo' => "SEO rules:\n"
                ."- Optimize for search intent and readability with natural keyword placement.\n"
                ."- Keep metadata within length limits; never keyword-stuff.",
            'personal-career' => "Career rules:\n"
                ."- Be specific and results-oriented, tailored to the role and industry.\n"
                ."- Use strong action verbs and quantify achievements where possible.",
            'real-estate' => "Listing rules:\n"
                ."- Write vivid, accurate copy highlighting key features and lifestyle.\n"
                ."- Comply with fair-housing norms; use no discriminatory language.",
            'language' => "Language rules:\n"
                ."- Be accurate and natural in the target language, preserving meaning, tone, and nuance.\n"
                ."- Explain choices only when the user asks.",
            'productivity' => "Productivity rules:\n"
                ."- Be concise and organized; prefer clear steps, checklists, and reusable templates.",
            'ai-prompts' => "Prompt-design rules:\n"
                ."- Produce a reusable, copy-paste-ready prompt with a clear role, context, task, constraints, and output format.",
            'podcast' => "Podcast rules:\n"
                ."- Write for the ear: conversational, well-paced, with clear segments, hooks, and natural transitions.",
            'video' => "Video rules:\n"
                ."- Hook in the first few seconds, keep pacing tight, and give clear spoken or on-screen direction where relevant.",
            'image-design' => "Design rules:\n"
                ."- Be concrete and visual: name subject, style, composition, colour, lighting, and mood so a designer or image model can act on it directly.\n"
                ."- Stay true to the brief and any brand cues; do not invent brand names or trademarks.",
            default => '',
        };
    }

    /** Output-format contract per output_type, matched to how the result renders. */
    private function outputContract(string $outputType): string
    {
        return match ($outputType) {
            'code' => 'Format: return the solution as code in a single fenced block tagged with the language. Put any explanation AFTER the block, never inside it.',
            'list' => 'Format: return a clean list, one item per line, with no preamble or trailing commentary.',
            default => 'Format: return clean, well-structured Markdown — headings, short paragraphs, bold for emphasis, and lists or tables where they aid clarity.',
        };
    }

    /**
     * Per-category sampling temperature. Factual/precision tools run cool so they
     * stop inventing; creative tools run warm for variety; everything else sits at
     * a balanced default. Overrides PromptBuilder's flat 0.7 for every tool.
     */
    private function temperatureFor(string $categorySlug): float
    {
        return match ($categorySlug) {
            'development', 'legal-finance', 'academic', 'language', 'website-seo' => 0.3,
            'creative-writing', 'entertainment', 'social-media', 'advertising' => 0.9,
            default => 0.7,
        };
    }

    private function promptUser(string $name): string
    {
        // Only the task inputs. Language, length and the tool identity are set in
        // the system prompt (Always respond in {language} + getLengthInstruction),
        // so listing them here again just duplicated instructions and wasted
        // tokens. Fields a tool does not collect are stripped at build time.
        return <<<'PROMPT'
            Topic or task: {topic}
            Context: {description}
            Target audience: {audience}
            Tone: {tone}
            Format: {format}
            Key points: {keywords}
            Additional instructions: {additional}
            PROMPT;
    }

    private function fieldsFor(string $categorySlug, string $toolSlug): array
    {
        if ($categorySlug === 'development') {
            return [
                ['name' => 'topic', 'key' => 'topic', 'label' => 'Task', 'type' => 'textarea', 'required' => true, 'rows' => 5, 'placeholder' => 'Describe the code, bug, API, test, or developer task.'],
                ['name' => 'description', 'key' => 'description', 'label' => 'Existing Code or Context', 'type' => 'code_input', 'required' => false],
                ['name' => 'format', 'key' => 'format', 'label' => 'Programming Language', 'type' => 'select', 'options' => ['PHP', 'JavaScript', 'TypeScript', 'Python', 'Go', 'Rust', 'SQL', 'Java', 'C#'], 'default' => 'PHP'],
                ['name' => 'language', 'key' => 'language', 'label' => 'Response Language', 'type' => 'language_select', 'default' => 'English'],
                ['name' => 'additional', 'key' => 'additional', 'label' => 'Extra Requirements', 'type' => 'textarea', 'required' => false, 'rows' => 3],
            ];
        }

        if ($categorySlug === 'language') {
            return [
                ['name' => 'topic', 'key' => 'topic', 'label' => 'Text', 'type' => 'textarea', 'required' => true, 'rows' => 6, 'placeholder' => 'Paste the text to translate, rewrite, explain, or improve.'],
                ['name' => 'language', 'key' => 'language', 'label' => 'Target Language', 'type' => 'language_select', 'default' => 'English'],
                ['name' => 'tone', 'key' => 'tone', 'label' => 'Tone', 'type' => 'tone_select', 'default' => 'Professional'],
                ['name' => 'format', 'key' => 'format', 'label' => 'Output Format', 'type' => 'select', 'options' => ['Paragraph', 'Bullet Points', 'Table', 'Side-by-side'], 'default' => 'Paragraph'],
                ['name' => 'additional', 'key' => 'additional', 'label' => 'Extra Instructions', 'type' => 'textarea', 'required' => false, 'rows' => 3],
            ];
        }

        // Pure-metadata tools produce a title/description/markup, not prose, so the
        // tone, length, audience and format-dropdown fields are noise on their
        // form. They need only the page subject, target keywords, and language.
        // Same field KEYS as the default set, so the prompt placeholders are
        // unaffected — this only trims what the form shows.
        $metadataTools = ['meta-seo', 'meta-description', 'seo-title-generator', 'seo-meta-tags', 'schema-markup', 'open-graph-tags'];
        if (in_array($toolSlug, $metadataTools, true)) {
            return [
                ['name' => 'topic', 'key' => 'topic', 'label' => 'Page or Product', 'type' => 'text', 'required' => true, 'max_length' => 200, 'placeholder' => 'What the page is about'],
                ['name' => 'keywords', 'key' => 'keywords', 'label' => 'Target Keywords', 'type' => 'tags_input', 'required' => false],
                ['name' => 'language', 'key' => 'language', 'label' => 'Language', 'type' => 'language_select', 'default' => 'English'],
                ['name' => 'additional', 'key' => 'additional', 'label' => 'Extra Instructions', 'type' => 'textarea', 'required' => false, 'rows' => 2],
            ];
        }

        $fields = [
            ['name' => 'topic', 'key' => 'topic', 'label' => 'Topic or Task', 'type' => 'text', 'required' => true, 'max_length' => 200],
            ['name' => 'description', 'key' => 'description', 'label' => 'Context', 'type' => 'textarea', 'required' => false, 'rows' => 4],
            ['name' => 'audience', 'key' => 'audience', 'label' => 'Target Audience', 'type' => 'text', 'required' => false],
            ['name' => 'tone', 'key' => 'tone', 'label' => 'Tone of Voice', 'type' => 'tone_select', 'default' => 'Professional'],
            ['name' => 'language', 'key' => 'language', 'label' => 'Output Language', 'type' => 'language_select', 'default' => 'English'],
            ['name' => 'length', 'key' => 'length', 'label' => 'Output Length', 'type' => 'length_select', 'default' => 'medium'],
            ['name' => 'format', 'key' => 'format', 'label' => 'Format', 'type' => 'select', 'options' => ['Paragraph', 'Bullet Points', 'Numbered List', 'Table', 'Markdown'], 'default' => 'Markdown'],
            ['name' => 'keywords', 'key' => 'keywords', 'label' => 'Keywords or Key Points', 'type' => 'tags_input', 'required' => false],
            ['name' => 'additional', 'key' => 'additional', 'label' => 'Additional Instructions', 'type' => 'textarea', 'required' => false, 'rows' => 3],
        ];

        if (str_contains($toolSlug, 'email')) {
            array_splice($fields, 2, 0, [
                ['name' => 'recipient', 'key' => 'recipient', 'label' => 'Recipient', 'type' => 'text', 'required' => false],
            ]);
        }

        if (str_contains($toolSlug, 'ads') || str_contains($toolSlug, 'ad-') || str_contains($toolSlug, '-ad')) {
            array_splice($fields, 2, 0, [
                ['name' => 'platform', 'key' => 'platform', 'label' => 'Platform', 'type' => 'select', 'options' => ['Google', 'Facebook', 'Instagram', 'LinkedIn', 'TikTok', 'YouTube'], 'default' => 'Google'],
            ]);
        }

        if ($categorySlug === 'social-media') {
            $platform = $this->platformFromSlug($toolSlug);
            if ($platform) {
                array_splice($fields, 1, 0, [
                    ['name' => 'platform', 'key' => 'platform', 'label' => 'Platform', 'type' => 'hidden', 'default' => $platform],
                ]);
            }
        }

        return $fields;
    }

    private function platformFromSlug(string $toolSlug): ?string
    {
        return match (true) {
            str_starts_with($toolSlug, 'instagram-') => 'Instagram',
            str_starts_with($toolSlug, 'tiktok-') => 'TikTok',
            str_starts_with($toolSlug, 'facebook-') => 'Facebook',
            str_starts_with($toolSlug, 'twitter-') => 'Twitter/X',
            str_starts_with($toolSlug, 'linkedin-') => 'LinkedIn',
            str_starts_with($toolSlug, 'youtube-') && !str_contains($toolSlug, 'to-blog') => 'YouTube',
            default => null,
        };
    }

    private function outputType(string $slug, string $categorySlug): string
    {
        if ($categorySlug === 'development' && in_array($slug, ['code-generator', 'bug-fixer', 'unit-test', 'regex-generator', 'sql-query-generator', 'refactor-code', 'dockerfile-generator'], true)) {
            return 'code';
        }

        if (str_contains($slug, 'ideas') || str_contains($slug, 'generator') || str_contains($slug, 'questions') || str_contains($slug, 'hashtags')) {
            return 'list';
        }

        return 'markdown';
    }

    private function maxTokens(string $categorySlug, string $slug): int
    {
        if (in_array($categorySlug, ['business', 'academic', 'legal-finance'], true)) {
            return 3000;
        }
        if (in_array($slug, ['blog-article', 'ebook-generator', 'business-plan', 'privacy-policy', 'terms-conditions'], true)) {
            return 4096;
        }

        return 2048;
    }

    private function avgOutputTokens(string $categorySlug, string $slug): int
    {
        if (in_array($slug, ['blog-article', 'ebook-generator', 'business-plan'], true)) {
            return 1600;
        }
        if (in_array($categorySlug, ['social-media', 'advertising', 'email-marketing'], true)) {
            return 350;
        }
        if ($categorySlug === 'development') {
            return 900;
        }

        return 700;
    }

    /**
     * A category-appropriate worked example. Replaces the old single example
     * ("Launch plan for a new AI writing app") that was shown verbatim on every
     * tool — nonsensical on a code generator, a translator, or a listing writer.
     *
     * @return array{title:string,input:array<string,string>,output:string}
     */
    private function usageExample(string $slug, string $categoryName): array
    {
        [$topic, $output] = match ($slug) {
            'development' => ['Validate an email address in Python', 'Working, commented code with a short explanation.'],
            'legal-finance' => ['Freelance web-design service agreement', 'A structured contract draft with the standard clauses.'],
            'blog-content' => ['10 ways to improve remote team productivity', 'A publish-ready article with headings and a conclusion.'],
            'social-media' => ['Launch of a new cold-brew coffee blend', 'Caption options with hooks and relevant hashtags.'],
            'advertising' => ['Facebook ad for a productivity app', 'Punchy ad copy with a headline and a clear call to action.'],
            'email-marketing' => ['Black Friday sale announcement', 'A subject line and email body with one clear CTA.'],
            'ecommerce' => ['Stainless-steel insulated water bottle', 'A benefit-led product description with a feature list.'],
            'business' => ['Quarterly plan for a small marketing agency', 'A structured, prioritized business document.'],
            'academic' => ['The impact of remote work on productivity', 'A structured piece with a clear thesis and argument.'],
            'website-seo' => ['Homepage copy for a dog-grooming business', 'Search-optimized, readable website content.'],
            'creative-writing' => ['A short story about a lighthouse keeper', 'An original, vivid piece in the requested style.'],
            'personal-career' => ['Marketing manager with 5 years experience', 'A polished, results-focused draft ready to send.'],
            'health-fitness' => ['A 3-day beginner home workout plan', 'A clear, evidence-based, easy-to-follow plan.'],
            'real-estate' => ['3-bed family home with a large garden', 'A vivid, accurate listing that highlights key features.'],
            'entertainment' => ['Trivia questions about 90s movies', 'Fun, engaging content ready to share.'],
            'language' => ['Translate a product page into Spanish', 'A natural, accurate translation that keeps the tone.'],
            'marketing-strategy' => ['Go-to-market plan for a new SaaS app', 'A measurable plan across audience and channels.'],
            'customer-support' => ['Reply to a late-delivery complaint', 'A clear, empathetic response with next steps.'],
            'productivity' => ['Weekly plan to launch a side project', 'An organized, ready-to-use checklist or template.'],
            'sales' => ['Follow-up for a stalled software deal', 'Persuasive, buyer-focused copy with a next step.'],
            'hr-recruiting' => ['Job description for a senior developer', 'A professional, inclusive, well-structured draft.'],
            'podcast' => ['Episode on starting a first business', 'A conversational, well-paced script with segments.'],
            'ai-prompts' => ['A prompt to write cold sales emails', 'A reusable, copy-paste-ready prompt.'],
            'video' => ['A 30-second ad for a fitness app', 'A tight, hook-first script with clear direction.'],
            'image-design' => ['Logo brief for an eco-friendly brand', 'A clear, visual brief a designer can act on.'],
            default => ['A clear description of what you need', "A structured, ready-to-edit {$categoryName} result."],
        };

        return [
            'title' => 'Example',
            'input' => ['topic' => $topic],
            'output' => $output,
        ];
    }

    private function description(string $name, string $categoryName, string $categorySlug): string
    {
        return "Use {$name} to create {$this->categoryValueProp($categorySlug)} in seconds. Guided inputs, editable output, ready to use.";
    }

    /**
     * A short phrase describing what a category actually produces, used across the
     * tool's description, about section and metadata so all 412 pages carry
     * distinct, category-accurate copy instead of one boilerplate line.
     */
    private function categoryValueProp(string $slug): string
    {
        return match ($slug) {
            'development' => 'clean, working code and clear technical answers',
            'legal-finance' => 'clear, well-structured legal and financial documents',
            'blog-content' => 'engaging, SEO-ready blog content',
            'social-media' => 'scroll-stopping social media posts and captions',
            'advertising' => 'high-converting ad copy',
            'email-marketing' => 'emails that get opened and clicked',
            'ecommerce' => 'product copy that sells',
            'business' => 'structured, actionable business documents',
            'academic' => 'well-argued, properly structured academic writing',
            'website-seo' => 'search-optimized website content',
            'creative-writing' => 'original, vivid creative writing',
            'personal-career' => 'standout career and job-application materials',
            'health-fitness' => 'clear, evidence-based health and fitness content',
            'real-estate' => 'vivid, accurate property listings',
            'entertainment' => 'fun, engaging entertainment content',
            'language' => 'accurate, natural translations and language help',
            'marketing-strategy' => 'measurable, channel-aware marketing plans',
            'customer-support' => 'clear, empathetic customer support replies',
            'productivity' => 'organized, ready-to-use plans and templates',
            'sales' => 'persuasive, buyer-focused sales copy',
            'hr-recruiting' => 'professional, inclusive HR and recruiting content',
            'podcast' => 'conversational, well-paced podcast scripts',
            'ai-prompts' => 'reusable, copy-paste-ready AI prompts',
            'video' => 'tight, hook-first video scripts',
            'image-design' => 'clear, visual design and image briefs',
            default => 'polished, ready-to-use content',
        };
    }

    private function featuredSlugs(): array
    {
        return [
            'blog-article', 'instagram-caption', 'cold-email', 'product-description',
            'business-plan', 'code-generator', 'meta-seo', 'translator',
        ];
    }

    private function catalog(): array
    {
        return [
            'blog-content' => $this->tools([
                'blog-article', 'blog-intro', 'blog-outline', 'blog-conclusion', 'blog-section',
                'blog-ideas', 'article-rewriter', 'content-improver', 'paragraph-generator',
                'tldr-summary', 'explain-to-child', 'news-article', 'press-release',
                'ebook-generator', 'undetectable-rewriter', 'proofreading', 'rephrase',
                'text-expander', 'text-summarizer', 'rss-content', 'youtube-to-blog',
                'wiki-to-article', 'listicle-generator',
            ]),
            'social-media' => $this->tools([
                'instagram-caption', 'instagram-bio', 'instagram-hashtags', 'instagram-reel-script',
                'tiktok-script', 'tiktok-caption', 'tiktok-hook',
                'facebook-post', 'facebook-headline', 'facebook-video-script',
                'twitter-tweet', 'twitter-thread', 'twitter-bio', 'viral-tweet-ideas',
                'linkedin-post', 'linkedin-headline', 'linkedin-summary',
                'youtube-title', 'youtube-description', 'youtube-tags', 'youtube-to-blog-post',
                'pinterest-description', 'social-media-reply', 'ama-post',
                'trending-ideas', 'clickbait-title', 'social-bio', 'content-calendar',
                'hashtag-strategy', 'video-description', 'video-ideas',
                'newsletter-intro',
                'youtube-script', 'youtube-chapters', 'video-sales-letter',
                'explainer-video-script', 'video-hook-generator', 'shorts-script',
            ]),
            'advertising' => $this->tools([
                'google-ads-headline', 'google-ads-description', 'facebook-ad', 'instagram-ad',
                'linkedin-ad', 'youtube-ads-script', 'tiktok-ad-script', 'display-ad-copy',
                'native-ad-copy', 'aida', 'pas', 'before-after-bridge', 'ad-hook-generator',
                'retargeting-ad', 'product-ad-copy', 'app-install-ad', 'classified-ad',
                'cta-generator',
            ]),
            'email-marketing' => $this->tools([
                'cold-email', 'follow-up-email', 'welcome-email', 'newsletter-content',
                'sales-email', 'subject-lines', 'email-sequence', 'drip-campaign',
                'reactivation-email', 'thank-you-email', 'apology-email', 'event-invitation-email',
                'product-launch-email', 'testimonial-request-email', 'referral-email',
                'email-generator', 'winback-email', 'onboarding-email-sequence',
            ]),
            'ecommerce' => $this->tools([
                'product-description', 'product-features', 'product-name-ideas', 'product-title',
                'why-choose-product', 'customer-review', 'review-responder', 'amazon-listing',
                'shopify-product', 'product-comparison', 'upsell-message', 'flash-sale-copy',
                'abandoned-cart-email', 'order-confirmation-email', 'promo-sms',
                'discount-announcement', 'holiday-sale-copy',
                'app-store-description', 'etsy-listing', 'ebay-listing',
                'product-bullet-points', 'size-guide', 'image-alt-text',
            ]),
            'business' => $this->tools([
                'business-plan', 'swot-analysis', 'pitch-deck-script', 'meeting-minutes',
                'okr-generator', 'executive-summary', 'mission-statement', 'vision-statement',
                'company-bio', 'project-proposal', 'grant-proposal', 'market-analysis',
                'business-model-canvas', 'kpi-dashboard-copy', 'quarterly-report',
                'investor-update', 'job-description', 'sop-generator', 'risk-assessment',
                'stakeholder-email',
                'release-notes', 'changelog-generator', 'product-hunt-launch',
                'feature-announcement', 'push-notification-copy',
                'elevator-pitch', 'one-line-pitch', 'lean-canvas', 'yc-application',
            ]),
            'academic' => $this->tools([
                'research-outline', 'literature-review', 'research-question',
                'essay-outline', 'thesis-statement', 'argument-builder',
                'essay-writer', 'abstract-writer',
                'citation-generator', 'study-guide',
                'lesson-plan', 'quiz-generator', 'flashcards',
                'study-notes', 'discussion-post', 'lab-report', 'scholarship-essay',
                'academic-cover-letter', 'lecture-summary', 'multiple-choice-questions',
                'rubric-generator',
            ]),
            'development' => $this->tools([
                'code-generator', 'api-endpoint-generator', 'bug-fixer', 'unit-test',
                'api-docs', 'git-commit', 'code-explainer', 'regex-generator',
                'sql-query-generator', 'code-reviewer', 'code-optimizer', 'complexity-analyzer',
                'refactor-code', 'docstring-generator', 'readme-generator', 'dockerfile-generator',
                'cli-command-helper', 'algorithm-explainer', 'error-message-explainer',
            ]),
            'website-seo' => $this->tools([
                'meta-seo', 'landing-page-copy', 'faq-generator', 'schema-markup',
                'privacy-policy', 'terms-conditions', 'about-us-page', 'homepage-copy',
                'feature-page-copy', 'service-page-copy', 'seo-title-generator',
                'meta-description', 'keyword-cluster', 'content-brief',
                'internal-link-suggestions', 'robots-txt-generator', 'sitemap-plan',
                'local-seo-page', 'product-page-seo', 'paraphrasing-tool', 'seo-blog',
                'pricing-page-copy', 'people-also-ask', 'featured-snippet',
                'seo-content-audit', 'topic-cluster-planner',
            ]),
            'creative-writing' => $this->tools([
                'story-generator', 'song-lyrics', 'poem-writer', 'dialogue-writer',
                'script-writer', 'character-bio', 'plot-ideas', 'worldbuilding',
                'comedy-sketch', 'children-story', 'screenplay-scene', 'metaphor-generator',
                'writing-prompts', 'game-storyline',
            ]),
            'personal-career' => $this->tools([
                'personal-bio', 'cover-letter', 'resume-builder', 'resignation-letter',
                'salary-negotiation', 'linkedin-profile', 'personal-statement',
                'recommendation-letter', 'interview-answers', 'networking-message',
            ]),
            'health-fitness' => $this->tools([
                'workout-plan', 'meal-plan', 'recipe-generator', 'mental-health-tips',
                'habit-tracker-plan', 'sleep-improvement-plan', 'wellness-newsletter',
            ]),
            'real-estate' => $this->tools([
                'property-listing', 'neighborhood-guide', 'investment-analysis',
                'open-house-description', 'real-estate-email',
            ]),
            'entertainment' => $this->tools([
                'travel-planner', 'gift-ideas', 'trivia-questions', 'event-planner',
                'party-theme-ideas', 'movie-recommendations', 'game-night-ideas',
                'itinerary-generator',
            ]),
            'language' => $this->tools([
                'translator', 'grammar-checker', 'tone-changer', 'synonym-finder',
                'language-tutor', 'idiom-explainer', 'text-simplifier',
            ]),
            'marketing-strategy' => $this->tools([
                'brand-voice-guide', 'competitor-analysis', 'audience-profile',
                'gtm-strategy', 'positioning-statement', 'marketing-plan',
                'campaign-brief', 'buyer-persona', 'value-proposition', 'brand-story',
                'content-strategy', 'channel-strategy', 'survey-questions',
                'case-study',
                'business-name-generator', 'brand-name-generator', 'slogan-generator',
                'domain-name-ideas', 'startup-name-generator', 'tagline-generator',
            ]),
            'customer-support' => $this->tools([
                'ticket-reply', 'kb-article', 'onboarding-guide', 'chatbot-script',
                'refund-response', 'complaint-apology', 'troubleshooting-guide',
                'canned-responses',
            ]),
            'legal-finance' => $this->tools([
                'contract-summary', 'legal-privacy-policy', 'disclaimer',
                'fundraising-pitch', 'budget-plan', 'invoice-email', 'investment-memo',
                'nda-generator', 'cookie-policy', 'refund-policy', 'return-policy',
                'gdpr-statement', 'affiliate-disclosure', 'service-agreement', 'shipping-policy',
            ]),
            'productivity' => $this->tools([
                'pros-cons', 'smart-goals', 'action-plan', 'how-to-guide',
                'prompt-generator', 'checklist-generator', 'decision-matrix',
                'meeting-agenda', 'weekly-plan', 'project-timeline', 'brainstorming-ideas',
                'productivity-system',
                'email-reply-generator', 'meeting-summary-notes', 'daily-standup',
                'journaling-prompts',
            ]),
            'sales' => $this->tools([
                'cold-call-script', 'sales-proposal', 'objection-handling',
                'discovery-call-questions', 'linkedin-outreach-dm', 'sales-follow-up',
                'sales-pitch', 'upsell-crosssell-script', 'rfp-response', 'case-study-interview',
            ]),
            'hr-recruiting' => $this->tools([
                'job-ad', 'interview-questions-hiring', 'offer-letter',
                'candidate-rejection-email', 'performance-review', 'employee-onboarding-email',
                'employee-handbook-section', 'promotion-announcement', 'reference-check-questions',
                'recruiter-outreach',
            ]),
            'podcast' => $this->tools([
                'podcast-outline', 'podcast-show-notes', 'podcast-title-ideas',
                'podcast-description', 'podcast-interview-questions', 'podcast-episode-summary',
            ]),
            'ai-prompts' => $this->tools([
                'midjourney-prompt', 'stable-diffusion-prompt', 'dalle-prompt',
                'chatgpt-prompt-optimizer', 'system-prompt-generator', 'negative-prompt-generator',
            ]),
            'video' => $this->tools([
                'video-script-outline', 'video-storyboard', 'voiceover-script',
                'video-shot-list', 'b-roll-ideas', 'video-thumbnail-text',
                'video-intro-script', 'video-outro-cta', 'webinar-script',
                'product-demo-script', 'course-lesson-script', 'testimonial-video-questions',
                'video-editing-brief',
                'live-stream-script', 'video-series-plan', 'youtube-community-post',
                'video-caption-file', 'tutorial-script', 'video-ad-storyboard',
                'video-content-calendar', 'unboxing-script', 'video-chapter-summary',
            ]),
            'image-design' => $this->tools([
                'image-caption', 'carousel-copy', 'infographic-outline', 'infographic-copy',
                'logo-design-brief', 'graphic-design-brief', 'moodboard-concept',
                'color-palette-ideas', 'photo-shoot-brief', 'product-photography-brief',
                'stock-photo-keywords', 'meme-caption',
                'presentation-slide-copy', 'banner-copy', 'packaging-copy', 'icon-set-brief',
                'brand-style-guide', 'ui-microcopy', 'poster-copy', 'image-concept-brief',
                'flyer-copy', 'album-cover-brief', 'typography-pairing',
            ]),
        ];
    }

    private function tools(array $slugs): array
    {
        return array_map(fn (string $slug): array => [
            'slug' => $slug,
            'name' => $this->nameFor($slug),
        ], $slugs);
    }

    private function nameFor(string $slug): string
    {
        $names = [
            'tldr-summary' => 'TL;DR Summarization',
            'rss-content' => 'Content from RSS Feed',
            'youtube-to-blog' => 'YouTube to Blog Post',
            'youtube-to-blog-post' => 'YouTube Video to Blog',
            'twitter-tweet' => 'X / Twitter Tweet',
            'twitter-thread' => 'X / Twitter Thread',
            'twitter-bio' => 'X / Twitter Bio Writer',
            'linkedin-headline' => 'LinkedIn Headline Writer',
            'instagram-bio' => 'Instagram Bio Writer',
            'tiktok-hook' => 'TikTok Hook Writer',
            'email-generator' => 'Email Campaign Generator',
            'winback-email' => 'Win-Back Email',
            'case-study' => 'Case Study Generator',
            'cta-generator' => 'CTA Button Text Generator',
            'listicle-generator' => 'Listicle Generator',
            'seo-blog' => 'SEO-Optimized Blog Post',
            'paraphrasing-tool' => 'Paraphrasing Tool',
            'newsletter-intro' => 'Newsletter Intro',
            'product-title' => 'Product Title Optimizer',
            'order-confirmation-email' => 'Order Confirmation Email',
            'promo-sms' => 'Promotional SMS',
            'discount-announcement' => 'Discount Announcement',
            'holiday-sale-copy' => 'Holiday Sale Copy',
            'api-endpoint-generator' => 'API Endpoint Generator',
            'code-reviewer' => 'Code Reviewer',
            'code-optimizer' => 'Code Optimizer',
            'complexity-analyzer' => 'Complexity Analyzer',
            'research-outline' => 'Research Outline Generator',
            'literature-review' => 'Literature Review Helper',
            'research-question' => 'Research Question Generator',
            'argument-builder' => 'Argument Builder',
            'abstract-writer' => 'Abstract Writer',
            'grammar-checker' => 'Grammar Checker',
            'study-guide' => 'Study Guide Generator',
            'ama-post' => 'AMA Post',
            'aida' => 'AIDA Copywriting Formula',
            'pas' => 'PAS Copywriting Formula',
            'okr-generator' => 'OKR Generator',
            'api-docs' => 'API Documentation',
            'readme-generator' => 'README Generator',
            'gtm-strategy' => 'Go-to-Market Strategy',
            'kb-article' => 'Knowledge Base Article',
            'dalle-prompt' => 'DALL·E Prompt',
            'chatgpt-prompt-optimizer' => 'ChatGPT Prompt Optimizer',
            'rfp-response' => 'RFP Response',
            'nda-generator' => 'NDA Generator',
            'gdpr-statement' => 'GDPR Statement',
            'yc-application' => 'YC Application Answers',
            'linkedin-outreach-dm' => 'LinkedIn Outreach DM',
            'seo-content-audit' => 'SEO Content Audit',
            'youtube-script' => 'YouTube Script',
            'youtube-chapters' => 'YouTube Chapters',
            'ebay-listing' => 'eBay Listing',
            'upsell-crosssell-script' => 'Upsell & Cross-Sell Script',
            'interview-questions-hiring' => 'Interview Questions (Hiring)',
            'shorts-script' => 'Shorts / Reels Script',
            'meeting-summary-notes' => 'Meeting Summary From Notes',
            'one-line-pitch' => 'One-Line Pitch',
            'podcast-outline' => 'Podcast Episode Outline',
            'daily-standup' => 'Daily Standup Update',
            'recruiter-outreach' => 'Recruiter Outreach Message',
            'b-roll-ideas' => 'B-Roll Ideas',
            'video-outro-cta' => 'Video Outro & CTA',
            'testimonial-video-questions' => 'Testimonial Video Questions',
            'youtube-community-post' => 'YouTube Community Post',
            'video-caption-file' => 'Video Captions & Subtitles',
            'ui-microcopy' => 'UI Microcopy',
        ];

        return $names[$slug] ?? Str::headline($slug);
    }

    private function stageTags(): array
    {
        return [
            'value-proposition' => ['stage' => 'awareness', 'pairs_with' => 'landing-page-copy'],
            'brand-story' => ['stage' => 'awareness', 'pairs_with' => 'value-proposition'],
            'press-release' => ['stage' => 'awareness', 'pairs_with' => 'brand-story'],
            'competitor-analysis' => ['stage' => 'consideration', 'pairs_with' => 'value-proposition'],
            'landing-page-copy' => ['stage' => 'consideration', 'pairs_with' => 'facebook-ad'],
            'case-study' => ['stage' => 'consideration', 'pairs_with' => 'landing-page-copy'],
            'facebook-ad' => ['stage' => 'conversion', 'pairs_with' => 'landing-page-copy'],
            'google-ads-headline' => ['stage' => 'conversion', 'pairs_with' => 'facebook-ad'],
            'cta-generator' => ['stage' => 'conversion', 'pairs_with' => 'landing-page-copy'],
            'email-generator' => ['stage' => 'retention', 'pairs_with' => 'facebook-ad'],
            'abandoned-cart-email' => ['stage' => 'retention', 'pairs_with' => 'email-generator'],
            'winback-email' => ['stage' => 'retention', 'pairs_with' => 'email-generator'],
            'blog-article' => ['content_type' => 'articles'],
            'blog-outline' => ['content_type' => 'articles'],
            'listicle-generator' => ['content_type' => 'articles'],
            'seo-blog' => ['content_type' => 'seo'],
            'meta-seo' => ['content_type' => 'seo'],
            'faq-generator' => ['content_type' => 'seo'],
            'article-rewriter' => ['content_type' => 'rewriting'],
            'content-improver' => ['content_type' => 'rewriting', 'writing_stage' => 'polish'],
            'paraphrasing-tool' => ['content_type' => 'rewriting'],
            'linkedin-post' => ['content_type' => 'social'],
            'twitter-thread' => ['content_type' => 'social'],
            'newsletter-intro' => ['content_type' => 'social'],
            'product-description' => ['ecom_stage' => 'product-listing'],
            'amazon-listing' => ['ecom_stage' => 'product-listing'],
            'product-title' => ['ecom_stage' => 'product-listing'],
            'review-responder' => ['ecom_stage' => 'product-listing'],
            'abandoned-cart-email' => ['ecom_stage' => 'email-retention'],
            'winback-email' => ['ecom_stage' => 'email-retention'],
            'order-confirmation-email' => ['ecom_stage' => 'email-retention'],
            'upsell-message' => ['ecom_stage' => 'email-retention'],
            'flash-sale-copy' => ['ecom_stage' => 'promotions'],
            'promo-sms' => ['ecom_stage' => 'promotions'],
            'discount-announcement' => ['ecom_stage' => 'promotions'],
            'holiday-sale-copy' => ['ecom_stage' => 'promotions'],
            'code-generator' => ['dev_category' => 'generate'],
            'api-endpoint-generator' => ['dev_category' => 'generate'],
            'regex-generator' => ['dev_category' => 'generate'],
            'sql-query-generator' => ['dev_category' => 'generate'],
            'bug-fixer' => ['dev_category' => 'debug'],
            'code-explainer' => ['dev_category' => 'debug'],
            'error-message-explainer' => ['dev_category' => 'debug'],
            'code-reviewer' => ['dev_category' => 'optimize'],
            'code-optimizer' => ['dev_category' => 'optimize'],
            'complexity-analyzer' => ['dev_category' => 'optimize'],
            'api-docs' => ['dev_category' => 'document'],
            'unit-test' => ['dev_category' => 'document'],
            'git-commit' => ['dev_category' => 'document'],
            'readme-generator' => ['dev_category' => 'document'],
            'research-outline' => ['writing_stage' => 'research'],
            'literature-review' => ['writing_stage' => 'research'],
            'research-question' => ['writing_stage' => 'research'],
            'essay-outline' => ['writing_stage' => 'outline'],
            'thesis-statement' => ['writing_stage' => 'outline'],
            'argument-builder' => ['writing_stage' => 'outline'],
            'essay-writer' => ['writing_stage' => 'write'],
            'paragraph-generator' => ['writing_stage' => 'write'],
            'abstract-writer' => ['writing_stage' => 'write'],
            'grammar-checker' => ['writing_stage' => 'polish'],
            'citation-generator' => ['writing_stage' => 'polish'],
            'study-guide' => ['writing_stage' => 'polish'],
        ];
    }
}
