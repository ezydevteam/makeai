<?php

declare(strict_types=1);

namespace Addons\AiImagePro\Services;

use App\Models\Faq;
use App\Models\FaqCategory;
use Illuminate\Support\Facades\Schema;

/**
 * Resolves every piece of copy on the public landing page.
 *
 * Two jobs, and the second is the important one:
 *
 * 1. Read the admin's setting, whatever it is.
 * 2. Fall back to a complete, well-written default when the operator has not set
 *    it — so a fresh install ships a landing page that already looks finished.
 *    An empty hero heading on a page a buyer just installed is the difference
 *    between "premium product" and "unfinished template".
 *
 * The defaults live here rather than in the manifest because the manifest's
 * `default` is a scalar, and half of this content is structured lists. The
 * seeder persists these same values so the admin can edit them from the panel;
 * this class guarantees the page renders correctly even if it never ran.
 */
class LandingContentService
{
    private const SLUG = OperationRegistry::SLUG;

    /**
     * The shipped copy for every scalar landing key — pure constants, NO setting
     * reads.
     *
     * This separation matters: content() resolves setting-first *with these behind
     * it*, while the seeder persists these verbatim. If the seeder derived its values
     * from content() instead, it would be reading the very settings it is meant to be
     * seeding — and would happily re-persist a stale cached value as though it were
     * the shipped default.
     *
     * @return array<string, string>
     */
    public function defaultText(): array
    {
        return [
            'landing_hero_badge' => 'Free to try — no signup required',
            'landing_hero_heading' => 'AI image generator: create images and photos from text',
            'landing_hero_subheading' => 'Describe anything you can imagine and watch it become an image in seconds. Then upscale it, cut out the background or resize it — all on this page.',

            'landing_examples_heading' => 'Get started with',

            'landing_features_heading' => 'Built for every creative project',
            'landing_features_subheading' => 'Everything you need to go from an idea to a finished, usable image.',

            'landing_usecases_heading' => 'Generate images for any project',
            'landing_usecases_subheading' => 'One generator, versatile enough for whatever you are working on.',

            'landing_benefits_heading' => 'An AI image generator that does more',
            'landing_benefits_subheading' => 'Not just a text-to-image box — a complete editing suite around it.',

            'landing_steps_heading' => 'How it works',
            'landing_steps_subheading' => 'Professional-quality visuals in three simple steps.',

            'landing_about_heading' => '',
            'landing_about_body' => '',

            'landing_faq_heading' => 'Frequently asked questions',

            'landing_cta_heading' => 'Bring your ideas to life',
            'landing_cta_subheading' => 'Turn a sentence into a finished image. Start creating in seconds — no credit card needed.',
            'landing_cta_button_text' => 'Generate your image',

            'studio_heading' => 'What can I do for you?',
            'studio_subheading' => 'Hi, there!',

            'seo_title' => 'AI Image Generator — Create Images from Text',
            'seo_description' => 'Generate images from text with AI, then upscale, remove backgrounds, resize and edit them — free to try, no signup required.',
        ];
    }

    /**
     * The shipped structured lists, keyed by setting. Pure constants — see defaultText().
     *
     * @return array<string, array<int, array<string, string>>>
     */
    public function defaultLists(): array
    {
        return [
            'landing_examples' => $this->defaultExamples(),
            'landing_features' => $this->defaultFeatures(),
            'landing_usecases' => $this->defaultUsecases(),
            'landing_benefits' => $this->defaultBenefits(),
            'landing_steps' => $this->defaultSteps(),
        ];
    }

    /**
     * Scalar copy, resolved setting-first with the shipped default behind it.
     *
     * @return array<string, mixed>
     */
    public function content(): array
    {
        return [
            // 'default' follows the site container; 'boxed' is a narrower centred column.
            'pageWidth' => $this->pageWidth(),
            // Purple→blue gradient on headings and primary buttons.
            'gradient' => (bool) addon_setting(self::SLUG, 'landing_gradient_enabled', false),

            'showBreadcrumb' => (bool) addon_setting(self::SLUG, 'landing_show_breadcrumb', true),

            'heroBadge' => $this->text('landing_hero_badge'),
            'heroHeading' => $this->text('landing_hero_heading'),
            'heroSubheading' => $this->text('landing_hero_subheading'),

            // Per-section on/off switches. Each gates its section on the public page,
            // on top of the "hide when the section has no content" rule the frontend
            // already applies — a section shows only when it is enabled AND populated.
            'examplesEnabled' => $this->sectionEnabled('landing_examples_enabled'),
            'examplesHeading' => $this->text('landing_examples_heading'),

            'featuresEnabled' => $this->sectionEnabled('landing_features_enabled'),
            'featuresHeading' => $this->text('landing_features_heading'),
            'featuresSubheading' => $this->text('landing_features_subheading'),

            'usecasesEnabled' => $this->sectionEnabled('landing_usecases_enabled'),
            'usecasesHeading' => $this->text('landing_usecases_heading'),
            'usecasesSubheading' => $this->text('landing_usecases_subheading'),

            'benefitsEnabled' => $this->sectionEnabled('landing_benefits_enabled'),
            'benefitsHeading' => $this->text('landing_benefits_heading'),
            'benefitsSubheading' => $this->text('landing_benefits_subheading'),

            'stepsEnabled' => $this->sectionEnabled('landing_steps_enabled'),
            'stepsHeading' => $this->text('landing_steps_heading'),
            'stepsSubheading' => $this->text('landing_steps_subheading'),

            'aboutEnabled' => $this->sectionEnabled('landing_about_enabled'),
            'aboutHeading' => $this->text('landing_about_heading'),
            'aboutBody' => $this->text('landing_about_body'),

            'faqEnabled' => (bool) addon_setting(self::SLUG, 'landing_faq_enabled', true),
            'faqHeading' => $this->text('landing_faq_heading'),

            'ctaEnabled' => $this->sectionEnabled('landing_cta_enabled'),
            'ctaHeading' => $this->text('landing_cta_heading'),
            'ctaSubheading' => $this->text('landing_cta_subheading'),
            'ctaButtonText' => $this->text('landing_cta_button_text'),
            'ctaButtonLink' => (string) (addon_setting(self::SLUG, 'landing_cta_button_link') ?? ''),
        ];
    }

    /**
     * Example prompt cards — the "Get started with" grid, shared by the landing
     * page and the Studio's empty state.
     *
     * @return array<int, array<string, string>>
     */
    public function examples(): array
    {
        return $this->list('landing_examples', $this->defaultExamples());
    }

    /** @return array<int, array<string, string>> */
    private function defaultExamples(): array
    {
        return [
            [
                'title' => 'Photorealistic portrait',
                'description' => 'A lifelike portrait with natural light and shallow depth of field.',
                'image' => '',
                'prompt' => 'A photorealistic portrait of a golden retriever wearing sunglasses on a sunny beach, shallow depth of field, warm afternoon light',
            ],
            [
                'title' => 'Product shot',
                'description' => 'Clean studio product photography on a seamless background.',
                'image' => '',
                'prompt' => 'Studio product photograph of a minimalist ceramic coffee cup on a seamless light grey background, soft shadows, commercial lighting',
            ],
            [
                'title' => 'Social media ad',
                'description' => 'A vibrant, scroll-stopping creative for Instagram or Facebook.',
                'image' => '',
                'prompt' => 'A vibrant summer sale social media ad for a fashion brand, bold typography space, tropical colours, high contrast',
            ],
            [
                'title' => 'Brand logo concept',
                'description' => 'A modern, minimal mark for a specialty brand.',
                'image' => '',
                'prompt' => 'A modern minimalist logo concept for a specialty coffee brand, geometric, single colour, clean vector look',
            ],
            [
                'title' => 'Digital illustration',
                'description' => 'Stylised artwork with rich colour and painterly texture.',
                'image' => '',
                'prompt' => 'A whimsical digital illustration of a floating island city at sunset, painterly texture, rich colour palette',
            ],
            [
                'title' => 'Interior visualisation',
                'description' => 'An architectural interior with realistic materials and light.',
                'image' => '',
                'prompt' => 'A bright Scandinavian living room interior, natural oak floors, large windows, soft morning light, architectural photography',
            ],
        ];
    }

    /** @return array<int, array<string, string>> */
    public function features(): array
    {
        return $this->list('landing_features', $this->defaultFeatures());
    }

    /** @return array<int, array<string, string>> */
    private function defaultFeatures(): array
    {
        return [
            [
                'title' => 'Text to image in seconds',
                'body' => 'Describe what you want in plain language and get a finished image back. No prompt engineering degree required — write it the way you would say it.',
                'image' => '',
                'cta_text' => 'Try it now',
                'cta_link' => '',
                'cta_icon' => 'ti ti-sparkles',
            ],
            [
                'title' => 'Professional-grade results',
                'body' => 'High-resolution output suitable for print, web and social. Every image is yours to use, and the upscaler takes it further when you need more detail.',
                'image' => '',
                'cta_text' => 'Start creating',
                'cta_link' => '',
                'cta_icon' => 'ti ti-arrows-maximize',
            ],
            [
                'title' => 'A full editing suite, built in',
                'body' => 'Cut out the background, erase an object, expand the canvas, restyle it, or just crop and compress it. You never have to leave the page or open another tool.',
                'image' => '',
                'cta_text' => 'See the tools',
                'cta_link' => '',
                'cta_icon' => 'ti ti-adjustments',
            ],
            [
                'title' => 'Free tools, no account needed',
                'body' => 'Resize, crop, convert and compress run right in the browser session at no cost. Try the generator free too — sign up only when you want to keep your work.',
                'image' => '',
                'cta_text' => 'Get started',
                'cta_link' => '',
                'cta_icon' => 'ti ti-rocket',
            ],
        ];
    }

    /** @return array<int, array<string, string>> */
    public function usecases(): array
    {
        return $this->list('landing_usecases', $this->defaultUsecases());
    }

    /** @return array<int, array<string, string>> */
    private function defaultUsecases(): array
    {
        return [
            [
                'icon' => 'ti ti-speakerphone',
                'title' => 'Marketing & advertising',
                'body' => 'Create scroll-stopping visuals for campaigns, social posts, display ads and landing pages — without a stock photo subscription.',
            ],
            [
                'icon' => 'ti ti-article',
                'title' => 'Content creation',
                'body' => 'Generate original featured images, blog illustrations, thumbnails and header art that actually match what you wrote.',
            ],
            [
                'icon' => 'ti ti-palette',
                'title' => 'Design & branding',
                'body' => 'Explore logo directions, product concepts, packaging mockups and moodboards in minutes instead of days.',
            ],
            [
                'icon' => 'ti ti-heart',
                'title' => 'Personal projects',
                'body' => 'Make personalised gifts, prints, invitations and artwork — bring an idea in your head into something you can hold.',
            ],
        ];
    }

    /** @return array<int, array<string, string>> */
    public function benefits(): array
    {
        return $this->list('landing_benefits', $this->defaultBenefits());
    }

    /** @return array<int, array<string, string>> */
    private function defaultBenefits(): array
    {
        return [
            [
                'icon' => 'ti ti-wand',
                'title' => 'Beyond basic generation',
                'body' => 'Most generators stop at a single image. This one hands you the whole toolkit — upscale, background removal, inpainting, expand and more.',
            ],
            [
                'icon' => 'ti ti-cpu',
                'title' => 'Powered by leading models',
                'body' => 'Choose the model that fits the job. Swap between them freely and use the one that gives you the look you are after.',
            ],
            [
                'icon' => 'ti ti-bolt',
                'title' => 'Fast enough to iterate',
                'body' => 'Results in seconds, so you can try ten directions in the time it used to take to brief one.',
            ],
        ];
    }

    /** @return array<int, array<string, string>> */
    public function steps(): array
    {
        return $this->list('landing_steps', $this->defaultSteps());
    }

    /** @return array<int, array<string, string>> */
    private function defaultSteps(): array
    {
        return [
            [
                'title' => 'Describe your image',
                'body' => 'Write a prompt in plain language. Add a style, an aspect ratio and a reference image if you have one.',
                'image' => '',
            ],
            [
                'title' => 'Generate',
                'body' => 'Pick a model and hit generate. Get your image back in seconds — not quite right? Adjust the prompt and go again.',
                'image' => '',
            ],
            [
                'title' => 'Refine and download',
                'body' => 'Upscale it, remove the background, crop or compress it, then download. Everything stays on the same page.',
                'image' => '',
            ],
        ];
    }

    /**
     * FAQs come from the site's own FAQ manager, filtered to the category the
     * admin picked — so a buyer maintains one FAQ list, not two.
     *
     * @return array<int, array<string, string>>
     */
    public function faqs(): array
    {
        if (! (bool) addon_setting(self::SLUG, 'landing_faq_enabled', true)) {
            return [];
        }

        $categoryId = (int) addon_setting(self::SLUG, 'landing_faq_category_id', 0);

        if ($categoryId <= 0 || ! Schema::hasTable('faqs')) {
            return [];
        }

        return Faq::query()
            ->where('category_id', $categoryId)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get(['question', 'answer'])
            ->map(fn (Faq $faq) => [
                'question' => (string) $faq->question,
                'answer' => (string) $faq->answer,
            ])
            ->all();
    }

    /**
     * The FAQ categories the admin can choose from, for the settings dropdown.
     *
     * @return array<int, array<string, mixed>>
     */
    public function faqCategories(): array
    {
        if (! Schema::hasTable('faq_categories')) {
            return [];
        }

        return FaqCategory::query()
            ->withCount('faqs')
            ->orderBy('sort_order')
            ->get()
            ->map(fn (FaqCategory $category) => [
                'id' => (int) $category->id,
                'name' => (string) $category->name,
                'faqs_count' => (int) ($category->faqs_count ?? 0),
            ])
            ->all();
    }

    /** @return array<string, string> */
    public function seo(): array
    {
        return [
            'title' => $this->text('seo_title', 'AI Image Generator — Create Images from Text'),
            'description' => $this->text(
                'seo_description',
                'Generate images from text with AI, then upscale, remove backgrounds, resize and edit them — free to try, no signup required.',
            ),
        ];
    }

    public function studioHeading(): string
    {
        return $this->text('studio_heading', 'What can I do for you?');
    }

    public function studioSubheading(): string
    {
        return $this->text('studio_subheading', 'Hi, there!');
    }

    /**
     * A section on/off switch, defaulting to on. A section the operator never
     * touched must render, so a fresh install ships the full page.
     */
    private function sectionEnabled(string $key): bool
    {
        return (bool) addon_setting(self::SLUG, $key, true);
    }

    /**
     * Only the two widths the page actually implements — an unknown value from a
     * hand-edited settings row must not produce a broken layout class.
     */
    private function pageWidth(): string
    {
        $width = (string) addon_setting(self::SLUG, 'landing_page_width', 'default');

        return in_array($width, ['default', 'boxed'], true) ? $width : 'default';
    }

    /**
     * A setting the admin cleared (empty string) falls back to the shipped copy —
     * a blank hero heading is never what anyone wanted. The exception is a key whose
     * shipped default is itself empty (About), which stays empty and simply hides
     * that section.
     */
    private function text(string $key): string
    {
        $default = $this->defaultText()[$key] ?? '';
        $value = addon_setting(self::SLUG, $key);

        if (! is_string($value) || trim($value) === '') {
            return $default;
        }

        return $value;
    }

    /**
     * A structured list setting, with the shipped default behind it.
     *
     * @param  array<int, array<string, string>>  $default
     * @return array<int, array<string, string>>
     */
    private function list(string $key, array $default): array
    {
        $value = addon_setting(self::SLUG, $key);

        if (is_string($value)) {
            $value = json_decode($value, true);
        }

        if (! is_array($value) || $value === []) {
            return $default;
        }

        // Drop rows the admin left completely blank rather than rendering empty cards.
        $rows = array_values(array_filter(
            $value,
            fn ($row) => is_array($row) && $this->rowHasContent($row),
        ));

        return $rows === [] ? $default : $this->normalizeRows($rows);
    }

    /**
     * Every cell of every row is guaranteed to reach the frontend as a string.
     *
     * Laravel's ConvertEmptyStringsToNull middleware rewrites every blank field of the
     * admin's settings form to NULL before it is stored — so the moment an operator saves
     * a repeater with an empty image or description, that key comes back as null rather
     * than ''. The Vue components type these as optional strings, and `withDefaults` only
     * substitutes for `undefined`, never `null`, so a null sailed through and blew up on
     * `.trim()`. Normalising here fixes it for every consumer at once instead of making
     * each component defend itself.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, string>>
     */
    private function normalizeRows(array $rows): array
    {
        return array_map(
            fn (array $row) => array_map(
                fn ($cell) => is_scalar($cell) ? (string) $cell : '',
                $row,
            ),
            $rows,
        );
    }

    /** @param  array<string, mixed>  $row */
    private function rowHasContent(array $row): bool
    {
        foreach ($row as $cell) {
            if (is_scalar($cell) && trim((string) $cell) !== '') {
                return true;
            }
        }

        return false;
    }
}
