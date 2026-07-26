<?php

namespace Addons\AiRepurposer\Services;

use Addons\AiRepurposer\Models\RpJob;
use Addons\AiRepurposer\Models\RpOutput;
use App\Services\AiService;
use Illuminate\Support\Facades\Log;

class RepurposeService
{
    public const FORMATS = [
        'blog_post'          => ['label' => 'Blog Post',              'icon' => 'ti-file-text',       'min_words'  => 800],
        'twitter_thread'     => ['label' => 'X / Twitter Thread',     'icon' => 'ti-brand-twitter',   'min_words'  => 0],
        'linkedin_article'   => ['label' => 'LinkedIn Article',       'icon' => 'ti-brand-linkedin',  'min_words'  => 400],
        'email_newsletter'   => ['label' => 'Email Newsletter',       'icon' => 'ti-mail',            'min_words'  => 200],
        'tiktok_script'      => ['label' => 'TikTok / Reels Script',  'icon' => 'ti-brand-tiktok',    'min_words'  => 0],
        'podcast_show_notes' => ['label' => 'Podcast Show Notes',     'icon' => 'ti-microphone',      'min_words'  => 150],
        'key_quotes'         => ['label' => 'Key Quotes',             'icon' => 'ti-quote',           'min_words'  => 0],
        'chapter_markers'    => ['label' => 'Chapter Markers',        'icon' => 'ti-list-numbers',    'min_words'  => 0],
    ];

    public function __construct(private AiService $ai) {}

    public function generateFormat(string $transcript, string $title, string $format, array $options = []): string
    {
        // The addon's own ai_model setting defaults to null, so this fallback is what most
        // installs actually generate with — it has to be a slug the catalog still carries.
        $model = addon_setting('ai-repurposer', 'ai_model') ?: settings('default_ai_model', config('ai.fallback_model'));
        $truncated = $this->truncateTranscript($transcript, $format);

        $prompt = $this->buildPrompt($truncated, $title, $format, $options);

        return $this->ai->generate($prompt, $model);
    }

    public function generateAll(RpJob $job, array $formats): void
    {
        $title = $job->source_title ?? __('Untitled');

        foreach ($formats as $format) {
            try {
                $content = $this->generateFormat(
                    $job->transcript ?? '',
                    $title,
                    $format,
                    ['chapters' => $job->chapters ?? []]
                );

                $wordCount = str_word_count(strip_tags($content));

                RpOutput::updateOrCreate(
                    ['rp_job_id' => $job->id, 'format' => $format],
                    [
                        'content'    => $content,
                        'word_count' => $wordCount,
                        'user_id'    => $job->user_id,
                        'metadata'   => $this->buildMetadata($format, $content),
                    ]
                );

                $completed = array_merge($job->formats_completed ?? [], [$format]);
                $job->update(['formats_completed' => array_unique($completed)]);
            } catch (\Throwable $e) {
                Log::warning("Repurpose format {$format} failed for job {$job->id}: " . $e->getMessage());
            }
        }
    }

    public function regenerateFormat(RpJob $job, string $format): string
    {
        $title = $job->source_title ?? __('Untitled');

        $content = $this->generateFormat(
            $job->transcript ?? '',
            $title,
            $format,
            ['chapters' => $job->chapters ?? []]
        );

        $wordCount = str_word_count(strip_tags($content));

        RpOutput::updateOrCreate(
            ['rp_job_id' => $job->id, 'format' => $format],
            [
                'content'    => $content,
                'word_count' => $wordCount,
                'user_id'    => $job->user_id,
                'metadata'   => $this->buildMetadata($format, $content),
            ]
        );

        return $content;
    }

    protected function truncateTranscript(string $transcript, string $format): string
    {
        $maxChars = match ($format) {
            'twitter_thread'     => 6000,
            'tiktok_script'      => 4000,
            'key_quotes'         => 8000,
            'chapter_markers'    => 4000,
            default              => 12000,
        };

        return mb_strlen($transcript) > $maxChars
            ? mb_substr($transcript, 0, $maxChars) . '…'
            : $transcript;
    }

    protected function buildPrompt(string $transcript, string $title, string $format, array $options = []): string
    {
        $base = "Title: {$title}\n\nContent:\n{$transcript}\n\n---\n\n";

        return $base . match ($format) {
            'blog_post' => sprintf(
                "Write a comprehensive, engaging blog post based on the content above.\n"
                . "Include a compelling intro, clear H2/H3 headings, key insights, and a strong conclusion.\n"
                . "Minimum %d words. Format as Markdown.",
                addon_setting('ai-repurposer', 'blog_post_min_words', 800)
            ),

            'twitter_thread' => sprintf(
                "Write a Twitter/X thread based on the content above. Make it engaging and punchy.\n"
                . "Format: number each tweet (1/, 2/, 3/...) on new lines.\n"
                . "Max %d tweets. Each tweet max 280 characters.\n"
                . "End with a CTA encouraging engagement.",
                addon_setting('ai-repurposer', 'twitter_thread_length', 10)
            ),

            'linkedin_article' => "Write a professional LinkedIn article based on the content above.\n"
                . "Use a hook opening that grabs attention, clear sections with subheadings, professional tone.\n"
                . "Include 3-5 relevant hashtags at the end. Format as Markdown.",

            'email_newsletter' => "Write an email newsletter based on the content above.\n"
                . "Start with 3 subject line options (Subject A:, Subject B:, Subject C:).\n"
                . "Then write the newsletter body: conversational tone, 2-3 key takeaways, clear CTA.\n"
                . "Include the subject lines first, then the body.",

            'tiktok_script' => "Write a 60-second TikTok/Reels video script based on the content above.\n"
                . "Format:\n"
                . "HOOK (first 3 seconds — grab attention):\n"
                . "BODY (key points as quick, energetic bullets):\n"
                . "CTA (call to action at the end):",

            'podcast_show_notes' => "Write podcast show notes for this episode based on the content above.\n"
                . "Include: Episode Summary (2 paragraphs), Key Topics Covered (bullet list),\n"
                . "Key Takeaways (3-5 bullets), Timestamps (estimate 5-8 chapters from content flow).\n"
                . "Format as Markdown.",

            'key_quotes' => "Extract 8-10 powerful, quotable statements from the content above.\n"
                . "Format: one quote per line, in quotation marks.\n"
                . "Only extract direct, impactful statements that stand alone.",

            'chapter_markers' => isset($options['chapters']) && ! empty($options['chapters'])
                ? $this->buildChaptersPrompt($options['chapters'])
                : "Create YouTube chapter markers based on the content above.\n"
                    . "Format: HH:MM:SS - Chapter Title (one per line).\n"
                    . "Start with 00:00:00 - Introduction. Create 6-10 logical chapters.",
        };
    }

    protected function buildChaptersPrompt(array $chapters): string
    {
        $chapterLines = collect($chapters)
            ->map(fn ($c) => gmdate('H:i:s', $c['start_seconds']) . ' - ' . $c['title'])
            ->implode("\n");

        return "Refine and format these YouTube chapter markers. Fill in gaps between existing chapters.\n"
            . "Ensure the first chapter starts at 00:00:00.\n"
            . "Format: HH:MM:SS - Chapter Title (one per line).\n\n"
            . "Existing chapters:\n{$chapterLines}\n\n"
            . "Now produce the final chapter list:";
    }

    public function buildMetadata(string $format, string $content): array
    {
        return match ($format) {
            'twitter_thread' => [
                'tweet_count' => substr_count($content, "\n") + 1,
            ],
            'blog_post' => [
                'reading_time' => max(1, (int) ceil(str_word_count(strip_tags($content)) / 200)),
            ],
            'tiktok_script' => [
                'estimated_seconds' => 60,
            ],
            default => [],
        };
    }
}
