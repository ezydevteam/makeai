<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class HomepageBuilderRequest extends FormRequest
{
    private const SECTION_TYPES = [
        'hero',
        'features',
        'tools_showcase',
        'how_it_works',
        'pricing',
        'testimonials',
        'faq',
        'stats_bar',
        'cta_banner',
        'latest_posts',
        'newsletter',
        'integrations',
        'custom_html',
    ];

    public function authorize(): bool
    {
        return auth('admin')->check();
    }

    public function rules(): array
    {
        return [
            'sections' => ['required', 'array'],
            'sections.*.id' => ['required', 'string', 'max:80'],
            'sections.*.type' => ['required', 'string', Rule::in(self::SECTION_TYPES)],
            'sections.*.enabled' => ['required', 'boolean'],
            'sections.*.core' => ['required', 'boolean'],
            'sections.*.config' => ['required', 'array'],
            'sections.*.config.headline' => ['nullable', 'string', 'max:300'],
            'sections.*.config.subheadline' => ['nullable', 'string', 'max:500'],
            'sections.*.config.title' => ['nullable', 'string', 'max:300'],
            'sections.*.config.subtitle' => ['nullable', 'string', 'max:500'],
            'sections.*.config.primary_cta_text' => ['nullable', 'string', 'max:100'],
            'sections.*.config.primary_cta_link' => ['nullable', 'string', 'max:500'],
            'sections.*.config.secondary_cta_text' => ['nullable', 'string', 'max:100'],
            'sections.*.config.secondary_cta_link' => ['nullable', 'string', 'max:500'],
            'sections.*.config.cta_text' => ['nullable', 'string', 'max:100'],
            'sections.*.config.cta_link' => ['nullable', 'string', 'max:500'],
            'sections.*.config.content' => ['nullable', 'string', 'max:20000'],
            'sections.*.config.background_type' => ['nullable', 'string', 'max:50'],
            'sections.*.config.background_value' => ['nullable', 'string', 'max:2048'],
            'sections.*.config.layout' => ['nullable', 'string', 'max:50'],
            'sections.*.config.items' => ['nullable', 'array'],
            'sections.*.config.items.*.icon' => ['nullable', 'string', 'max:50'],
            'sections.*.config.items.*.title' => ['nullable', 'string', 'max:200'],
            'sections.*.config.items.*.description' => ['nullable', 'string', 'max:500'],
            'sections.*.config.items.*.image_url' => ['nullable', 'string', 'max:500'],
            'sections.*.config.stats' => ['nullable', 'array'],
            'sections.*.config.stats.*.number' => ['nullable', 'string', 'max:20'],
            'sections.*.config.stats.*.label' => ['nullable', 'string', 'max:100'],
            'settings' => ['required', 'array'],
            'settings.seo' => ['required', 'array'],
            'settings.seo.meta_title' => ['nullable', 'string', 'max:160'],
            'settings.seo.meta_description' => ['nullable', 'string', 'max:255'],
            'settings.seo.og_image' => ['nullable', 'string', 'max:2048'],
            'settings.scroll_to_top' => ['required', 'array'],
            'settings.scroll_to_top.enabled' => ['required', 'boolean'],
            'settings.scroll_to_top.position' => ['required', 'string', Rule::in(['left', 'right'])],
            'settings.scroll_to_top.show_after_px' => ['required', 'integer', 'min:0', 'max:5000'],
            'settings.chat_widget_embed' => ['nullable', 'string', 'max:20000'],
        ];
    }
}
